<?php
declare(strict_types=1);

namespace App\Modules\GestionCitas\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Modules\CatalogoMedico\Models\MedicoModel;
use App\Modules\GestionCitas\Models\CitaModel;

/**
 * CU12: Consultar Agenda Médica Diaria
 */
final class AgendaController extends Controller
{
    public function index(): void
    {
        $citaModel = new CitaModel();
        $medicoModel = new MedicoModel();

        $fecha = trim((string) ($_GET['fecha'] ?? date('Y-m-d')));
        $estado = trim((string) ($_GET['estado'] ?? ''));
        $idMedico = isset($_GET['id_medico']) && $_GET['id_medico'] !== '' ? (int) $_GET['id_medico'] : null;

        // El rol Médico ve su propia agenda del día por defecto
        if (Auth::hasRole('Médico') && Auth::medicoId()) {
            $idMedico = Auth::medicoId();
        }

        $filters = [
            'fecha_exacta' => $fecha,
            'orden'        => 'cronologico',
        ];
        if ($idMedico !== null && $idMedico > 0) {
            $filters['id_medico'] = $idMedico;
        }
        if ($estado !== '') {
            $filters['estado'] = $estado;
        }

        $citasDia = $citaModel->listCitas($filters, 200);
        $proximasFechas = $citaModel->getProximasFechasConCitas($idMedico, 6);

        $this->render('GestionCitas/agenda', [
            'pageTitle'      => 'Agenda Médica Diaria (CU12)',
            'activeMenu'     => 'agenda',
            'citas'          => $citasDia,
            'fecha'          => $fecha,
            'estado'         => $estado,
            'selectedMedico' => $idMedico,
            'medicos'        => $medicoModel->listAll(),
            'proximasFechas' => $proximasFechas,
        ]);
    }
}
