<?php
declare(strict_types=1);

namespace App\Modules\CatalogoMedico\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DatabaseExceptionParser;
use App\Core\Validator;
use App\Modules\CatalogoMedico\Models\HorarioModel;
use App\Modules\CatalogoMedico\Models\MedicoModel;

/**
 * CU06: Gestionar Horarios Semanales de Atención Médica
 */
final class HorarioController extends Controller
{
    private HorarioModel $model;

    public function __construct()
    {
        $this->model = new HorarioModel();
    }

    public function index(): void
    {
        $medicoModel = new MedicoModel();
        $idMedico = isset($_GET['id_medico']) && $_GET['id_medico'] !== '' ? (int) $_GET['id_medico'] : null;

        if (Auth::hasRole('Médico') && Auth::medicoId()) {
            $idMedico = Auth::medicoId();
        }

        $horarios = $this->model->listAll($idMedico);

        // Organizar grilla semanal Lunes (1) a Sábado (6)
        $grillaSemanal = [1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 6 => []];
        foreach ($horarios as $h) {
            $dia = (int) $h['dia_semana'];
            if (isset($grillaSemanal[$dia])) {
                $grillaSemanal[$dia][] = $h;
            }
        }

        $this->render('CatalogoMedico/horarios', [
            'pageTitle'      => 'Horarios de Atención Médica (CU06)',
            'activeMenu'     => 'horarios',
            'horarios'       => $horarios,
            'grillaSemanal'  => $grillaSemanal,
            'medicos'        => $medicoModel->listAll(['estado' => 'Activo']),
            'selectedMedico' => $idMedico,
            'canManage'      => Auth::hasRole('Administrador'),
        ]);
    }

    public function guardar(): void
    {
        $this->validatePostWithCsrf();

        $idMedico = (int) ($_POST['id_medico'] ?? 0);
        $diaSemana = (int) ($_POST['dia_semana'] ?? 0);
        $horaInicio = Validator::normalizeTime((string) ($_POST['hora_inicio'] ?? ''));
        $horaFin = Validator::normalizeTime((string) ($_POST['hora_fin'] ?? ''));
        $duracion = (int) ($_POST['duracion_minutos'] ?? 30);

        if ($idMedico <= 0 || $diaSemana < 1 || $diaSemana > 6) {
            Auth::setFlash('warning', 'Seleccione un médico activo y un día válido de Lunes (1) a Sábado (6).', 'Validación CU06');
            $this->redirect('horarios');
        }

        if (!Validator::isValidTime($horaInicio) || !Validator::isValidTime($horaFin) || $horaFin <= $horaInicio) {
            Auth::setFlash('warning', 'La hora de finalización debe ser mayor que la hora de inicio.', 'Rango horario inválido');
            $this->redirect('horarios', ['id_medico' => $idMedico]);
        }

        if (!in_array($duracion, [15, 20, 30, 45, 60], true)) {
            $duracion = 30;
        }

        try {
            $idHorario = $this->model->asignarHorario(
                $idMedico,
                (int) Auth::id(),
                $diaSemana,
                $horaInicio,
                $horaFin,
                $duracion,
                client_ip()
            );
            Auth::setFlash('success', "Turno semanal (#{$idHorario}) registrado exitosamente mediante sp_asignar_horario_medico.", 'Horario asignado');
        } catch (\Throwable $e) {
            Auth::setFlash('error', DatabaseExceptionParser::parse($e), 'Conflicto de Turno');
        }

        $this->redirect('horarios', ['id_medico' => $idMedico]);
    }

    public function conmutarEstado(): void
    {
        $this->validatePostWithCsrf();
        $idHorario = (int) ($_POST['id_horario'] ?? 0);
        $idMedico = (int) ($_POST['id_medico_filtro'] ?? 0);

        if ($idHorario > 0) {
            try {
                $nuevo = $this->model->conmutarEstado($idHorario, (int) Auth::id(), client_ip());
                Auth::setFlash('success', "Estado del turno actualizado a {$nuevo}.", 'Turno modificado');
            } catch (\Throwable $e) {
                Auth::setFlash('error', DatabaseExceptionParser::parse($e), 'Conflicto de Turno');
            }
        }

        $this->redirect('horarios', $idMedico > 0 ? ['id_medico' => $idMedico] : []);
    }
}
