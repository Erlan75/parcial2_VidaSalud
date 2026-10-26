<?php
declare(strict_types=1);

namespace App\Modules\GestionCitas\Models;

use Database;
use PDO;

/**
 * Modelo Transaccional Central del Paquete GestionCitas (CU08, CU09, CU10, CU11, CU12, CU13)
 * Cumple 100% la Regla Smart-DB delegando en fn_obtener_intervalos_disponibles y sp_*
 */
final class CitaModel
{
    /**
     * CU08/CU09 (Regla Smart-DB): Invoca obligatoriamente SELECT * FROM fn_obtener_intervalos_disponibles(:id_medico, :fecha)
     */
    public function obtenerIntervalosDisponibles(int $idMedico, string $fecha): array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT TO_CHAR(hora_inicio, 'HH24:MI') AS hora_inicio_fmt,
                   TO_CHAR(hora_fin, 'HH24:MI') AS hora_fin_fmt,
                   hora_inicio::text AS hora_inicio,
                   hora_fin::text AS hora_fin,
                   disponible,
                   motivo_no_disponible
              FROM fn_obtener_intervalos_disponibles(:id_medico::bigint, :fecha::date)
             ORDER BY hora_inicio ASC
        ");
        $stmt->execute([
            ':id_medico' => $idMedico,
            ':fecha'     => $fecha,
        ]);
        $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Si retorna vacío, consultar causa informativa (bloqueo de agenda o día sin turno)
        $motivoVacio = null;
        if (empty($slots)) {
            $bStmt = $pdo->prepare("
                SELECT tipo_bloqueo, motivo
                  FROM bloqueos_agenda
                 WHERE (id_medico IS NULL OR id_medico = :med)
                   AND :fecha::date BETWEEN fecha_inicio AND fecha_fin
                 LIMIT 1
            ");
            $bStmt->execute([':med' => $idMedico, ':fecha' => $fecha]);
            $bloq = $bStmt->fetch(PDO::FETCH_ASSOC);
            if ($bloq) {
                $motivoVacio = "Fecha bloqueada ({$bloq['tipo_bloqueo']}): {$bloq['motivo']}";
            } else {
                $diasStmt = $pdo->prepare("
                    SELECT STRING_AGG(
                               CASE dia_semana
                                   WHEN 1 THEN 'Lunes' WHEN 2 THEN 'Martes' WHEN 3 THEN 'Miércoles'
                                   WHEN 4 THEN 'Jueves' WHEN 5 THEN 'Viernes' WHEN 6 THEN 'Sábado'
                               END || ' (' || TO_CHAR(hora_inicio, 'HH24:MI') || '-' || TO_CHAR(hora_fin, 'HH24:MI') || ')',
                               ', ' ORDER BY dia_semana
                           )
                      FROM horarios_atencion
                     WHERE id_medico = :med AND estado = 'Activo'
                ");
                $diasStmt->execute([':med' => $idMedico]);
                $diasAtencion = (string) ($diasStmt->fetchColumn() ?: 'Ninguno configurado');
                $motivoVacio = "El médico seleccionado no atiende el día elegido. Sus turnos activos son: {$diasAtencion}.";
            }
        }

        return [
            'slots'        => $slots,
            'motivo_vacio' => $motivoVacio,
        ];
    }

    /**
     * Validación anti-spam: verifica si el paciente ya cuenta con una cita activa
     * ('Programada' o 'Confirmada') en el futuro para la misma especialidad o con el mismo médico.
     */
    public function verificarCitaActivaFuturaPaciente(int $idPaciente, int $idMedico, int $idEspecialidad): ?array
    {
        $pdo = Database::getConnection();
        $sql = "
            SELECT c.id_cita,
                   c.codigo_cita,
                   c.fecha_cita,
                   TO_CHAR(c.hora_inicio, 'HH24:MI') AS hora_inicio_fmt,
                   c.estado,
                   c.id_medico,
                   c.id_especialidad,
                   m.nombres || ' ' || m.apellidos AS medico_nombre,
                   e.nombre AS especialidad_nombre
              FROM citas c
              JOIN medicos m ON m.id_medico = c.id_medico
              JOIN especialidades e ON e.id_especialidad = c.id_especialidad
             WHERE c.id_paciente = :pac
               AND c.estado IN ('Programada', 'Confirmada')
               AND (
                   c.fecha_cita > CURRENT_DATE
                   OR (c.fecha_cita = CURRENT_DATE AND c.hora_fin >= CURRENT_TIME)
               )
               AND (c.id_especialidad = :esp OR c.id_medico = :med)
             ORDER BY c.fecha_cita ASC, c.hora_inicio ASC
             LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':pac' => $idPaciente,
            ':esp' => $idEspecialidad,
            ':med' => $idMedico,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Invoca CALL sp_programar_cita(...) aplicando validación anti-spam de citas activas futuras
     */
    public function programarCita(
        int $idPaciente,
        int $idMedico,
        int $idEspecialidad,
        int $idUsuarioRegistro,
        string $fechaCita,
        string $horaInicio,
        string $motivoConsulta,
        string $ip,
        bool $validarAntiSpam = true
    ): array {
        if ($validarAntiSpam) {
            $citaActiva = $this->verificarCitaActivaFuturaPaciente($idPaciente, $idMedico, $idEspecialidad);
            if ($citaActiva !== null) {
                $fechaFmt = date('d/m/Y', strtotime((string) $citaActiva['fecha_cita']));
                $detalle = ((int) $citaActiva['id_medico'] === $idMedico)
                    ? "con el(la) Dr(a). {$citaActiva['medico_nombre']}"
                    : "en la especialidad de {$citaActiva['especialidad_nombre']}";
                throw new \RuntimeException(
                    "No es posible agendar la cita: ya tiene una cita activa ({$citaActiva['codigo_cita']} para el {$fechaFmt} a las {$citaActiva['hora_inicio_fmt']}) {$detalle}. " .
                    "Para reservar un nuevo turno en esta especialidad o con este médico, debe esperar a que se realice su consulta actual o cancelarla previamente."
                );
            }
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            CALL sp_programar_cita(
                :pac::bigint,
                :med::bigint,
                :esp::integer,
                :usr::bigint,
                :fecha::date,
                :hora::time,
                :motivo::text,
                :ip::varchar,
                NULL,
                NULL
            )
        ");
        $stmt->execute([
            ':pac'    => $idPaciente,
            ':med'    => $idMedico,
            ':esp'    => $idEspecialidad,
            ':usr'    => $idUsuarioRegistro,
            ':fecha'  => $fechaCita,
            ':hora'   => $horaInicio,
            ':motivo' => trim($motivoConsulta),
            ':ip'     => $ip,
        ]);
        $out = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'id_cita'     => (int) ($out['p_id_cita'] ?? 0),
            'codigo_cita' => (string) ($out['p_codigo_cita'] ?? ''),
        ];
    }

    /**
     * CU09 (Regla Smart-DB): Invoca obligatoriamente CALL sp_reprogramar_cita(...)
     */
    public function reprogramarCita(
        int $idCita,
        int $idUsuario,
        string $nuevaFecha,
        string $nuevaHoraInicio,
        string $ip
    ): void {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            CALL sp_reprogramar_cita(
                :cita::bigint,
                :usr::bigint,
                :fecha::date,
                :hora::time,
                :ip::varchar
            )
        ");
        $stmt->execute([
            ':cita'  => $idCita,
            ':usr'   => $idUsuario,
            ':fecha' => $nuevaFecha,
            ':hora'  => $nuevaHoraInicio,
            ':ip'    => $ip,
        ]);
    }

    /**
     * CU10 (Regla Smart-DB): Invoca obligatoriamente CALL sp_cancelar_cita(...)
     */
    public function cancelarCita(
        int $idCita,
        int $idUsuario,
        string $motivoCancelacion,
        string $ip
    ): void {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            CALL sp_cancelar_cita(
                :cita::bigint,
                :usr::bigint,
                :motivo::text,
                :ip::varchar
            )
        ");
        $stmt->execute([
            ':cita'   => $idCita,
            ':usr'    => $idUsuario,
            ':motivo' => trim($motivoCancelacion),
            ':ip'     => $ip,
        ]);
    }

    /**
     * CU11 (Regla Smart-DB): Invoca obligatoriamente CALL sp_controlar_asistencia_cita(...)
     */
    public function controlarAsistencia(
        int $idCita,
        int $idUsuario,
        string $nuevoEstado,
        string $ip
    ): void {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            CALL sp_controlar_asistencia_cita(
                :cita::bigint,
                :usr::bigint,
                :estado::varchar,
                :ip::varchar
            )
        ");
        $stmt->execute([
            ':cita'   => $idCita,
            ':usr'    => $idUsuario,
            ':estado' => $nuevoEstado,
            ':ip'     => $ip,
        ]);
    }

    /**
     * CU11 (Regla Smart-DB): Invoca obligatoriamente CALL sp_registrar_atencion_medica(...)
     */
    public function registrarAtencionMedica(
        int $idCita,
        int $idMedico,
        int $idUsuarioMedico,
        string $notasMedicas,
        string $ip
    ): void {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            CALL sp_registrar_atencion_medica(
                :cita::bigint,
                :med::bigint,
                :usr::bigint,
                :notas::text,
                :ip::varchar
            )
        ");
        $stmt->execute([
            ':cita'  => $idCita,
            ':med'   => $idMedico,
            ':usr'   => $idUsuarioMedico,
            ':notas' => trim($notasMedicas),
            ':ip'    => $ip,
        ]);
    }

    public function findByIdOrCodigo(?int $idCita = null, ?string $codigoCita = null): ?array
    {
        $pdo = Database::getConnection();
        $cond = $idCita !== null ? 'c.id_cita = :val' : 'UPPER(c.codigo_cita) = UPPER(:val)';
        $val = $idCita !== null ? $idCita : trim((string) $codigoCita);

        $sql = "
            SELECT c.*,
                   p.codigo_paciente,
                   p.ci AS paciente_ci,
                   p.nombres || ' ' || p.apellidos AS paciente_nombre,
                   p.telefono AS paciente_telefono,
                   p.correo AS paciente_correo,
                   p.sexo AS paciente_sexo,
                   EXTRACT(YEAR FROM AGE(CURRENT_DATE, p.fecha_nacimiento))::int AS paciente_edad,
                   COALESCE(fm.tipo_sangre, 'Desconocido') AS tipo_sangre,
                   COALESCE(fm.alergias, 'Ninguna conocida') AS alergias,
                   COALESCE(fm.enfermedades_cronicas, 'Ninguna reportada') AS enfermedades_cronicas,
                   COALESCE(fm.medicacion_actual, 'Ninguna') AS medicacion_actual,
                   m.codigo_medico,
                   m.registro_profesional,
                   m.nombres || ' ' || m.apellidos AS medico_nombre,
                   e.codigo_especialidad,
                   e.nombre AS especialidad_nombre,
                   cc.motivo_cancelacion,
                   cc.fecha_cancelacion
              FROM citas c
              JOIN pacientes p ON p.id_paciente = c.id_paciente
              LEFT JOIN fichas_medicas fm ON fm.id_paciente = p.id_paciente
              JOIN medicos m ON m.id_medico = c.id_medico
              JOIN especialidades e ON e.id_especialidad = c.id_especialidad
              LEFT JOIN cancelaciones_cita cc ON cc.id_cita = c.id_cita
             WHERE {$cond}
             LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':val' => $val]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listCitas(array $filters = [], int $limit = 300): array
    {
        $pdo = Database::getConnection();
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['id_paciente'])) {
            $where[] = 'c.id_paciente = :pac';
            $params[':pac'] = (int) $filters['id_paciente'];
        }
        if (!empty($filters['id_medico'])) {
            $where[] = 'c.id_medico = :med';
            $params[':med'] = (int) $filters['id_medico'];
        }
        if (!empty($filters['id_especialidad'])) {
            $where[] = 'c.id_especialidad = :esp';
            $params[':esp'] = (int) $filters['id_especialidad'];
        }
        if (!empty($filters['estado'])) {
            $where[] = 'c.estado = :estado';
            $params[':estado'] = (string) $filters['estado'];
        }
        if (!empty($filters['fecha_exacta'])) {
            $where[] = 'c.fecha_cita = :fexacta::date';
            $params[':fexacta'] = (string) $filters['fecha_exacta'];
        }
        if (!empty($filters['fecha_desde'])) {
            $where[] = 'c.fecha_cita >= :fdesde::date';
            $params[':fdesde'] = (string) $filters['fecha_desde'];
        }
        if (!empty($filters['fecha_hasta'])) {
            $where[] = 'c.fecha_cita <= :fhasta::date';
            $params[':fhasta'] = (string) $filters['fecha_hasta'];
        }
        if (!empty($filters['q'])) {
            $where[] = "(
                c.codigo_cita ILIKE :q OR
                p.ci ILIKE :q OR
                p.codigo_paciente ILIKE :q OR
                (p.nombres || ' ' || p.apellidos) ILIKE :q OR
                (m.nombres || ' ' || m.apellidos) ILIKE :q
            )";
            $params[':q'] = '%' . trim((string) $filters['q']) . '%';
        }

        $orderDir = (!empty($filters['orden']) && $filters['orden'] === 'cronologico')
            ? 'c.fecha_cita ASC, c.hora_inicio ASC'
            : 'c.fecha_cita DESC, c.hora_inicio DESC';

        $sql = "
            SELECT c.*,
                   p.codigo_paciente,
                   p.ci AS paciente_ci,
                   p.nombres || ' ' || p.apellidos AS paciente_nombre,
                   p.telefono AS paciente_telefono,
                   EXTRACT(YEAR FROM AGE(CURRENT_DATE, p.fecha_nacimiento))::int AS paciente_edad,
                   COALESCE(fm.tipo_sangre, 'Desconocido') AS tipo_sangre,
                   COALESCE(fm.alergias, 'Ninguna conocida') AS alergias,
                   COALESCE(fm.enfermedades_cronicas, 'Ninguna reportada') AS enfermedades_cronicas,
                   COALESCE(fm.medicacion_actual, 'Ninguna') AS medicacion_actual,
                   m.codigo_medico,
                   m.nombres || ' ' || m.apellidos AS medico_nombre,
                   e.nombre AS especialidad_nombre,
                   cc.motivo_cancelacion,
                   cc.fecha_cancelacion
              FROM citas c
              JOIN pacientes p ON p.id_paciente = c.id_paciente
              LEFT JOIN fichas_medicas fm ON fm.id_paciente = p.id_paciente
              JOIN medicos m ON m.id_medico = c.id_medico
              JOIN especialidades e ON e.id_especialidad = c.id_especialidad
              LEFT JOIN cancelaciones_cita cc ON cc.id_cita = c.id_cita
             WHERE " . implode(' AND ', $where) . "
             ORDER BY {$orderDir}
             LIMIT " . max(10, min(1000, $limit));

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fechas próximas con turnos activos para facilitar navegación en Agenda Médica (CU12)
     */
    public function getProximasFechasConCitas(?int $idMedico = null, int $limit = 6): array
    {
        $pdo = Database::getConnection();
        $where = "c.fecha_cita >= CURRENT_DATE AND c.estado IN ('Programada', 'Confirmada')";
        $params = [];
        if ($idMedico !== null && $idMedico > 0) {
            $where .= ' AND c.id_medico = :med';
            $params[':med'] = $idMedico;
        }

        $sql = "
            SELECT c.fecha_cita,
                   COUNT(*) AS total_citas
              FROM citas c
             WHERE {$where}
             GROUP BY c.fecha_cita
             ORDER BY c.fecha_cita ASC
             LIMIT {$limit}
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
