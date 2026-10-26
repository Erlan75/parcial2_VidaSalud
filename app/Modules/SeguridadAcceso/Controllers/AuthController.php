<?php
declare(strict_types=1);

namespace App\Modules\SeguridadAcceso\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DatabaseExceptionParser;
use App\Core\Mailer;
use App\Core\Validator;
use App\Modules\PacientesClinica\Models\PacienteModel;
use App\Modules\SeguridadAcceso\Models\UsuarioModel;
use Database;

/**
 * Controlador de Autenticación, Registro de Pacientes, Cierre de Sesión, Perfil y Recuperación SMTP
 */
final class AuthController extends Controller
{
    private UsuarioModel $usuarioModel;

    public function __construct()
    {
        $this->usuarioModel = new UsuarioModel();
    }

    public function loginForm(): void
    {
        if (Auth::check()) {
            $this->redirect(Auth::homeRouteForRole(Auth::role()));
        }

        $health = Database::checkHealth();
        $openModal = !empty($_GET['registro']) || !empty($_SESSION['open_register_modal']);
        unset($_SESSION['open_register_modal']);

        $this->render('SeguridadAcceso/login', [
            'pageTitle'         => 'Iniciar Sesión · Clínica Vida Salud',
            'dbHealth'          => $health,
            'openRegisterModal' => $openModal,
        ], 'auth');
    }

    public function registroPacienteForm(): void
    {
        if (Auth::check()) {
            $this->redirect(Auth::homeRouteForRole(Auth::role()));
        }

        $health = Database::checkHealth();
        $this->render('SeguridadAcceso/login', [
            'pageTitle'         => 'Crear Cuenta de Paciente · Clínica Vida Salud',
            'dbHealth'          => $health,
            'openRegisterModal' => true,
        ], 'auth');
    }

    public function registroPacientePost(): void
    {
        $this->validatePostWithCsrf();

        $ci = trim((string) ($_POST['ci'] ?? ''));
        $nombres = trim((string) ($_POST['nombres'] ?? ''));
        $apellidos = trim((string) ($_POST['apellidos'] ?? ''));
        $fnac = trim((string) ($_POST['fecha_nacimiento'] ?? ''));
        $sexo = strtoupper(trim((string) ($_POST['sexo'] ?? '')));
        $telefono = trim((string) ($_POST['telefono'] ?? ''));
        $correo = strtolower(trim((string) ($_POST['correo'] ?? '')));
        $direccion = trim((string) ($_POST['direccion'] ?? ''));
        $nombreUsuario = trim((string) ($_POST['nombre_usuario'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (!Validator::isValidCI($ci)) {
            $_SESSION['open_register_modal'] = true;
            Auth::setFlash('warning', 'Ingrese una Cédula de Identidad válida (5 a 12 dígitos).', 'Validación');
            $this->redirect('seguridad/login');
        }

        if (strlen($nombres) < 2 || strlen($apellidos) < 2 || !in_array($sexo, ['M', 'F', 'O'], true)) {
            $_SESSION['open_register_modal'] = true;
            Auth::setFlash('warning', 'Complete sus nombres, apellidos y sexo.', 'Datos incompletos');
            $this->redirect('seguridad/login');
        }

        if (!Validator::isValidDate($fnac) || $fnac > date('Y-m-d')) {
            $_SESSION['open_register_modal'] = true;
            Auth::setFlash('warning', 'Ingrese una fecha de nacimiento válida.', 'Validación');
            $this->redirect('seguridad/login');
        }

        if (!Validator::isValidPhone($telefono) || !Validator::isValidEmail($correo) || strlen($direccion) < 3) {
            $_SESSION['open_register_modal'] = true;
            Auth::setFlash('warning', 'Complete un número de teléfono, correo electrónico válido y dirección domiciliaria.', 'Datos de contacto');
            $this->redirect('seguridad/login');
        }

        if (strlen($nombreUsuario) < 3) {
            $_SESSION['open_register_modal'] = true;
            Auth::setFlash('warning', 'El nombre de usuario debe contener al menos 3 caracteres.', 'Credenciales');
            $this->redirect('seguridad/login');
        }

        $pwdCheck = Validator::validatePassword($password);
        if (!$pwdCheck['valid']) {
            $_SESSION['open_register_modal'] = true;
            Auth::setFlash('error', 'Contraseña insegura: ' . implode(' ', $pwdCheck['errors']), 'Seguridad de contraseña');
            $this->redirect('seguridad/login');
        }

        try {
            $pacienteModel = new PacienteModel();
            $res = $pacienteModel->registrarPacienteCompleto(
                [
                    'ci'               => $ci,
                    'nombres'          => $nombres,
                    'apellidos'        => $apellidos,
                    'fecha_nacimiento' => $fnac,
                    'sexo'             => $sexo,
                    'telefono'         => $telefono,
                    'correo'           => $correo,
                    'direccion'        => $direccion,
                    'tipo_sangre'      => 'Desconocido',
                ],
                [
                    'nombre_usuario' => $nombreUsuario,
                    'password'       => $password,
                ],
                null,
                client_ip()
            );

            $user = !empty($res['id_usuario'])
                ? $this->usuarioModel->findById((int) $res['id_usuario'])
                : $this->usuarioModel->findByIdentifier($nombreUsuario);

            if ($user) {
                Auth::login($user);
                $this->usuarioModel->registerLoginAudit(
                    (int) $user['id_usuario'],
                    (string) $user['nombre_usuario'],
                    (string) $user['rol_nombre'],
                    client_ip()
                );
                Auth::setFlash(
                    'success',
                    '¡Cuenta creada exitosamente! Bienvenido(a) ' . $user['nombre_completo'] . ' (Expediente ' . $res['codigo_paciente'] . '). Ya puede programar su cita médica.',
                    'Registro completado'
                );
                $this->redirect('citas/programar');
            }

            Auth::setFlash('success', 'Cuenta registrada exitosamente. Ya puede iniciar sesión.', 'Registro completado');
            $this->redirect('seguridad/login');
        } catch (\Throwable $e) {
            $_SESSION['open_register_modal'] = true;
            Auth::setFlash('error', DatabaseExceptionParser::parse($e), 'No se pudo completar el registro');
            $this->redirect('seguridad/login');
        }
    }

    public function loginPost(): void
    {
        $this->validatePostWithCsrf();

        $identifier = trim((string) ($_POST['identificador'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($identifier === '' || $password === '') {
            Auth::setFlash('warning', 'Ingrese su nombre de usuario o correo electrónico y su contraseña.', 'Campos requeridos');
            $this->redirect('seguridad/login');
        }

        try {
            $user = $this->usuarioModel->findByIdentifier($identifier);
            if (!$user || !$this->usuarioModel->verifyUserPassword($user, $password)) {
                Auth::setFlash('error', 'Credenciales inválidas. Verifique su usuario/correo y contraseña.', 'Acceso denegado');
                $this->redirect('seguridad/login');
            }

            if (($user['estado'] ?? '') !== 'Activo') {
                Auth::setFlash('error', 'Su cuenta de usuario se encuentra inactiva. Contacte a Administración.', 'Cuenta suspendida');
                $this->redirect('seguridad/login');
            }

            Auth::login($user);
            $this->usuarioModel->registerLoginAudit(
                (int) $user['id_usuario'],
                (string) $user['nombre_usuario'],
                (string) $user['rol_nombre'],
                client_ip()
            );

            Auth::setFlash('success', 'Bienvenido(a), ' . $user['nombre_completo'] . ' (' . $user['rol_nombre'] . ').', 'Sesión iniciada');
            $this->redirect(Auth::homeRouteForRole((string) $user['rol_nombre']));
        } catch (\Throwable $e) {
            Auth::setFlash('error', DatabaseExceptionParser::parse($e), 'Error de conexión');
            $this->redirect('seguridad/login');
        }
    }

    /**
     * CU02: Cerrar sesión con auditoría LOGOUT y bloqueo de caché de navegador
     */
    public function logout(): void
    {
        if (Auth::check()) {
            try {
                $u = Auth::user();
                if ($u) {
                    $this->usuarioModel->registerLogoutAudit(
                        (int) $u['id_usuario'],
                        (string) $u['nombre_usuario'],
                        client_ip()
                    );
                }
            } catch (\Throwable) {
                // Continuar destrucción de sesión incluso si la BD está desconectada
            }
        }

        Auth::logout();
        Auth::startSession();
        Auth::setFlash('info', 'Su sesión se ha cerrado de forma segura.', 'Sesión finalizada');
        $this->redirect('seguridad/login');
    }

    public function forgotForm(): void
    {
        if (Auth::check()) {
            $this->redirect('dashboard');
        }
        $mailCfg = require ROOT_PATH . '/config/mail.php';
        $this->render('SeguridadAcceso/forgot_password', [
            'pageTitle' => 'Recuperar Contraseña · Clínica Vida Salud',
            'mailCfg'   => $mailCfg,
            'mailInfo'  => $_SESSION['last_reset_info'] ?? null,
        ], 'auth');
        unset($_SESSION['last_reset_info']);
    }

    public function forgotPost(): void
    {
        $this->validatePostWithCsrf();

        $correo = strtolower(trim((string) ($_POST['correo_electronico'] ?? '')));
        if (!Validator::isValidEmail($correo)) {
            Auth::setFlash('warning', 'Ingrese una dirección de correo electrónico válida.', 'Validación');
            $this->redirect('seguridad/forgot');
        }

        try {
            $tokenData = $this->usuarioModel->crearTokenRecuperacion($correo, client_ip());
            if (!$tokenData) {
                // Por seguridad anti-enumeración mostramos mensaje estándar
                Auth::setFlash('info', 'Si el correo está asociado a una cuenta activa, recibirá las instrucciones de restablecimiento en breve.', 'Solicitud procesada');
                $this->redirect('seguridad/forgot');
            }

            $resetUrl = url('seguridad/reset', [
                'rid'   => $tokenData['id_reset'],
                'token' => $tokenData['token'],
            ]);

            $sendResult = Mailer::sendPasswordResetEmail(
                (string) $tokenData['user']['correo_electronico'],
                (string) $tokenData['user']['nombre_completo'],
                $resetUrl,
                30
            );

            $_SESSION['last_reset_info'] = $sendResult;
            if (!empty($sendResult['sent_smtp'])) {
                Auth::setFlash('success', $sendResult['message'], 'Correo Gmail Enviado');
            } else {
                Auth::setFlash('info', $sendResult['message'], 'Enlace de Recuperación Generado');
            }
            $this->redirect('seguridad/forgot');
        } catch (\Throwable $e) {
            Auth::setFlash('error', DatabaseExceptionParser::parse($e), 'Error');
            $this->redirect('seguridad/forgot');
        }
    }

    public function resetForm(): void
    {
        $idReset = (int) ($_GET['rid'] ?? 0);
        $token = trim((string) ($_GET['token'] ?? ''));

        if ($idReset <= 0 || $token === '') {
            Auth::setFlash('error', 'El enlace de recuperación es incompleto o inválido.', 'Enlace inválido');
            $this->redirect('seguridad/login');
        }

        try {
            $validRow = $this->usuarioModel->validarTokenRecuperacion($idReset, $token);
            if (!$validRow) {
                Auth::setFlash('error', 'El enlace de restablecimiento ya fue utilizado o ha expirado (vigencia 30 min).', 'Enlace expirado');
                $this->redirect('seguridad/forgot');
            }

            $this->render('SeguridadAcceso/reset_password', [
                'pageTitle' => 'Nueva Contraseña · Clínica Vida Salud',
                'idReset'   => $idReset,
                'token'     => $token,
                'resetUser' => $validRow,
            ], 'auth');
        } catch (\Throwable $e) {
            Auth::setFlash('error', DatabaseExceptionParser::parse($e), 'Error');
            $this->redirect('seguridad/login');
        }
    }

    public function resetPost(): void
    {
        $this->validatePostWithCsrf();

        $idReset = (int) ($_POST['rid'] ?? 0);
        $token = trim((string) ($_POST['token'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        if ($password !== $confirm) {
            Auth::setFlash('warning', 'Las contraseñas ingresadas no coinciden.', 'Validación');
            $this->redirect('seguridad/reset', ['rid' => $idReset, 'token' => $token]);
        }

        $pwdCheck = Validator::validatePassword($password);
        if (!$pwdCheck['valid']) {
            Auth::setFlash('error', implode(' ', $pwdCheck['errors']), 'Complejidad de contraseña');
            $this->redirect('seguridad/reset', ['rid' => $idReset, 'token' => $token]);
        }

        try {
            $this->usuarioModel->aplicarRecuperacionPassword($idReset, $token, $password, client_ip());
            Auth::setFlash('success', 'Su contraseña ha sido actualizada exitosamente. Ya puede iniciar sesión.', 'Contraseña restablecida');
            $this->redirect('seguridad/login');
        } catch (\Throwable $e) {
            Auth::setFlash('error', DatabaseExceptionParser::parse($e), 'Error');
            $this->redirect('seguridad/forgot');
        }
    }

    public function perfil(): void
    {
        $user = $this->usuarioModel->findById((int) Auth::id());
        $this->render('SeguridadAcceso/perfil', [
            'pageTitle'   => 'Mi Perfil y Seguridad',
            'activeMenu'  => 'perfil',
            'userDetails' => $user,
        ]);
    }

    public function cambiarPasswordPost(): void
    {
        $this->validatePostWithCsrf();

        $actual = (string) ($_POST['password_actual'] ?? '');
        $nueva = (string) ($_POST['password_nueva'] ?? '');
        $confirm = (string) ($_POST['password_confirmar'] ?? '');

        if ($nueva !== $confirm) {
            Auth::setFlash('warning', 'La nueva contraseña y su confirmación no coinciden.', 'Validación');
            $this->redirect('seguridad/perfil');
        }

        $pwdCheck = Validator::validatePassword($nueva);
        if (!$pwdCheck['valid']) {
            Auth::setFlash('error', implode(' ', $pwdCheck['errors']), 'Contraseña débil');
            $this->redirect('seguridad/perfil');
        }

        try {
            $this->usuarioModel->cambiarPasswordPropia((int) Auth::id(), $actual, $nueva, client_ip());
            Auth::setFlash('success', 'Su contraseña personal se actualizó correctamente con cifrado BCRYPT.', 'Seguridad actualizada');
        } catch (\Throwable $e) {
            Auth::setFlash('error', DatabaseExceptionParser::parse($e), 'No se pudo cambiar la contraseña');
        }

        $this->redirect('seguridad/perfil');
    }
}
