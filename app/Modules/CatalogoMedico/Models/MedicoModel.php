<?php
declare(strict_types=1);

namespace App\Modules\CatalogoMedico\Models;

use Database;
use PDO;

/**
 * CU05: Modelo de Médicos y Asignación N:M de Especialidades (RF03, RN02, RN04)
 */
final class MedicoModel
{
    public function listAll(array $filters = []): array
    {
        $pdo = Database::getConnection();
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['estado'])) {
            $where[] = 'm.estado = :estado';
            $params[':estado'] = (string) $filters['estado'];
        }
        if (!empty($filters['id_especialidad'])) {
            $where[] = 'EXISTS (SELECT 1 FROM medico_especialidad me2 WHERE me2.id_medico = m.id_medico AND me2.id_especialidad = :esp)';
            $params[':esp'] = (int) $filters['id_especialidad'];
        }
        if (!empty($filters['q'])) {
            $where[] = "(m.nombres ILIKE :q OR m.apellidos ILIKE :q OR m.codigo_medico ILIKE :q OR m.registro_profesional ILIKE :q)";
            $params[':q'] = '%' . trim((string) $filters['q']) . '%';
        }

        $sql = "
            SELECT m.*,
                   u.nombre_usuario,
                   COALESCE(
                       STRING_AGG(DISTINCT e.nombre, ', ' ORDER BY e.nombre),
                       'Sin especialidad'
                   ) AS especialidades_nombres,
                   COALESCE(
                       ARRAY_TO_STRING(ARRAY_AGG(DISTINCT e.id_especialidad), ','),
                       ''
                   ) AS especialidades_ids_csv,
                   (SELECT COUNT(*) FROM horarios_atencion h WHERE h.id_medico = m.id_medico AND h.estado = 'Activo') AS total_turnos,
                   (SELECT COUNT(*) FROM citas c WHERE c.id_medico = m.id_medico) AS total_citas
              FROM medicos m
              JOIN usuarios u ON u.id_usuario = m.id_usuario
              LEFT JOIN medico_especialidad me ON me.id_medico = m.id_medico
              LEFT JOIN especialidades e ON e.id_especialidad = me.id_especialidad
             WHERE " . implode(' AND ', $where) . "
             GROUP BY m.id_medico, u.nombre_usuario
             ORDER BY m.id_medico ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listByEspecialidadActivos(int $idEspecialidad): array
    {
        $pdo = Database::getConnection();
        $sql = "
            SELECT m.id_medico,
                   m.codigo_medico,
                   m.nombres,
                   m.apellidos,
                   m.registro_profesional,
                   COALESCE(
                       STRING_AGG(
                           CASE h.dia_semana
                               WHEN 1 THEN 'Lun' WHEN 2 THEN 'Mar' WHEN 3 THEN 'Mié'
                               WHEN 4 THEN 'Jue' WHEN 5 THEN 'Vie' WHEN 6 THEN 'Sáb'
                           END || ' ' || TO_CHAR(h.hora_inicio, 'HH24:MI') || '-' || TO_CHAR(h.hora_fin, 'HH24:MI'),
                           ' · ' ORDER BY h.dia_semana
                       ),
                       'Sin horarios configurados'
                   ) AS resumen_horarios
              FROM medicos m
              JOIN medico_especialidad me ON me.id_medico = m.id_medico
              LEFT JOIN horarios_atencion h ON h.id_medico = m.id_medico AND h.estado = 'Activo'
             WHERE me.id_especialidad = :esp
               AND m.estado = 'Activo'
             GROUP BY m.id_medico
             ORDER BY m.apellidos ASC, m.nombres ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':esp' => $idEspecialidad]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * CU05 (Regla Smart-DB): Invoca obligatoriamente CALL sp_registrar_medico_completo(...)
     * Soporta creación atómica previa del usuario Médico (sp_crear_usuario) si se solicita.
     */
    public function registrarMedicoCompleto(
        ?int $existingUserId,
        ?array $newUserCredentials,
        string $registroProfesional,
        string $nombres,
        string $apellidos,
        string $telefono,
        string $correo,
        array $especialidadesIds,
        int $idAdmin,
        string $ip
    ): array {
        $cleanEspIds = array_values(array_unique(array_filter(array_map('intval', $especialidadesIds), static fn(int $v) => $v > 0)));
        if (empty($cleanEspIds)) {
            throw new \RuntimeException('Debe seleccionar al menos una especialidad médica activa.');
        }

        $pgIntArray = '{' . implode(',', $cleanEspIds) . '}';
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $idUsuarioMedico = $existingUserId;

            if (!$idUsuarioMedico) {
                // Obtener id_rol de 'Médico'
                $rolMedicoId = (int) $pdo->query("SELECT id_rol FROM roles WHERE nombre = 'Médico' LIMIT 1")->fetchColumn();
                $nombreCompleto = 'Dr(a). ' . trim($nombres) . ' ' . trim($apellidos);
                $username = trim((string) ($newUserCredentials['nombre_usuario'] ?? ''));
                $pwdHash = password_hash((string) ($newUserCredentials['password'] ?? ''), PASSWORD_BCRYPT);

                $spUser = $pdo->prepare("
                    CALL sp_crear_usuario(
                        :rol::integer,
                        :nom::varchar,
                        :usr::varchar,
                        :cor::varchar,
                        :tel::varchar,
                        :pwd::varchar,
                        :adm::bigint,
                        :ip::varchar,
                        NULL
                    )
                ");
                $spUser->execute([
                    ':rol' => $rolMedicoId,
                    ':nom' => $nombreCompleto,
                    ':usr' => $username,
                    ':cor' => strtolower(trim($correo)),
                    ':tel' => trim($telefono),
                    ':pwd' => $pwdHash,
                    ':adm' => $idAdmin,
                    ':ip'  => $ip,
                ]);
                $outUser = $spUser->fetch(PDO::FETCH_ASSOC);
                $idUsuarioMedico = (int) ($outUser['p_id_usuario'] ?? 0);
            }

            $spMed = $pdo->prepare("
                CALL sp_registrar_medico_completo(
                    :uid::bigint,
                    :reg::varchar,
                    :nom::varchar,
                    :ape::varchar,
                    :tel::varchar,
                    :cor::varchar,
                    :esps::integer[],
                    :ip::varchar,
                    NULL,
                    NULL
                )
            ");
            $spMed->execute([
                ':uid'  => $idUsuarioMedico,
                ':reg'  => strtoupper(trim($registroProfesional)),
                ':nom'  => trim($nombres),
                ':ape'  => trim($apellidos),
                ':tel'  => trim($telefono),
                ':cor'  => strtolower(trim($correo)),
                ':esps' => $pgIntArray,
                ':ip'   => $ip,
            ]);
            $outMed = $spMed->fetch(PDO::FETCH_ASSOC) ?: [];

            $pdo->commit();
            return [
                'id_medico'     => (int) ($outMed['p_id_medico'] ?? 0),
                'codigo_medico' => (string) ($outMed['p_codigo_medico'] ?? ''),
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function actualizarMedico(
        int $idMedico,
        string $registroProfesional,
        string $nombres,
        string $apellidos,
        string $telefono,
        string $correo,
        array $especialidadesIds,
        int $idAdmin,
        string $ip
    ): void {
        $cleanEspIds = array_values(array_unique(array_filter(array_map('intval', $especialidadesIds), static fn(int $v) => $v > 0)));
        if (empty($cleanEspIds)) {
            throw new \RuntimeException('El médico debe conservar al menos una especialidad asignada.');
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $prevStmt = $pdo->prepare('SELECT * FROM medicos WHERE id_medico = :id FOR UPDATE');
            $prevStmt->execute([':id' => $idMedico]);
            $prev = $prevStmt->fetch(PDO::FETCH_ASSOC);
            if (!$prev) {
                throw new \RuntimeException('El médico indicado no existe.');
            }

            $upd = $pdo->prepare("
                UPDATE medicos
                   SET registro_profesional = :reg,
                       nombres = :nom,
                       apellidos = :ape,
                       telefono = :tel,
                       correo = :cor
                 WHERE id_medico = :id
            ");
            $upd->execute([
                ':reg' => strtoupper(trim($registroProfesional)),
                ':nom' => trim($nombres),
                ':ape' => trim($apellidos),
                ':tel' => trim($telefono),
                ':cor' => strtolower(trim($correo)),
                ':id'  => $idMedico,
            ]);

            // Sincronizar relación N:M medico_especialidad sin romper FKs históricas
            $placeholders = implode(',', array_fill(0, count($cleanEspIds), '?'));
            $delStmt = $pdo->prepare("DELETE FROM medico_especialidad WHERE id_medico = ? AND id_especialidad NOT IN ({$placeholders})");
            $delStmt->execute(array_merge([$idMedico], $cleanEspIds));

            $insEsp = $pdo->prepare("
                INSERT INTO medico_especialidad (id_medico, id_especialidad, fecha_asignacion)
                VALUES (:med, :esp, CURRENT_DATE)
                ON CONFLICT (id_medico, id_especialidad) DO NOTHING
            ");
            foreach ($cleanEspIds as $espId) {
                $insEsp->execute([':med' => $idMedico, ':esp' => $espId]);
            }

            $aud = $pdo->prepare("
                INSERT INTO auditorias (id_usuario, modulo, operacion, id_registro_afectado, datos_previos, datos_nuevos, ip_origen)
                VALUES (:uid, 'Médicos', 'UPDATE', :reg, :prev::jsonb, :new::jsonb, :ip)
            ");
            $aud->execute([
                ':uid'  => $idAdmin,
                ':reg'  => (string) $idMedico,
                ':prev' => json_encode($prev, JSON_UNESCAPED_UNICODE),
                ':new'  => json_encode(['registro' => strtoupper(trim($registroProfesional)), 'nombres' => trim($nombres), 'apellidos' => trim($apellidos), 'especialidades' => $cleanEspIds], JSON_UNESCAPED_UNICODE),
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

    public function conmutarEstado(int $idMedico, int $idAdmin, string $ip): string
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT estado, codigo_medico FROM medicos WHERE id_medico = :id FOR UPDATE');
            $stmt->execute([':id' => $idMedico]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new \RuntimeException('Médico no encontrado.');
            }

            $nuevo = ($row['estado'] === 'Activo') ? 'Inactivo' : 'Activo';
            $pdo->prepare('UPDATE medicos SET estado = :est WHERE id_medico = :id')
                ->execute([':est' => $nuevo, ':id' => $idMedico]);

            $aud = $pdo->prepare("
                INSERT INTO auditorias (id_usuario, modulo, operacion, id_registro_afectado, datos_previos, datos_nuevos, ip_origen)
                VALUES (:uid, 'Médicos', 'UPDATE', :reg, :prev::jsonb, :new::jsonb, :ip)
            ");
            $aud->execute([
                ':uid'  => $idAdmin,
                ':reg'  => (string) $idMedico,
                ':prev' => json_encode(['estado' => $row['estado']], JSON_UNESCAPED_UNICODE),
                ':new'  => json_encode(['estado' => $nuevo], JSON_UNESCAPED_UNICODE),
                ':ip'   => $ip,
            ]);

            $pdo->commit();
            return $nuevo;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
