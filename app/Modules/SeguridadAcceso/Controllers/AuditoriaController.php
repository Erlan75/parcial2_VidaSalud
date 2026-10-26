<?php
declare(strict_types=1);

namespace App\Modules\SeguridadAcceso\Controllers;

use App\Core\Controller;
use App\Modules\SeguridadAcceso\Models\AuditoriaModel;
use App\Modules\SeguridadAcceso\Models\UsuarioModel;

/**
 * CU17: Consultar Bitácora Inmutable de Auditoría del Sistema
 */
final class AuditoriaController extends Controller
{
    public function index(): void
    {
        $auditoriaModel = new AuditoriaModel();
        $usuarioModel = new UsuarioModel();

        $filters = [
            'fecha_desde' => $_GET['fecha_desde'] ?? '',
            'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
            'id_usuario'  => $_GET['id_usuario'] ?? '',
            'modulo'      => $_GET['modulo'] ?? '',
            'operacion'   => $_GET['operacion'] ?? '',
            'q'           => $_GET['q'] ?? '',
        ];

        $this->render('SeguridadAcceso/auditorias', [
            'pageTitle' => 'Bitácora de Auditoría',
            'activeMenu'=> 'auditorias',
            'logs'      => $auditoriaModel->listLogs($filters, 300),
            'modulos'   => $auditoriaModel->getModulosDisponibles(),
            'usuarios'  => $usuarioModel->listAll(),
            'stats'     => $auditoriaModel->getStats(),
            'filters'   => $filters,
        ]);
    }
}
