<?php
declare(strict_types=1);

namespace App\Modules\CatalogoMedico\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DatabaseExceptionParser;
use App\Core\Validator;
use App\Modules\CatalogoMedico\Models\BloqueoModel;
use App\Modules\CatalogoMedico\Models\MedicoModel;

/**
 * CU16: Gestionar Feriados Institucionales y Bloqueos de Disponibilidad Médica
 */
final class BloqueoController extends Controller
{
    private BloqueoModel $model;

    public function __construct()
    {
        $this->model = new BloqueoModel();
    }

    public function index(): void
    {
        $medicoModel = new MedicoModel();
        $scopeMed = Auth::hasRole('Médico') ? Auth::medicoId() : null;

        $resumenContingencia = $_SESSION['vs_contingency_summary'] ?? null;
        unset($_SESSION['vs_contingency_summary']);

        $this->render('CatalogoMedico/bloqueos', [
            'pageTitle'             => 'Feriados y Licencias',
            'activeMenu'            => 'bloqueos',
            'bloqueos'              => $this->model->listAll($scopeMed),
            'medicos'               => $medicoModel->listAll(['estado' => 'Activo']),
            'canManage'             => Auth::hasRole('Administrador'),
            'resumenContingencia'   => $resumenContingencia,
            'historialContingencia' => $this->model->listarReubicacionesContingenciaRecientes($scopeMed, 20),
        ]);
    }

    public function guardar(): void
    {
        $this->validatePostWithCsrf();

        $tipoBloqueo = trim((string) ($_POST['tipo_bloqueo'] ?? ''));
        $rawMedico = $_POST['id_medico'] ?? '';
        $idMedico = ($tipoBloqueo === 'Feriado Institucional' || $rawMedico === '' || $rawMedico === '0')
            ? null
            : (int) $rawMedico;

        $fechaInicio = trim((string) ($_POST['fecha_inicio'] ?? ''));
        $fechaFin = trim((string) ($_POST['fecha_fin'] ?? ''));
        $motivo = trim((string) ($_POST['motivo'] ?? ''));
        $reprogramarAfectadas = !empty($_POST['reprogramar_afectadas']);

        $tiposPermitidos = ['Feriado Institucional', 'Vacaciones', 'Licencia Médica', 'Congreso'];
        if (!in_array($tipoBloqueo, $tiposPermitidos, true)) {
            Auth::setFlash('warning', 'Seleccione un tipo de bloqueo válido.', 'Validación');
            $this->redirect('bloqueos');
        }

        if ($tipoBloqueo !== 'Feriado Institucional' && ($idMedico === null || $idMedico <= 0)) {
            Auth::setFlash('warning', 'Para Vacaciones, Licencia Médica o Congreso debe seleccionar al médico correspondiente.', 'Validación');
            $this->redirect('bloqueos');
        }

        if (!Validator::isValidDate($fechaInicio) || !Validator::isValidDate($fechaFin) || strlen($motivo) < 4) {
            Auth::setFlash('warning', 'Ingrese fechas válidas y un motivo descriptivo (mínimo 4 caracteres).', 'Validación');
            $this->redirect('bloqueos');
        }

        try {
            $idAdmin = (int) Auth::id();
            $ip = client_ip();

            $id = $this->model->registrarBloqueo(
                $idMedico,
                $idAdmin,
                $tipoBloqueo,
                $fechaInicio,
                $fechaFin,
                $motivo,
                $ip
            );

            if ($reprogramarAfectadas) {
                $resumen = $this->model->reubicarYNotificarCitasAfectadas(
                    $idMedico,
                    $fechaInicio,
                    $fechaFin,
                    $idAdmin,
                    $tipoBloqueo,
                    $motivo,
                    $ip,
                    $id
                );
                $_SESSION['vs_contingency_summary'] = $resumen;

                if ($resumen['total_reubicadas'] > 0) {
                    Auth::setFlash(
                        'success',
                        "Bloqueo (#{$id}) registrado. Se reprogramaron automáticamente {$resumen['total_reubicadas']} de {$resumen['total_afectadas']} cita(s) activa(s) al próximo turno disponible y se notificó a los pacientes.",
                        'Contingencia Ejecutada'
                    );
                } elseif ($resumen['total_afectadas'] === 0) {
                    Auth::setFlash(
                        'success',
                        "Bloqueo de agenda (#{$id}) registrado exitosamente. No se encontraron citas activas afectadas en las fechas bloqueadas.",
                        'Bloqueo Activo'
                    );
                } else {
                    Auth::setFlash(
                        'warning',
                        "Bloqueo (#{$id}) registrado. Se detectaron {$resumen['total_afectadas']} cita(s) en el rango, pero no se hallaron horarios libres dentro de los próximos 60 días.",
                        'Revisión Requerida'
                    );
                }
            } else {
                Auth::setFlash('success', "Bloqueo de agenda (#{$id}) registrado exitosamente.", 'Bloqueo Activo');
            }
        } catch (\Throwable $e) {
            Auth::setFlash('error', DatabaseExceptionParser::parse($e), 'No se pudo registrar el bloqueo');
        }

        $this->redirect('bloqueos');
    }

    public function eliminar(): void
    {
        $this->validatePostWithCsrf();
        $idBloqueo = (int) ($_POST['id_bloqueo'] ?? 0);
        if ($idBloqueo > 0) {
            try {
                $this->model->eliminarBloqueoFuturo($idBloqueo, (int) Auth::id(), client_ip());
                Auth::setFlash('success', 'El bloqueo de disponibilidad fue levantado y registrado en auditoría.', 'Bloqueo revocado');
            } catch (\Throwable $e) {
                Auth::setFlash('error', DatabaseExceptionParser::parse($e), 'Error');
            }
        }
        $this->redirect('bloqueos');
    }
}
