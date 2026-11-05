<?php
declare(strict_types=1);

namespace App\Modules\ReportesAnalitica\Controllers;

use App\Core\Controller;
use App\Modules\CatalogoMedico\Models\EspecialidadModel;
use App\Modules\CatalogoMedico\Models\MedicoModel;
use App\Modules\ReportesAnalitica\Models\ReporteModel;

/**
 * CU14: Centro de Reportes y Estadísticas Clínicas
 */
final class ReporteController extends Controller
{
    public function index(): void
    {
        $reporteModel = new ReporteModel();
        $medicoModel = new MedicoModel();
        $espModel = new EspecialidadModel();

        $tipo = (string) ($_GET['tipo'] ?? 'citas_medicas');
        $filters = [
            'q'               => trim((string) ($_GET['q'] ?? '')),
            'fecha_desde'     => trim((string) ($_GET['fecha_desde'] ?? '')),
            'fecha_hasta'     => trim((string) ($_GET['fecha_hasta'] ?? '')),
            'id_medico'       => trim((string) ($_GET['id_medico'] ?? '')),
            'id_especialidad' => trim((string) ($_GET['id_especialidad'] ?? '')),
            'estado'          => trim((string) ($_GET['estado'] ?? '')),
            'estado_paciente' => trim((string) ($_GET['estado_paciente'] ?? '')),
            'id_rol'          => trim((string) ($_GET['id_rol'] ?? '')),
        ];

        $reportData = $reporteModel->generarReporte($tipo, $filters);
        $kpis = $reporteModel->getDashboardKpis();
        $chartEstados = $reporteModel->getChartDistribucionEstados($filters);
        $chartEspecialidades = $reporteModel->getChartDemandaEspecialidades($filters);

        $this->render('ReportesAnalitica/reportes', [
            'pageTitle'           => 'Centro de Reportes y Estadísticas Clínicas',
            'activeMenu'          => 'reportes',
            'tipoReporte'         => $tipo,
            'filters'             => $filters,
            'filtersDesc'         => $reporteModel->buildFiltersDescription($filters),
            'reportData'          => $reportData,
            'kpis'                => $kpis,
            'chartEstados'        => $chartEstados,
            'chartEspecialidades' => $chartEspecialidades,
            'medicos'             => $medicoModel->listAll(),
            'especialidades'      => $espModel->listAll(),
        ]);
    }
}
