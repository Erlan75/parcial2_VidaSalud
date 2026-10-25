<?php
declare(strict_types=1);

namespace App\Modules\CatalogoMedico\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DatabaseExceptionParser;
use App\Core\Validator;
use App\Modules\CatalogoMedico\Models\EspecialidadModel;
use App\Modules\CatalogoMedico\Models\MedicoModel;
use App\Modules\SeguridadAcceso\Models\UsuarioModel;

/**
 * CU05: Gestionar Médicos y Asignación de Especialidades
 */
final class MedicoController extends Controller
{
    private MedicoModel $model;

    public function __construct()
    {
        $this->model = new MedicoModel();
    }

    public function index(): void
    {
        $espModel = new EspecialidadModel();
        $usrModel = new UsuarioModel();

        $filters = [
            'id_especialidad' => $_GET['id_especialidad'] ?? '',
            'estado'          => $_GET['estado'] ?? '',
            'q'               => $_GET['q'] ?? '',
        ];

        $this->render('CatalogoMedico/medicos', [
            'pageTitle'         => 'Plantel Médico y Especialidades (CU05)',
            'activeMenu'        => 'medicos',
            'medicos'           => $this->model->listAll($filters),
            'especialidades'    => $espModel->listAll(true),
            'usuariosSinMedico' => $usrModel->getUnassignedDoctorUsers(),
            'filters'           => $filters,
            'canEdit'           => Auth::hasRole('Administrador'),
        ]);
    }

    public function guardar(): void
    {
        $this->validatePostWithCsrf();

        $modoCuenta = (string) ($_POST['modo_cuenta'] ?? 'nueva');
        $existingUserId = ($modoCuenta === 'existente') ? (int) ($_POST['id_usuario_existente'] ?? 0) : null;
        $nombreUsuario = trim((string) ($_POST['nombre_usuario'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $registro = trim((string) ($_POST['registro_profesional'] ?? ''));
        $nombres = trim((string) ($_POST['nombres'] ?? ''));
        $apellidos = trim((string) ($_POST['apellidos'] ?? ''));
        $telefono = trim((string) ($_POST['telefono'] ?? ''));
        $correo = strtolower(trim((string) ($_POST['correo'] ?? '')));
        $especialidades = $_POST['especialidades'] ?? [];

        if ($registro === '' || $nombres === '' || $apellidos === '' || !Validator::isValidEmail($correo)) {
            Auth::setFlash('warning', 'Complete la matrícula profesional, nombres, apellidos y correo institucional válido.', 'Datos inválidos');
            $this->redirect('medicos');
        }

        if (!is_array($especialidades) || empty($especialidades)) {
            Auth::setFlash('warning', 'Seleccione al menos una especialidad médica para el profesional.', 'Especialidad requerida');
            $this->redirect('medicos');
        }

        $newUserCreds = null;
        if (!$existingUserId) {
            if (strlen($nombreUsuario) < 3) {
                Auth::setFlash('warning', 'Ingrese un nombre de usuario válido (mínimo 3 caracteres) para la cuenta del médico.', 'Cuenta de médico');
                $this->redirect('medicos');
            }
            $pwdCheck = Validator::validatePassword($password);
            if (!$pwdCheck['valid']) {
                Auth::setFlash('error', 'Contraseña del médico rechazada: ' . implode(' ', $pwdCheck['errors']), 'Seguridad CU03');
                $this->redirect('medicos');
            }
            $newUserCreds = [
                'nombre_usuario' => $nombreUsuario,
                'password'       => $password,
            ];
        }

        try {
            $res = $this->model->registrarMedicoCompleto(
                $existingUserId,
                $newUserCreds,
                $registro,
                $nombres,
                $apellidos,
                $telefono,
                $correo,
                $especialidades,
                (int) Auth::id(),
                client_ip()
            );
            Auth::setFlash('success', "Médico registrado con código {$res['codigo_medico']} vía sp_registrar_medico_completo.", 'Alta de Médico');
        } catch (\Throwable $e) {
            Auth::setFlash('error', DatabaseExceptionParser::parse($e), 'Error del Motor PostgreSQL');
        }

        $this->redirect('medicos');
    }

    public function actualizar(): void
    {
        $this->validatePostWithCsrf();

        $idMedico = (int) ($_POST['id_medico'] ?? 0);
        $registro = trim((string) ($_POST['registro_profesional'] ?? ''));
        $nombres = trim((string) ($_POST['nombres'] ?? ''));
        $apellidos = trim((string) ($_POST['apellidos'] ?? ''));
        $telefono = trim((string) ($_POST['telefono'] ?? ''));
        $correo = strtolower(trim((string) ($_POST['correo'] ?? '')));
        $especialidades = is_array($_POST['especialidades'] ?? null) ? $_POST['especialidades'] : [];

        if ($idMedico <= 0 || $registro === '' || $nombres === '' || $apellidos === '' || !Validator::isValidEmail($correo)) {
            Auth::setFlash('warning', 'Verifique todos los datos obligatorios del médico.', 'Validación');
            $this->redirect('medicos');
        }

        try {
            $this->model->actualizarMedico(
                $idMedico,
                $registro,
                $nombres,
                $apellidos,
                $telefono,
                $correo,
                $especialidades,
                (int) Auth::id(),
                client_ip()
            );
            Auth::setFlash('success', 'Perfil profesional y especialidades actualizados correctamente.', 'Actualización exitosa');
        } catch (\Throwable $e) {
            Auth::setFlash('error', DatabaseExceptionParser::parse($e), 'Error al actualizar');
        }

        $this->redirect('medicos');
    }

    public function conmutarEstado(): void
    {
        $this->validatePostWithCsrf();
        $idMedico = (int) ($_POST['id_medico'] ?? 0);
        if ($idMedico > 0) {
            try {
                $nuevo = $this->model->conmutarEstado($idMedico, (int) Auth::id(), client_ip());
                Auth::setFlash('success', "Estado del médico cambiado a {$nuevo} (conservando historial de citas).", 'Estado actualizado');
            } catch (\Throwable $e) {
                Auth::setFlash('error', DatabaseExceptionParser::parse($e), 'Error');
            }
        }
        $this->redirect('medicos');
    }
}
