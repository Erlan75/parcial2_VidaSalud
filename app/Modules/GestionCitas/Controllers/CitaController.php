<?php
declare(strict_types=1);

namespace App\Modules\GestionCitas\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DatabaseExceptionParser;
use App\Core\QrGenerator;
use App\Core\Validator;
use App\Modules\CatalogoMedico\Models\EspecialidadModel;
use App\Modules\CatalogoMedico\Models\MedicoModel;
use App\Modules\GestionCitas\Models\CitaModel;
use App\Modules\PacientesClinica\Models\PacienteModel;

/**
 * Controlador de Programación, Reprogramación, Cancelación, Asistencia y Consulta QR (CU08, CU09, CU10, CU11)
 */
final class CitaController extends Controller
{
    private CitaModel $model;

    public function __construct()
    {
        $this->model = new CitaModel();
    }

    public function index(): void
    {
        $espModel = new EspecialidadModel();
        $medModel = new MedicoModel();

        $filters = [
            'q'               => $_GET['q'] ?? '',
            'estado'          => $_GET['estado'] ?? '',
            'id_medico'       => $_GET['id_medico'] ?? '',
            'id_especialidad' => $_GET['id_especialidad'] ?? '',
            'fecha_desde'     => $_GET['fecha_desde'] ?? '',
            'fecha_hasta'     => $_GET['fecha_hasta'] ?? '',
        ];

        // Restricción de alcance por rol
        if (Auth::hasRole('Paciente')) {
            $filters['id_paciente'] = (int) Auth::pacienteId();
        } elseif (Auth::hasRole('Médico') && empty($filters['id_medico'])) {
            $filters['id_medico'] = (int) Auth::medicoId();
        }

        $this->render('GestionCitas/listado', [
            'pageTitle'      => Auth::hasRole('Paciente') ? 'Mis Citas Médicas' : 'Gestión Integral de Citas Médicas',
            'activeMenu'     => 'citas_listado',
            'citas'          => $this->model->listCitas($filters, 300),
            'especialidades' => $espModel->listAll(true),
            'medicos'        => $medModel->listAll(),
            'filters'        => $filters,
        ]);
    }

    /**
     * Interfaz interactiva paso a paso para programar cita médica
     */
    public function programarForm(): void
    {
        $espModel = new EspecialidadModel();
        $pacModel = new PacienteModel();

        $preselectedPaciente = null;
        if (Auth::hasRole('Paciente')) {
            $idPacPropio = (int) Auth::pacienteId();
            $preselectedPaciente = $idPacPropio > 0 ? $pacModel->findById($idPacPropio) : null;
        } elseif (!empty($_GET['id_paciente'])) {
            $preselectedPaciente = $pacModel->findById((int) $_GET['id_paciente']);
        }

        $this->render('GestionCitas/programar', [
            'pageTitle'           => 'Programar Cita Médica',
            'activeMenu'          => 'citas_programar',
            'especialidades'      => $espModel->listAll(true),
            'preselectedPaciente' => $preselectedPaciente,
            'pacientesIniciales'  => Auth::hasRole('Paciente') ? [] : $pacModel->listAll(['estado' => 'Activo'], 40),
        ]);
    }

    /**
     * Endpoint AJAX: Médicos activos asignados a una especialidad
     */
    public function medicosPorEspecialidadAjax(): void
    {
        $idEsp = (int) ($_GET['id_especialidad'] ?? 0);
        if ($idEsp <= 0) {
            $this->json(['ok' => true, 'medicos' => []]);
        }
        try {
            $medModel = new MedicoModel();
            $this->json([
                'ok'      => true,
                'medicos' => $medModel->listByEspecialidadActivos($idEsp),
            ]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'message' => DatabaseExceptionParser::parse($e)], 500);
        }
    }

    /**
     * Endpoint AJAX CU08/CU09: Invoca fn_obtener_intervalos_disponibles(:id_medico, :fecha)
     */
    public function intervalosDisponiblesAjax(): void
    {
        $idMedico = (int) ($_GET['id_medico'] ?? 0);
        $fecha = trim((string) ($_GET['fecha'] ?? ''));

        if ($idMedico <= 0 || !Validator::isValidDate($fecha)) {
            $this->json(['ok' => false, 'message' => 'Seleccione un médico activo y una fecha válida.'], 400);
        }

        try {
            $data = $this->model->obtenerIntervalosDisponibles($idMedico, $fecha);
            $this->json([
                'ok'           => true,
                'fecha'        => $fecha,
                'slots'        => $data['slots'],
                'motivo_vacio' => $data['motivo_vacio'],
            ]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'message' => DatabaseExceptionParser::parse($e)], 400);
        }
    }

    /**
     * Confirmación atómica con sp_programar_cita y regla anti-spam para pacientes
     */
    public function guardar(): void
    {
        $this->validatePostWithCsrf();

        $esPaciente = Auth::hasRole('Paciente');
        if ($esPaciente) {
            $idPaciente = (int) Auth::pacienteId();
            $postedPaciente = (int) ($_POST['id_paciente'] ?? $idPaciente);
            if ($idPaciente <= 0 || ($postedPaciente > 0 && $postedPaciente !== $idPaciente)) {
                if ($this->isAjax()) {
                    $this->json(['ok' => false, 'message' => 'Solo puede programar citas médicas para su propia cuenta de paciente.'], 403);
                }
                Auth::setFlash('error', 'Solo puede programar citas médicas para su propia cuenta de paciente.', 'Acceso restringido');
                $this->redirect('citas/programar');
            }
        } else {
            $idPaciente = (int) ($_POST['id_paciente'] ?? 0);
        }

        $idEspecialidad = (int) ($_POST['id_especialidad'] ?? 0);
        $idMedico = (int) ($_POST['id_medico'] ?? 0);
        $fechaCita = trim((string) ($_POST['fecha_cita'] ?? ''));
        $horaInicio = Validator::normalizeTime((string) ($_POST['hora_inicio'] ?? ''));
        $motivo = trim((string) ($_POST['motivo_consulta'] ?? ''));

        if ($idPaciente <= 0 || $idEspecialidad <= 0 || $idMedico <= 0 || !Validator::isValidDate($fechaCita) || !Validator::isValidTime($horaInicio)) {
            if ($this->isAjax()) {
                $this->json(['ok' => false, 'message' => 'Complete paciente, especialidad, médico, fecha y bloque horario disponible.'], 422);
            }
            Auth::setFlash('warning', 'Complete paciente, especialidad, médico, fecha y bloque horario.', 'Datos incompletos');
            $this->redirect('citas/programar');
        }

        if (strlen($motivo) < 4) {
            $motivo = 'Consulta médica ambulatoria programada';
        }

        try {
            $res = $this->model->programarCita(
                $idPaciente,
                $idMedico,
                $idEspecialidad,
                (int) Auth::id(),
                $fechaCita,
                $horaInicio,
                $motivo,
                client_ip(),
                true
            );

            $ticketUrl = url('exportar/comprobante', ['id_cita' => $res['id_cita']]);
            if ($this->isAjax()) {
                $this->json([
                    'ok'          => true,
                    'id_cita'     => $res['id_cita'],
                    'codigo_cita' => $res['codigo_cita'],
                    'ticket_url'  => $ticketUrl,
                    'message'     => "Cita {$res['codigo_cita']} programada exitosamente.",
                ]);
            }

            Auth::setFlash(
                'success',
                "Cita médica registrada con código {$res['codigo_cita']} para el " . date('d/m/Y', strtotime($fechaCita)) . ' a las ' . substr($horaInicio, 0, 5) . '.',
                'Cita Programada'
            );
            $this->redirect('citas', ['q' => $res['codigo_cita']]);
        } catch (\Throwable $e) {
            $cleanError = DatabaseExceptionParser::parse($e);
            if ($this->isAjax()) {
                $this->json(['ok' => false, 'message' => $cleanError], 409);
            }
            Auth::setFlash('error', $cleanError, 'No se pudo programar la cita');
            $this->redirect('citas/programar');
        }
    }

    /**
     * Reprogramar cita médica activa vía sp_reprogramar_cita
     */
    public function reprogramar(): void
    {
        $this->validatePostWithCsrf();

        $idCita = (int) ($_POST['id_cita'] ?? 0);
        $nuevaFecha = trim((string) ($_POST['nueva_fecha'] ?? ''));
        $nuevaHora = Validator::normalizeTime((string) ($_POST['nueva_hora_inicio'] ?? ''));
        $returnTo = trim((string) ($_POST['return_to'] ?? 'citas'));

        if ($idCita <= 0 || !Validator::isValidDate($nuevaFecha) || !Validator::isValidTime($nuevaHora)) {
            Auth::setFlash('warning', 'Seleccione la nueva fecha y un bloque horario libre para reprogramar.', 'Datos requeridos');
            $this->redirect($returnTo);
        }

        // Si es rol Paciente, verificar que la cita le pertenezca
        if (Auth::hasRole('Paciente')) {
            $cita = $this->model->findByIdOrCodigo($idCita);
            if (!$cita || (int) $cita['id_paciente'] !== (int) Auth::pacienteId()) {
                Auth::setFlash('error', 'No tiene autorización para modificar una cita ajena.', 'Seguridad');
                $this->redirect('citas');
            }
        }

        try {
            $this->model->reprogramarCita($idCita, (int) Auth::id(), $nuevaFecha, $nuevaHora, client_ip());
            Auth::setFlash('success', 'Cita reprogramada exitosamente liberando el horario anterior.', 'Cita Reprogramada');
        } catch (\Throwable $e) {
            Auth::setFlash('error', DatabaseExceptionParser::parse($e), 'No se pudo reprogramar');
        }

        $this->redirect($returnTo);
    }

    /**
     * Cancelar cita médica con motivo obligatorio vía sp_cancelar_cita
     */
    public function cancelar(): void
    {
        $this->validatePostWithCsrf();

        $idCita = (int) ($_POST['id_cita'] ?? 0);
        $motivo = trim((string) ($_POST['motivo_cancelacion'] ?? ''));
        $returnTo = trim((string) ($_POST['return_to'] ?? 'citas'));

        if ($idCita <= 0 || mb_strlen($motivo, 'UTF-8') < 5) {
            Auth::setFlash('warning', 'Debe ingresar un motivo de cancelación descriptivo de al menos 5 caracteres.', 'Motivo obligatorio');
            $this->redirect($returnTo);
        }

        if (Auth::hasRole('Paciente')) {
            $cita = $this->model->findByIdOrCodigo($idCita);
            if (!$cita || (int) $cita['id_paciente'] !== (int) Auth::pacienteId()) {
                Auth::setFlash('error', 'No tiene autorización para cancelar una cita ajena.', 'Seguridad');
                $this->redirect('citas');
            }
        }

        try {
            $this->model->cancelarCita($idCita, (int) Auth::id(), $motivo, client_ip());
            Auth::setFlash('success', 'Cita cancelada conservando su registro histórico y liberando el cupo.', 'Cita Cancelada');
        } catch (\Throwable $e) {
            Auth::setFlash('error', DatabaseExceptionParser::parse($e), 'Cancelación rechazada');
        }

        $this->redirect($returnTo);
    }

    /**
     * Controlar asistencia (Confirmada / Inasistencia)
     */
    public function asistencia(): void
    {
        $this->validatePostWithCsrf();

        $idCita = (int) ($_POST['id_cita'] ?? 0);
        $nuevoEstado = trim((string) ($_POST['nuevo_estado'] ?? ''));
        $returnTo = trim((string) ($_POST['return_to'] ?? 'agenda'));

        try {
            $this->model->controlarAsistencia($idCita, (int) Auth::id(), $nuevoEstado, client_ip());
            Auth::setFlash('success', "Asistencia registrada como «{$nuevoEstado}».", 'Control de Asistencia');
        } catch (\Throwable $e) {
            Auth::setFlash('error', DatabaseExceptionParser::parse($e), 'Error en control de asistencia');
        }

        $this->redirect($returnTo);
    }

    /**
     * Registrar diagnóstico/evolución y cerrar consulta como Atendida
     */
    public function atender(): void
    {
        $this->validatePostWithCsrf();

        $idCita = (int) ($_POST['id_cita'] ?? 0);
        $notasMedicas = trim((string) ($_POST['notas_medicas'] ?? ''));
        $returnTo = trim((string) ($_POST['return_to'] ?? 'agenda'));

        if ($idCita <= 0 || mb_strlen($notasMedicas, 'UTF-8') < 5) {
            Auth::setFlash('warning', 'Redacte el diagnóstico, evolución e indicaciones médicas (mínimo 5 caracteres).', 'Evolución clínica requerida');
            $this->redirect($returnTo);
        }

        $cita = $this->model->findByIdOrCodigo($idCita);
        if (!$cita) {
            Auth::setFlash('error', 'La cita especificada no existe.', 'Error');
            $this->redirect($returnTo);
        }

        // Si el usuario tiene rol Médico, se envía su propio id_medico para validar pertenencia estricta
        $idMedicoEjecutor = Auth::hasRole('Médico')
            ? (int) Auth::medicoId()
            : (int) $cita['id_medico'];

        try {
            $this->model->registrarAtencionMedica(
                $idCita,
                $idMedicoEjecutor,
                (int) Auth::id(),
                $notasMedicas,
                client_ip()
            );
            Auth::setFlash('success', "Consulta médica {$cita['codigo_cita']} finalizada como Atendida con notas clínicas registradas.", 'Atención Médica Registrada');
        } catch (\Throwable $e) {
            Auth::setFlash('error', DatabaseExceptionParser::parse($e), 'No se pudo registrar la atención');
        }

        $this->redirect($returnTo);
    }

    /**
     * Verificación pública/interna del Código QR de un comprobante de cita (CM-xxxxx)
     */
    public function verificarQr(): void
    {
        $codigo = trim((string) ($_GET['codigo'] ?? ''));
        $cita = $codigo !== '' ? $this->model->findByIdOrCodigo(null, $codigo) : null;
        $verifyUrl = url('citas/verificar', ['codigo' => $codigo]);
        $qrSvg = $cita ? QrGenerator::toSvg($verifyUrl, 150) : '';

        $this->render('GestionCitas/verificar_qr', [
            'pageTitle' => 'Verificación Oficial de Comprobante QR · Clínica Vida Salud',
            'codigo'    => $codigo,
            'cita'      => $cita,
            'qrSvg'     => $qrSvg,
        ], 'public_verify');
    }
}
