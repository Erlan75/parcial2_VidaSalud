<?php
declare(strict_types=1);

namespace App\Modules\ReportesAnalitica\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\ExcelGenerator;
use App\Core\PdfGenerator;
use App\Modules\GestionCitas\Models\CitaModel;
use App\Modules\ReportesAnalitica\Models\ReporteModel;

/**
 * CU15: Exportar Reportes Ejecutivos (PDF .pdf / Excel .xlsx / CSV .csv)
 * y Comprobante Oficial de Cita en PDF con Código QR Vectorial
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
        $filename = 'Comprobante_' . $cita['codigo_cita'] . '_' . date('Ymd_Hi') . '.pdf';

        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header("Content-Disposition: {$disposition}; filename=\"{$filename}\"");
            header('Content-Length: ' . strlen($pdfBytes));
            header('Cache-Control: private, max-age=0, must-revalidate');
        }
        echo $pdfBytes;
        exit;
    }

    /**
     * TIPO 1: Exportación de Reporte Parametrizado a PDF (.pdf) con Nomenclatura Explícita
     */
    public function reportePdf(): void
    {
        $tipo = (string) ($_GET['tipo'] ?? 'citas_fecha');
        $filters = $this->extractFiltersFromRequest();

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

        $user = Auth::user();
        $generatedBy = ($user['nombre_completo'] ?? 'Usuario') . ' (' . ($user['rol'] ?? 'Administrador') . ')';

        $pdfBytes = PdfGenerator::buildExecutiveReport(
            $report['title'],
            $subtitle,
            $report['columns'],
            $report['rows'],
            $summary,
            $generatedBy,
            $report['orientation']
        );

        $baseName = (string) ($report['file_base'] ?? $tipo);
        $filename = ExcelGenerator::buildFilename($baseName, 'pdf');
        $disposition = !empty($_GET['download']) ? 'attachment' : 'inline';

        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header("Content-Disposition: {$disposition}; filename=\"{$filename}\"");
            header('Content-Length: ' . strlen($pdfBytes));
            header('Cache-Control: private, max-age=0, must-revalidate');
        }
        echo $pdfBytes;
        exit;
    }

    /**
     * TIPO 1: Exportación de Reporte Parametrizado a Microsoft Excel Nativo (.xlsx)
     */
    public function reporteExcel(): void
    {
        $tipo = (string) ($_GET['tipo'] ?? 'citas_fecha');
        $filters = $this->extractFiltersFromRequest();

        $reporteModel = new ReporteModel();
        $report = $reporteModel->generarReporte($tipo, $filters);
        $subtitle = $reporteModel->buildFiltersDescription($filters);

        $user = Auth::user();
        $generatedBy = ($user['nombre_completo'] ?? 'Usuario') . ' (' . ($user['rol'] ?? 'Administrador') . ')';

        $xlsxBytes = ExcelGenerator::buildXlsx(
            $report['title'],
            $subtitle,
            $report['columns'],
            $report['rows'],
            $generatedBy
        );

        $baseName = (string) ($report['file_base'] ?? $tipo);
        $filename = ExcelGenerator::buildFilename($baseName, 'xlsx');

        if (!headers_sent()) {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            header('Content-Length: ' . strlen($xlsxBytes));
            header('Cache-Control: no-store, no-cache, must-revalidate');
        }
        echo $xlsxBytes;
        exit;
    }

    /**
     * TIPO 1: Exportación de Reporte Parametrizado a CSV (.csv con BOM UTF-8)
     */
    public function reporteCsv(): void
    {
        $tipo = (string) ($_GET['tipo'] ?? 'citas_fecha');
        $filters = $this->extractFiltersFromRequest();

        $reporteModel = new ReporteModel();
        $report = $reporteModel->generarReporte($tipo, $filters);
        $subtitle = $reporteModel->buildFiltersDescription($filters);

        $user = Auth::user();
        $generatedBy = ($user['nombre_completo'] ?? 'Usuario') . ' (' . ($user['rol'] ?? 'Administrador') . ')';

        $csvBytes = ExcelGenerator::buildCsv(
            $report['title'],
            $subtitle,
            $report['columns'],
            $report['rows'],
            $generatedBy
        );

        $baseName = (string) ($report['file_base'] ?? $tipo);
        $filename = ExcelGenerator::buildFilename($baseName, 'csv');

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
     * TIPO 2: Exportación de Selección Visual e Interactiva a PDF (.pdf)
     * Exporta exactamente las columnas y filas marcadas en la interfaz.
     */
    public function seleccionInteractivaPdf(): void
    {
        [$title, $subtitle, $columns, $rows, $baseSlug] = $this->resolveInteractiveSelectionPayload();

        $user = Auth::user();
        $generatedBy = ($user['nombre_completo'] ?? 'Usuario') . ' (' . ($user['rol'] ?? 'Administrador') . ')';

        $summary = [
            'Filas Seleccionadas'    => count($rows),
            'Columnas Incluidas'     => count($columns),
            'Modo de Generación'     => 'Tipo 2: Interactivo',
            'Fecha Emisión'          => date('d/m/Y H:i'),
        ];

        $orientation = count($columns) > 5 ? 'L' : 'P';
        $pdfBytes = PdfGenerator::buildExecutiveReport(
            $title,
            $subtitle,
            $columns,
            $rows,
            $summary,
            $generatedBy,
            $orientation
        );

        $filename = ExcelGenerator::buildFilename($baseSlug, 'pdf');
        $disposition = !empty($_REQUEST['download']) ? 'attachment' : 'inline';

        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header("Content-Disposition: {$disposition}; filename=\"{$filename}\"");
            header('Content-Length: ' . strlen($pdfBytes));
            header('Cache-Control: private, max-age=0, must-revalidate');
        }
        echo $pdfBytes;
        exit;
    }

    /**
     * TIPO 2: Exportación de Selección Visual e Interactiva a Excel (.xlsx o .csv)
     * Exporta exactamente las columnas y filas marcadas en la interfaz.
     */
    public function seleccionInteractivaExcel(): void
    {
        [$title, $subtitle, $columns, $rows, $baseSlug] = $this->resolveInteractiveSelectionPayload();

        $user = Auth::user();
        $generatedBy = ($user['nombre_completo'] ?? 'Usuario') . ' (' . ($user['rol'] ?? 'Administrador') . ')';
        $formato = strtolower(trim((string) ($_REQUEST['formato'] ?? 'xlsx')));

        if ($formato === 'csv') {
            $bytes = ExcelGenerator::buildCsv($title, $subtitle, $columns, $rows, $generatedBy);
            $filename = ExcelGenerator::buildFilename($baseSlug, 'csv');
            if (!headers_sent()) {
                header('Content-Type: text/csv; charset=UTF-8');
                header("Content-Disposition: attachment; filename=\"{$filename}\"");
                header('Content-Length: ' . strlen($bytes));
                header('Cache-Control: no-store, no-cache');
            }
            echo $bytes;
            exit;
        }

        $xlsxBytes = ExcelGenerator::buildXlsx($title, $subtitle, $columns, $rows, $generatedBy);
        $filename = ExcelGenerator::buildFilename($baseSlug, 'xlsx');

        if (!headers_sent()) {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            header('Content-Length: ' . strlen($xlsxBytes));
            header('Cache-Control: no-store, no-cache, must-revalidate');
        }
        echo $xlsxBytes;
        exit;
    }

    private function extractFiltersFromRequest(): array
    {
        return [
            'fecha_desde'     => trim((string) ($_GET['fecha_desde'] ?? '')),
            'fecha_hasta'     => trim((string) ($_GET['fecha_hasta'] ?? '')),
            'id_medico'       => trim((string) ($_GET['id_medico'] ?? '')),
            'id_especialidad' => trim((string) ($_GET['id_especialidad'] ?? '')),
            'estado'          => trim((string) ($_GET['estado'] ?? '')),
            'estado_paciente' => trim((string) ($_GET['estado_paciente'] ?? '')),
        ];
    }

    /**
     * Procesa los parámetros enviados desde el constructor visual Tipo 2 (columnas, IDs de filas, chips).
     */
    private function resolveInteractiveSelectionPayload(): array
    {
        $reporteModel = new ReporteModel();
        $catalog = $reporteModel->getInteractiveColumnsCatalog();

        // Columnas marcadas
        $rawCols = $_REQUEST['cols'] ?? array_keys($catalog);
        if (is_string($rawCols)) {
            $rawCols = array_filter(array_map('trim', explode(',', $rawCols)));
        }
        $selectedColumns = [];
        if (is_array($rawCols)) {
            foreach ($rawCols as $colKey) {
                if (isset($catalog[$colKey])) {
                    $selectedColumns[] = $catalog[$colKey];
                }
            }
        }
        if (empty($selectedColumns)) {
            $selectedColumns = array_values($catalog);
        }

        // IDs de filas marcadas
        $rawIds = $_REQUEST['ids'] ?? [];
        if (is_string($rawIds)) {
            $rawIds = array_filter(array_map('intval', explode(',', $rawIds)));
        }
        $selectedIds = is_array($rawIds) ? array_values(array_filter(array_map('intval', $rawIds))) : [];

        // Filtros visuales activos (tarjetas de estados, chips de especialidades y médicos)
        $rawEstados = $_REQUEST['estados'] ?? [];
        if (is_string($rawEstados)) {
            $rawEstados = array_filter(array_map('trim', explode(',', $rawEstados)));
        }
        $estados = is_array($rawEstados) ? array_values(array_filter($rawEstados)) : [];

        $rawEsp = $_REQUEST['especialidades'] ?? [];
        if (is_string($rawEsp)) {
            $rawEsp = array_filter(array_map('intval', explode(',', $rawEsp)));
        }
        $especialidades = is_array($rawEsp) ? array_values(array_filter(array_map('intval', $rawEsp))) : [];

        $rawMed = $_REQUEST['medicos'] ?? [];
        if (is_string($rawMed)) {
            $rawMed = array_filter(array_map('intval', explode(',', $rawMed)));
        }
        $medicos = is_array($rawMed) ? array_values(array_filter(array_map('intval', $rawMed))) : [];

        $rows = $reporteModel->getDatasetInteractivo($selectedIds, $estados, $especialidades, $medicos);

        $filterDescParts = [];
        if (!empty($estados)) {
            $filterDescParts[] = 'Estados: ' . implode(', ', $estados);
        }
        if (!empty($especialidades)) {
            $filterDescParts[] = count($especialidades) . ' especialidad(es) seleccionada(s)';
        }
        if (!empty($medicos)) {
            $filterDescParts[] = count($medicos) . ' médico(s) seleccionado(s)';
        }
        $colLabels = array_column($selectedColumns, 'label');
        $filterDescParts[] = 'Columnas (' . count($colLabels) . '): ' . implode(', ', $colLabels);
        $filterDescParts[] = 'Filas marcadas: ' . count($rows);

        $title = 'Reporte Tipo 2: Selección Visual e Interactiva de Citas';
        $subtitle = implode('  |  ', $filterDescParts);

        $baseSlug = 'Seleccion_Interactiva_Citas';
        if (!empty($estados) && count($estados) <= 2) {
            $baseSlug = 'Seleccion_' . implode('_', array_map([ExcelGenerator::class, 'slugifyReportName'], $estados));
        }

        return [$title, $subtitle, $selectedColumns, $rows, $baseSlug];
    }
}
