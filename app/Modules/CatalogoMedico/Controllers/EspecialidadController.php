<?php
declare(strict_types=1);

namespace App\Modules\CatalogoMedico\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DatabaseExceptionParser;
use App\Modules\CatalogoMedico\Models\EspecialidadModel;

/**
 * CU04: Gestionar Especialidades Médicas
 */
final class EspecialidadController extends Controller
{
    private EspecialidadModel $model;

    public function __construct()
    {
        $this->model = new EspecialidadModel();
    }

    public function index(): void
    {
        $this->render('CatalogoMedico/especialidades', [
            'pageTitle'      => 'Especialidades Médicas (CU04)',
            'activeMenu'     => 'especialidades',
            'especialidades' => $this->model->listAll(false),
        ]);
    }

    public function guardar(): void
    {
        $this->validatePostWithCsrf();

        $codigo = strtoupper(trim((string) ($_POST['codigo_especialidad'] ?? '')));
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));

        if (strlen($codigo) < 3 || strlen($nombre) < 3) {
            Auth::setFlash('warning', 'El código y el nombre de la especialidad deben tener al menos 3 caracteres.', 'Validación');
            $this->redirect('especialidades');
        }

        try {
            $this->model->crear($codigo, $nombre, $descripcion, (int) Auth::id(), client_ip());
            Auth::setFlash('success', "Especialidad «{$nombre}» ({$codigo}) registrada exitosamente.", 'Especialidad creada');
        } catch (\Throwable $e) {
            Auth::setFlash('error', DatabaseExceptionParser::parse($e), 'No se pudo registrar');
        }

        $this->redirect('especialidades');
    }

    public function actualizar(): void
    {
        $this->validatePostWithCsrf();

        $id = (int) ($_POST['id_especialidad'] ?? 0);
        $codigo = strtoupper(trim((string) ($_POST['codigo_especialidad'] ?? '')));
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));

        if ($id <= 0 || strlen($codigo) < 3 || strlen($nombre) < 3) {
            Auth::setFlash('warning', 'Complete todos los campos obligatorios de la especialidad.', 'Validación');
            $this->redirect('especialidades');
        }

        try {
            $this->model->actualizar($id, $codigo, $nombre, $descripcion, (int) Auth::id(), client_ip());
            Auth::setFlash('success', 'Especialidad médica actualizada correctamente.', 'Cambios guardados');
        } catch (\Throwable $e) {
            Auth::setFlash('error', DatabaseExceptionParser::parse($e), 'Error al editar');
        }

        $this->redirect('especialidades');
    }

    public function conmutarEstado(): void
    {
        $this->validatePostWithCsrf();
        $id = (int) ($_POST['id_especialidad'] ?? 0);
        if ($id > 0) {
            try {
                $nuevo = $this->model->conmutarEstado($id, (int) Auth::id(), client_ip());
                Auth::setFlash('success', "Estado de la especialidad cambiado a {$nuevo}.", 'Baja/Alta Lógica');
            } catch (\Throwable $e) {
                Auth::setFlash('error', DatabaseExceptionParser::parse($e), 'Error');
            }
        }
        $this->redirect('especialidades');
    }
}
