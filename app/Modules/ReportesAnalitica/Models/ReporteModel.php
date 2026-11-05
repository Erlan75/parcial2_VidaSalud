<?php
declare(strict_types=1);

namespace App\Modules\ReportesAnalitica\Models;

use Database;
use PDO;

/**
 * Modelo Analítico y de Reportes Hospitalarios del Paquete ReportesAnalitica (CU14, CU15)
 */
final class ReporteModel
{
    public function getDashboardKpis(?int $idMedico = null, ?int $idPaciente = null): array
    {
        $pdo = Database::getConnection();

        if ($idPaciente !== null && $idPaciente > 0) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) AS total_citas,
                       COUNT(*) FILTER (WHERE estado IN ('Programada', 'Confirmada') AND fecha_cita >= CURRENT_DATE) AS proximas,
                       COUNT(*) FILTER (WHERE estado = 'Atendida') AS atendidas,
                       COUNT(*) FILTER (WHERE estado = 'Cancelada') AS canceladas
                  FROM citas
                 WHERE id_paciente = :pac
            ");
            $stmt->execute([':pac' => $idPaciente]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        if ($idMedico !== null && $idMedico > 0) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) AS total_citas,
                       COUNT(*) FILTER (WHERE fecha_cita = CURRENT_DATE AND estado <> 'Cancelada') AS citas_hoy,
                       COUNT(*) FILTER (WHERE estado IN ('Programada', 'Confirmada') AND fecha_cita >= CURRENT_DATE) AS pendientes_futuras,
                       COUNT(*) FILTER (WHERE estado = 'Atendida') AS total_atendidas
                  FROM citas
                 WHERE id_medico = :med
            ");
            $stmt->execute([':med' => $idMedico]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        $sql = "
            SELECT (SELECT COUNT(*) FROM pacientes WHERE estado = 'Activo') AS pacientes_activos,
                   (SELECT COUNT(*) FROM medicos WHERE estado = 'Activo') AS medicos_activos,
                   (SELECT COUNT(*) FROM especialidades WHERE estado = 'Activo') AS especialidades_activas,
                   (SELECT COUNT(*) FROM citas) AS total_citas,
                   (SELECT COUNT(*) FROM citas WHERE estado IN ('Programada', 'Confirmada') AND fecha_cita >= CURRENT_DATE) AS citas_futuras,
                   (SELECT COUNT(*) FROM citas WHERE estado = 'Atendida') AS citas_atendidas,
                   (SELECT COUNT(*) FROM citas WHERE estado = 'Cancelada') AS citas_canceladas,
                   (SELECT COUNT(*) FROM citas WHERE estado = 'Inasistencia') AS citas_inasistencia,
                   (SELECT COUNT(*) FROM citas WHERE estado = 'Programada') AS citas_programadas,
                   (SELECT COUNT(*) FROM citas WHERE estado = 'Confirmada') AS citas_confirmadas
        ";
        return $pdo->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getChartDistribucionEstados(array $filters = []): array
    {
        $pdo = Database::getConnection();
        [$where, $params] = $this->buildCitaWhere($filters);

        $sql = "
            SELECT c.estado, COUNT(*)::int AS total
              FROM citas c
             WHERE {$where}
             GROUP BY c.estado
             ORDER BY c.estado ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getChartDemandaEspecialidades(array $filters = []): array
    {
        $pdo = Database::getConnection();
        [$where, $params] = $this->buildCitaWhere($filters);

        $sql = "
            SELECT e.id_especialidad,
                   e.nombre AS especialidad,
                   COUNT(c.id_cita)::int AS total
              FROM especialidades e
              LEFT JOIN citas c ON c.id_especialidad = e.id_especialidad AND ({$where})
             GROUP BY e.id_especialidad, e.nombre
             ORDER BY total DESC, e.nombre ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Construye cualquiera de los 7 reportes hospitalarios oficiales (CU14 / CU15):
     *   1. citas_medicas (o citas_fecha)          -> Citas Médicas (General y por Filtros)
     *   2. agenda_diaria (o agenda_imprimible)    -> Agenda Médica Diaria
     *   3. resumen_asistencia (estados_ausentismo)-> Resumen de Asistencia, Cancelaciones e Inasistencias
     *   4. padron_pacientes                       -> Padrón Completo de Pacientes y Fichas Médicas
     *   5. medicos_especialidades (citas_medico)  -> Directorio de Médicos y Especialidades
     *   6. horarios_vigentes                      -> Horarios de Atención Médica Vigentes
     *   7. usuarios_roles                         -> Usuarios y Roles del Sistema
     */
    public function generarReporte(string $tipoReporte, array $filters = []): array
    {
        return match ($tipoReporte) {
            'agenda_diaria', 'agenda_imprimible'                             => $this->reporteAgendaImprimible($filters),
            'resumen_asistencia', 'estados_ausentismo', 'demanda_especialidad' => $this->reporteResumenAsistencia($filters),
            'padron_pacientes'                                               => $this->reportePadronPacientes($filters),
            'medicos_especialidades', 'citas_medico'                         => $this->reporteMedicosEspecialidades($filters),
            'horarios_vigentes'                                              => $this->reporteHorariosVigentes($filters),
            'usuarios_roles'                                                 => $this->reporteUsuariosRoles($filters),
            default                                                          => $this->reporteCitasMedicas($filters),
        };
    }

    /**
     * Genera descripción legible de los filtros aplicados para cabeceras de PDF y Excel.
     */
    public function buildFiltersDescription(array $filters): string
    {
        $pdo = Database::getConnection();
        $parts = [];

        if (!empty($filters['q'])) {
            $parts[] = 'Búsqueda: "' . $filters['q'] . '"';
        }
        if (!empty($filters['fecha_desde'])) {
            $parts[] = 'Desde: ' . $filters['fecha_desde'];
        }
        if (!empty($filters['fecha_hasta'])) {
            $parts[] = 'Hasta: ' . $filters['fecha_hasta'];
        }
        if (!empty($filters['id_especialidad'])) {
            $stmt = $pdo->prepare('SELECT nombre FROM especialidades WHERE id_especialidad = :id');
            $stmt->execute([':id' => (int) $filters['id_especialidad']]);
            $espNombre = $stmt->fetchColumn();
            if ($espNombre) {
                $parts[] = 'Especialidad: ' . $espNombre;
            }
        }
        if (!empty($filters['id_medico'])) {
            $stmt = $pdo->prepare("SELECT 'Dr(a). ' || nombres || ' ' || apellidos FROM medicos WHERE id_medico = :id");
            $stmt->execute([':id' => (int) $filters['id_medico']]);
            $medNombre = $stmt->fetchColumn();
            if ($medNombre) {
                $parts[] = 'Médico: ' . $medNombre;
            }
        }
        if (!empty($filters['estado'])) {
            $parts[] = 'Estado: ' . $filters['estado'];
        }
        if (!empty($filters['id_rol'])) {
            $stmt = $pdo->prepare('SELECT nombre FROM roles WHERE id_rol = :id');
            $stmt->execute([':id' => (int) $filters['id_rol']]);
            $rolNombre = $stmt->fetchColumn();
            if ($rolNombre) {
                $parts[] = 'Rol: ' . $rolNombre;
            }
        }

        return empty($parts)
            ? 'Histórico General Consolidado (Sin filtros restrictivos)'
            : implode('  |  ', $parts);
    }

    /**
     * 1. Citas Médicas (General y por Filtros)
     */
    private function reporteCitasMedicas(array $filters): array
    {
        $pdo = Database::getConnection();
        [$where, $params] = $this->buildCitaWhere($filters);

        $sql = "
            SELECT c.codigo_cita,
                   TO_CHAR(c.fecha_cita, 'DD/MM/YYYY') AS fecha,
                   TO_CHAR(c.hora_inicio, 'HH24:MI') || ' - ' || TO_CHAR(c.hora_fin, 'HH24:MI') AS horario,
                   p.codigo_paciente || ' - ' || p.nombres || ' ' || p.apellidos AS paciente,
                   p.ci AS paciente_ci,
                   'Dr(a). ' || m.nombres || ' ' || m.apellidos AS medico,
                   e.nombre AS especialidad,
                   c.estado,
                   COALESCE(c.motivo_consulta, '-') AS motivo
              FROM citas c
              JOIN pacientes p ON p.id_paciente = c.id_paciente
              JOIN medicos m ON m.id_medico = c.id_medico
              JOIN especialidades e ON e.id_especialidad = c.id_especialidad
             WHERE {$where}
             ORDER BY c.fecha_cita DESC, c.hora_inicio ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'title'       => 'Reporte de Citas Médicas (General y por Filtros)',
            'file_base'   => 'Citas_Medicas',
            'orientation' => 'L',
            'columns'     => [
                ['key' => 'codigo_cita',  'label' => 'Código',       'width' => 10],
                ['key' => 'fecha',        'label' => 'Fecha',        'width' => 10],
                ['key' => 'horario',      'label' => 'Horario',      'width' => 12],
                ['key' => 'paciente',     'label' => 'Paciente',     'width' => 22],
                ['key' => 'paciente_ci',  'label' => 'CI',           'width' => 9],
                ['key' => 'medico',       'label' => 'Médico',       'width' => 16],
                ['key' => 'especialidad', 'label' => 'Especialidad', 'width' => 12],
                ['key' => 'estado',       'label' => 'Estado',       'width' => 9],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * 2. Agenda Médica Diaria
     */
    private function reporteAgendaImprimible(array $filters): array
    {
        $pdo = Database::getConnection();
        $adjustedFilters = $filters;
        if (empty($adjustedFilters['fecha_desde']) && empty($adjustedFilters['fecha_hasta'])) {
            $adjustedFilters['fecha_desde'] = '2026-10-26';
            $adjustedFilters['fecha_hasta'] = '2026-10-26';
        }
        [$where, $params] = $this->buildCitaWhere($adjustedFilters);

        $sql = "
            SELECT TO_CHAR(c.fecha_cita, 'DD/MM/YYYY') AS fecha,
                   TO_CHAR(c.hora_inicio, 'HH24:MI') || ' - ' || TO_CHAR(c.hora_fin, 'HH24:MI') AS horario,
                   c.codigo_cita,
                   p.nombres || ' ' || p.apellidos AS paciente,
                   p.ci AS paciente_ci,
                   COALESCE(fm.tipo_sangre, '-') AS sangre,
                   'Dr(a). ' || m.nombres || ' ' || m.apellidos AS medico,
                   e.nombre AS especialidad,
                   c.estado,
                   COALESCE(c.motivo_consulta, '-') AS motivo
              FROM citas c
              JOIN pacientes p ON p.id_paciente = c.id_paciente
              LEFT JOIN fichas_medicas fm ON fm.id_paciente = p.id_paciente
              JOIN medicos m ON m.id_medico = c.id_medico
              JOIN especialidades e ON e.id_especialidad = c.id_especialidad
             WHERE {$where}
             ORDER BY c.fecha_cita ASC, m.apellidos ASC, c.hora_inicio ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $fechaTitulo = !empty($adjustedFilters['fecha_desde'])
            ? date('d/m/Y', strtotime((string) $adjustedFilters['fecha_desde']))
            : date('d/m/Y');

        return [
            'title'       => 'Agenda Médica Diaria (' . $fechaTitulo . ')',
            'file_base'   => 'Agenda_Medica_Diaria',
            'orientation' => 'L',
            'columns'     => [
                ['key' => 'horario',      'label' => 'Hora',         'width' => 11],
                ['key' => 'codigo_cita',  'label' => 'Código',       'width' => 10],
                ['key' => 'paciente',     'label' => 'Paciente',     'width' => 20],
                ['key' => 'paciente_ci',  'label' => 'CI',           'width' => 9],
                ['key' => 'sangre',       'label' => 'Sangre',       'width' => 7],
                ['key' => 'medico',       'label' => 'Médico',       'width' => 17],
                ['key' => 'especialidad', 'label' => 'Especialidad', 'width' => 12],
                ['key' => 'estado',       'label' => 'Estado',       'width' => 8],
                ['key' => 'motivo',       'label' => 'Motivo',       'width' => 12],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * 3. Resumen de Asistencia, Cancelaciones e Inasistencias
     */
    private function reporteResumenAsistencia(array $filters): array
    {
        $pdo = Database::getConnection();
        [$where, $params] = $this->buildCitaWhere($filters);

        $sql = "
            WITH conteo AS (
                SELECT c.estado,
                       COUNT(*)::int AS cantidad
                  FROM citas c
                 WHERE {$where}
                 GROUP BY c.estado
            )
            SELECT estado,
                   cantidad,
                   CASE
                       WHEN SUM(cantidad) OVER () > 0
                       THEN ROUND((cantidad::numeric / SUM(cantidad) OVER ()) * 100, 2) || '%'
                       ELSE '0.00%'
                   END AS porcentaje,
                   CASE estado
                       WHEN 'Atendida'     THEN 'Consulta clínica finalizada con evolución y diagnóstico'
                       WHEN 'Confirmada'   THEN 'Paciente confirmado en recepción para atención'
                       WHEN 'Programada'   THEN 'Turno reservado pendiente de atención'
                       WHEN 'Cancelada'    THEN 'Cancelación justificada (cupo liberado en agenda)'
                       WHEN 'Inasistencia' THEN 'Ausentismo del paciente al turno programado'
                   END AS interpretacion
              FROM conteo
             ORDER BY cantidad DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'title'       => 'Resumen de Asistencia, Cancelaciones e Inasistencias',
            'file_base'   => 'Resumen_Asistencia_Inasistencias',
            'orientation' => 'P',
            'columns'     => [
                ['key' => 'estado',         'label' => 'Estado Clínico',         'width' => 20],
                ['key' => 'cantidad',       'label' => 'N° Citas',               'width' => 15],
                ['key' => 'porcentaje',     'label' => 'Porcentaje (%)',         'width' => 18],
                ['key' => 'interpretacion', 'label' => 'Descripción Operativa',  'width' => 47],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * 4. Padrón Completo de Pacientes y Fichas Médicas
     */
    private function reportePadronPacientes(array $filters): array
    {
        $pdo = Database::getConnection();
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['estado'])) {
            $where[] = 'p.estado = :ep';
            $params[':ep'] = (string) $filters['estado'];
        } elseif (!empty($filters['estado_paciente'])) {
            $where[] = 'p.estado = :ep';
            $params[':ep'] = (string) $filters['estado_paciente'];
        }

        $sql = "
            SELECT p.codigo_paciente,
                   p.ci,
                   p.nombres || ' ' || p.apellidos AS nombre_completo,
                   p.sexo,
                   EXTRACT(YEAR FROM AGE(CURRENT_DATE, p.fecha_nacimiento))::int || ' años' AS edad,
                   p.telefono,
                   COALESCE(fm.tipo_sangre, 'Desconocido') AS tipo_sangre,
                   COALESCE(fm.alergias, 'Ninguna conocida') AS alergias,
                   COALESCE(fm.enfermedades_cronicas, 'Ninguna') AS enfermedades_cronicas,
                   p.estado,
                   COUNT(c.id_cita)::int AS total_citas
              FROM pacientes p
              LEFT JOIN fichas_medicas fm ON fm.id_paciente = p.id_paciente
              LEFT JOIN citas c ON c.id_paciente = p.id_paciente
             WHERE " . implode(' AND ', $where) . "
             GROUP BY p.id_paciente, fm.tipo_sangre, fm.alergias, fm.enfermedades_cronicas
             ORDER BY p.id_paciente ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'title'       => 'Padrón Completo de Pacientes y Fichas Médicas',
            'file_base'   => 'Padron_Pacientes_Fichas',
            'orientation' => 'L',
            'columns'     => [
                ['key' => 'codigo_paciente',       'label' => 'Código',        'width' => 9],
                ['key' => 'ci',                    'label' => 'CI',            'width' => 8],
                ['key' => 'nombre_completo',       'label' => 'Paciente',      'width' => 19],
                ['key' => 'edad',                  'label' => 'Edad',          'width' => 7],
                ['key' => 'telefono',              'label' => 'Teléfono',      'width' => 9],
                ['key' => 'tipo_sangre',           'label' => 'Sangre',        'width' => 7],
                ['key' => 'alergias',              'label' => 'Alergias',      'width' => 16],
                ['key' => 'enfermedades_cronicas', 'label' => 'Enf. Crónicas', 'width' => 14],
                ['key' => 'estado',                'label' => 'Estado',        'width' => 6],
                ['key' => 'total_citas',           'label' => 'Citas',         'width' => 5],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * 5. Directorio de Médicos y Especialidades
     */
    private function reporteMedicosEspecialidades(array $filters): array
    {
        $pdo = Database::getConnection();
        [$where, $params] = $this->buildCitaWhere($filters);

        $medWhere = ['1=1'];
        if (!empty($filters['id_medico'])) {
            $medWhere[] = 'm.id_medico = :id_medico';
        }
        if (!empty($filters['id_especialidad'])) {
            $medWhere[] = 'EXISTS (SELECT 1 FROM medico_especialidad me2 WHERE me2.id_medico = m.id_medico AND me2.id_especialidad = :id_especialidad)';
        }

        $sql = "
            SELECT m.codigo_medico,
                   'Dr(a). ' || m.nombres || ' ' || m.apellidos AS medico,
                   m.registro_profesional,
                   COALESCE((
                       SELECT STRING_AGG(e2.nombre, ', ' ORDER BY e2.nombre)
                         FROM medico_especialidad me
                         JOIN especialidades e2 ON e2.id_especialidad = me.id_especialidad
                        WHERE me.id_medico = m.id_medico
                   ), 'Sin asignar') AS especialidades,
                   m.telefono,
                   m.correo,
                   COUNT(c.id_cita)::int AS total_citas,
                   COUNT(c.id_cita) FILTER (WHERE c.estado = 'Atendida')::int AS atendidas,
                   COUNT(c.id_cita) FILTER (WHERE c.estado IN ('Programada', 'Confirmada'))::int AS activas,
                   m.estado
              FROM medicos m
              LEFT JOIN citas c ON c.id_medico = m.id_medico AND ({$where})
             WHERE " . implode(' AND ', $medWhere) . "
             GROUP BY m.id_medico
             ORDER BY m.id_medico ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'title'       => 'Directorio de Médicos y Especialidades Clínicas',
            'file_base'   => 'Medicos_Especialidades',
            'orientation' => 'L',
            'columns'     => [
                ['key' => 'codigo_medico',        'label' => 'Código',          'width' => 9],
                ['key' => 'medico',               'label' => 'Profesional',     'width' => 20],
                ['key' => 'registro_profesional', 'label' => 'Matrícula',       'width' => 11],
                ['key' => 'especialidades',       'label' => 'Especialidades',  'width' => 18],
                ['key' => 'telefono',             'label' => 'Teléfono',        'width' => 9],
                ['key' => 'correo',               'label' => 'Correo',          'width' => 15],
                ['key' => 'total_citas',          'label' => 'Citas',           'width' => 6],
                ['key' => 'atendidas',            'label' => 'Atend.',          'width' => 6],
                ['key' => 'estado',               'label' => 'Estado',          'width' => 6],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * 6. Horarios de Atención Médica Vigentes
     */
    private function reporteHorariosVigentes(array $filters): array
    {
        $pdo = Database::getConnection();
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['id_medico'])) {
            $where[] = 'h.id_medico = :id_medico';
            $params[':id_medico'] = (int) $filters['id_medico'];
        }
        if (!empty($filters['id_especialidad'])) {
            $where[] = 'EXISTS (SELECT 1 FROM medico_especialidad me2 WHERE me2.id_medico = m.id_medico AND me2.id_especialidad = :id_esp)';
            $params[':id_esp'] = (int) $filters['id_especialidad'];
        }

        $sql = "
            SELECT m.codigo_medico,
                   'Dr(a). ' || m.nombres || ' ' || m.apellidos AS medico,
                   COALESCE((
                       SELECT STRING_AGG(e.nombre, ', ' ORDER BY e.nombre)
                         FROM medico_especialidad me
                         JOIN especialidades e ON e.id_especialidad = me.id_especialidad
                        WHERE me.id_medico = m.id_medico
                   ), '-') AS especialidades,
                   CASE h.dia_semana
                       WHEN 1 THEN 'Lunes'
                       WHEN 2 THEN 'Martes'
                       WHEN 3 THEN 'Miércoles'
                       WHEN 4 THEN 'Jueves'
                       WHEN 5 THEN 'Viernes'
                       WHEN 6 THEN 'Sábado'
                       ELSE 'Domingo'
                   END AS dia_atencion,
                   TO_CHAR(h.hora_inicio, 'HH24:MI') || ' - ' || TO_CHAR(h.hora_fin, 'HH24:MI') AS bloque_horario,
                   h.duracion_minutos || ' min' AS duracion_consulta,
                   h.estado
              FROM horarios_atencion h
              JOIN medicos m ON m.id_medico = h.id_medico
             WHERE " . implode(' AND ', $where) . "
             ORDER BY m.apellidos ASC, h.dia_semana ASC, h.hora_inicio ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'title'       => 'Horarios de Atención Médica Vigentes (Lunes a Sábado)',
            'file_base'   => 'Horarios_Atencion_Vigentes',
            'orientation' => 'L',
            'columns'     => [
                ['key' => 'codigo_medico',     'label' => 'Código Médico', 'width' => 11],
                ['key' => 'medico',            'label' => 'Profesional',   'width' => 24],
                ['key' => 'especialidades',    'label' => 'Especialidades','width' => 22],
                ['key' => 'dia_atencion',      'label' => 'Día Semana',    'width' => 13],
                ['key' => 'bloque_horario',    'label' => 'Horario',       'width' => 13],
                ['key' => 'duracion_consulta', 'label' => 'Duración Cita', 'width' => 10],
                ['key' => 'estado',            'label' => 'Estado',        'width' => 7],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * 7. Usuarios y Roles del Sistema
     */
    private function reporteUsuariosRoles(array $filters): array
    {
        $pdo = Database::getConnection();
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['id_rol'])) {
            $where[] = 'u.id_rol = :id_rol';
            $params[':id_rol'] = (int) $filters['id_rol'];
        }
        if (!empty($filters['estado'])) {
            $where[] = 'u.estado = :est';
            $params[':est'] = (string) $filters['estado'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(u.nombre_completo ILIKE :q OR u.nombre_usuario ILIKE :q OR u.correo_electronico ILIKE :q)';
            $params[':q'] = '%' . trim((string) $filters['q']) . '%';
        }

        $sql = "
            SELECT u.id_usuario,
                   u.nombre_completo,
                   u.nombre_usuario,
                   r.nombre AS rol_nombre,
                   u.correo_electronico,
                   COALESCE(u.telefono, '-') AS telefono,
                   COALESCE(m.codigo_medico, p.codigo_paciente, '-') AS ficha_vinculada,
                   u.estado
              FROM usuarios u
              JOIN roles r ON r.id_rol = u.id_rol
              LEFT JOIN medicos m ON m.id_usuario = u.id_usuario
              LEFT JOIN pacientes p ON p.id_usuario = u.id_usuario
             WHERE " . implode(' AND ', $where) . "
             ORDER BY r.id_rol ASC, u.id_usuario ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'title'       => 'Directorio de Usuarios y Roles del Sistema (RBAC)',
            'file_base'   => 'Usuarios_Y_Roles',
            'orientation' => 'L',
            'columns'     => [
                ['key' => 'id_usuario',         'label' => 'ID',              'width' => 6],
                ['key' => 'nombre_completo',    'label' => 'Nombre Completo', 'width' => 23],
                ['key' => 'nombre_usuario',     'label' => 'Usuario',         'width' => 14],
                ['key' => 'rol_nombre',         'label' => 'Rol RBAC',        'width' => 13],
                ['key' => 'correo_electronico', 'label' => 'Correo',          'width' => 20],
                ['key' => 'telefono',           'label' => 'Teléfono',        'width' => 9],
                ['key' => 'ficha_vinculada',    'label' => 'Cód. Clínico',    'width' => 8],
                ['key' => 'estado',             'label' => 'Estado',          'width' => 7],
            ],
            'rows' => $rows,
        ];
    }

    private function buildCitaWhere(array $filters): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['fecha_desde'])) {
            $where[] = 'c.fecha_cita >= :fdesde::date';
            $params[':fdesde'] = (string) $filters['fecha_desde'];
        }
        if (!empty($filters['fecha_hasta'])) {
            $where[] = 'c.fecha_cita <= :fhasta::date';
            $params[':fhasta'] = (string) $filters['fecha_hasta'];
        }
        if (!empty($filters['id_medico'])) {
            $where[] = 'c.id_medico = :id_medico';
            $params[':id_medico'] = (int) $filters['id_medico'];
        }
        if (!empty($filters['id_especialidad'])) {
            $where[] = 'c.id_especialidad = :id_especialidad';
            $params[':id_especialidad'] = (int) $filters['id_especialidad'];
        }
        if (!empty($filters['estado']) && in_array($filters['estado'], ['Programada', 'Confirmada', 'Atendida', 'Cancelada', 'Inasistencia'], true)) {
            $where[] = 'c.estado = :estado';
            $params[':estado'] = (string) $filters['estado'];
        }
        if (!empty($filters['q'])) {
            $where[] = "(c.codigo_cita ILIKE :q OR EXISTS (SELECT 1 FROM pacientes px WHERE px.id_paciente = c.id_paciente AND (px.nombres || ' ' || px.apellidos ILIKE :q OR px.ci ILIKE :q OR px.codigo_paciente ILIKE :q)))";
            $params[':q'] = '%' . trim((string) $filters['q']) . '%';
        }

        return [implode(' AND ', $where), $params];
    }
}
