<?php
declare(strict_types=1);

namespace App\Modules\PacientesClinica\Models;

use Database;
use PDO;

/**
 * CU07: Modelo de Pacientes, Fichas Médicas Preventivas y Contactos de Auxilio (RF02, RN01, RN11)
 */
final class PacienteModel
{
    public function listAll(array $filters = [], int $limit = 300): array
    {
        $pdo = Database::getConnection();
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['estado'])) {
            $where[] = 'p.estado = :estado';
            $params[':estado'] = (string) $filters['estado'];
        }
        if (!empty($filters['tipo_sangre'])) {
            $where[] = 'fm.tipo_sangre = :sangre';
            $params[':sangre'] = (string) $filters['tipo_sangre'];
        }
        if (!empty($filters['q'])) {
            $where[] = "(
                p.ci ILIKE :q OR
                p.codigo_paciente ILIKE :q OR
                p.nombres ILIKE :q OR
                p.apellidos ILIKE :q OR
                (p.nombres || ' ' || p.apellidos) ILIKE :q
            )";
            $params[':q'] = '%' . trim((string) $filters['q']) . '%';
        }

        $sql = "
            SELECT p.*,
                   EXTRACT(YEAR FROM AGE(CURRENT_DATE, p.fecha_nacimiento))::int AS edad,
                   COALESCE(fm.tipo_sangre, 'Desconocido') AS tipo_sangre,
                   COALESCE(fm.alergias, 'Ninguna conocida') AS alergias,
                   COALESCE(fm.enfermedades_cronicas, 'Ninguna reportada') AS enfermedades_cronicas,
                   COALESCE(fm.medicacion_actual, 'Ninguna') AS medicacion_actual,
                   COALESCE(fm.observaciones, '') AS observaciones,
                   fm.fecha_actualizacion AS ficha_actualizada,
                   ce.id_contacto,
                   COALESCE(ce.nombre_completo, '') AS contacto_nombre,
                   COALESCE(ce.parentesco, '') AS contacto_parentesco,
                   COALESCE(ce.telefono, '') AS contacto_telefono,
                   u.nombre_usuario AS portal_usuario,
                   (SELECT COUNT(*) FROM citas c WHERE c.id_paciente = p.id_paciente) AS total_citas
              FROM pacientes p
              LEFT JOIN fichas_medicas fm ON fm.id_paciente = p.id_paciente
              LEFT JOIN LATERAL (
                  SELECT id_contacto, nombre_completo, parentesco, telefono
                    FROM contactos_emergencia c2
                   WHERE c2.id_paciente = p.id_paciente
                   ORDER BY c2.id_contacto ASC
                   LIMIT 1
              ) ce ON TRUE
              LEFT JOIN usuarios u ON u.id_usuario = p.id_usuario
             WHERE " . implode(' AND ', $where) . "
             ORDER BY p.id_paciente DESC
             LIMIT " . max(10, min(1000, $limit));

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $idPaciente): ?array
    {
        $pdo = Database::getConnection();
        $sql = "
            SELECT p.*,
                   EXTRACT(YEAR FROM AGE(CURRENT_DATE, p.fecha_nacimiento))::int AS edad,
                   COALESCE(fm.tipo_sangre, 'Desconocido') AS tipo_sangre,
                   COALESCE(fm.alergias, 'Ninguna conocida') AS alergias,
                   COALESCE(fm.enfermedades_cronicas, 'Ninguna reportada') AS enfermedades_cronicas,
                   COALESCE(fm.medicacion_actual, 'Ninguna') AS medicacion_actual,
                   COALESCE(fm.observaciones, '') AS observaciones,
                   fm.fecha_actualizacion AS ficha_actualizada,
                   COALESCE(ce.nombre_completo, '') AS contacto_nombre,
                   COALESCE(ce.parentesco, '') AS contacto_parentesco,
                   COALESCE(ce.telefono, '') AS contacto_telefono
              FROM pacientes p
              LEFT JOIN fichas_medicas fm ON fm.id_paciente = p.id_paciente
              LEFT JOIN LATERAL (
                  SELECT nombre_completo, parentesco, telefono
                    FROM contactos_emergencia c2
                   WHERE c2.id_paciente = p.id_paciente
                   ORDER BY c2.id_contacto ASC
                   LIMIT 1
              ) ce ON TRUE
             WHERE p.id_paciente = :id
             LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $idPaciente]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function searchActivos(string $term, int $limit = 15): array
    {
        $pdo = Database::getConnection();
        $sql = "
            SELECT p.id_paciente,
                   p.codigo_paciente,
                   p.ci,
                   p.nombres,
                   p.apellidos,
                   p.telefono,
                   p.sexo,
                   EXTRACT(YEAR FROM AGE(CURRENT_DATE, p.fecha_nacimiento))::int AS edad,
                   COALESCE(fm.tipo_sangre, 'Desconocido') AS tipo_sangre,
                   COALESCE(fm.alergias, 'Ninguna conocida') AS alergias
              FROM pacientes p
              LEFT JOIN fichas_medicas fm ON fm.id_paciente = p.id_paciente
             WHERE p.estado = 'Activo'
               AND (
                   p.ci ILIKE :q OR
                   p.codigo_paciente ILIKE :q OR
                   p.nombres ILIKE :q OR
                   p.apellidos ILIKE :q OR
                   (p.nombres || ' ' || p.apellidos) ILIKE :q
               )
             ORDER BY p.apellidos ASC, p.nombres ASC
             LIMIT " . max(5, min(50, $limit));

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':q' => '%' . trim($term) . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * CU07 (Regla Smart-DB): Invoca obligatoriamente CALL sp_registrar_paciente_completo(...)
     */
    public function registrarPacienteCompleto(
        array $data,
        ?array $portalCredentials,
        ?int $idUsuarioEjecutor,
        string $ip
    ): array {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $idUsuarioPortal = null;

            if ($portalCredentials !== null) {
                $rolPacId = (int) $pdo->query("SELECT id_rol FROM roles WHERE nombre = 'Paciente' LIMIT 1")->fetchColumn();
                $nombreCompleto = trim((string) $data['nombres']) . ' ' . trim((string) $data['apellidos']);
                $pwdHash = password_hash((string) $portalCredentials['password'], PASSWORD_BCRYPT);

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
                $spUser->bindValue(':rol', $rolPacId, PDO::PARAM_INT);
                $spUser->bindValue(':nom', $nombreCompleto, PDO::PARAM_STR);
                $spUser->bindValue(':usr', trim((string) $portalCredentials['nombre_usuario']), PDO::PARAM_STR);
                $spUser->bindValue(':cor', strtolower(trim((string) $data['correo'])), PDO::PARAM_STR);
                $spUser->bindValue(':tel', trim((string) $data['telefono']), PDO::PARAM_STR);
                $spUser->bindValue(':pwd', $pwdHash, PDO::PARAM_STR);
                $spUser->bindValue(':adm', $idUsuarioEjecutor, ($idUsuarioEjecutor === null || $idUsuarioEjecutor <= 0) ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $spUser->bindValue(':ip', $ip, PDO::PARAM_STR);
                $spUser->execute();
                $outUser = $spUser->fetch(PDO::FETCH_ASSOC);
                $idUsuarioPortal = (int) ($outUser['p_id_usuario'] ?? 0);
            }

            $stmt = $pdo->prepare("
                CALL sp_registrar_paciente_completo(
                    :id_usuario::bigint,
                    :ci::varchar,
                    :nombres::varchar,
                    :apellidos::varchar,
                    :fecha_nacimiento::date,
                    :sexo::char(1),
                    :telefono::varchar,
                    :correo::varchar,
                    :direccion::varchar,
                    :tipo_sangre::varchar,
                    :alergias::text,
                    :enfermedades_cronicas::text,
                    :medicacion_actual::text,
                    :contacto_nombre::varchar,
                    :contacto_parentesco::varchar,
                    :contacto_telefono::varchar,
                    :ip::varchar,
                    NULL,
                    NULL
                )
            ");

            $stmt->bindValue(':id_usuario', $idUsuarioPortal, $idUsuarioPortal === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':ci', trim((string) $data['ci']), PDO::PARAM_STR);
            $stmt->bindValue(':nombres', trim((string) $data['nombres']), PDO::PARAM_STR);
            $stmt->bindValue(':apellidos', trim((string) $data['apellidos']), PDO::PARAM_STR);
            $stmt->bindValue(':fecha_nacimiento', (string) $data['fecha_nacimiento'], PDO::PARAM_STR);
            $stmt->bindValue(':sexo', strtoupper((string) $data['sexo']), PDO::PARAM_STR);
            $stmt->bindValue(':telefono', trim((string) $data['telefono']), PDO::PARAM_STR);
            $stmt->bindValue(':correo', strtolower(trim((string) ($data['correo'] ?? ''))), PDO::PARAM_STR);
            $stmt->bindValue(':direccion', trim((string) ($data['direccion'] ?? '')), PDO::PARAM_STR);
            $stmt->bindValue(':tipo_sangre', (string) ($data['tipo_sangre'] ?? 'Desconocido'), PDO::PARAM_STR);
            $stmt->bindValue(':alergias', trim((string) ($data['alergias'] ?? '')), PDO::PARAM_STR);
            $stmt->bindValue(':enfermedades_cronicas', trim((string) ($data['enfermedades_cronicas'] ?? '')), PDO::PARAM_STR);
            $stmt->bindValue(':medicacion_actual', trim((string) ($data['medicacion_actual'] ?? '')), PDO::PARAM_STR);
            $stmt->bindValue(':contacto_nombre', trim((string) ($data['contacto_nombre'] ?? '')), PDO::PARAM_STR);
            $stmt->bindValue(':contacto_parentesco', trim((string) ($data['contacto_parentesco'] ?? '')), PDO::PARAM_STR);
            $stmt->bindValue(':contacto_telefono', trim((string) ($data['contacto_telefono'] ?? '')), PDO::PARAM_STR);
            $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
            $stmt->execute();

            $out = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $idPac = (int) ($out['p_id_paciente'] ?? 0);

            // Si tiene observaciones adicionales en ficha, actualizarlas
            if (!empty($data['observaciones']) && $idPac > 0) {
                $pdo->prepare('UPDATE fichas_medicas SET observaciones = :obs WHERE id_paciente = :id')
                    ->execute([':obs' => trim((string) $data['observaciones']), ':id' => $idPac]);
            }

            $pdo->commit();
            return [
                'id_paciente'     => $idPac,
                'codigo_paciente' => (string) ($out['p_codigo_paciente'] ?? ''),
                'id_usuario'      => $idUsuarioPortal,
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * CU07 (Regla Smart-DB): Invoca obligatoriamente CALL sp_actualizar_ficha_medica(...)
     */
    public function actualizarFichaMedica(
        int $idPaciente,
        int $idUsuarioEjecutor,
        string $tipoSangre,
        string $alergias,
        string $enfermedadesCronicas,
        string $medicacionActual,
        string $observaciones,
        string $ip
    ): void {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            CALL sp_actualizar_ficha_medica(
                :pac::bigint,
                :usr::bigint,
                :sangre::varchar,
                :alergias::text,
                :cronicas::text,
                :medicacion::text,
                :obs::text,
                :ip::varchar
            )
        ");
        $stmt->execute([
            ':pac'        => $idPaciente,
            ':usr'        => $idUsuarioEjecutor,
            ':sangre'     => $tipoSangre,
            ':alergias'   => trim($alergias),
            ':cronicas'   => trim($enfermedadesCronicas),
            ':medicacion' => trim($medicacionActual),
            ':obs'        => trim($observaciones),
            ':ip'         => $ip,
        ]);
    }

    public function actualizarDatosDemograficosYContacto(
        int $idPaciente,
        array $data,
        int $idUsuarioEjecutor,
        string $ip
    ): void {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $prevStmt = $pdo->prepare('SELECT * FROM pacientes WHERE id_paciente = :id FOR UPDATE');
            $prevStmt->execute([':id' => $idPaciente]);
            $prev = $prevStmt->fetch(PDO::FETCH_ASSOC);
            if (!$prev) {
                throw new \RuntimeException('Paciente no encontrado.');
            }

            $upd = $pdo->prepare("
                UPDATE pacientes
                   SET ci = :ci,
                       nombres = :nom,
                       apellidos = :ape,
                       fecha_nacimiento = :fnac,
                       sexo = :sexo,
                       telefono = :tel,
                       correo = :cor,
                       direccion = :dir
                 WHERE id_paciente = :id
            ");
            $upd->execute([
                ':ci'   => trim((string) $data['ci']),
                ':nom'  => trim((string) $data['nombres']),
                ':ape'  => trim((string) $data['apellidos']),
                ':fnac' => (string) $data['fecha_nacimiento'],
                ':sexo' => strtoupper((string) $data['sexo']),
                ':tel'  => trim((string) $data['telefono']),
                ':cor'  => strtolower(trim((string) ($data['correo'] ?? ''))),
                ':dir'  => trim((string) ($data['direccion'] ?? '')),
                ':id'   => $idPaciente,
            ]);

            // Actualizar contacto de emergencia
            $pdo->prepare('DELETE FROM contactos_emergencia WHERE id_paciente = :id')
                ->execute([':id' => $idPaciente]);

            if (!empty(trim((string) ($data['contacto_nombre'] ?? '')))) {
                $pdo->prepare("
                    INSERT INTO contactos_emergencia (id_paciente, nombre_completo, parentesco, telefono)
                    VALUES (:id, :nom, :par, :tel)
                ")->execute([
                    ':id'  => $idPaciente,
                    ':nom' => trim((string) $data['contacto_nombre']),
                    ':par' => trim((string) ($data['contacto_parentesco'] ?? 'Familiar')),
                    ':tel' => trim((string) ($data['contacto_telefono'] ?? '')),
                ]);
            }

            $aud = $pdo->prepare("
                INSERT INTO auditorias (id_usuario, modulo, operacion, id_registro_afectado, datos_previos, datos_nuevos, ip_origen)
                VALUES (:uid, 'Pacientes', 'UPDATE', :reg, :prev::jsonb, :new::jsonb, :ip)
            ");
            $aud->execute([
                ':uid'  => $idUsuarioEjecutor,
                ':reg'  => (string) $idPaciente,
                ':prev' => json_encode($prev, JSON_UNESCAPED_UNICODE),
                ':new'  => json_encode($data, JSON_UNESCAPED_UNICODE),
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

    public function conmutarEstado(int $idPaciente, int $idUsuarioEjecutor, string $ip): string
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT estado, codigo_paciente FROM pacientes WHERE id_paciente = :id FOR UPDATE');
            $stmt->execute([':id' => $idPaciente]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new \RuntimeException('Paciente no encontrado.');
            }

            $nuevo = ($row['estado'] === 'Activo') ? 'Inactivo' : 'Activo';
            $pdo->prepare('UPDATE pacientes SET estado = :est WHERE id_paciente = :id')
                ->execute([':est' => $nuevo, ':id' => $idPaciente]);

            $aud = $pdo->prepare("
                INSERT INTO auditorias (id_usuario, modulo, operacion, id_registro_afectado, datos_previos, datos_nuevos, ip_origen)
                VALUES (:uid, 'Pacientes', 'UPDATE', :reg, :prev::jsonb, :new::jsonb, :ip)
            ");
            $aud->execute([
                ':uid'  => $idUsuarioEjecutor,
                ':reg'  => (string) $idPaciente,
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
