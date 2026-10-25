<?php
declare(strict_types=1);

namespace App\Modules\ReportesAnalitica\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Modules\GestionCitas\Models\CitaModel;
use App\Modules\PacientesClinica\Models\PacienteModel;
use App\Modules\ReportesAnalitica\Models\ReporteModel;

/**
 * Panel Principal (Dashboard Adaptativo según Rol RBAC)
 */
final class DashboardController extends Controller
{
    public function index(): void
    {
        $reporteModel = new ReporteModel();
        $citaModel = new CitaModel();
        $role = Auth::role();

        $idMedico = ($role === 'Médico') ? Auth::medicoId() : null;
        $idPaciente = ($role === 'Paciente') ? Auth::pacienteId() : null;

        $kpis = $reporteModel->getDashboardKpis($idMedico, $idPaciente);
        $fichaPaciente = ($idPaciente !== null && $idPaciente > 0)
            ? (new PacienteModel())->findById($idPaciente)
            : null;

        $proximasFilters = [
            'fecha_desde' => date('Y-m-d'),
            'orden'       => 'cronologico',
        ];
        if ($idMedico) {
            $proximasFilters['id_medico'] = $idMedico;
        }
        if ($idPaciente) {
            $proximasFilters['id_paciente'] = $idPaciente;
        }

        $proximasCitas = $citaModel->listCitas($proximasFilters, 10);
        $chartEstados = $reporteModel->getChartDistribucionEstados();
        $chartEspecialidades = $reporteModel->getChartDemandaEspecialidades();

        $this->render('ReportesAnalitica/dashboard', [
            'pageTitle'           => 'Panel Clínico Principal · Clínica Vida Salud',
            'activeMenu'          => 'dashboard',
            'kpis'                => $kpis,
            'proximasCitas'       => $proximasCitas,
            'chartEstados'        => $chartEstados,
            'chartEspecialidades' => $chartEspecialidades,
            'fichaPaciente'       => $fichaPaciente,
        ]);
    }
}
