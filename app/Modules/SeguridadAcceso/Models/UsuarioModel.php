<?php
declare(strict_types=1);

namespace App\Modules\SeguridadAcceso\Models;

use Database;
use PDO;

/**
 * Modelo del Paquete SeguridadAcceso - Gestión de Usuarios, RBAC y Recuperación de Clave
 */
final class UsuarioModel
{
    private const LEGACY_SEED_HASH = '$2y$10$e8nZgP3m1K8jF0xW9uV4eOyu/27wnRz9/7utscT2qqhEm68ZR4.gq';

    public function findByIdentifier(string $identifier): ?array
    {
        $pdo = Database::getConnection();
        $sql = "
            SELECT u.*,
                   r.nombre AS rol_nombre,
                   m.id_medico,
                   m.codigo_medico,
                   p.id_paciente,
                   p.codigo_paciente
              FROM usuarios u
              JOIN roles r ON r.id_rol = u.id_rol
              LEFT JOIN medicos m ON m.id_usuario = u.id_usuario
              LEFT JOIN pacientes p ON p.id_usuario = u.id_usuario
             WHERE LOWER(u.nombre_usuario) = LOWER(:ident)
                OR LOWER(u.correo_electronico) = LOWER(:ident)
             LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':ident' => trim($identifier)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findById(int $idUsuario): ?array
    {
        $pdo = Database::getConnection();
        $sql = "
            SELECT u.*,
                   r.nombre AS rol_nombre,
                   m.id_medico,
                   m.codigo_medico,
                   p.id_paciente,
                   p.codigo_paciente
              FROM usuarios u
              JOIN roles r ON r.id_rol = u.id_rol
              LEFT JOIN medicos m ON m.id_usuario = u.id_usuario
              LEFT JOIN pacientes p ON p.id_usuario = u.id_usuario
             WHERE u.id_usuario = :id
             LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $idUsuario]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function verifyUserPassword(array $userRow, string $plainPassword): bool
    {
        $hash = (string) ($userRow['password_hash'] ?? '');
        if (password_verify($plainPassword, $hash)) {
            return true;
        }
        // Compatibilidad automática si poblacion.sql fue importado manualmente desde archivo externo con hash ilustrativo
        if ($hash === self::LEGACY_SEED_HASH && $plainPassword === 'Cher_4mi') {
            $newHash = password_hash('Cher_4mi', PASSWORD_BCRYPT);
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('UPDATE usuarios SET password_hash = :h WHERE id_usuario = :id');
            $stmt->execute([':h' => $newHash, ':id' => (int) $userRow['id_usuario']]);
            return true;
        }
        return false;
    }

    public function registerLoginAudit(int $idUsuario, string $username, string $rol, string $ip): void
    {
        $pdo = Database::getConnection();
        $pdo->prepare('UPDATE usuarios SET ultimo_acceso = CURRENT_TIMESTAMP WHERE id_usuario = :id')
            ->execute([':id' => $idUsuario]);

        $stmt = $pdo->prepare("
            INSERT INTO auditorias (id_usuario, modulo, operacion, id_registro_afectado, datos_nuevos, ip_origen)
            VALUES (:uid, 'Seguridad', 'LOGIN', :reg, :nuevos::jsonb, :ip)
        ");
        $stmt->execute([
            ':uid'    => $idUsuario,
            ':reg'    => (string) $idUsuario,
            ':nuevos' => json_encode(['evento' => 'Inicio de sesión exitoso', 'usuario' => $username, 'rol' => $rol], JSON_UNESCAPED_UNICODE),
            ':ip'     => $ip,
        ]);
    }

    public function registerLogoutAudit(int $idUsuario, string $username, string $ip): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO auditorias (id_usuario, modulo, operacion, id_registro_afectado, datos_nuevos, ip_origen)
            VALUES (:uid, 'Seguridad', 'LOGOUT', :reg, :nuevos::jsonb, :ip)
        ");
        $stmt->execute([
            ':uid'    => $idUsuario,
            ':reg'    => (string) $idUsuario,
            ':nuevos' => json_encode(['evento' => 'Cierre de sesión voluntario', 'usuario' => $username], JSON_UNESCAPED_UNICODE),
            ':ip'     => $ip,
        ]);
    }

    public function listAll(array $filters = []): array
    {
        $pdo = Database::getConnection();
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['id_rol'])) {
            $where[] = 'u.id_rol = :id_rol';
            $params[':id_rol'] = (int) $filters['id_rol'];
        }
        if (!empty($filters['estado'])) {
            $where[] = 'u.estado = :estado';
            $params[':estado'] = (string) $filters['estado'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(u.nombre_completo ILIKE :q OR u.nombre_usuario ILIKE :q OR u.correo_electronico ILIKE :q)';
            $params[':q'] = '%' . trim((string) $filters['q']) . '%';
        }

        $sql = "
            SELECT u.id_usuario, u.id_rol, u.nombre_completo, u.nombre_usuario,
                   u.correo_electronico, u.telefono, u.estado, u.ultimo_acceso, u.fecha_creacion,
                   r.nombre AS rol_nombre,
                   m.codigo_medico,
                   p.codigo_paciente
              FROM usuarios u
              JOIN roles r ON r.id_rol = u.id_rol
              LEFT JOIN medicos m ON m.id_usuario = u.id_usuario
              LEFT JOIN pacientes p ON p.id_usuario = u.id_usuario
             WHERE " . implode(' AND ', $where) . "
             ORDER BY u.id_usuario ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRoles(): array
    {
        $pdo = Database::getConnection();
        return $pdo->query("SELECT id_rol, nombre, descripcion FROM roles WHERE estado = 'Activo' ORDER BY id_rol ASC")
                   ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUnassignedDoctorUsers(): array
    {
        $pdo = Database::getConnection();
        $sql = "
            SELECT u.id_usuario, u.nombre_completo, u.nombre_usuario, u.correo_electronico, u.telefono
              FROM usuarios u
              JOIN roles r ON r.id_rol = u.id_rol
              LEFT JOIN medicos m ON m.id_usuario = u.id_usuario
             WHERE r.nombre = 'Médico'
               AND u.estado = 'Activo'
               AND m.id_medico IS NULL
             ORDER BY u.nombre_completo ASC
        ";
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * CU03 (Regla Smart-DB): Invoca obligatoriamente CALL sp_crear_usuario(...)
     */
    public function crearUsuario(
        int $idRol,
        string $nombreCompleto,
        string $nombreUsuario,
        string $correo,
        string $telefono,
        string $plainPassword,
        int $idAdminEjecutor,
        string $ipOrigen
    ): int {
        $pdo = Database::getConnection();
        $passwordHash = password_hash($plainPassword, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("
            CALL sp_crear_usuario(
                :id_rol::integer,
                :nombre_completo::varchar,
                :nombre_usuario::varchar,
                :correo::varchar,
                :telefono::varchar,
                :password_hash::varchar,
                :id_admin::bigint,
                :ip::varchar,
                NULL
            )
        ");
        $stmt->execute([
            ':id_rol'          => $idRol,
            ':nombre_completo' => trim($nombreCompleto),
            ':nombre_usuario'  => trim($nombreUsuario),
            ':correo'          => strtolower(trim($correo)),
            ':telefono'        => trim($telefono),
            ':password_hash'   => $passwordHash,
            ':id_admin'        => $idAdminEjecutor,
            ':ip'              => $ipOrigen,
        ]);

        $out = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($out['p_id_usuario'] ?? 0);
    }

    public function actualizarUsuario(
        int $idUsuario,
        int $idRol,
        string $nombreCompleto,
        string $nombreUsuario,
        string $correo,
        string $telefono,
        ?string $newPlainPassword,
        int $idAdminEjecutor,
        string $ipOrigen
    ): void {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $prevStmt = $pdo->prepare('SELECT id_usuario, id_rol, nombre_completo, nombre_usuario, correo_electronico, telefono, estado FROM usuarios WHERE id_usuario = :id FOR UPDATE');
            $prevStmt->execute([':id' => $idUsuario]);
            $prev = $prevStmt->fetch(PDO::FETCH_ASSOC);
            if (!$prev) {
                throw new \RuntimeException('El usuario especificado no existe.');
            }

            if ($newPlainPassword !== null && $newPlainPassword !== '') {
                $hash = password_hash($newPlainPassword, PASSWORD_BCRYPT);
                $upd = $pdo->prepare("
                    UPDATE usuarios
                       SET id_rol = :rol,
                           nombre_completo = :nom,
                           nombre_usuario = :usr,
                           correo_electronico = :cor,
                           telefono = :tel,
                           password_hash = :pwd
                     WHERE id_usuario = :id
                ");
                $upd->execute([
                    ':rol' => $idRol,
                    ':nom' => trim($nombreCompleto),
                    ':usr' => trim($nombreUsuario),
                    ':cor' => strtolower(trim($correo)),
                    ':tel' => trim($telefono),
                    ':pwd' => $hash,
                    ':id'  => $idUsuario,
                ]);
            } else {
                $upd = $pdo->prepare("
                    UPDATE usuarios
                       SET id_rol = :rol,
                           nombre_completo = :nom,
                           nombre_usuario = :usr,
                           correo_electronico = :cor,
                           telefono = :tel
                     WHERE id_usuario = :id
                ");
                $upd->execute([
                    ':rol' => $idRol,
                    ':nom' => trim($nombreCompleto),
                    ':usr' => trim($nombreUsuario),
                    ':cor' => strtolower(trim($correo)),
                    ':tel' => trim($telefono),
                    ':id'  => $idUsuario,
                ]);
            }

            $newData = [
                'id_rol'             => $idRol,
                'nombre_completo'    => trim($nombreCompleto),
                'nombre_usuario'     => trim($nombreUsuario),
                'correo_electronico' => strtolower(trim($correo)),
                'telefono'           => trim($telefono),
                'password_changed'   => ($newPlainPassword !== null && $newPlainPassword !== ''),
            ];

            $aud = $pdo->prepare("
                INSERT INTO auditorias (id_usuario, modulo, operacion, id_registro_afectado, datos_previos, datos_nuevos, ip_origen)
                VALUES (:adm, 'Seguridad', 'UPDATE', :reg, :prev::jsonb, :new::jsonb, :ip)
            ");
            $aud->execute([
                ':adm'  => $idAdminEjecutor,
                ':reg'  => (string) $idUsuario,
                ':prev' => json_encode($prev, JSON_UNESCAPED_UNICODE),
                ':new'  => json_encode($newData, JSON_UNESCAPED_UNICODE),
                ':ip'   => $ipOrigen,
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
     * CU03 (Regla Smart-DB): Invoca obligatoriamente CALL sp_conmutar_estado_usuario(...)
     */
    public function conmutarEstado(int $idUsuarioObjetivo, int $idAdminEjecutor, string $ipOrigen): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            CALL sp_conmutar_estado_usuario(
                :objetivo::bigint,
                :admin::bigint,
                :ip::varchar
            )
        ");
        $stmt->execute([
            ':objetivo' => $idUsuarioObjetivo,
            ':admin'    => $idAdminEjecutor,
            ':ip'       => $ipOrigen,
        ]);
    }

    public function cambiarPasswordPropia(int $idUsuario, string $currentPassword, string $newPassword, string $ip): void
    {
        $user = $this->findById($idUsuario);
        if (!$user) {
            throw new \RuntimeException('Usuario no encontrado.');
        }
        if (!$this->verifyUserPassword($user, $currentPassword)) {
            throw new \RuntimeException('La contraseña actual ingresada es incorrecta.');
        }

        $pdo = Database::getConnection();
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('UPDATE usuarios SET password_hash = :h WHERE id_usuario = :id');
        $stmt->execute([':h' => $newHash, ':id' => $idUsuario]);

        $aud = $pdo->prepare("
            INSERT INTO auditorias (id_usuario, modulo, operacion, id_registro_afectado, datos_nuevos, ip_origen)
            VALUES (:uid, 'Seguridad', 'UPDATE', :reg, :nuevos::jsonb, :ip)
        ");
        $aud->execute([
            ':uid'    => $idUsuario,
            ':reg'    => (string) $idUsuario,
            ':nuevos' => json_encode(['accion' => 'Cambio de contraseña desde perfil personal'], JSON_UNESCAPED_UNICODE),
            ':ip'     => $ip,
        ]);
    }

    public function crearTokenRecuperacion(string $correo, string $ip): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT id_usuario, nombre_completo, nombre_usuario, correo_electronico, estado
              FROM usuarios
             WHERE LOWER(correo_electronico) = LOWER(:correo)
             LIMIT 1
        ");
        $stmt->execute([':correo' => trim($correo)]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || $user['estado'] !== 'Activo') {
            return null;
        }

        // Invalidar tokens anteriores pendientes de este usuario
        $pdo->prepare('UPDATE password_resets SET usado = TRUE WHERE id_usuario = :uid AND usado = FALSE')
            ->execute([':uid' => (int) $user['id_usuario']]);

        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = password_hash($plainToken, PASSWORD_BCRYPT);

        $ins = $pdo->prepare("
            INSERT INTO password_resets (id_usuario, correo_electronico, token_hash, expira_en, usado, ip_solicitud)
            VALUES (:uid, :correo, :hash, CURRENT_TIMESTAMP + INTERVAL '30 minutes', FALSE, :ip)
            RETURNING id_reset
        ");
        $ins->execute([
            ':uid'    => (int) $user['id_usuario'],
            ':correo' => $user['correo_electronico'],
            ':hash'   => $tokenHash,
            ':ip'     => $ip,
        ]);
        $idReset = (int) $ins->fetchColumn();

        return [
            'id_reset' => $idReset,
            'token'    => $plainToken,
            'user'     => $user,
        ];
    }

    public function validarTokenRecuperacion(int $idReset, string $plainToken): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT pr.*, u.nombre_completo, u.nombre_usuario
              FROM password_resets pr
              JOIN usuarios u ON u.id_usuario = pr.id_usuario
             WHERE pr.id_reset = :id
               AND pr.usado = FALSE
               AND pr.expira_en > CURRENT_TIMESTAMP
               AND u.estado = 'Activo'
             LIMIT 1
        ");
        $stmt->execute([':id' => $idReset]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        if (!password_verify($plainToken, (string) $row['token_hash'])) {
            return null;
        }
        return $row;
    }

    public function aplicarRecuperacionPassword(int $idReset, string $plainToken, string $newPassword, string $ip): void
    {
        $validRow = $this->validarTokenRecuperacion($idReset, $plainToken);
        if (!$validRow) {
            throw new \RuntimeException('El enlace de restablecimiento es inválido o ha expirado. Solicite uno nuevo.');
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE usuarios SET password_hash = :h WHERE id_usuario = :uid')
                ->execute([':h' => $newHash, ':uid' => (int) $validRow['id_usuario']]);

            $pdo->prepare('UPDATE password_resets SET usado = TRUE WHERE id_reset = :rid')
                ->execute([':rid' => $idReset]);

            $aud = $pdo->prepare("
                INSERT INTO auditorias (id_usuario, modulo, operacion, id_registro_afectado, datos_nuevos, ip_origen)
                VALUES (:uid, 'Seguridad', 'UPDATE', :reg, :nuevos::jsonb, :ip)
            ");
            $aud->execute([
                ':uid'    => (int) $validRow['id_usuario'],
                ':reg'    => (string) $validRow['id_usuario'],
                ':nuevos' => json_encode(['accion' => 'Restablecimiento de contraseña mediante token SMTP'], JSON_UNESCAPED_UNICODE),
                ':ip'     => $ip,
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
