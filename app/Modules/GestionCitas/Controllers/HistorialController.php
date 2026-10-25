<?php
declare(strict_types=1);

namespace App\Modules\GestionCitas\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Modules\GestionCitas\Models\CitaModel;
use App\Modules\PacientesClinica\Models\PacienteModel;

/**
 * CU13: Consultar Historial Clínico de Citas por Paciente
 */
final class HistorialController extends Controller
{
    public function index(): void
    {
        $pacModel = new PacienteModel();
        $citaModel = new CitaModel();

        $idPaciente = 0;
        if (Auth::hasRole('Paciente')) {
            $idPaciente = (int) Auth::pacienteId();
        } elseif (!empty($_GET['id_paciente'])) {
            $idPaciente = (int) $_GET['id_paciente'];
        }

        $pacientesLista = Auth::hasRole('Paciente') ? [] : $pacModel->listAll([], 200);
        if ($idPaciente <= 0 && !empty($pacientesLista)) {
            $idPaciente = (int) $pacientesLista[0]['id_paciente'];
        }

        $paciente = $idPaciente > 0 ? $pacModel->findById($idPaciente) : null;
        $historial = $paciente ? $citaModel->listCitas(['id_paciente' => $idPaciente], 500) : [];

        $resumen = [
            'total'        => count($historial),
            'atendidas'    => 0,
            'programadas'  => 0,
            'canceladas'   => 0,
            'inasistencias'=> 0,
        ];
        foreach ($historial as $c) {
            match ($c['estado']) {
                'Atendida' => $resumen['atendidas']++,
                'Programada', 'Confirmada' => $resumen['programadas']++,
                'Cancelada' => $resumen['canceladas']++,
                'Inasistencia' => $resumen['inasistencias']++,
                default => null,
            };
        }

        $this->render('GestionCitas/historial', [
            'pageTitle'      => Auth::hasRole('Paciente') ? 'Mi Historial Clínico de Citas (CU13)' : 'Historial Clínico de Citas por Paciente (CU13)',
            'activeMenu'     => 'historial',
            'paciente'       => $paciente,
            'historial'      => $historial,
            'resumen'        => $resumen,
            'pacientesLista' => $pacientesLista,
        ]);
    }
}
