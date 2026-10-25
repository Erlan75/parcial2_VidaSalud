<?php
declare(strict_types=1);

/**
 * Bootstrap de Configuración Principal - Clínica Vida Salud
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

if (!function_exists('load_env_file')) {
    function load_env_file(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            $pos = strpos($trimmed, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($trimmed, 0, $pos));
            $val = trim(substr($trimmed, $pos + 1));
            if (
                (str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                (str_starts_with($val, "'") && str_ends_with($val, "'"))
            ) {
                $val = substr($val, 1, -1);
            }
            $_ENV[$key] = $val;
            $_SERVER[$key] = $val;
        }
    }
}

load_env_file(ROOT_PATH . DIRECTORY_SEPARATOR . '.env');

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($val === false || $val === null) {
            return $default;
        }
        if (is_string($val)) {
            $lower = strtolower($val);
            if ($lower === 'true') {
                return true;
            }
            if ($lower === 'false') {
                return false;
            }
            if ($lower === 'null') {
                return null;
            }
        }
        return $val;
    }
}

$timezone = (string) env('APP_TIMEZONE', 'America/La_Paz');
date_default_timezone_set($timezone ?: 'America/La_Paz');

// Autoloader PSR-4 ligero para el espacio de nombres App\
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = ROOT_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR;
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

// Detección dinámica de URL base para funcionar en Render (HTTPS proxy), php -S y Apache
if (!defined('BASE_URL')) {
    if (PHP_SAPI === 'cli') {
        define('BASE_URL', rtrim((string) env('APP_URL', 'http://localhost:8000'), '/'));
    } else {
        $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || $forwardedProto === 'https'
            || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;
        $scheme = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';

        if (PHP_SAPI === 'cli-server') {
            define('BASE_URL', $scheme . '://' . $host);
        } else {
            $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
            $dir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/.');
            define('BASE_URL', $scheme . '://' . $host . ($dir === '' ? '' : $dir));
        }
    }
}

if (!function_exists('url')) {
    function url(string $route = '', array $params = []): string
    {
        $base = BASE_URL . '/index.php';
        $query = [];
        if ($route !== '') {
            $query['r'] = ltrim($route, '/');
        }
        foreach ($params as $k => $v) {
            if ($v !== null && $v !== '') {
                $query[$k] = $v;
            }
        }
        return empty($query) ? $base : ($base . '?' . http_build_query($query));
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        $clean = ltrim($path, '/');
        $base = BASE_URL;
        if (PHP_SAPI === 'cli-server') {
            return $base . '/assets/' . $clean;
        }
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME'] ?? ''));
        if (!str_ends_with($base, '/public') && !str_ends_with($scriptDir, '/public') && is_dir(ROOT_PATH . '/public/assets') && !is_dir($scriptDir . '/assets')) {
            return $base . '/public/assets/' . $clean;
        }
        return $base . '/assets/' . $clean;
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('client_ip')) {
    function client_ip(): string
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }
        return substr($ip, 0, 45) ?: '127.0.0.1';
    }
}

if (!function_exists('dias_semana_map')) {
    function dias_semana_map(): array
    {
        return [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ];
    }
}

if (!function_exists('badge_estado_cita')) {
    function badge_estado_cita(string $estado): string
    {
        $map = [
            'Programada'   => ['bg' => '#0d6efd', 'text' => '#ffffff', 'icon' => 'bi-calendar-event'],
            'Confirmada'   => ['bg' => '#ffc107', 'text' => '#1e293b', 'icon' => 'bi-person-check-fill'],
            'Atendida'     => ['bg' => '#198754', 'text' => '#ffffff', 'icon' => 'bi-check-circle-fill'],
            'Cancelada'    => ['bg' => '#dc3545', 'text' => '#ffffff', 'icon' => 'bi-x-circle-fill'],
            'Inasistencia' => ['bg' => '#6c757d', 'text' => '#ffffff', 'icon' => 'bi-slash-circle-fill'],
        ];
        $cfg = $map[$estado] ?? ['bg' => '#6c757d', 'text' => '#ffffff', 'icon' => 'bi-circle'];
        return sprintf(
            '<span class="badge rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1" style="background-color:%s;color:%s;"><i class="bi %s"></i> %s</span>',
            $cfg['bg'],
            $cfg['text'],
            $cfg['icon'],
            e($estado)
        );
    }
}
