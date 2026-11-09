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
                   cc.fecha_cancelacion,
                   CASE
                       WHEN aud_cont.id_auditoria IS NOT NULL
                            OR COALESCE(c.motivo_consulta, '') ILIKE '%Reprogramación automática por contingencia%'
                       THEN TRUE
                       ELSE FALSE
                   END AS reprogramada_contingencia,
                   COALESCE(aud_cont.datos_nuevos->>'fecha_anterior', aud_cont.datos_previos->>'fecha_cita') AS contingencia_fecha_anterior,
                   SUBSTRING(COALESCE(aud_cont.datos_nuevos->>'hora_anterior', aud_cont.datos_previos->>'hora_inicio', '') FROM 1 FOR 5) AS contingencia_hora_anterior,
                   COALESCE(aud_cont.datos_nuevos->>'tipo_bloqueo', 'Bloqueo de disponibilidad médica') AS contingencia_tipo_bloqueo,
                   COALESCE(aud_cont.datos_nuevos->>'motivo_bloqueo', 'Contingencia institucional') AS contingencia_motivo_bloqueo,
                   aud_cont.fecha_hora AS contingencia_fecha_hora
              FROM citas c
              JOIN pacientes p ON p.id_paciente = c.id_paciente
              LEFT JOIN fichas_medicas fm ON fm.id_paciente = p.id_paciente
              JOIN medicos m ON m.id_medico = c.id_medico
              JOIN especialidades e ON e.id_especialidad = c.id_especialidad
              LEFT JOIN cancelaciones_cita cc ON cc.id_cita = c.id_cita
              LEFT JOIN LATERAL (
                  SELECT a.id_auditoria,
                         a.datos_previos,
                         a.datos_nuevos,
                         a.fecha_hora
                    FROM auditorias a
                   WHERE a.modulo = 'Citas Médicas'
                     AND a.operacion = 'UPDATE'
                     AND a.id_registro_afectado = c.id_cita::text
                     AND (
                         COALESCE(a.datos_nuevos->>'reprogramacion_contingencia', 'false') = 'true'
                         OR COALESCE(a.datos_nuevos->>'motivo_consulta', '') ILIKE '%Reprogramación automática por contingencia%'
                     )
                   ORDER BY a.id_auditoria DESC
                   LIMIT 1
              ) aud_cont ON TRUE
             WHERE " . implode(' AND ', $where) . "
             ORDER BY {$orderDir}
             LIMIT " . max(10, min(1000, $limit));

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * CU16: Algoritmo de Reubicación Automática Masiva Libre de Colisiones por Contingencia / Bloqueo de Agenda
     */
    public function reubicarCitasPorContingencia(
        ?int $idMedico,
        string $fechaInicio,
        string $fechaFin,
        int $idAdmin,
        string $tipoBloqueo,
        string $motivoBloqueo,
        string $ip,
        ?int $idBloqueo = null
    ): array {
        $pdo = Database::getConnection();

        $sqlAfectadas = "
            SELECT c.id_cita,
                   c.codigo_cita,
                   c.id_paciente,
                   c.id_medico,
                   c.id_especialidad,
                   c.fecha_cita::text AS fecha_anterior,
                   TO_CHAR(c.hora_inicio, 'HH24:MI') AS hora_anterior,
                   TO_CHAR(c.hora_fin, 'HH24:MI') AS hora_fin_anterior,
                   COALESCE(c.motivo_consulta, '') AS motivo_consulta,
                   c.estado,
                   p.codigo_paciente,
                   p.nombres || ' ' || p.apellidos AS paciente_nombre,
                   COALESCE(NULLIF(TRIM(p.correo), ''), u_pac.correo_electronico, '') AS paciente_correo,
                   p.telefono AS paciente_telefono,
                   m.codigo_medico,
                   m.nombres || ' ' || m.apellidos AS medico_nombre,
                   e.nombre AS especialidad
              FROM citas c
              JOIN pacientes p ON p.id_paciente = c.id_paciente
              LEFT JOIN usuarios u_pac ON u_pac.id_usuario = p.id_usuario
              JOIN medicos m ON m.id_medico = c.id_medico
              JOIN especialidades e ON e.id_especialidad = c.id_especialidad
             WHERE c.estado IN ('Programada', 'Confirmada')
               AND c.fecha_cita BETWEEN :ini::date AND :fin::date
               AND (:med::bigint IS NULL OR c.id_medico = :med::bigint)
             ORDER BY c.fecha_cita ASC, c.hora_inicio ASC
        ";

        $stmtAfectadas = $pdo->prepare($sqlAfectadas);
        $stmtAfectadas->bindValue(':ini', $fechaInicio, PDO::PARAM_STR);
        $stmtAfectadas->bindValue(':fin', $fechaFin, PDO::PARAM_STR);
        $stmtAfectadas->bindValue(':med', $idMedico, $idMedico === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmtAfectadas->execute();
        $citasAfectadas = $stmtAfectadas->fetchAll(PDO::FETCH_ASSOC);

        $reubicadas = [];
        $noReubicadas = [];

        if (empty($citasAfectadas)) {
            return [
                'total_afectadas'   => 0,
                'total_reubicadas'  => 0,
                'reubicadas'        => [],
                'no_reubicadas'     => [],
            ];
        }

        // Sentencias preparadas reutilizables para búsqueda y validación de colisiones
        $stmtSlotDisponible = $pdo->prepare("
            SELECT hora_inicio::text AS hora_inicio,
                   hora_fin::text AS hora_fin,
                   TO_CHAR(hora_inicio, 'HH24:MI') AS hora_inicio_fmt,
                   TO_CHAR(hora_fin, 'HH24:MI') AS hora_fin_fmt,
                   disponible
              FROM fn_obtener_intervalos_disponibles(:id_medico::bigint, :fecha_candidata::date)
             WHERE disponible = true
             ORDER BY hora_inicio ASC
             LIMIT 1
        ");

        $stmtSlotsTodos = $pdo->prepare("
            SELECT hora_inicio::text AS hora_inicio,
                   hora_fin::text AS hora_fin,
                   TO_CHAR(hora_inicio, 'HH24:MI') AS hora_inicio_fmt,
                   TO_CHAR(hora_fin, 'HH24:MI') AS hora_fin_fmt,
                   disponible
              FROM fn_obtener_intervalos_disponibles(:id_medico::bigint, :fecha_candidata::date)
             WHERE disponible = true
             ORDER BY hora_inicio ASC
        ");

        $stmtConflictoPaciente = $pdo->prepare("
            SELECT 1
              FROM citas
             WHERE id_paciente = :id_paciente::bigint
               AND fecha_cita = :fecha_candidata::date
               AND estado IN ('Programada', 'Confirmada')
               AND id_cita <> :id_cita::bigint
               AND (hora_inicio < :hora_fin::time AND hora_fin > :hora_inicio::time)
             LIMIT 1
        ");

        $stmtUpdateMotivo = $pdo->prepare("
            UPDATE citas
               SET motivo_consulta = :motivo
             WHERE id_cita = :id_cita::bigint
        ");

        $stmtUpdateAuditoria = $pdo->prepare("
            UPDATE auditorias
               SET ip_origen = :ip,
                   datos_nuevos = COALESCE(datos_nuevos, '{}'::jsonb) || jsonb_build_object(
                       'reprogramacion_contingencia', true,
                       'observacion_contingencia', 'Reprogramación automática por contingencia/bloqueo de agenda',
                       'id_bloqueo', :id_bloqueo::bigint,
                       'tipo_bloqueo', :tipo_bloqueo::text,
                       'motivo_bloqueo', :motivo_bloqueo::text,
                       'codigo_cita', :codigo_cita::text,
                       'fecha_anterior', :fecha_anterior::text,
                       'hora_anterior', :hora_anterior::text,
                       'nueva_fecha', :nueva_fecha::text,
                       'nueva_hora', :nueva_hora::text
                   )
             WHERE id_auditoria = (
                 SELECT MAX(id_auditoria)
                   FROM auditorias
                  WHERE modulo = 'Citas Médicas'
                    AND operacion = 'UPDATE'
                    AND id_registro_afectado = :id_cita_txt
             )
        ");

        $baseTs = max(strtotime($fechaFin), strtotime(date('Y-m-d')));

        foreach ($citasAfectadas as $cita) {
            $idCita = (int) $cita['id_cita'];
            $idMedCita = (int) $cita['id_medico'];
            $idPacCita = (int) $cita['id_paciente'];
            $encontrado = false;
            $ultimoError = 'No se encontró horario disponible en los próximos 60 días.';

            for ($diaOffset = 1; $diaOffset <= 60; $diaOffset++) {
                $fechaCandidata = date('Y-m-d', strtotime("+{$diaOffset} days", $baseTs));

                try {
                    // 1. Consultar primer bloque disponible según fn_obtener_intervalos_disponibles(...) LIMIT 1
                    $stmtSlotDisponible->execute([
                        ':id_medico'       => $idMedCita,
                        ':fecha_candidata' => $fechaCandidata,
                    ]);
                    $primerSlot = $stmtSlotDisponible->fetch(PDO::FETCH_ASSOC);

                    if (!$primerSlot) {
                        continue;
                    }

                    // Verificar que el paciente no tenga ya otra cita activa en esa misma fecha_candidata y hora_inicio
                    $stmtConflictoPaciente->execute([
                        ':id_paciente'     => $idPacCita,
                        ':fecha_candidata' => $fechaCandidata,
                        ':id_cita'         => $idCita,
                        ':hora_inicio'     => $primerSlot['hora_inicio'],
                        ':hora_fin'        => $primerSlot['hora_fin'],
                    ]);

                    $slotElegido = null;
                    if (!$stmtConflictoPaciente->fetchColumn()) {
                        $slotElegido = $primerSlot;
                    } else {
                        // Si el primer bloque choca con otra cita del mismo paciente, evaluar los demás bloques libres del mismo día
                        $stmtSlotsTodos->execute([
                            ':id_medico'       => $idMedCita,
                            ':fecha_candidata' => $fechaCandidata,
                        ]);
                        $candidatosDia = $stmtSlotsTodos->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($candidatosDia as $cand) {
                            $stmtConflictoPaciente->execute([
                                ':id_paciente'     => $idPacCita,
                                ':fecha_candidata' => $fechaCandidata,
                                ':id_cita'         => $idCita,
                                ':hora_inicio'     => $cand['hora_inicio'],
                                ':hora_fin'        => $cand['hora_fin'],
                            ]);
                            if (!$stmtConflictoPaciente->fetchColumn()) {
                                $slotElegido = $cand;
                                break;
                            }
                        }
                    }

                    if ($slotElegido === null) {
                        continue;
                    }

                    $nuevaHoraInicio = (string) $slotElegido['hora_inicio'];
                    $nuevaHoraFmt = (string) $slotElegido['hora_inicio_fmt'];

                    // 2. Ejecutar procedimiento almacenado CALL sp_reprogramar_cita(...)
                    $this->reprogramarCita($idCita, $idAdmin, $fechaCandidata, $nuevaHoraInicio, $ip);

                    // 3. Concatenar en motivo_consulta y enriquecer registro en tabla auditorias
                    $motivoLimpio = trim((string) preg_replace('/\s*\|\s*\[Reprogramación automática por contingencia.*?\]/u', '', (string) $cita['motivo_consulta']));
                    if ($motivoLimpio === '') {
                        $motivoLimpio = 'Consulta médica programada';
                    }
                    $leyendaContingencia = sprintf(
                        'Reprogramación automática por contingencia/bloqueo de agenda (%s: %s | movida de %s %s a %s %s)',
                        $tipoBloqueo,
                        $motivoBloqueo,
                        date('d/m/Y', strtotime((string) $cita['fecha_anterior'])),
                        $cita['hora_anterior'],
                        date('d/m/Y', strtotime($fechaCandidata)),
                        $nuevaHoraFmt
                    );
                    $nuevoMotivo = $motivoLimpio . ' | [' . $leyendaContingencia . ']';

                    $stmtUpdateMotivo->execute([
                        ':motivo'  => $nuevoMotivo,
                        ':id_cita' => $idCita,
                    ]);

                    $stmtUpdateAuditoria->bindValue(':ip', $ip, PDO::PARAM_STR);
                    $stmtUpdateAuditoria->bindValue(':id_bloqueo', $idBloqueo, $idBloqueo === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                    $stmtUpdateAuditoria->bindValue(':tipo_bloqueo', $tipoBloqueo, PDO::PARAM_STR);
                    $stmtUpdateAuditoria->bindValue(':motivo_bloqueo', $motivoBloqueo, PDO::PARAM_STR);
                    $stmtUpdateAuditoria->bindValue(':codigo_cita', (string) $cita['codigo_cita'], PDO::PARAM_STR);
                    $stmtUpdateAuditoria->bindValue(':fecha_anterior', (string) $cita['fecha_anterior'], PDO::PARAM_STR);
                    $stmtUpdateAuditoria->bindValue(':hora_anterior', (string) $cita['hora_anterior'], PDO::PARAM_STR);
                    $stmtUpdateAuditoria->bindValue(':nueva_fecha', $fechaCandidata, PDO::PARAM_STR);
                    $stmtUpdateAuditoria->bindValue(':nueva_hora', $nuevaHoraFmt, PDO::PARAM_STR);
                    $stmtUpdateAuditoria->bindValue(':id_cita_txt', (string) $idCita, PDO::PARAM_STR);
                    $stmtUpdateAuditoria->execute();

                    $reubicadas[] = [
                        'id_cita'         => $idCita,
                        'codigo_cita'     => (string) $cita['codigo_cita'],
                        'id_paciente'     => $idPacCita,
                        'codigo_paciente' => (string) $cita['codigo_paciente'],
                        'paciente_nombre' => (string) $cita['paciente_nombre'],
                        'paciente_correo' => (string) $cita['paciente_correo'],
                        'medico_nombre'   => (string) $cita['medico_nombre'],
                        'especialidad'    => (string) $cita['especialidad'],
                        'fecha_anterior'  => (string) $cita['fecha_anterior'],
                        'hora_anterior'   => (string) $cita['hora_anterior'],
                        'nueva_fecha'     => $fechaCandidata,
                        'nueva_hora'      => $nuevaHoraFmt,
                        'nueva_hora_fin'  => (string) $slotElegido['hora_fin_fmt'],
                        'ticket_url'      => url('exportar/comprobante', ['id_cita' => $idCita, 'codigo' => $cita['codigo_cita']]),
                        'verify_url'      => url('citas/verificar', ['codigo' => $cita['codigo_cita']]),
                    ];

                    $encontrado = true;
                    break;
                } catch (\Throwable $e) {
                    $ultimoError = $e->getMessage();
                    continue;
                }
            }

            if (!$encontrado) {
                $noReubicadas[] = [
                    'id_cita'         => $idCita,
                    'codigo_cita'     => (string) $cita['codigo_cita'],
                    'paciente_nombre' => (string) $cita['paciente_nombre'],
                    'medico_nombre'   => (string) $cita['medico_nombre'],
                    'especialidad'    => (string) $cita['especialidad'],
                    'fecha_anterior'  => (string) $cita['fecha_anterior'],
                    'hora_anterior'   => (string) $cita['hora_anterior'],
                    'motivo_fallo'    => $ultimoError,
                ];
            }
        }

        return [
            'total_afectadas'  => count($citasAfectadas),
            'total_reubicadas' => count($reubicadas),
            'reubicadas'       => $reubicadas,
            'no_reubicadas'    => $noReubicadas,
        ];
    }

    /**
     * Obtiene las citas activas que fueron reprogramadas automáticamente por contingencia
     * para desplegar el banner de Alerta Visual en el Portal Web (Paciente y Recepción).
     */
    public function obtenerAlertasContingencia(?int $idPaciente = null, ?int $idMedico = null, int $limit = 15): array
    {
        $pdo = Database::getConnection();
        $where = [
            "c.estado IN ('Programada', 'Confirmada')",
            'c.fecha_cita >= CURRENT_DATE',
        ];
        $params = [];

        if ($idPaciente !== null && $idPaciente > 0) {
            $where[] = 'c.id_paciente = :pac';
            $params[':pac'] = $idPaciente;
        }
        if ($idMedico !== null && $idMedico > 0) {
            $where[] = 'c.id_medico = :med';
            $params[':med'] = $idMedico;
        }

        $sql = "
            SELECT c.id_cita,
                   c.codigo_cita,
                   c.id_paciente,
                   c.id_medico,
                   c.fecha_cita::text AS nueva_fecha,
                   TO_CHAR(c.hora_inicio, 'HH24:MI') AS nueva_hora,
                   TO_CHAR(c.hora_fin, 'HH24:MI') AS nueva_hora_fin,
                   c.estado,
                   p.codigo_paciente,
                   p.nombres || ' ' || p.apellidos AS paciente_nombre,
                   m.nombres || ' ' || m.apellidos AS medico_nombre,
                   e.nombre AS especialidad_nombre,
                   COALESCE(aud.datos_nuevos->>'fecha_anterior', aud.datos_previos->>'fecha_cita') AS fecha_anterior,
                   SUBSTRING(COALESCE(aud.datos_nuevos->>'hora_anterior', aud.datos_previos->>'hora_inicio', '') FROM 1 FOR 5) AS hora_anterior,
                   COALESCE(aud.datos_nuevos->>'tipo_bloqueo', 'Bloqueo de disponibilidad médica') AS tipo_bloqueo,
                   COALESCE(aud.datos_nuevos->>'motivo_bloqueo', 'Contingencia institucional') AS motivo_bloqueo,
                   aud.fecha_hora AS fecha_reprogramacion
              FROM citas c
              JOIN pacientes p ON p.id_paciente = c.id_paciente
              JOIN medicos m ON m.id_medico = c.id_medico
              JOIN especialidades e ON e.id_especialidad = c.id_especialidad
              JOIN LATERAL (
                  SELECT a.datos_previos,
                         a.datos_nuevos,
                         a.fecha_hora
                    FROM auditorias a
                   WHERE a.modulo = 'Citas Médicas'
                     AND a.operacion = 'UPDATE'
                     AND a.id_registro_afectado = c.id_cita::text
                     AND (
                         COALESCE(a.datos_nuevos->>'reprogramacion_contingencia', 'false') = 'true'
                         OR COALESCE(a.datos_nuevos->>'motivo_consulta', '') ILIKE '%Reprogramación automática por contingencia%'
                     )
                   ORDER BY a.id_auditoria DESC
                   LIMIT 1
              ) aud ON TRUE
             WHERE " . implode(' AND ', $where) . "
             ORDER BY aud.fecha_hora DESC, c.fecha_cita ASC, c.hora_inicio ASC
             LIMIT " . max(1, min(100, $limit));

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
