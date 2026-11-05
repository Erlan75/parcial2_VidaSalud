<?php
declare(strict_types=1);

namespace App\Modules\ReportesAnalitica\Controllers;

use App\Core\AiSqlReportService;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\ExcelGenerator;
use App\Core\PdfGenerator;
use App\Modules\CatalogoMedico\Models\EspecialidadModel;
use App\Modules\CatalogoMedico\Models\MedicoModel;
use App\Modules\ReportesAnalitica\Models\ReporteModel;
use Throwable;

/**
 * CU14 & CU15 Evolucionado:
 *   - Tipo 1: Reporte Parametrizado por Filtros de Datos (.pdf / .xlsx / .csv)
 *   - Tipo 2: Selección Visual e Interactiva por Interfaz (Tarjetas, Chips, Columnas y Filas)
 *   - Tipo 3: Reporte Inteligente por Nota de Voz / Lenguaje Natural con IA (Voice-to-SQL + Síntesis de Voz)
 */
final class ReporteController extends Controller
{
    public function index(): void
    {
        $reporteModel = new ReporteModel();
        $medicoModel = new MedicoModel();
        $espModel = new EspecialidadModel();

        $tipo = (string) ($_GET['tipo'] ?? 'citas_fecha');
        $modoTab = (string) ($_GET['modo'] ?? 'tipo1');
        if (!in_array($modoTab, ['tipo1', 'tipo2', 'tipo3'], true)) {
            $modoTab = 'tipo1';
        }

        $filters = [
            'fecha_desde'     => trim((string) ($_GET['fecha_desde'] ?? '')),
            'fecha_hasta'     => trim((string) ($_GET['fecha_hasta'] ?? '')),
            'id_medico'       => trim((string) ($_GET['id_medico'] ?? '')),
            'id_especialidad' => trim((string) ($_GET['id_especialidad'] ?? '')),
            'estado'          => trim((string) ($_GET['estado'] ?? '')),
            'estado_paciente' => trim((string) ($_GET['estado_paciente'] ?? '')),
        ];

        $reportData = $reporteModel->generarReporte($tipo, $filters);
        $kpis = $reporteModel->getDashboardKpis();
        $chartEstados = $reporteModel->getChartDistribucionEstados($filters);
        $chartEspecialidades = $reporteModel->getChartDemandaEspecialidades($filters);

        // Dataset e inventario de columnas para el Reporte Tipo 2 (Selección Interactiva)
        $interactiveDataset = $reporteModel->getDatasetInteractivo();
        $interactiveColumns = $reporteModel->getInteractiveColumnsCatalog();

        // Último reporte generado por Voz/IA en sesión (si existe)
        $lastAiReport = $_SESSION['vs_last_ai_report'] ?? null;

        $this->render('ReportesAnalitica/reportes', [
            'pageTitle'           => 'Reportes Ejecutivos (Tipo 1, 2 y 3 con IA por Voz)',
            'activeMenu'          => 'reportes',
            'modoTab'             => $modoTab,
            'tipoReporte'         => $tipo,
            'filters'             => $filters,
            'filtersDesc'         => $reporteModel->buildFiltersDescription($filters),
            'reportData'          => $reportData,
            'kpis'                => $kpis,
            'chartEstados'        => $chartEstados,
            'chartEspecialidades' => $chartEspecialidades,
            'medicos'             => $medicoModel->listAll(),
            'especialidades'      => $espModel->listAll(),
            'interactiveDataset'  => $interactiveDataset,
            'interactiveColumns'  => $interactiveColumns,
            'lastAiReport'        => $lastAiReport,
        ]);
    }

    /**
     * Endpoint AJAX POST /reportes/ia-voz
     * Recibe la instrucción de voz o texto en lenguaje natural, genera y ejecuta el SQL SELECT
     * dentro de una transacción READ ONLY en PostgreSQL y devuelve el reporte estructurado + resumen de voz.
     */
    public function iaVoz(): void
    {
        $rawBody = (string) file_get_contents('php://input');
        $jsonInput = json_decode($rawBody, true);

        $prompt = '';
        if (is_array($jsonInput) && isset($jsonInput['prompt'])) {
            $prompt = trim((string) $jsonInput['prompt']);
        } elseif (isset($_POST['prompt'])) {
            $prompt = trim((string) $_POST['prompt']);
        }

        if ($prompt === '') {
            $this->json([
                'ok'      => false,
                'message' => 'No se recibió ninguna instrucción de voz o texto. Por favor dicte o escriba su consulta.',
            ], 422);
        }

        try {
            $result = AiSqlReportService::generateReportFromPrompt($prompt);
            $_SESSION['vs_last_ai_report'] = $result;
            $this->json($result);
        } catch (Throwable $e) {
            $this->json([
                'ok'      => false,
                'message' => 'Error al procesar el reporte inteligente: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint GET /reportes/ia-exportar-pdf
     * Descarga el último reporte generado por Voz/IA en formato PDF (.pdf) con nombre explícito.
     */
    public function iaExportarPdf(): void
    {
        $report = $this->resolveAiReportForExport();

        $user = Auth::user();
        $generatedBy = ($user['nombre_completo'] ?? 'Usuario') . ' (' . ($user['rol'] ?? 'Administrador') . ')';

        $subtitle = 'Instrucción de Voz IA: "' . $report['prompt'] . '"';
        $summary = [
            'Total Registros IA' => $report['total_registros'],
            'Motor Analítico'    => 'Voice-to-SQL ReadOnly',
            'Transacción BD'     => 'PostgreSQL READ ONLY',
            'Fecha Emisión'      => date('d/m/Y H:i'),
        ];

        $orientation = count($report['columns']) > 5 ? 'L' : 'P';
        $pdfBytes = PdfGenerator::buildExecutiveReport(
            (string) $report['titulo_reporte'],
            $subtitle,
            $report['columns'],
            $report['rows'],
            $summary,
            $generatedBy,
            $orientation
        );

        $filename = ExcelGenerator::buildFilename(
            (string) ($report['nombre_archivo_sugerido'] ?? $report['titulo_reporte']),
            'pdf'
        );
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
     * Endpoint GET /reportes/ia-exportar-excel
     * Descarga el último reporte generado por Voz/IA en formato Excel (.xlsx o .csv) con nombre explícito.
     */
    public function iaExportarExcel(): void
    {
        $report = $this->resolveAiReportForExport();

        $user = Auth::user();
        $generatedBy = ($user['nombre_completo'] ?? 'Usuario') . ' (' . ($user['rol'] ?? 'Administrador') . ')';
        $subtitle = 'Instrucción de Voz IA: "' . $report['prompt'] . '"';
        $extraMeta = [
            'subtitle_extra' => 'SQL Ejecutado (Read-Only): ' . preg_replace('/\s+/', ' ', (string) $report['sql_generado']),
        ];

        $formato = strtolower(trim((string) ($_GET['formato'] ?? 'xlsx')));
        $baseSlug = (string) ($report['nombre_archivo_sugerido'] ?? $report['titulo_reporte']);

        if ($formato === 'csv') {
            $csvBytes = ExcelGenerator::buildCsv(
                (string) $report['titulo_reporte'],
                $subtitle,
                $report['columns'],
                $report['rows'],
                $generatedBy,
                $extraMeta
            );
            $filename = ExcelGenerator::buildFilename($baseSlug, 'csv');
            if (!headers_sent()) {
                header('Content-Type: text/csv; charset=UTF-8');
                header("Content-Disposition: attachment; filename=\"{$filename}\"");
                header('Content-Length: ' . strlen($csvBytes));
                header('Cache-Control: no-store, no-cache');
            }
            echo $csvBytes;
            exit;
        }

        $xlsxBytes = ExcelGenerator::buildXlsx(
            (string) $report['titulo_reporte'],
            $subtitle,
            $report['columns'],
            $report['rows'],
            $generatedBy,
            $extraMeta
        );
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

    private function resolveAiReportForExport(): array
    {
        $promptFromQuery = trim((string) ($_GET['prompt'] ?? ''));
        if ($promptFromQuery !== '') {
            $report = AiSqlReportService::generateReportFromPrompt($promptFromQuery);
            $_SESSION['vs_last_ai_report'] = $report;
            return $report;
        }

        if (!empty($_SESSION['vs_last_ai_report']) && is_array($_SESSION['vs_last_ai_report'])) {
            return $_SESSION['vs_last_ai_report'];
        }

        $defaultPrompt = 'Mostrar todas las citas canceladas o inasistencias agrupadas por médico';
        $report = AiSqlReportService::generateReportFromPrompt($defaultPrompt);
        $_SESSION['vs_last_ai_report'] = $report;
        return $report;
    }
}
