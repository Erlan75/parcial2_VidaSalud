<?php
declare(strict_types=1);

namespace App\Modules\ReportesAnalitica\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\PdfGenerator;
use App\Modules\GestionCitas\Models\CitaModel;
use App\Modules\ReportesAnalitica\Models\ReporteModel;

/**
 * CU15: Exportar Reportes Ejecutivos (PDF / CSV-Excel) y Comprobante de Cita en PDF con Código QR
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

        // Control RBAC para rol Paciente: solo puede descargar sus propios tickets
        if (Auth::hasRole('Paciente') && (int) $cita['id_paciente'] !== (int) Auth::pacienteId()) {
            Auth::setFlash('error', 'No tiene autorización para descargar comprobantes de otros pacientes.', 'Seguridad');
            $this->redirect('citas');
        }

        $verifyUrl = url('citas/verificar', ['codigo' => $cita['codigo_cita']]);
        $pdfBytes = PdfGenerator::buildCitaTicket($cita, $verifyUrl);

        $disposition = !empty($_GET['download']) ? 'attachment' : 'inline';
        $filename = 'Comprobante_' . $cita['codigo_cita'] . '_VidaSalud.pdf';

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
     * Exportación de cualquiera de los 6 Reportes Administrativos a PDF Formal Membretado
     */
    public function reportePdf(): void
    {
        $tipo = (string) ($_GET['tipo'] ?? 'citas_fecha');
        $filters = [
            'fecha_desde'     => $_GET['fecha_desde'] ?? '',
            'fecha_hasta'     => $_GET['fecha_hasta'] ?? '',
            'id_medico'       => $_GET['id_medico'] ?? '',
            'id_especialidad' => $_GET['id_especialidad'] ?? '',
            'estado'          => $_GET['estado'] ?? '',
            'estado_paciente' => $_GET['estado_paciente'] ?? '',
        ];

        $reporteModel = new ReporteModel();
        $report = $reporteModel->generarReporte($tipo, $filters);
        $kpis = $reporteModel->getDashboardKpis();

        $subtitleParts = [];
        if ($filters['fecha_desde'] !== '') {
            $subtitleParts[] = 'Desde: ' . $filters['fecha_desde'];
        }
        if ($filters['fecha_hasta'] !== '') {
            $subtitleParts[] = 'Hasta: ' . $filters['fecha_hasta'];
        }
        if ($filters['estado'] !== '') {
            $subtitleParts[] = 'Estado: ' . $filters['estado'];
        }
        $subtitle = empty($subtitleParts)
            ? 'Filtro aplicado: Histórico General Consolidado'
            : implode('  |  ', $subtitleParts);

        $summary = [
            'Registros en Reporte' => count($report['rows']),
            'Pacientes Activos'    => $kpis['pacientes_activos'] ?? 0,
            'Citas Atendidas'      => $kpis['citas_atendidas'] ?? 0,
            'Citas en Agenda'      => $kpis['citas_futuras'] ?? 0,
        ];

        $user = Auth::user();
        $generatedBy = ($user['nombre_completo'] ?? 'Usuario') . ' (' . ($user['rol'] ?? '') . ')';

        $pdfBytes = PdfGenerator::buildExecutiveReport(
            $report['title'],
            $subtitle,
            $report['columns'],
            $report['rows'],
            $summary,
            $generatedBy,
            $report['orientation']
        );

        $filename = 'Reporte_' . $tipo . '_' . date('Ymd_His') . '.pdf';
        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header("Content-Disposition: inline; filename=\"{$filename}\"");
            header('Content-Length: ' . strlen($pdfBytes));
        }
        echo $pdfBytes;
        exit;
    }

    /**
     * Exportación de cualquiera de los 6 Reportes Administrativos a CSV/Excel (UTF-8 con BOM)
     */
    public function reporteCsv(): void
    {
        $tipo = (string) ($_GET['tipo'] ?? 'citas_fecha');
        $filters = [
            'fecha_desde'     => $_GET['fecha_desde'] ?? '',
            'fecha_hasta'     => $_GET['fecha_hasta'] ?? '',
            'id_medico'       => $_GET['id_medico'] ?? '',
            'id_especialidad' => $_GET['id_especialidad'] ?? '',
            'estado'          => $_GET['estado'] ?? '',
            'estado_paciente' => $_GET['estado_paciente'] ?? '',
        ];

        $reporteModel = new ReporteModel();
        $report = $reporteModel->generarReporte($tipo, $filters);

        $filename = 'Reporte_' . $tipo . '_' . date('Ymd_His') . '.csv';
        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=UTF-8');
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            header('Cache-Control: no-store, no-cache');
        }

        $out = fopen('php://output', 'wb');
        // BOM UTF-8 para apertura directa con acentos en Microsoft Excel
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, ['CLÍNICA VIDA SALUD - ' . mb_strtoupper($report['title'], 'UTF-8')], ';');
        fputcsv($out, ['Generado el: ' . date('d/m/Y H:i:s')], ';');
        fputcsv($out, [], ';');

        $headers = array_column($report['columns'], 'label');
        fputcsv($out, $headers, ';');

        foreach ($report['rows'] as $row) {
            $line = [];
            foreach ($report['columns'] as $col) {
                $line[] = (string) ($row[$col['key']] ?? '');
            }
            fputcsv($out, $line, ';');
        }

        fclose($out);
        exit;
    }
}
