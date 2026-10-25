<?php
declare(strict_types=1);

namespace App\Modules\CatalogoMedico\Models;

use Database;
use PDO;

/**
 * CU06: Modelo de Horarios Semanales de Atención Médica (RF05, RN05, RN08)
 */
final class HorarioModel
{
    public function listAll(?int $idMedico = null): array
    {
        $pdo = Database::getConnection();
        $where = '';
        $params = [];
        if ($idMedico !== null && $idMedico > 0) {
            $where = 'WHERE h.id_medico = :med';
            $params[':med'] = $idMedico;
        }

        $sql = "
            SELECT h.*,
                   m.codigo_medico,
                   m.nombres || ' ' || m.apellidos AS medico_nombre,
                   m.estado AS medico_estado,
                   COALESCE(STRING_AGG(DISTINCT e.nombre, ', '), '') AS especialidades
              FROM horarios_atencion h
              JOIN medicos m ON m.id_medico = h.id_medico
              LEFT JOIN medico_especialidad me ON me.id_medico = m.id_medico
              LEFT JOIN especialidades e ON e.id_especialidad = me.id_especialidad
              {$where}
             GROUP BY h.id_horario, m.codigo_medico, m.nombres, m.apellidos, m.estado
             ORDER BY m.apellidos ASC, h.dia_semana ASC, h.hora_inicio ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * CU06 (Regla Smart-DB): Invoca obligatoriamente CALL sp_asignar_horario_medico(...)
     */
    public function asignarHorario(
        int $idMedico,
        int $idUsuarioAdmin,
        int $diaSemana,
        string $horaInicio,
        string $horaFin,
        int $duracionMinutos,
        string $ip
    ): int {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            CALL sp_asignar_horario_medico(
                :med::bigint,
                :adm::bigint,
                :dia::smallint,
                :ini::time,
                :fin::time,
                :dur::integer,
                :ip::varchar,
                NULL
            )
        ");
        $stmt->execute([
            ':med' => $idMedico,
            ':adm' => $idUsuarioAdmin,
            ':dia' => $diaSemana,
            ':ini' => $horaInicio,
            ':fin' => $horaFin,
            ':dur' => $duracionMinutos,
            ':ip'  => $ip,
        ]);
        $out = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($out['p_id_horario'] ?? 0);
    }

    public function conmutarEstado(int $idHorario, int $idAdmin, string $ip): string
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM horarios_atencion WHERE id_horario = :id FOR UPDATE');
            $stmt->execute([':id' => $idHorario]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new \RuntimeException('El turno horario no existe.');
            }

            $nuevo = ($row['estado'] === 'Activo') ? 'Inactivo' : 'Activo';
            $pdo->prepare('UPDATE horarios_atencion SET estado = :est WHERE id_horario = :id')
                ->execute([':est' => $nuevo, ':id' => $idHorario]);

            $aud = $pdo->prepare("
                INSERT INTO auditorias (id_usuario, modulo, operacion, id_registro_afectado, datos_previos, datos_nuevos, ip_origen)
                VALUES (:uid, 'Horarios Médicos', 'UPDATE', :reg, :prev::jsonb, :new::jsonb, :ip)
            ");
            $aud->execute([
                ':uid'  => $idAdmin,
                ':reg'  => (string) $idHorario,
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
