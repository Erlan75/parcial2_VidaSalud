<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

/**
 * Gestor de Conexión PDO PostgreSQL (Smart-DB)
 * Soporta conexión local (127.0.0.1) y Cloud Serverless (Neon.tech / Render)
 * mediante variables individuales o DATABASE_URL con SSL (sslmode=require).
 */
final class Database
{
    private static ?PDO $instance = null;
    private static bool $extensionsChecked = false;

    public static function getConfig(): array
    {
        $databaseUrl = trim((string) env('DATABASE_URL', ''));
        $host = (string) env('DB_HOST', '127.0.0.1');
        $port = (string) env('DB_PORT', '5432');
        $dbname = (string) env('DB_DATABASE', 'parcial2_VidaSalud');
        $user = (string) env('DB_USERNAME', 'postgres');
        $password = (string) env('DB_PASSWORD', '');
        $sslmode = trim((string) env('DB_SSLMODE', ''));

        // Si se provee DATABASE_URL (formato Neon / Render: postgresql://user:pass@host:port/dbname?sslmode=require)
        if ($databaseUrl !== '') {
            $parsed = parse_url($databaseUrl);
            if (is_array($parsed)) {
                if (!empty($parsed['host'])) {
                    $host = (string) $parsed['host'];
                }
                if (!empty($parsed['port'])) {
                    $port = (string) $parsed['port'];
                }
                if (!empty($parsed['path'])) {
                    $pathDb = ltrim((string) $parsed['path'], '/');
                    if ($pathDb !== '') {
                        $dbname = $pathDb;
                    }
                }
                if (isset($parsed['user'])) {
                    $user = rawurldecode((string) $parsed['user']);
                }
                if (isset($parsed['pass'])) {
                    $password = rawurldecode((string) $parsed['pass']);
                }
                if (!empty($parsed['query'])) {
                    parse_str((string) $parsed['query'], $queryParams);
                    if (!empty($queryParams['sslmode']) && is_string($queryParams['sslmode'])) {
                        $sslmode = trim($queryParams['sslmode']);
                    }
                }
            }
        }

        // Si el host pertenece a Neon.tech y no se definió sslmode, forzar sslmode=require
        if ($sslmode === '' && str_contains(strtolower($host), '.neon.tech')) {
            $sslmode = 'require';
        }

        return [
            'host'     => $host,
            'port'     => $port !== '' ? $port : '5432',
            'dbname'   => $dbname,
            'user'     => $user,
            'password' => $password,
            'sslmode'  => $sslmode,
            'timezone' => (string) env('APP_TIMEZONE', 'America/La_Paz'),
        ];
    }

    /**
     * Construye el DSN de PDO pgsql inyectando sslmode cuando corresponda (ej. Neon.tech).
     */
    public static function buildDsn(array $cfg, ?string $overrideDbName = null): string
    {
        $db = $overrideDbName ?? (string) ($cfg['dbname'] ?? 'parcial2_VidaSalud');
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $cfg['host'] ?? '127.0.0.1',
            $cfg['port'] ?? '5432',
            $db
        );

        $sslmode = trim((string) ($cfg['sslmode'] ?? ''));
        if ($sslmode === '' && str_contains(strtolower((string) ($cfg['host'] ?? '')), '.neon.tech')) {
            $sslmode = 'require';
        }
        if ($sslmode !== '') {
            $dsn .= ';sslmode=' . $sslmode;
        }

        return $dsn;
    }

    public static function getConnection(bool $forceReconnect = false): PDO
    {
        if (self::$instance !== null && !$forceReconnect) {
            return self::$instance;
        }

        if (!extension_loaded('pdo_pgsql')) {
            throw new RuntimeException('La extensión PHP pdo_pgsql no está habilitada en php.ini. Actívela para conectar con PostgreSQL.');
        }

        $cfg = self::getConfig();
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $dsn = self::buildDsn($cfg);
            $pdo = new PDO($dsn, $cfg['user'], $cfg['password'], $options);
        } catch (PDOException $e) {
            $lowerDb = strtolower($cfg['dbname']);
            if ($lowerDb !== $cfg['dbname'] && str_contains($e->getMessage(), 'does not exist')) {
                $dsnLower = self::buildDsn($cfg, $lowerDb);
                $pdo = new PDO($dsnLower, $cfg['user'], $cfg['password'], $options);
            } else {
                throw $e;
            }
        }

        $tz = preg_replace('/[^A-Za-z0-9_\/+-]/', '', $cfg['timezone']) ?: 'America/La_Paz';
        $pdo->exec("SET TIME ZONE '{$tz}'");
        $pdo->exec("SET NAMES 'UTF8'");

        self::$instance = $pdo;
        self::ensureSmartExtensions($pdo);

        return self::$instance;
    }

    /**
     * Crea conexión al motor PostgreSQL apuntando a la BD administrativa 'postgres'
     * útil para crear automáticamente la BD del proyecto en entorno local.
     */
    public static function getServerConnection(?array $customCfg = null): PDO
    {
        $cfg = $customCfg ?? self::getConfig();
        $dsn = self::buildDsn($cfg, 'postgres');
        return new PDO($dsn, $cfg['user'], $cfg['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    /**
     * Verifica si la base de datos ya cuenta con las 13 tablas migradas.
     */
    public static function checkHealth(): array
    {
        try {
            $pdo = self::getConnection();
            $stmt = $pdo->query("
                SELECT COUNT(*) AS total
                  FROM information_schema.tables
                 WHERE table_schema = 'public'
                   AND table_name IN (
                       'roles','usuarios','pacientes','fichas_medicas','contactos_emergencia',
                       'especialidades','medicos','medico_especialidad','horarios_atencion',
                       'citas','cancelaciones_cita','bloqueos_agenda','auditorias'
                   )
            ");
            $count = (int) ($stmt->fetchColumn() ?: 0);
            return [
                'connected' => true,
                'migrated'  => $count === 13,
                'tables'    => $count,
                'error'     => null,
            ];
        } catch (Throwable $e) {
            return [
                'connected' => false,
                'migrated'  => false,
                'tables'    => 0,
                'error'     => \App\Core\DatabaseExceptionParser::parse($e),
            ];
        }
    }

    /**
     * Si el usuario importó los 4 SQL manualmente en pgAdmin sin pasar por migrate.php,
     * garantiza idempotentemente la existencia de password_resets y sp_actualizar_ficha_medica.
     */
    private static function ensureSmartExtensions(PDO $pdo): void
    {
        if (self::$extensionsChecked) {
            return;
        }
        self::$extensionsChecked = true;

        try {
            $hasUsers = (bool) $pdo->query("SELECT to_regclass('public.usuarios') IS NOT NULL")->fetchColumn();
            if (!$hasUsers) {
                return;
            }
            $hasPwdResets = (bool) $pdo->query("SELECT to_regclass('public.password_resets') IS NOT NULL")->fetchColumn();
            $hasSpFicha = (bool) $pdo->query("SELECT EXISTS(SELECT 1 FROM pg_proc WHERE proname = 'sp_actualizar_ficha_medica')")->fetchColumn();
            if (!$hasPwdResets || !$hasSpFicha) {
                $extFile = ROOT_PATH . '/database/extensions.sql';
                if (is_file($extFile)) {
                    $pdo->exec((string) file_get_contents($extFile));
                }
            }
        } catch (Throwable) {
            // Silencioso en caso de permisos limitados de solo lectura
        }
    }
}
