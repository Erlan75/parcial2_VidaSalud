<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Cliente Nativo SMTP TLS/SSL para Gmail + Bandeja Local de Respaldo
 * Permite envío real por Gmail (smtp.gmail.com:587 STARTTLS / 465 SSL) configurable en .env
 * y guarda copia auditable en storage/mail_outbox para verificación instantánea en laboratorio.
 */
final class Mailer
{
    public static function sendPasswordResetEmail(
        string $toEmail,
        string $toName,
        string $resetUrl,
        int $expiresMinutes = 30
    ): array {
        $subject = 'Recuperación de Contraseña - Clínica Vida Salud';
        $htmlBody = self::buildResetTemplate($toName, $resetUrl, $expiresMinutes);

        // Registrar respaldo local auditable en storage/mail_outbox
        $outboxDir = ROOT_PATH . '/storage/mail_outbox';
        if (!is_dir($outboxDir)) {
            @mkdir($outboxDir, 0775, true);
        }
        $logFile = $outboxDir . '/reset_' . date('Ymd_His') . '_' . substr(md5($toEmail), 0, 8) . '.html';
        @file_put_contents($logFile, $htmlBody);

        $cfg = require ROOT_PATH . '/config/mail.php';
        $smtpConfigured = !empty($cfg['enabled'])
            && !empty($cfg['username'])
            && !empty($cfg['password'])
            && $cfg['password'] !== 'xxxx_xxxx_xxxx_xxxx';

        if (!$smtpConfigured) {
            return [
                'sent_smtp' => false,
                'simulated' => true,
                'reset_url' => $resetUrl,
                'outbox'    => basename($logFile),
                'message'   => 'Enlace generado. SMTP Gmail está en modo simulación local (.env MAIL_ENABLED=false); configure su Contraseña de Aplicación de Gmail en Configuración SMTP para despacho real.',
            ];
        }

        try {
            self::sendViaSmtp($cfg, $toEmail, $toName, $subject, $htmlBody);
            return [
                'sent_smtp' => true,
                'simulated' => false,
                'reset_url' => $resetUrl,
                'outbox'    => basename($logFile),
                'message'   => "Correo enviado exitosamente vía SMTP ({$cfg['host']}) a {$toEmail}.",
            ];
        } catch (\Throwable $e) {
            return [
                'sent_smtp' => false,
                'simulated' => true,
                'reset_url' => $resetUrl,
                'outbox'    => basename($logFile),
                'error'     => $e->getMessage(),
                'message'   => 'No se pudo conectar al servidor SMTP Gmail (' . $e->getMessage() . '). Se generó respaldo seguro en la bandeja local para pruebas.',
            ];
        }
    }

    private static function sendViaSmtp(
        array $cfg,
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody
    ): void {
        $host = trim((string) $cfg['host']);
        $port = (int) ($cfg['port'] ?: 587);
        $encryption = strtolower(trim((string) ($cfg['encryption'] ?? 'tls')));
        $username = trim((string) $cfg['username']);
        $password = str_replace(' ', '', trim((string) $cfg['password']));
        $fromAddress = trim((string) ($cfg['from_address'] ?: $username));
        $fromName = trim((string) ($cfg['from_name'] ?: 'Clínica Vida Salud'));

        $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ]);

        $socket = @stream_socket_client($remote, $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            throw new \RuntimeException("Error de socket SMTP ({$errno}): {$errstr}");
        }
        stream_set_timeout($socket, 12);

        self::expectCode($socket, [220]);
        self::sendCmd($socket, 'EHLO vidasalud.local', [250]);

        if ($encryption === 'tls') {
            self::sendCmd($socket, 'STARTTLS', [220]);
            $cryptoOk = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
            if (!$cryptoOk) {
                fclose($socket);
                throw new \RuntimeException('Fallo al negociar cifrado STARTTLS con el servidor SMTP.');
            }
            self::sendCmd($socket, 'EHLO vidasalud.local', [250]);
        }

        self::sendCmd($socket, 'AUTH LOGIN', [334]);
        self::sendCmd($socket, base64_encode($username), [334]);
        self::sendCmd($socket, base64_encode($password), [235]);

        self::sendCmd($socket, "MAIL FROM:<{$fromAddress}>", [250]);
        self::sendCmd($socket, "RCPT TO:<{$toEmail}>", [250, 251]);
        self::sendCmd($socket, 'DATA', [354]);

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedFrom = '=?UTF-8?B?' . base64_encode($fromName) . "?= <{$fromAddress}>";
        $encodedTo = '=?UTF-8?B?' . base64_encode($toName) . "?= <{$toEmail}>";

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $encodedFrom,
            'To: ' . $encodedTo,
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];

        $payload = implode("\r\n", $headers) . "\r\n\r\n"
            . chunk_split(base64_encode($htmlBody)) . "\r\n.";

        fwrite($socket, $payload . "\r\n");
        self::expectCode($socket, [250]);
        @fwrite($socket, "QUIT\r\n");
        fclose($socket);
    }

    private static function sendCmd($socket, string $cmd, array $expectedCodes): string
    {
        fwrite($socket, $cmd . "\r\n");
        return self::expectCode($socket, $expectedCodes);
    }

    private static function expectCode($socket, array $expectedCodes): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new \RuntimeException('Respuesta SMTP inesperada: ' . trim($response));
        }
        return $response;
    }

    private static function buildResetTemplate(string $name, string $resetUrl, int $expiresMinutes): string
    {
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
        $year = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Restablecimiento de Contraseña - Clínica Vida Salud</title>
</head>
<body style="margin:0;padding:24px;background-color:#F4F7FA;font-family:'Segoe UI',Arial,sans-serif;color:#1e293b;">
  <div style="max-width:580px;margin:0 auto;background:#FFFFFF;border-radius:14px;overflow:hidden;box-shadow:0 6px 24px rgba(0,45,98,0.10);border:1px solid #D4E6F7;">
    <div style="background:linear-gradient(135deg,#002D62,#2B579A);padding:26px 32px;color:#FFFFFF;">
      <h2 style="margin:0;font-size:21px;letter-spacing:0.4px;">CLÍNICA VIDA SALUD</h2>
      <p style="margin:6px 0 0;font-size:13px;opacity:0.9;">Sistema Web de Gestión de Citas Médicas · Seguridad de Acceso</p>
    </div>
    <div style="padding:30px 32px;">
      <p style="margin-top:0;font-size:15px;">Estimado(a) <strong>{$safeName}</strong>,</p>
      <p style="font-size:14px;line-height:1.6;">Hemos recibido una solicitud para restablecer la contraseña de su cuenta en el portal clínico de <strong>Clínica Vida Salud</strong>.</p>
      <div style="text-align:center;margin:28px 0;">
        <a href="{$safeUrl}" style="display:inline-block;background-color:#002D62;color:#FFFFFF;text-decoration:none;padding:13px 28px;border-radius:8px;font-weight:600;font-size:14px;">Restablecer Mi Contraseña</a>
      </div>
      <p style="font-size:13px;color:#475569;line-height:1.5;">Este enlace temporal caducará en <strong>{$expiresMinutes} minutos</strong> y es de un solo uso. Si el botón no abre automáticamente, copie la siguiente dirección en su navegador:</p>
      <p style="word-break:break-all;background:#F4F7FA;padding:10px 12px;border-radius:6px;font-size:12px;color:#2B579A;">{$safeUrl}</p>
      <hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0;">
      <p style="font-size:12px;color:#64748b;margin-bottom:0;">Si usted no solicitó este cambio, puede ignorar este mensaje; su contraseña actual permanecerá protegida.</p>
    </div>
    <div style="background:#F4F7FA;padding:14px 32px;text-align:center;font-size:12px;color:#64748b;">
      © {$year} Clínica Vida Salud · Santa Cruz de la Sierra, Bolivia
    </div>
  </div>
</body>
</html>
HTML;
    }
}
