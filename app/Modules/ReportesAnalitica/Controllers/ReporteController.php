<?php
declare(strict_types=1);

namespace App\Modules\ReportesAnalitica\Controllers;

use App\Core\Controller;
use App\Modules\CatalogoMedico\Models\EspecialidadModel;
use App\Modules\CatalogoMedico\Models\MedicoModel;
use App\Modules\ReportesAnalitica\Models\ReporteModel;

/**
 * CU14: Generar Reportes Administrativos de Gestión y Analítica Estadística
 */
final class ReporteController extends Controller
{
    public function index(): void
    {
        $reporteModel = new ReporteModel();
        $medicoModel = new MedicoModel();
        $espModel = new EspecialidadModel();

        $tipo = (string) ($_GET['tipo'] ?? 'citas_fecha');
        $filters = [
            'fecha_desde'     => $_GET['fecha_desde'] ?? '',
            'fecha_hasta'     => $_GET['fecha_hasta'] ?? '',
            'id_medico'       => $_GET['id_medico'] ?? '',
            'id_especialidad' => $_GET['id_especialidad'] ?? '',
            'estado'          => $_GET['estado'] ?? '',
            'estado_paciente' => $_GET['estado_paciente'] ?? '',
        ];

        $reportData = $reporteModel->generarReporte($tipo, $filters);
        $kpis = $reporteModel->getDashboardKpis();
        $chartEstados = $reporteModel->getChartDistribucionEstados($filters);
        $chartEspecialidades = $reporteModel->getChartDemandaEspecialidades($filters);

        $this->render('ReportesAnalitica/reportes', [
            'pageTitle'           => 'Reportes y Estadísticas',
            'activeMenu'          => 'reportes',
            'tipoReporte'         => $tipo,
            'filters'             => $filters,
            'reportData'          => $reportData,
            'kpis'                => $kpis,
            'chartEstados'        => $chartEstados,
            'chartEspecialidades' => $chartEspecialidades,
            'medicos'             => $medicoModel->listAll(),
            'especialidades'      => $espModel->listAll(),
        ]);
    }
}
