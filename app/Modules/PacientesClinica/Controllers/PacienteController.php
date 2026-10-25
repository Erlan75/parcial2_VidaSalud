<?php
declare(strict_types=1);

namespace App\Modules\PacientesClinica\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DatabaseExceptionParser;
use App\Core\Validator;
use App\Modules\PacientesClinica\Models\PacienteModel;

/**
 * CU07: Gestionar Pacientes y Ficha Médica Básica Preventiva
 */
final class PacienteController extends Controller
{
    private PacienteModel $model;

    public function __construct()
    {
        $this->model = new PacienteModel();
    }

    public function index(): void
    {
        $filters = [
            'q'           => $_GET['q'] ?? '',
            'estado'      => $_GET['estado'] ?? '',
            'tipo_sangre' => $_GET['tipo_sangre'] ?? '',
        ];

        $this->render('PacientesClinica/pacientes', [
            'pageTitle'        => 'Padrón de Pacientes y Fichas Médicas (CU07)',
            'activeMenu'       => 'pacientes',
            'pacientes'        => $this->model->listAll($filters),
            'filters'          => $filters,
            'canRegister'      => Auth::hasRole(['Administrador', 'Recepcionista']),
            'canEditFicha'     => Auth::hasRole(['Administrador', 'Recepcionista', 'Médico']),
        ]);
    }

    /**
     * Buscador AJAX en tiempo real por CI, código PAC-xxxxx, nombres o apellidos
     */
    public function buscarAjax(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        try {
            $results = $this->model->searchActivos($q, 20);
            $this->json(['ok' => true, 'pacientes' => $results]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'message' => DatabaseExceptionParser::parse($e)], 500);
        }
    }

    public function guardar(): void
    {
        $this->validatePostWithCsrf();

        $ci = trim((string) ($_POST['ci'] ?? ''));
        $nombres = trim((string) ($_POST['nombres'] ?? ''));
        $apellidos = trim((string) ($_POST['apellidos'] ?? ''));
        $fnac = trim((string) ($_POST['fecha_nacimiento'] ?? ''));
        $sexo = strtoupper(trim((string) ($_POST['sexo'] ?? 'M')));
        $telefono = trim((string) ($_POST['telefono'] ?? ''));
        $correo = strtolower(trim((string) ($_POST['correo'] ?? '')));

        if (!Validator::isValidCI($ci)) {
            Auth::setFlash('warning', 'Ingrese una Cédula de Identidad válida (5 a 12 dígitos, ej. 5894123 o 5894123-1A).', 'Validación CI');
            $this->redirect('pacientes');
        }
        if (strlen($nombres) < 2 || strlen($apellidos) < 2 || !Validator::isValidPhone($telefono)) {
            Auth::setFlash('warning', 'Complete nombres, apellidos y un teléfono de contacto válido.', 'Datos obligatorios');
            $this->redirect('pacientes');
        }
        if (!Validator::isValidDate($fnac) || $fnac > date('Y-m-d')) {
            Auth::setFlash('error', 'La fecha de nacimiento es inválida o futura (no permitida).', 'Validación de fecha');
            $this->redirect('pacientes');
        }
        if ($correo !== '' && !Validator::isValidEmail($correo)) {
            Auth::setFlash('warning', 'El correo electrónico ingresado no tiene un formato válido.', 'Correo inválido');
            $this->redirect('pacientes');
        }

        $portalCreds = null;
        if (!empty($_POST['crear_cuenta_portal'])) {
            $usr = trim((string) ($_POST['portal_usuario'] ?? ''));
            $pwd = (string) ($_POST['portal_password'] ?? '');
            if ($correo === '') {
                Auth::setFlash('warning', 'Para habilitar acceso al portal del paciente es obligatorio indicar su correo electrónico.', 'Portal Paciente');
                $this->redirect('pacientes');
            }
            $pwdCheck = Validator::validatePassword($pwd);
            if (strlen($usr) < 3 || !$pwdCheck['valid']) {
                Auth::setFlash('error', 'Credenciales del portal inválidas: usuario mín. 3 caracteres y contraseña segura (' . implode(' ', $pwdCheck['errors']) . ').', 'Portal Paciente');
                $this->redirect('pacientes');
            }
            $portalCreds = ['nombre_usuario' => $usr, 'password' => $pwd];
        }

        try {
            $res = $this->model->registrarPacienteCompleto($_POST, $portalCreds, (int) Auth::id(), client_ip());
            Auth::setFlash(
                'success',
                "Paciente registrado exitosamente con código correlativo {$res['codigo_paciente']} (Ficha Médica + Contacto creados vía sp_registrar_paciente_completo).",
                'Alta Integral Exitosa'
            );
        } catch (\Throwable $e) {
            Auth::setFlash('error', DatabaseExceptionParser::parse($e), 'Rechazo del Motor PostgreSQL');
        }

        $this->redirect('pacientes');
    }

    public function actualizarFicha(): void
    {
        $this->validatePostWithCsrf();

        $idPaciente = (int) ($_POST['id_paciente'] ?? 0);
        $tipoSangre = trim((string) ($_POST['tipo_sangre'] ?? 'Desconocido'));
        $alergias = (string) ($_POST['alergias'] ?? '');
        $cronicas = (string) ($_POST['enfermedades_cronicas'] ?? '');
        $medicacion = (string) ($_POST['medicacion_actual'] ?? '');
        $observaciones = (string) ($_POST['observaciones'] ?? '');

        if ($idPaciente <= 0) {
            $this->redirect('pacientes');
        }

        try {
            $this->model->actualizarFichaMedica(
                $idPaciente,
                (int) Auth::id(),
                $tipoSangre,
                $alergias,
                $cronicas,
                $medicacion,
                $observaciones,
                client_ip()
            );
            Auth::setFlash('success', 'Ficha médica preventiva actualizada exitosamente mediante sp_actualizar_ficha_medica.', 'Ficha Clínica Actualizada');
        } catch (\Throwable $e) {
            Auth::setFlash('error', DatabaseExceptionParser::parse($e), 'Error al actualizar ficha');
        }

        $this->redirect('pacientes');
    }

    public function actualizarDatos(): void
    {
        $this->validatePostWithCsrf();

        $idPaciente = (int) ($_POST['id_paciente'] ?? 0);
        $fnac = trim((string) ($_POST['fecha_nacimiento'] ?? ''));

        if ($idPaciente <= 0 || !Validator::isValidDate($fnac) || $fnac > date('Y-m-d')) {
            Auth::setFlash('warning', 'Verifique los datos demográficos y que la fecha de nacimiento no sea futura.', 'Validación');
            $this->redirect('pacientes');
        }

        try {
            $this->model->actualizarDatosDemograficosYContacto($idPaciente, $_POST, (int) Auth::id(), client_ip());
            Auth::setFlash('success', 'Datos personales y contacto de emergencia actualizados.', 'Paciente actualizado');
        } catch (\Throwable $e) {
            Auth::setFlash('error', DatabaseExceptionParser::parse($e), 'Error al actualizar');
        }

        $this->redirect('pacientes');
    }

    public function conmutarEstado(): void
    {
        $this->validatePostWithCsrf();
        $idPaciente = (int) ($_POST['id_paciente'] ?? 0);
        if ($idPaciente > 0) {
            try {
                $nuevo = $this->model->conmutarEstado($idPaciente, (int) Auth::id(), client_ip());
                Auth::setFlash('success', "Estado del paciente actualizado a {$nuevo} (baja/alta lógica).", 'Estado modificado');
            } catch (\Throwable $e) {
                Auth::setFlash('error', DatabaseExceptionParser::parse($e), 'Error');
            }
        }
        $this->redirect('pacientes');
    }
}
