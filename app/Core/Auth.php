<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Gestión Centralizada de Sesiones, RBAC, CSRF y Cabeceras Anti-Caché (CU01, CU02)
 */
final class Auth
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $localSessDir = ROOT_PATH . '/storage/sessions';
            if (!is_dir($localSessDir)) {
                @mkdir($localSessDir, 0775, true);
            }
            if (is_dir($localSessDir) && is_writable($localSessDir)) {
                session_save_path($localSessDir);
            }

            if (!headers_sent()) {
                session_name('VIDASALUD_SESSID');
                session_set_cookie_params([
                    'lifetime' => 0,
                    'path'     => '/',
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }
            @session_start();
        }
    }

    /**
     * Impide almacenar páginas protegidas en el historial/caché del navegador (CU02 y Prueba #9).
     */
    public static function sendNoCacheHeaders(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Cache-Control: post-check=0, pre-check=0', false);
            header('Pragma: no-cache');
            header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
        }
    }

    public static function login(array $userData): void
    {
        self::startSession();
        session_regenerate_id(true);
        $_SESSION['auth_user'] = [
            'id_usuario'         => (int) $userData['id_usuario'],
            'id_rol'             => (int) $userData['id_rol'],
            'rol'                => (string) $userData['rol_nombre'],
            'nombre_completo'    => (string) $userData['nombre_completo'],
            'nombre_usuario'     => (string) $userData['nombre_usuario'],
            'correo_electronico' => (string) $userData['correo_electronico'],
            'telefono'           => (string) ($userData['telefono'] ?? ''),
            'id_medico'          => isset($userData['id_medico']) ? (int) $userData['id_medico'] : null,
            'codigo_medico'      => $userData['codigo_medico'] ?? null,
            'id_paciente'        => isset($userData['id_paciente']) ? (int) $userData['id_paciente'] : null,
            'codigo_paciente'    => $userData['codigo_paciente'] ?? null,
            'login_at'           => date('Y-m-d H:i:s'),
        ];
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                (bool) ($params['secure'] ?? false),
                (bool) ($params['httponly'] ?? true)
            );
        }
        session_destroy();
        self::sendNoCacheHeaders();
    }

    public static function check(): bool
    {
        self::startSession();
        return !empty($_SESSION['auth_user']['id_usuario']);
    }

    public static function user(): ?array
    {
        self::startSession();
        return $_SESSION['auth_user'] ?? null;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int) $u['id_usuario'] : null;
    }

    public static function role(): string
    {
        $u = self::user();
        return $u ? (string) $u['rol'] : '';
    }

    public static function medicoId(): ?int
    {
        $u = self::user();
        return !empty($u['id_medico']) ? (int) $u['id_medico'] : null;
    }

    public static function pacienteId(): ?int
    {
        $u = self::user();
        return !empty($u['id_paciente']) ? (int) $u['id_paciente'] : null;
    }

    public static function hasRole(array|string $roles): bool
    {
        if (!self::check()) {
            return false;
        }
        $allowed = is_array($roles) ? $roles : [$roles];
        return in_array(self::role(), $allowed, true);
    }

    public static function csrfToken(): string
    {
        self::startSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        self::startSession();
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals((string) $_SESSION['csrf_token'], $token);
    }

    public static function setFlash(string $type, string $message, string $title = ''): void
    {
        self::startSession();
        $_SESSION['flashes'][] = [
            'type'    => $type,
            'title'   => $title,
            'message' => $message,
        ];
    }

    public static function getFlashes(): array
    {
        self::startSession();
        $flashes = $_SESSION['flashes'] ?? [];
        unset($_SESSION['flashes']);
        return $flashes;
    }

    /**
     * Ruta inicial predeterminada según el rol autenticado (CU01).
     */
    public static function homeRouteForRole(string $role): string
    {
        return match ($role) {
            'Administrador' => 'dashboard',
            'Recepcionista' => 'dashboard',
            'Médico'        => 'agenda',
            'Paciente'      => 'dashboard',
            default         => 'dashboard',
        };
    }
}
