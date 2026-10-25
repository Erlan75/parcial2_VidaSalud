<?php
declare(strict_types=1);

namespace App\Modules\ReportesAnalitica\Models;

use Database;
use PDO;

/**
 * Modelo Analítico y de Reportes Ejecutivos del Paquete ReportesAnalitica (CU14, CU15)
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
                   (SELECT COUNT(*) FROM citas WHERE estado = 'Inasistencia') AS citas_inasistencia
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
            SELECT e.nombre AS especialidad,
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
     * Construye cualquiera de los 6 reportes oficiales de gestión (CU14 / CU15)
     */
    public function generarReporte(string $tipoReporte, array $filters = []): array
    {
        return match ($tipoReporte) {
            'citas_medico'         => $this->reporteProductividadMedico($filters),
            'demanda_especialidad' => $this->reporteDemandaEspecialidad($filters),
            'estados_ausentismo'   => $this->reporteEstadosYAusentismo($filters),
            'padron_pacientes'     => $this->reportePadronPacientes($filters),
            'agenda_imprimible'    => $this->reporteAgendaImprimible($filters),
            default                => $this->reporteCitasPorFecha($filters),
        };
    }

    private function reporteCitasPorFecha(array $filters): array
    {
        $pdo = Database::getConnection();
        [$where, $params] = $this->buildCitaWhere($filters);

        $sql = "
            SELECT c.codigo_cita,
                   TO_CHAR(c.fecha_cita, 'DD/MM/YYYY') AS fecha,
                   TO_CHAR(c.hora_inicio, 'HH24:MI') || ' - ' || TO_CHAR(c.hora_fin, 'HH24:MI') AS horario,
                   p.codigo_paciente || ' - ' || p.nombres || ' ' || p.apellidos AS paciente,
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
            'title'       => 'Reporte 1: Citas Médicas Programadas por Fecha',
            'orientation' => 'L',
            'columns'     => [
                ['key' => 'codigo_cita',  'label' => 'Código',       'width' => 11],
                ['key' => 'fecha',        'label' => 'Fecha',        'width' => 11],
                ['key' => 'horario',      'label' => 'Bloque',       'width' => 13],
                ['key' => 'paciente',     'label' => 'Paciente',     'width' => 23],
                ['key' => 'medico',       'label' => 'Médico',       'width' => 19],
                ['key' => 'especialidad', 'label' => 'Especialidad', 'width' => 13],
                ['key' => 'estado',       'label' => 'Estado',       'width' => 10],
            ],
            'rows' => $rows,
        ];
    }

    private function reporteProductividadMedico(array $filters): array
    {
        $pdo = Database::getConnection();
        [$where, $params] = $this->buildCitaWhere($filters);

        $sql = "
            SELECT m.codigo_medico,
                   'Dr(a). ' || m.nombres || ' ' || m.apellidos AS medico,
                   m.registro_profesional,
                   COUNT(c.id_cita)::int AS total_citas,
                   COUNT(c.id_cita) FILTER (WHERE c.estado = 'Atendida')::int AS atendidas,
                   COUNT(c.id_cita) FILTER (WHERE c.estado IN ('Programada', 'Confirmada'))::int AS activas,
                   COUNT(c.id_cita) FILTER (WHERE c.estado = 'Cancelada')::int AS canceladas,
                   COUNT(c.id_cita) FILTER (WHERE c.estado = 'Inasistencia')::int AS inasistencias,
                   CASE
                       WHEN COUNT(c.id_cita) > 0
                       THEN ROUND((COUNT(c.id_cita) FILTER (WHERE c.estado = 'Atendida')::numeric / COUNT(c.id_cita)) * 100, 1) || '%'
                       ELSE '0.0%'
                   END AS tasa_atencion
              FROM medicos m
              LEFT JOIN citas c ON c.id_medico = m.id_medico AND ({$where})
             GROUP BY m.id_medico
             ORDER BY total_citas DESC, m.apellidos ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'title'       => 'Reporte 2: Productividad Clínica y Citas por Médico',
            'orientation' => 'L',
            'columns'     => [
                ['key' => 'codigo_medico',        'label' => 'Código',       'width' => 10],
                ['key' => 'medico',               'label' => 'Profesional',  'width' => 26],
                ['key' => 'registro_profesional', 'label' => 'Matrícula',    'width' => 14],
                ['key' => 'total_citas',          'label' => 'Total Citas',  'width' => 10],
                ['key' => 'atendidas',            'label' => 'Atendidas',    'width' => 10],
                ['key' => 'activas',              'label' => 'En Agenda',    'width' => 10],
                ['key' => 'canceladas',           'label' => 'Canceladas',   'width' => 10],
                ['key' => 'inasistencias',        'label' => 'Ausencias',    'width' => 10],
            ],
            'rows' => $rows,
        ];
    }

    private function reporteDemandaEspecialidad(array $filters): array
    {
        $pdo = Database::getConnection();
        [$where, $params] = $this->buildCitaWhere($filters);

        $sql = "
            WITH base AS (
                SELECT e.id_especialidad,
                       e.codigo_especialidad,
                       e.nombre AS especialidad,
                       (SELECT COUNT(*) FROM medico_especialidad me JOIN medicos m2 ON m2.id_medico = me.id_medico WHERE me.id_especialidad = e.id_especialidad AND m2.estado = 'Activo')::int AS medicos_activos,
                       COUNT(c.id_cita)::int AS total_citas,
                       COUNT(c.id_cita) FILTER (WHERE c.estado = 'Atendida')::int AS atendidas,
                       COUNT(c.id_cita) FILTER (WHERE c.estado IN ('Programada', 'Confirmada'))::int AS activas,
                       COUNT(c.id_cita) FILTER (WHERE c.estado IN ('Cancelada', 'Inasistencia'))::int AS no_efectivas
                  FROM especialidades e
                  LEFT JOIN citas c ON c.id_especialidad = e.id_especialidad AND ({$where})
                 GROUP BY e.id_especialidad
            )
            SELECT b.*,
                   CASE
                       WHEN SUM(b.total_citas) OVER () > 0
                       THEN ROUND((b.total_citas::numeric / SUM(b.total_citas) OVER ()) * 100, 1) || '%'
                       ELSE '0.0%'
                   END AS participacion
              FROM base b
             ORDER BY b.total_citas DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'title'       => 'Reporte 3: Demanda de Citas por Especialidad Médica',
            'orientation' => 'L',
            'columns'     => [
                ['key' => 'codigo_especialidad', 'label' => 'Código',          'width' => 12],
                ['key' => 'especialidad',        'label' => 'Especialidad',    'width' => 24],
                ['key' => 'medicos_activos',     'label' => 'Médicos Activos', 'width' => 14],
                ['key' => 'total_citas',         'label' => 'Total Citas',     'width' => 12],
                ['key' => 'atendidas',           'label' => 'Atendidas',       'width' => 12],
                ['key' => 'activas',             'label' => 'Activas',         'width' => 12],
                ['key' => 'participacion',       'label' => '% Demanda',       'width' => 14],
            ],
            'rows' => $rows,
        ];
    }

    private function reporteEstadosYAusentismo(array $filters): array
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
                       WHEN 'Atendida'     THEN 'Consulta clínica finalizada con diagnóstico'
                       WHEN 'Confirmada'   THEN 'Paciente confirmado para atención en sala'
                       WHEN 'Programada'   THEN 'Turno reservado pendiente de atención'
                       WHEN 'Cancelada'    THEN 'Cancelación justificada (cupo liberado)'
                       WHEN 'Inasistencia' THEN 'Ausentismo del paciente al turno programado'
                   END AS interpretacion
              FROM conteo
             ORDER BY cantidad DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'title'       => 'Reporte 4: Distribución por Estado y Tasa de Ausentismo',
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

    private function reportePadronPacientes(array $filters): array
    {
        $pdo = Database::getConnection();
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['estado_paciente'])) {
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
                   p.estado,
                   COUNT(c.id_cita)::int AS total_citas
              FROM pacientes p
              LEFT JOIN fichas_medicas fm ON fm.id_paciente = p.id_paciente
              LEFT JOIN citas c ON c.id_paciente = p.id_paciente
             WHERE " . implode(' AND ', $where) . "
             GROUP BY p.id_paciente, fm.tipo_sangre
             ORDER BY p.id_paciente ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'title'       => 'Reporte 5: Padrón General de Pacientes Registrados',
            'orientation' => 'L',
            'columns'     => [
                ['key' => 'codigo_paciente', 'label' => 'Código',      'width' => 12],
                ['key' => 'ci',              'label' => 'CI',          'width' => 11],
                ['key' => 'nombre_completo', 'label' => 'Paciente',    'width' => 26],
                ['key' => 'sexo',            'label' => 'Sexo',        'width' => 7],
                ['key' => 'edad',            'label' => 'Edad',        'width' => 9],
                ['key' => 'telefono',        'label' => 'Teléfono',    'width' => 12],
                ['key' => 'tipo_sangre',     'label' => 'Sangre',      'width' => 9],
                ['key' => 'estado',          'label' => 'Estado',      'width' => 8],
                ['key' => 'total_citas',     'label' => 'Citas',       'width' => 6],
            ],
            'rows' => $rows,
        ];
    }

    private function reporteAgendaImprimible(array $filters): array
    {
        $pdo = Database::getConnection();
        $fecha = !empty($filters['fecha_desde']) ? (string) $filters['fecha_desde'] : '2026-10-26';
        $where = ['c.fecha_cita = :fecha::date'];
        $params = [':fecha' => $fecha];

        if (!empty($filters['id_medico'])) {
            $where[] = 'c.id_medico = :med';
            $params[':med'] = (int) $filters['id_medico'];
        }

        $sql = "
            SELECT TO_CHAR(c.hora_inicio, 'HH24:MI') || ' - ' || TO_CHAR(c.hora_fin, 'HH24:MI') AS horario,
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
             WHERE " . implode(' AND ', $where) . "
             ORDER BY m.apellidos ASC, c.hora_inicio ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'title'       => 'Reporte 6: Agenda Médica Diaria Imprimible (' . date('d/m/Y', strtotime($fecha)) . ')',
            'orientation' => 'L',
            'columns'     => [
                ['key' => 'horario',      'label' => 'Hora',         'width' => 12],
                ['key' => 'codigo_cita',  'label' => 'Código',       'width' => 10],
                ['key' => 'paciente',     'label' => 'Paciente',     'width' => 22],
                ['key' => 'paciente_ci',  'label' => 'CI',           'width' => 9],
                ['key' => 'sangre',       'label' => 'Sangre',       'width' => 7],
                ['key' => 'medico',       'label' => 'Médico',       'width' => 18],
                ['key' => 'estado',       'label' => 'Estado',       'width' => 10],
                ['key' => 'motivo',       'label' => 'Motivo',       'width' => 12],
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
        if (!empty($filters['estado'])) {
            $where[] = 'c.estado = :estado';
            $params[':estado'] = (string) $filters['estado'];
        }

        return [implode(' AND ', $where), $params];
    }
}
