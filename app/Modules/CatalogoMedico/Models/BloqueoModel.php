<?php
declare(strict_types=1);

namespace App\Modules\CatalogoMedico\Models;

use App\Core\Mailer;
use App\Modules\GestionCitas\Models\CitaModel;
use Database;
use PDO;

/**
 * CU16: Modelo de Feriados Institucionales y Bloqueos de Agenda Médica
 * Incluye Reprogramación Automática Masiva por Contingencia y Notificación Híbrida.
 */
final class BloqueoModel
{
    public function listAll(?int $idMedico = null): array
    {
        $pdo = Database::getConnection();
        $where = '';
        $params = [];
        if ($idMedico !== null && $idMedico > 0) {
            $where = 'WHERE (b.id_medico IS NULL OR b.id_medico = :med)';
            $params[':med'] = $idMedico;
        }

        $sql = "
            SELECT b.*,
                   m.codigo_medico,
                   CASE
                       WHEN b.id_medico IS NULL THEN 'Toda la Clínica (Feriado General)'
                       ELSE 'Dr(a). ' || m.nombres || ' ' || m.apellidos
                   END AS alcance_nombre
              FROM bloqueos_agenda b
              LEFT JOIN medicos m ON m.id_medico = b.id_medico
              {$where}
             ORDER BY b.fecha_inicio DESC, b.id_bloqueo DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * CU16 (Regla Smart-DB): Invoca obligatoriamente CALL sp_registrar_bloqueo_agenda(...)
     */
    public function registrarBloqueo(
        ?int $idMedico,
        int $idUsuarioAdmin,
        string $tipoBloqueo,
        string $fechaInicio,
        string $fechaFin,
        string $motivo,
        string $ip
    ): int {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            CALL sp_registrar_bloqueo_agenda(
                :med::bigint,
                :adm::bigint,
                :tipo::varchar,
                :ini::date,
                :fin::date,
                :motivo::varchar,
                :ip::varchar,
                NULL
            )
        ");
        $stmt->bindValue(':med', $idMedico, $idMedico === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':adm', $idUsuarioAdmin, PDO::PARAM_INT);
        $stmt->bindValue(':tipo', $tipoBloqueo, PDO::PARAM_STR);
        $stmt->bindValue(':ini', $fechaInicio, PDO::PARAM_STR);
        $stmt->bindValue(':fin', $fechaFin, PDO::PARAM_STR);
        $stmt->bindValue(':motivo', trim($motivo), PDO::PARAM_STR);
        $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
        $stmt->execute();

        $out = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($out['p_id_bloqueo'] ?? 0);
    }

    public function eliminarBloqueoFuturo(int $idBloqueo, int $idAdmin, string $ip): void
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM bloqueos_agenda WHERE id_bloqueo = :id FOR UPDATE');
            $stmt->execute([':id' => $idBloqueo]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new \RuntimeException('El bloqueo de agenda no existe.');
            }

            $pdo->prepare('DELETE FROM bloqueos_agenda WHERE id_bloqueo = :id')
                ->execute([':id' => $idBloqueo]);

            $aud = $pdo->prepare("
                INSERT INTO auditorias (id_usuario, modulo, operacion, id_registro_afectado, datos_previos, ip_origen)
                VALUES (:uid, 'Bloqueos Agenda', 'CANCEL', :reg, :prev::jsonb, :ip)
            ");
            $aud->execute([
                ':uid'  => $idAdmin,
                ':reg'  => (string) $idBloqueo,
                ':prev' => json_encode($row, JSON_UNESCAPED_UNICODE),
                ':ip'   => $ip,
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Reubica automáticamente todas las citas activas ('Programada' o 'Confirmada') afectadas por el bloqueo
     * al próximo turno disponible libre de colisiones y despacha la notificación híbrida (Correo SMTP + Alerta Web).
     */
    public function reubicarYNotificarCitasAfectadas(
        ?int $idMedico,
        string $fechaInicio,
        string $fechaFin,
        int $idUsuarioAdmin,
        string $tipoBloqueo,
        string $motivoBloqueo,
        string $ip,
        ?int $idBloqueo = null
    ): array {
        $citaModel = new CitaModel();
        $resultado = $citaModel->reubicarCitasPorContingencia(
            $idMedico,
            $fechaInicio,
            $fechaFin,
            $idUsuarioAdmin,
            $tipoBloqueo,
            $motivoBloqueo,
            $ip,
            $idBloqueo
        );

        $reubicadasConEstado = [];
        $enviadosSmtp = 0;
        $notificadosLocal = 0;

        foreach ($resultado['reubicadas'] as $citaReubicada) {
            try {
                $mailResult = Mailer::sendContingencyRescheduleEmail($citaReubicada, $tipoBloqueo, $motivoBloqueo);
            } catch (\Throwable $mailErr) {
                $mailResult = [
                    'sent_smtp'    => false,
                    'simulated'    => true,
                    'recipient'    => $citaReubicada['paciente_correo'] ?? '',
                    'outbox'       => '',
                    'status_label' => 'Alerta en Portal Web activa',
                    'message'      => 'Excepción SMTP controlada: ' . $mailErr->getMessage(),
                ];
            }

            if (!empty($mailResult['sent_smtp'])) {
                $enviadosSmtp++;
            } else {
                $notificadosLocal++;
            }

            $citaReubicada['correo_enviado_smtp'] = (bool) ($mailResult['sent_smtp'] ?? false);
            $citaReubicada['correo_simulado'] = (bool) ($mailResult['simulated'] ?? true);
            $citaReubicada['correo_estado_label'] = (string) ($mailResult['status_label'] ?? 'Notificado');
            $citaReubicada['correo_mensaje'] = (string) ($mailResult['message'] ?? '');
            $citaReubicada['correo_outbox'] = (string) ($mailResult['outbox'] ?? '');

            $reubicadasConEstado[] = $citaReubicada;
        }

        return [
            'id_bloqueo'        => $idBloqueo,
            'tipo_bloqueo'      => $tipoBloqueo,
            'motivo_bloqueo'    => $motivoBloqueo,
            'fecha_inicio'      => $fechaInicio,
            'fecha_fin'         => $fechaFin,
            'total_afectadas'   => (int) $resultado['total_afectadas'],
            'total_reubicadas'  => count($reubicadasConEstado),
            'enviados_smtp'     => $enviadosSmtp,
            'respaldos_locales' => $notificadosLocal,
            'reubicadas'        => $reubicadasConEstado,
            'no_reubicadas'     => $resultado['no_reubicadas'],
            'fecha_proceso'     => date('d/m/Y H:i:s'),
        ];
    }

    /**
     * Obtiene el historial reciente de citas reprogramadas automáticamente por contingencia
     * registrado en la tabla auditorias para exhibición permanente en la vista CU16.
     */
    public function listarReubicacionesContingenciaRecientes(?int $idMedico = null, int $limit = 20): array
    {
        $citaModel = new CitaModel();
        return $citaModel->obtenerAlertasContingencia(null, $idMedico, $limit);
    }
}
