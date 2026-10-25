<?php
declare(strict_types=1);

/**
 * Script Ejecutable de Migración Smart-DB - Clínica Vida Salud
 * Uso por consola (CLI):
 *   php database/migrate.php              (Ejecuta la migración completa)
 *   php database/migrate.php --if-empty   (Solo migra si las 13 tablas aún no existen - ideal para Docker/Render)
 *
 * Orden estricto de ejecución:
 *   1º Script.sql     (13 tablas 3FN, secuencias, índices y constraints)
 *   2º Triggers.sql   (8 triggers PL/pgSQL RN03..RN11, correlativos y auditoría RF17)
 *   3º PA.sql         (Procedimientos almacenados sp_... y fn_obtener_intervalos_disponibles)
 *   4º poblacion.sql  (Semilla oficial: roles, 18 usuarios, 5 especialidades, 8 médicos, 27 horarios, 26 pacientes, 56 citas)
 *   5º extensions.sql (Tabla auxiliar password_resets + sp_actualizar_ficha_medica + verificación hash BCRYPT)
 */

require_once dirname(__DIR__) . '/config/database.php';

final class DatabaseMigrator
{
    public static function resolveSqlDirectory(): string
    {
        $localDir = ROOT_PATH . DIRECTORY_SEPARATOR . 'database';
        $externalDir = dirname(ROOT_PATH) . DIRECTORY_SEPARATOR . 'BD_vidaSalud';

        $required = ['Script.sql', 'Triggers.sql', 'PA.sql', 'poblacion.sql'];
        $localValid = true;
        foreach ($required as $file) {
            if (!is_file($localDir . DIRECTORY_SEPARATOR . $file) || !is_readable($localDir . DIRECTORY_SEPARATOR . $file)) {
                $localValid = false;
                break;
            }
        }
        if ($localValid) {
            return $localDir;
        }

        $externalValid = true;
        foreach ($required as $file) {
            if (!is_file($externalDir . DIRECTORY_SEPARATOR . $file) || !is_readable($externalDir . DIRECTORY_SEPARATOR . $file)) {
                $externalValid = false;
                break;
            }
        }
        return $externalValid ? $externalDir : $localDir;
    }

    public static function run(?array $customConfig = null): array
    {
        $steps = [];
        $cfg = $customConfig ?? Database::getConfig();
        $dbName = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($cfg['dbname'] ?? ''));

        if ($dbName === '') {
            throw new InvalidArgumentException('El nombre de la base de datos no es válido.');
        }

        $isCloudManaged = str_contains(strtolower((string) ($cfg['host'] ?? '')), '.neon.tech')
            || !empty($cfg['sslmode'])
            || trim((string) env('DATABASE_URL', '')) !== '';

        // 0. Verificar / Crear la Base de Datos en PostgreSQL local si no existe
        if ($isCloudManaged) {
            $steps[] = [
                'step'    => '0. Conexión Cloud Serverless (SSL)',
                'file'    => $dbName,
                'status'  => 'ok',
                'message' => "Conectado a '{$dbName}' en {$cfg['host']} (sslmode=" . ($cfg['sslmode'] ?: 'require') . ").",
            ];
        } else {
            try {
                $serverPdo = Database::getServerConnection($cfg);
                $stmt = $serverPdo->prepare('SELECT datname FROM pg_database WHERE LOWER(datname) = LOWER(:db) LIMIT 1');
                $stmt->execute([':db' => $dbName]);
                $existingDb = $stmt->fetchColumn();
                if (!$existingDb) {
                    $serverPdo->exec(sprintf('CREATE DATABASE "%s" WITH ENCODING \'UTF8\'', $dbName));
                    $steps[] = [
                        'step'    => '0. Creación de Base de Datos',
                        'file'    => $dbName,
                        'status'  => 'ok',
                        'message' => "Base de datos '{$dbName}' creada automáticamente en PostgreSQL.",
                    ];
                } else {
                    $dbName = (string) $existingDb;
                    $steps[] = [
                        'step'    => '0. Verificación de Base de Datos',
                        'file'    => $dbName,
                        'status'  => 'ok',
                        'message' => "Base de datos '{$dbName}' detectada y lista.",
                    ];
                }
            } catch (Throwable) {
                $steps[] = [
                    'step'    => '0. Conexión directa a Base de Datos',
                    'file'    => $dbName,
                    'status'  => 'info',
                    'message' => 'Conectando directamente a la base de datos configurada.',
                ];
            }
        }

        // Conectar a la BD destino
        $pdo = Database::getConnection(true);

        $sqlDir = self::resolveSqlDirectory();
        $orderedFiles = [
            '1º Estructura 3FN (Script.sql)'               => $sqlDir . DIRECTORY_SEPARATOR . 'Script.sql',
            '2º Blindaje de Triggers (Triggers.sql)'       => $sqlDir . DIRECTORY_SEPARATOR . 'Triggers.sql',
            '3º Procedimientos Smart-DB (PA.sql)'          => $sqlDir . DIRECTORY_SEPARATOR . 'PA.sql',
            '4º Semilla Masiva Oficial (poblacion.sql)'    => $sqlDir . DIRECTORY_SEPARATOR . 'poblacion.sql',
            '5º Extensiones SMTP y Ficha (extensions.sql)' => ROOT_PATH . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'extensions.sql',
        ];

        // Asegurar limpieza de password_resets antes de Script.sql por la FK a usuarios
        $pdo->exec('DROP TABLE IF EXISTS password_resets CASCADE;');

        foreach ($orderedFiles as $label => $filePath) {
            if (!is_file($filePath)) {
                throw new RuntimeException("No se encontró el archivo SQL requerido: {$filePath}");
            }
            $sql = (string) file_get_contents($filePath);
            // Remover posible BOM UTF-8
            $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;

            $startTime = microtime(true);
            $pdo->exec($sql);
            $elapsed = round((microtime(true) - $startTime) * 1000, 1);

            $steps[] = [
                'step'    => $label,
                'file'    => basename($filePath),
                'status'  => 'ok',
                'message' => "Ejecutado correctamente en {$elapsed} ms.",
            ];
        }

        // Normalizar hash BCRYPT real de 'Cher_4mi' en caso de que poblacion.sql provenga del archivo externo con hash ilustrativo
        $legacyHash = '$2y$10$e8nZgP3m1K8jF0xW9uV4eOyu/27wnRz9/7utscT2qqhEm68ZR4.gq';
        $validHash = password_hash('Cher_4mi', PASSWORD_BCRYPT);
        $fixStmt = $pdo->prepare('UPDATE usuarios SET password_hash = :valid WHERE password_hash = :legacy');
        $fixStmt->execute([':valid' => $validHash, ':legacy' => $legacyHash]);
        $fixedRows = $fixStmt->rowCount();
        if ($fixedRows > 0) {
            $steps[] = [
                'step'    => '6º Sincronización Criptográfica BCRYPT',
                'file'    => 'usuarios.password_hash',
                'status'  => 'ok',
                'message' => "{$fixedRows} cuentas semilla actualizadas con hash BCRYPT verificable para 'Cher_4mi'.",
            ];
        }

        // Resumen estadístico post-migración
        $summary = [
            'roles'          => (int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn(),
            'usuarios'       => (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn(),
            'especialidades' => (int) $pdo->query('SELECT COUNT(*) FROM especialidades')->fetchColumn(),
            'medicos'        => (int) $pdo->query('SELECT COUNT(*) FROM medicos')->fetchColumn(),
            'horarios'       => (int) $pdo->query('SELECT COUNT(*) FROM horarios_atencion')->fetchColumn(),
            'pacientes'      => (int) $pdo->query('SELECT COUNT(*) FROM pacientes')->fetchColumn(),
            'citas'          => (int) $pdo->query('SELECT COUNT(*) FROM citas')->fetchColumn(),
            'auditorias'     => (int) $pdo->query('SELECT COUNT(*) FROM auditorias')->fetchColumn(),
        ];

        return [
            'success' => true,
            'source'  => $sqlDir,
            'steps'   => $steps,
            'summary' => $summary,
        ];
    }
}

// Ejecución directa desde línea de comandos: php database/migrate.php [--if-empty]
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $args = $_SERVER['argv'] ?? [];
    $onlyIfEmpty = in_array('--if-empty', $args, true);

    echo "====================================================================\n";
    echo "  MIGRADOR SMART-DB - CLÍNICA VIDA SALUD (PostgreSQL + PDO)\n";
    echo "====================================================================\n";

    try {
        if ($onlyIfEmpty) {
            $health = Database::checkHealth();
            if (!empty($health['migrated'])) {
                echo " [OK] Las 13 tablas ya existen en la base de datos. Omitiendo migración inicial.\n";
                exit(0);
            }
            echo " [INFO] Base de datos vacía detectada ({$health['tables']}/13 tablas). Ejecutando migración automática...\n";
        }

        $result = DatabaseMigrator::run();
        echo "Origen SQL: {$result['source']}\n\n";
        foreach ($result['steps'] as $s) {
            echo sprintf(" [OK] %-44s -> %s\n", $s['step'], $s['message']);
        }
        echo "\nResumen de Registros Semilla:\n";
        foreach ($result['summary'] as $table => $total) {
            echo sprintf("  - %-16s : %d\n", ucfirst($table), $total);
        }
        echo "\n¡Migración completada al 100%! Credencial general de prueba: Cher_4mi\n";
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDERR, "\n[ERROR DE MIGRACIÓN]: " . $e->getMessage() . "\n");
        fwrite(STDERR, "Verifique sus variables de entorno (DATABASE_URL o DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, DB_SSLMODE).\n");
        exit(1);
    }
}
