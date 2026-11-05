<?php
declare(strict_types=1);

namespace App\Modules\ReportesAnalitica\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\ExcelGenerator;
use App\Core\PdfGenerator;
use App\Modules\CatalogoMedico\Models\EspecialidadModel;
use App\Modules\CatalogoMedico\Models\HorarioModel;
use App\Modules\CatalogoMedico\Models\MedicoModel;
use App\Modules\GestionCitas\Models\CitaModel;
use App\Modules\PacientesClinica\Models\PacienteModel;
use App\Modules\ReportesAnalitica\Models\ReporteModel;
use App\Modules\SeguridadAcceso\Models\UsuarioModel;

/**
 * CU15: Exportación Directa de Reportes Hospitalarios y Listados Operativos en PDF (.pdf) y Excel (.xlsx)
 */
final class ExportacionController extends Controller
{
    /**
     * Ticket / Comprobante Oficial de Cita Médica en PDF con Código QR Real Autogenerado
     */
    public function comprobantePdf(): void
    {
        $idCita = isset($_GET['id_cita']) ? (int) $_GET['id_cita'] : null;
        $codigo = isset($_GET['codigo']) ? trim((string) $_GET['codigo']) : null;

        $citaModel = new CitaModel();
        $cita = $citaModel->findByIdOrCodigo($idCita && $idCita > 0 ? $idCita : null, $codigo);

        if (!$cita) {
            Auth::setFlash('error', 'No se encontró la cita médica solicitada para generar el comprobante.', 'No encontrada');
            $this->redirect('citas');
        }

        if (Auth::hasRole('Paciente') && (int) $cita['id_paciente'] !== (int) Auth::pacienteId()) {
            Auth::setFlash('error', 'No tiene autorización para descargar comprobantes de otros pacientes.', 'Seguridad');
            $this->redirect('citas');
        }

        $verifyUrl = url('citas/verificar', ['codigo' => $cita['codigo_cita']]);
        $pdfBytes = PdfGenerator::buildCitaTicket($cita, $verifyUrl);

        $disposition = !empty($_GET['download']) ? 'attachment' : 'inline';
        $filename = 'Comprobante_' . $cita['codigo_cita'] . '_' . date('Ymd') . '.pdf';

        $this->sendPdfResponse($pdfBytes, $filename, $disposition);
    }

    /**
     * Descarga de Ficha Médica e Historial Clínico Individual por Paciente (.pdf)
     */
    public function fichaPacientePdf(): void
    {
        $idPaciente = (int) ($_GET['id_paciente'] ?? 0);
        if (Auth::hasRole('Paciente')) {
            $idPaciente = (int) Auth::pacienteId();
        }

        if ($idPaciente <= 0) {
            Auth::setFlash('error', 'Debe indicar un paciente válido para exportar su ficha e historial.', 'Paciente requerido');
            $this->redirect('pacientes');
        }

        $pacienteModel = new PacienteModel();
        $paciente = $pacienteModel->findById($idPaciente);
        if (!$paciente) {
            Auth::setFlash('error', 'No se encontró el expediente del paciente solicitado.', 'No encontrado');
            $this->redirect('pacientes');
        }

        $citaModel = new CitaModel();
        $citas = $citaModel->listCitas(['id_paciente' => $idPaciente], 200);

        $pdfBytes = PdfGenerator::buildPatientClinicalFile($paciente, $citas, $this->getIssuerLabel());
        $cod = (string) ($paciente['codigo_paciente'] ?? ('PAC_' . $idPaciente));
        $filename = "Ficha_Historial_{$cod}_" . date('Ymd') . '.pdf';
        $disposition = !empty($_GET['download']) ? 'attachment' : 'inline';

        $this->sendPdfResponse($pdfBytes, $filename, $disposition);
    }

    /**
     * Exportación desde el Centro de Reportes a PDF (.pdf)
     */
    public function reportePdf(): void
    {
        $tipo = (string) ($_GET['tipo'] ?? 'citas_medicas');
        $filters = $this->extractCommonFilters();

        $reporteModel = new ReporteModel();
        $report = $reporteModel->generarReporte($tipo, $filters);
        $kpis = $reporteModel->getDashboardKpis();
        $subtitle = $reporteModel->buildFiltersDescription($filters);

        $summary = [
            'Registros en Reporte' => count($report['rows']),
            'Pacientes Activos'    => $kpis['pacientes_activos'] ?? 0,
            'Citas Atendidas'      => $kpis['citas_atendidas'] ?? 0,
            'Citas en Agenda'      => $kpis['citas_futuras'] ?? 0,
        ];

        $pdfBytes = PdfGenerator::buildExecutiveReport(
            $report['title'],
            $subtitle,
            $report['columns'],
            $report['rows'],
            $summary,
            $this->getIssuerLabel(),
            $report['orientation']
        );

        $baseName = (string) ($report['file_base'] ?? $tipo);
        $filename = 'Reporte_' . $baseName . '_' . date('Ymd') . '.pdf';
        $disposition = !empty($_GET['download']) ? 'attachment' : 'inline';

        $this->sendPdfResponse($pdfBytes, $filename, $disposition);
    }

    /**
     * Exportación desde el Centro de Reportes a Excel (.xlsx)
     */
    public function reporteExcel(): void
    {
        $tipo = (string) ($_GET['tipo'] ?? 'citas_medicas');
        $filters = $this->extractCommonFilters();

        $reporteModel = new ReporteModel();
        $report = $reporteModel->generarReporte($tipo, $filters);
        $subtitle = $reporteModel->buildFiltersDescription($filters);

        $xlsxBytes = ExcelGenerator::buildXlsx(
            $report['title'],
            $subtitle,
            $report['columns'],
            $report['rows'],
            $this->getIssuerLabel()
        );

        $baseName = (string) ($report['file_base'] ?? $tipo);
        $filename = 'Reporte_' . $baseName . '_' . date('Ymd') . '.xlsx';

        $this->sendXlsxResponse($xlsxBytes, $filename);
    }

    /**
     * Exportación desde el Centro de Reportes a CSV (.csv)
     */
    public function reporteCsv(): void
    {
        $tipo = (string) ($_GET['tipo'] ?? 'citas_medicas');
        $filters = $this->extractCommonFilters();

        $reporteModel = new ReporteModel();
        $report = $reporteModel->generarReporte($tipo, $filters);
        $subtitle = $reporteModel->buildFiltersDescription($filters);

        $csvBytes = ExcelGenerator::buildCsv(
            $report['title'],
            $subtitle,
            $report['columns'],
            $report['rows'],
            $this->getIssuerLabel()
        );

        $baseName = (string) ($report['file_base'] ?? $tipo);
        $filename = 'Reporte_' . $baseName . '_' . date('Ymd') . '.csv';

        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=UTF-8');
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            header('Content-Length: ' . strlen($csvBytes));
            header('Cache-Control: no-store, no-cache');
        }
        echo $csvBytes;
        exit;
    }

    /**
     * MÓDULO CONTROL DE CITAS: Exportar citas filtradas a PDF (.pdf)
     */
    public function citasPdf(): void
    {
        [$title, $subtitle, $columns, $rows] = $this->buildFilteredCitasPayload();
        $pdfBytes = PdfGenerator::buildExecutiveReport(
            $title,
            $subtitle,
            $columns,
            $rows,
            ['Total Citas Exportadas' => count($rows), 'Fecha Emisión' => date('d/m/Y H:i')],
            $this->getIssuerLabel(),
            'L'
        );
        $this->sendPdfResponse($pdfBytes, 'Reporte_Control_Citas_' . date('Ymd') . '.pdf', 'inline');
    }

    /**
     * MÓDULO CONTROL DE CITAS: Exportar citas filtradas a Excel (.xlsx)
     */
    public function citasExcel(): void
    {
        [$title, $subtitle, $columns, $rows] = $this->buildFilteredCitasPayload();
        $xlsxBytes = ExcelGenerator::buildXlsx($title, $subtitle, $columns, $rows, $this->getIssuerLabel());
        $this->sendXlsxResponse($xlsxBytes, 'Reporte_Control_Citas_' . date('Ymd') . '.xlsx');
    }

    /**
     * MÓDULO PACIENTES: Exportar lista de pacientes filtrada a PDF (.pdf)
     */
    public function pacientesPdf(): void
    {
        [$title, $subtitle, $columns, $rows] = $this->buildFilteredPacientesPayload();
        $pdfBytes = PdfGenerator::buildExecutiveReport(
            $title,
            $subtitle,
            $columns,
            $rows,
            ['Total Pacientes' => count($rows), 'Fecha Emisión' => date('d/m/Y H:i')],
            $this->getIssuerLabel(),
            'L'
        );
        $this->sendPdfResponse($pdfBytes, 'Reporte_Padron_Pacientes_' . date('Ymd') . '.pdf', 'inline');
    }

    /**
     * MÓDULO PACIENTES: Exportar lista de pacientes filtrada a Excel (.xlsx)
     */
    public function pacientesExcel(): void
    {
        [$title, $subtitle, $columns, $rows] = $this->buildFilteredPacientesPayload();
        $xlsxBytes = ExcelGenerator::buildXlsx($title, $subtitle, $columns, $rows, $this->getIssuerLabel());
        $this->sendXlsxResponse($xlsxBytes, 'Reporte_Padron_Pacientes_' . date('Ymd') . '.xlsx');
    }

    /**
     * MÓDULO MÉDICOS: Exportar directorio de médicos filtrado a PDF (.pdf)
     */
    public function medicosPdf(): void
    {
        [$title, $subtitle, $columns, $rows] = $this->buildFilteredMedicosPayload();
        $pdfBytes = PdfGenerator::buildExecutiveReport(
            $title,
            $subtitle,
            $columns,
            $rows,
            ['Total Médicos' => count($rows), 'Fecha Emisión' => date('d/m/Y H:i')],
            $this->getIssuerLabel(),
            'L'
        );
        $this->sendPdfResponse($pdfBytes, 'Reporte_Plantel_Medico_' . date('Ymd') . '.pdf', 'inline');
    }

    /**
     * MÓDULO MÉDICOS: Exportar directorio de médicos filtrado a Excel (.xlsx)
     */
    public function medicosExcel(): void
    {
        [$title, $subtitle, $columns, $rows] = $this->buildFilteredMedicosPayload();
        $xlsxBytes = ExcelGenerator::buildXlsx($title, $subtitle, $columns, $rows, $this->getIssuerLabel());
        $this->sendXlsxResponse($xlsxBytes, 'Reporte_Plantel_Medico_' . date('Ymd') . '.xlsx');
    }

    /**
     * MÓDULO ESPECIALIDADES: Exportar catálogo de especialidades a PDF (.pdf)
     */
    public function especialidadesPdf(): void
    {
        [$title, $subtitle, $columns, $rows] = $this->buildFilteredEspecialidadesPayload();
        $pdfBytes = PdfGenerator::buildExecutiveReport(
            $title,
            $subtitle,
            $columns,
            $rows,
            ['Total Especialidades' => count($rows), 'Fecha Emisión' => date('d/m/Y H:i')],
            $this->getIssuerLabel(),
            'L'
        );
        $this->sendPdfResponse($pdfBytes, 'Reporte_Especialidades_Medicas_' . date('Ymd') . '.pdf', 'inline');
    }

    /**
     * MÓDULO ESPECIALIDADES: Exportar catálogo de especialidades a Excel (.xlsx)
     */
    public function especialidadesExcel(): void
    {
        [$title, $subtitle, $columns, $rows] = $this->buildFilteredEspecialidadesPayload();
        $xlsxBytes = ExcelGenerator::buildXlsx($title, $subtitle, $columns, $rows, $this->getIssuerLabel());
        $this->sendXlsxResponse($xlsxBytes, 'Reporte_Especialidades_Medicas_' . date('Ymd') . '.xlsx');
    }

    /**
     * MÓDULO HORARIOS: Exportar horarios vigentes a PDF (.pdf)
     */
    public function horariosPdf(): void
    {
        [$title, $subtitle, $columns, $rows] = $this->buildFilteredHorariosPayload();
        $pdfBytes = PdfGenerator::buildExecutiveReport(
            $title,
            $subtitle,
            $columns,
            $rows,
            ['Total Turnos Semanales' => count($rows), 'Fecha Emisión' => date('d/m/Y H:i')],
            $this->getIssuerLabel(),
            'L'
        );
        $this->sendPdfResponse($pdfBytes, 'Reporte_Horarios_Atencion_' . date('Ymd') . '.pdf', 'inline');
    }

    /**
     * MÓDULO HORARIOS: Exportar horarios vigentes a Excel (.xlsx)
     */
    public function horariosExcel(): void
    {
        [$title, $subtitle, $columns, $rows] = $this->buildFilteredHorariosPayload();
        $xlsxBytes = ExcelGenerator::buildXlsx($title, $subtitle, $columns, $rows, $this->getIssuerLabel());
        $this->sendXlsxResponse($xlsxBytes, 'Reporte_Horarios_Atencion_' . date('Ymd') . '.xlsx');
    }

    /**
     * MÓDULO USUARIOS Y ROLES: Exportar directorio de usuarios a PDF (.pdf)
     */
    public function usuariosPdf(): void
    {
        [$title, $subtitle, $columns, $rows] = $this->buildFilteredUsuariosPayload();
        $pdfBytes = PdfGenerator::buildExecutiveReport(
            $title,
            $subtitle,
            $columns,
            $rows,
            ['Total Usuarios' => count($rows), 'Fecha Emisión' => date('d/m/Y H:i')],
            $this->getIssuerLabel(),
            'L'
        );
        $this->sendPdfResponse($pdfBytes, 'Reporte_Usuarios_Y_Roles_' . date('Ymd') . '.pdf', 'inline');
    }

    /**
     * MÓDULO USUARIOS Y ROLES: Exportar directorio de usuarios a Excel (.xlsx)
     */
    public function usuariosExcel(): void
    {
        [$title, $subtitle, $columns, $rows] = $this->buildFilteredUsuariosPayload();
        $xlsxBytes = ExcelGenerator::buildXlsx($title, $subtitle, $columns, $rows, $this->getIssuerLabel());
        $this->sendXlsxResponse($xlsxBytes, 'Reporte_Usuarios_Y_Roles_' . date('Ymd') . '.xlsx');
    }

    private function buildFilteredCitasPayload(): array
    {
        $filters = [
            'q'               => trim((string) ($_GET['q'] ?? '')),
            'fecha_desde'     => trim((string) ($_GET['fecha_desde'] ?? '')),
            'fecha_hasta'     => trim((string) ($_GET['fecha_hasta'] ?? '')),
            'id_especialidad' => trim((string) ($_GET['id_especialidad'] ?? '')),
            'id_medico'       => trim((string) ($_GET['id_medico'] ?? '')),
            'estado'          => trim((string) ($_GET['estado'] ?? '')),
        ];
        if (Auth::hasRole('Médico')) {
            $filters['id_medico'] = (int) Auth::medicoId();
        } elseif (Auth::hasRole('Paciente')) {
            $filters['id_paciente'] = (int) Auth::pacienteId();
        }

        $citaModel = new CitaModel();
        $rawCitas = $citaModel->listCitas($filters, 500);

        $rows = [];
        foreach ($rawCitas as $c) {
            $rows[] = [
                'codigo_cita'  => $c['codigo_cita'] ?? '',
                'fecha_hora'   => date('d/m/Y', strtotime((string) $c['fecha_cita'])) . ' ' . substr((string) $c['hora_inicio'], 0, 5) . '-' . substr((string) $c['hora_fin'], 0, 5),
                'paciente'     => ($c['paciente_nombre'] ?? '') . ' (CI: ' . ($c['paciente_ci'] ?? '') . ')',
                'medico'       => 'Dr(a). ' . ($c['medico_nombre'] ?? ''),
                'especialidad' => $c['especialidad_nombre'] ?? '',
                'estado'       => $c['estado'] ?? '',
                'motivo'       => $c['motivo_consulta'] ?: '-',
            ];
        }

        $columns = [
            ['key' => 'codigo_cita',  'label' => 'Código',       'width' => 11],
            ['key' => 'fecha_hora',   'label' => 'Fecha / Hora', 'width' => 15],
            ['key' => 'paciente',     'label' => 'Paciente (CI)','width' => 23],
            ['key' => 'medico',       'label' => 'Médico',       'width' => 17],
            ['key' => 'especialidad', 'label' => 'Especialidad', 'width' => 12],
            ['key' => 'estado',       'label' => 'Estado',       'width' => 10],
            ['key' => 'motivo',       'label' => 'Motivo',       'width' => 12],
        ];

        $subtitle = (new ReporteModel())->buildFiltersDescription($filters);
        return ['Listado de Control y Seguimiento de Citas Médicas', $subtitle, $columns, $rows];
    }

    private function buildFilteredPacientesPayload(): array
    {
        $filters = [
            'q'           => trim((string) ($_GET['q'] ?? '')),
            'tipo_sangre' => trim((string) ($_GET['tipo_sangre'] ?? '')),
            'estado'      => trim((string) ($_GET['estado'] ?? '')),
        ];

        $pacientes = (new PacienteModel())->listAll($filters, 500);
        $rows = [];
        foreach ($pacientes as $p) {
            $rows[] = [
                'codigo_paciente' => $p['codigo_paciente'] ?? '',
                'ci'              => $p['ci'] ?? '',
                'paciente'        => ($p['nombres'] ?? '') . ' ' . ($p['apellidos'] ?? ''),
                'edad_sexo'       => ($p['edad'] ?? 0) . ' años (' . ($p['sexo'] ?? '') . ')',
                'tipo_sangre'     => $p['tipo_sangre'] ?? 'Desconocido',
                'alergias'        => $p['alergias'] ?: 'Ninguna conocida',
                'telefono'        => $p['telefono'] ?? '',
                'contacto_aux'    => !empty($p['contacto_nombre']) ? ($p['contacto_nombre'] . ' (' . $p['contacto_telefono'] . ')') : '-',
                'estado'          => $p['estado'] ?? '',
            ];
        }

        $columns = [
            ['key' => 'codigo_paciente', 'label' => 'Código',       'width' => 10],
            ['key' => 'ci',              'label' => 'CI',           'width' => 9],
            ['key' => 'paciente',        'label' => 'Paciente',     'width' => 20],
            ['key' => 'edad_sexo',       'label' => 'Edad/Sexo',    'width' => 10],
            ['key' => 'tipo_sangre',     'label' => 'Sangre',       'width' => 7],
            ['key' => 'alergias',        'label' => 'Alergias',     'width' => 16],
            ['key' => 'telefono',        'label' => 'Teléfono',     'width' => 9],
            ['key' => 'contacto_aux',    'label' => 'Emergencia',   'width' => 12],
            ['key' => 'estado',          'label' => 'Estado',       'width' => 7],
        ];

        $parts = [];
        if ($filters['q'] !== '') $parts[] = 'Búsqueda: "' . $filters['q'] . '"';
        if ($filters['tipo_sangre'] !== '') $parts[] = 'Sangre: ' . $filters['tipo_sangre'];
        if ($filters['estado'] !== '') $parts[] = 'Estado: ' . $filters['estado'];
        $subtitle = empty($parts) ? 'Padrón General Consolidado' : implode('  |  ', $parts);

        return ['Padrón Clínico de Pacientes y Ficha Médica Preventiva', $subtitle, $columns, $rows];
    }

    private function buildFilteredMedicosPayload(): array
    {
        $filters = [
            'q'               => trim((string) ($_GET['q'] ?? '')),
            'id_especialidad' => trim((string) ($_GET['id_especialidad'] ?? '')),
            'estado'          => trim((string) ($_GET['estado'] ?? '')),
        ];

        $medicos = (new MedicoModel())->listAll($filters);
        $rows = [];
        foreach ($medicos as $m) {
            $rows[] = [
                'codigo_medico'        => $m['codigo_medico'] ?? '',
                'medico'               => 'Dr(a). ' . ($m['nombres'] ?? '') . ' ' . ($m['apellidos'] ?? ''),
                'registro_profesional' => $m['registro_profesional'] ?? '',
                'especialidades'       => $m['especialidades_nombres'] ?? '',
                'correo'               => $m['correo'] ?? '',
                'telefono'             => $m['telefono'] ?? '',
                'total_turnos'         => $m['total_turnos'] ?? 0,
                'total_citas'          => $m['total_citas'] ?? 0,
                'estado'               => $m['estado'] ?? '',
            ];
        }

        $columns = [
            ['key' => 'codigo_medico',        'label' => 'Código',         'width' => 9],
            ['key' => 'medico',               'label' => 'Médico',         'width' => 20],
            ['key' => 'registro_profesional', 'label' => 'Matrícula',      'width' => 11],
            ['key' => 'especialidades',       'label' => 'Especialidades', 'width' => 18],
            ['key' => 'correo',               'label' => 'Correo',         'width' => 16],
            ['key' => 'telefono',             'label' => 'Teléfono',       'width' => 9],
            ['key' => 'total_turnos',         'label' => 'Días Turno',     'width' => 5],
            ['key' => 'total_citas',          'label' => 'Citas',          'width' => 5],
            ['key' => 'estado',               'label' => 'Estado',         'width' => 7],
        ];

        $subtitle = (new ReporteModel())->buildFiltersDescription($filters);
        return ['Directorio del Cuerpo Médico y Especialidades Asociadas', $subtitle, $columns, $rows];
    }

    private function buildFilteredEspecialidadesPayload(): array
    {
        $especialidades = (new EspecialidadModel())->listAll();
        $rows = [];
        foreach ($especialidades as $e) {
            $rows[] = [
                'codigo_especialidad' => $e['codigo_especialidad'] ?? '',
                'nombre'              => $e['nombre'] ?? '',
                'descripcion'         => $e['descripcion'] ?: 'Sin descripción',
                'medicos_activos'     => $e['medicos_activos'] ?? 0,
                'total_citas'         => $e['total_citas'] ?? 0,
                'estado'              => $e['estado'] ?? '',
            ];
        }

        $columns = [
            ['key' => 'codigo_especialidad', 'label' => 'Código',          'width' => 12],
            ['key' => 'nombre',              'label' => 'Especialidad',    'width' => 22],
            ['key' => 'descripcion',         'label' => 'Descripción',     'width' => 38],
            ['key' => 'medicos_activos',     'label' => 'Médicos Activos', 'width' => 10],
            ['key' => 'total_citas',         'label' => 'Total Citas',     'width' => 10],
            ['key' => 'estado',              'label' => 'Estado',          'width' => 8],
        ];

        return ['Catálogo Oficial de Especialidades Médicas', 'Catálogo Institucional Activo', $columns, $rows];
    }

    private function buildFilteredHorariosPayload(): array
    {
        $idMedico = !empty($_GET['id_medico']) ? (int) $_GET['id_medico'] : null;
        if (Auth::hasRole('Médico')) {
            $idMedico = (int) Auth::medicoId();
        }

        $horarios = (new HorarioModel())->listAll($idMedico);
        $diasMap = dias_semana_map();
        $rows = [];
        foreach ($horarios as $h) {
            $d = (int) ($h['dia_semana'] ?? 1);
            $rows[] = [
                'codigo_medico' => $h['codigo_medico'] ?? '',
                'medico'        => 'Dr(a). ' . ($h['medico_nombre'] ?? ''),
                'especialidades'=> $h['especialidades'] ?? '',
                'dia'           => $diasMap[$d] ?? 'Lunes',
                'horario'       => substr((string) $h['hora_inicio'], 0, 5) . ' - ' . substr((string) $h['hora_fin'], 0, 5),
                'duracion'      => ($h['duracion_minutos'] ?? 30) . ' min',
                'estado'        => $h['estado'] ?? '',
            ];
        }

        $columns = [
            ['key' => 'codigo_medico',  'label' => 'Código',         'width' => 11],
            ['key' => 'medico',         'label' => 'Médico',         'width' => 24],
            ['key' => 'especialidades', 'label' => 'Especialidades', 'width' => 22],
            ['key' => 'dia',            'label' => 'Día Semana',     'width' => 13],
            ['key' => 'horario',        'label' => 'Bloque Horario', 'width' => 13],
            ['key' => 'duracion',       'label' => 'Duración Cita',  'width' => 9],
            ['key' => 'estado',         'label' => 'Estado',         'width' => 8],
        ];

        $subtitle = $idMedico ? ('Filtrado por Médico ID #' . $idMedico) : 'Grilla Semanal Completa (Lunes a Sábado)';
        return ['Horarios Semanales de Atención Médica', $subtitle, $columns, $rows];
    }

    private function buildFilteredUsuariosPayload(): array
    {
        $filters = [
            'q'      => trim((string) ($_GET['q'] ?? '')),
            'id_rol' => trim((string) ($_GET['id_rol'] ?? '')),
            'estado' => trim((string) ($_GET['estado'] ?? '')),
        ];

        $usuarios = (new UsuarioModel())->listAll($filters);
        $rows = [];
        foreach ($usuarios as $u) {
            $rows[] = [
                'id_usuario'         => $u['id_usuario'] ?? '',
                'nombre_completo'    => $u['nombre_completo'] ?? '',
                'nombre_usuario'     => $u['nombre_usuario'] ?? '',
                'rol_nombre'         => $u['rol_nombre'] ?? '',
                'correo_electronico' => $u['correo_electronico'] ?? '',
                'telefono'           => $u['telefono'] ?: '-',
                'ficha'              => $u['codigo_medico'] ?? ($u['codigo_paciente'] ?? '-'),
                'estado'             => $u['estado'] ?? '',
            ];
        }

        $columns = [
            ['key' => 'id_usuario',         'label' => 'ID',              'width' => 6],
            ['key' => 'nombre_completo',    'label' => 'Nombre Completo', 'width' => 23],
            ['key' => 'nombre_usuario',     'label' => 'Usuario',         'width' => 14],
            ['key' => 'rol_nombre',         'label' => 'Rol RBAC',        'width' => 13],
            ['key' => 'correo_electronico', 'label' => 'Correo',          'width' => 20],
            ['key' => 'telefono',           'label' => 'Teléfono',        'width' => 9],
            ['key' => 'ficha',              'label' => 'Ficha',           'width' => 8],
            ['key' => 'estado',             'label' => 'Estado',          'width' => 7],
        ];

        $subtitle = (new ReporteModel())->buildFiltersDescription($filters);
        return ['Directorio de Usuarios y Roles del Sistema (RBAC)', $subtitle, $columns, $rows];
    }

    private function extractCommonFilters(): array
    {
        return [
            'q'               => trim((string) ($_GET['q'] ?? '')),
            'fecha_desde'     => trim((string) ($_GET['fecha_desde'] ?? '')),
            'fecha_hasta'     => trim((string) ($_GET['fecha_hasta'] ?? '')),
            'id_medico'       => trim((string) ($_GET['id_medico'] ?? '')),
            'id_especialidad' => trim((string) ($_GET['id_especialidad'] ?? '')),
            'estado'          => trim((string) ($_GET['estado'] ?? '')),
            'estado_paciente' => trim((string) ($_GET['estado_paciente'] ?? '')),
            'id_rol'          => trim((string) ($_GET['id_rol'] ?? '')),
        ];
    }

    private function getIssuerLabel(): string
    {
        $user = Auth::user();
        return ($user['nombre_completo'] ?? 'Usuario') . ' (' . ($user['rol'] ?? 'Administrador') . ')';
    }

    private function sendPdfResponse(string $pdfBytes, string $filename, string $disposition = 'inline'): never
    {
        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header("Content-Disposition: {$disposition}; filename=\"{$filename}\"");
            header('Content-Length: ' . strlen($pdfBytes));
            header('Cache-Control: private, max-age=0, must-revalidate');
        }
        echo $pdfBytes;
        exit;
    }

    private function sendXlsxResponse(string $xlsxBytes, string $filename): never
    {
        if (!headers_sent()) {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            header('Content-Length: ' . strlen($xlsxBytes));
            header('Cache-Control: no-store, no-cache, must-revalidate');
        }
        echo $xlsxBytes;
        exit;
    }
}
