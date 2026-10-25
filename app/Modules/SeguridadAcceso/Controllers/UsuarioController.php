<?php
declare(strict_types=1);

namespace App\Modules\SeguridadAcceso\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DatabaseExceptionParser;
use App\Core\Validator;
use App\Modules\SeguridadAcceso\Models\UsuarioModel;

/**
 * CU03: Gestionar Usuarios y Roles (RBAC)
 */
final class UsuarioController extends Controller
{
    private UsuarioModel $model;

    public function __construct()
    {
        $this->model = new UsuarioModel();
    }

    public function index(): void
    {
        $filters = [
            'id_rol' => $_GET['id_rol'] ?? '',
            'estado' => $_GET['estado'] ?? '',
            'q'      => $_GET['q'] ?? '',
        ];

        $this->render('SeguridadAcceso/usuarios', [
            'pageTitle'  => 'Gestión de Usuarios y Roles (CU03)',
            'activeMenu' => 'usuarios',
            'usuarios'   => $this->model->listAll($filters),
            'roles'      => $this->model->getRoles(),
            'filters'    => $filters,
        ]);
    }

    public function guardar(): void
    {
        $this->validatePostWithCsrf();

        $idRol = (int) ($_POST['id_rol'] ?? 0);
        $nombreCompleto = trim((string) ($_POST['nombre_completo'] ?? ''));
        $nombreUsuario = trim((string) ($_POST['nombre_usuario'] ?? ''));
        $correo = strtolower(trim((string) ($_POST['correo_electronico'] ?? '')));
        $telefono = trim((string) ($_POST['telefono'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($idRol <= 0 || strlen($nombreCompleto) < 4 || strlen($nombreUsuario) < 3) {
            Auth::setFlash('warning', 'Complete el rol, nombre completo (mín. 4 letras) y nombre de usuario (mín. 3 caracteres).', 'Datos incompletos');
            $this->redirect('usuarios');
        }

        if (!Validator::isValidEmail($correo)) {
            Auth::setFlash('warning', 'El correo electrónico ingresado no tiene un formato institucional o personal válido.', 'Correo inválido');
            $this->redirect('usuarios');
        }

        $pwdCheck = Validator::validatePassword($password);
        if (!$pwdCheck['valid']) {
            Auth::setFlash('error', 'Contraseña rechazada: ' . implode(' ', $pwdCheck['errors']), 'Política de Seguridad CU03');
            $this->redirect('usuarios');
        }

        try {
            $nuevoId = $this->model->crearUsuario(
                $idRol,
                $nombreCompleto,
                $nombreUsuario,
                $correo,
                $telefono,
                $password,
                (int) Auth::id(),
                client_ip()
            );
            Auth::setFlash('success', "Usuario '{$nombreUsuario}' (ID #{$nuevoId}) creado exitosamente vía sp_crear_usuario.", 'Alta de Usuario');
        } catch (\Throwable $e) {
            Auth::setFlash('error', DatabaseExceptionParser::parse($e), 'Error del Motor PostgreSQL');
        }

        $this->redirect('usuarios');
    }

    public function actualizar(): void
    {
        $this->validatePostWithCsrf();

        $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
        $idRol = (int) ($_POST['id_rol'] ?? 0);
        $nombreCompleto = trim((string) ($_POST['nombre_completo'] ?? ''));
        $nombreUsuario = trim((string) ($_POST['nombre_usuario'] ?? ''));
        $correo = strtolower(trim((string) ($_POST['correo_electronico'] ?? '')));
        $telefono = trim((string) ($_POST['telefono'] ?? ''));
        $newPassword = (string) ($_POST['password_opcional'] ?? '');

        if ($idUsuario <= 0 || $idRol <= 0 || strlen($nombreCompleto) < 4 || strlen($nombreUsuario) < 3) {
            Auth::setFlash('warning', 'Verifique todos los datos obligatorios del usuario.', 'Validación');
            $this->redirect('usuarios');
        }

        if (!Validator::isValidEmail($correo)) {
            Auth::setFlash('warning', 'El formato del correo electrónico no es válido.', 'Validación');
            $this->redirect('usuarios');
        }

        if ($newPassword !== '') {
            $pwdCheck = Validator::validatePassword($newPassword);
            if (!$pwdCheck['valid']) {
                Auth::setFlash('error', 'Nueva contraseña rechazada: ' . implode(' ', $pwdCheck['errors']), 'Política de Seguridad CU03');
                $this->redirect('usuarios');
            }
        }

        try {
            $this->model->actualizarUsuario(
                $idUsuario,
                $idRol,
                $nombreCompleto,
                $nombreUsuario,
                $correo,
                $telefono,
                $newPassword !== '' ? $newPassword : null,
                (int) Auth::id(),
                client_ip()
            );
            Auth::setFlash('success', "Datos del usuario '{$nombreUsuario}' actualizados y auditados.", 'Actualización exitosa');
        } catch (\Throwable $e) {
            Auth::setFlash('error', DatabaseExceptionParser::parse($e), 'Error al actualizar');
        }

        $this->redirect('usuarios');
    }

    public function conmutarEstado(): void
    {
        $this->validatePostWithCsrf();

        $idObjetivo = (int) ($_POST['id_usuario'] ?? 0);
        if ($idObjetivo <= 0) {
            $this->redirect('usuarios');
        }

        try {
            $this->model->conmutarEstado($idObjetivo, (int) Auth::id(), client_ip());
            Auth::setFlash('success', 'Estado de la cuenta actualizado correctamente mediante sp_conmutar_estado_usuario.', 'Cambio de Estado');
        } catch (\Throwable $e) {
            Auth::setFlash('error', DatabaseExceptionParser::parse($e), 'Operación rechazada');
        }

        $this->redirect('usuarios');
    }
}
