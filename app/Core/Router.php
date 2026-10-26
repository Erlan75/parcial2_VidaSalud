<?php
declare(strict_types=1);

namespace App\Core;

use App\Modules\CatalogoMedico\Controllers\BloqueoController;
use App\Modules\CatalogoMedico\Controllers\EspecialidadController;
use App\Modules\CatalogoMedico\Controllers\HorarioController;
use App\Modules\CatalogoMedico\Controllers\MedicoController;
use App\Modules\GestionCitas\Controllers\AgendaController;
use App\Modules\GestionCitas\Controllers\CitaController;
use App\Modules\GestionCitas\Controllers\HistorialController;
use App\Modules\PacientesClinica\Controllers\PacienteController;
use App\Modules\ReportesAnalitica\Controllers\DashboardController;
use App\Modules\ReportesAnalitica\Controllers\ExportacionController;
use App\Modules\ReportesAnalitica\Controllers\ReporteController;
use App\Modules\SeguridadAcceso\Controllers\AuditoriaController;
use App\Modules\SeguridadAcceso\Controllers\AuthController;
use App\Modules\SeguridadAcceso\Controllers\SistemaController;
use App\Modules\SeguridadAcceso\Controllers\UsuarioController;
use Database;

/**
 * Enrutador Central MVC orientado a los 5 Paquetes de Dominio UML con Middleware RBAC
 */
final class Router
{
    public static function dispatch(): void
    {
        Auth::startSession();
        Auth::sendNoCacheHeaders();

        $route = self::resolveCurrentRoute();
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Rutas públicas (accesibles sin sesión activa)
        $publicRoutes = [
            'seguridad/login',
            'seguridad/forgot',
            'seguridad/reset',
            'registro-paciente',
            'seguridad/registro-paciente',
            'citas/verificar',
            'sistema/migracion',
            'sistema/guardar-config',
            'sistema/ejecutar-migracion',
        ];

        // Si no hay ruta explícita, enviar según sesión
        if ($route === '') {
            $route = Auth::check() ? Auth::homeRouteForRole(Auth::role()) : 'seguridad/login';
        }

        // PRUEBA #9 DEL EXAMEN: Bloqueo estricto de cualquier ruta interna sin sesión iniciada
        if (!in_array($route, $publicRoutes, true) && !Auth::check()) {
            $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
                || str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

            if ($isAjax) {
                http_response_code(401);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => false, 'message' => 'Sesión no autenticada o expirada. Inicie sesión nuevamente.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            Auth::setFlash('warning', 'Acceso restringido: Debe iniciar sesión para ingresar a los módulos internos del sistema.', 'Ruta Protegida');
            header('Location: ' . url('seguridad/login'));
            exit;
        }

        // Verificar salud de BD si intenta usar módulos internos y la BD aún no fue migrada
        if (!str_starts_with($route, 'sistema/') && !in_array($route, ['seguridad/login', 'seguridad/logout', 'seguridad/forgot', 'seguridad/reset', 'registro-paciente', 'seguridad/registro-paciente'], true)) {
            $health = Database::checkHealth();
            if (!$health['migrated']) {
                Auth::setFlash('warning', 'La base de datos PostgreSQL aún no está inicializada. Ejecute la configuración inicial en 1 clic.', 'Configuración Inicial');
                header('Location: ' . url('sistema/migracion'));
                exit;
            }
        }

        // Mapa declarativo de rutas -> [Controlador, Método, Roles Permitidos (vacío = público o todos autenticados)]
        $routes = [
            // Seguridad y Acceso
            'seguridad/login'             => [AuthController::class, $method === 'POST' ? 'loginPost' : 'loginForm', []],
            'registro-paciente'           => [AuthController::class, $method === 'POST' ? 'registroPacientePost' : 'registroPacienteForm', []],
            'seguridad/registro-paciente' => [AuthController::class, $method === 'POST' ? 'registroPacientePost' : 'registroPacienteForm', []],
            'seguridad/logout'            => [AuthController::class, 'logout', []],
            'seguridad/forgot'           => [AuthController::class, $method === 'POST' ? 'forgotPost' : 'forgotForm', []],
            'seguridad/reset'            => [AuthController::class, $method === 'POST' ? 'resetPost' : 'resetForm', []],
            'seguridad/perfil'           => [AuthController::class, 'perfil', []],
            'seguridad/cambiar-password' => [AuthController::class, 'cambiarPasswordPost', []],

            'usuarios'                   => [UsuarioController::class, 'index', ['Administrador']],
            'usuarios/guardar'           => [UsuarioController::class, 'guardar', ['Administrador']],
            'usuarios/actualizar'        => [UsuarioController::class, 'actualizar', ['Administrador']],
            'usuarios/conmutar-estado'   => [UsuarioController::class, 'conmutarEstado', ['Administrador']],

            'auditorias'                 => [AuditoriaController::class, 'index', ['Administrador']],

            'sistema/migracion'          => [SistemaController::class, 'migracion', []],
            'sistema/guardar-config'     => [SistemaController::class, 'guardarConfiguracion', []],
            'sistema/ejecutar-migracion' => [SistemaController::class, 'ejecutarMigracion', []],
            'sistema/pruebas'            => [SistemaController::class, 'pruebas', ['Administrador', 'Recepcionista']],
            'sistema/ejecutar-prueba'    => [SistemaController::class, 'ejecutarPruebaMotor', ['Administrador', 'Recepcionista']],

            // PAQUETE 2: CatalogoMedico
            'especialidades'                 => [EspecialidadController::class, 'index', ['Administrador', 'Recepcionista']],
            'especialidades/guardar'         => [EspecialidadController::class, 'guardar', ['Administrador']],
            'especialidades/actualizar'      => [EspecialidadController::class, 'actualizar', ['Administrador']],
            'especialidades/conmutar-estado' => [EspecialidadController::class, 'conmutarEstado', ['Administrador']],

            'medicos'                        => [MedicoController::class, 'index', ['Administrador', 'Recepcionista']],
            'medicos/guardar'                => [MedicoController::class, 'guardar', ['Administrador']],
            'medicos/actualizar'             => [MedicoController::class, 'actualizar', ['Administrador']],
            'medicos/conmutar-estado'        => [MedicoController::class, 'conmutarEstado', ['Administrador']],

            'horarios'                       => [HorarioController::class, 'index', ['Administrador', 'Recepcionista', 'Médico']],
            'horarios/guardar'               => [HorarioController::class, 'guardar', ['Administrador']],
            'horarios/conmutar-estado'       => [HorarioController::class, 'conmutarEstado', ['Administrador']],

            'bloqueos'                       => [BloqueoController::class, 'index', ['Administrador', 'Recepcionista', 'Médico']],
            'bloqueos/guardar'               => [BloqueoController::class, 'guardar', ['Administrador']],
            'bloqueos/eliminar'              => [BloqueoController::class, 'eliminar', ['Administrador']],

            // PAQUETE 3: PacientesClinica
            'pacientes'                  => [PacienteController::class, 'index', ['Administrador', 'Recepcionista', 'Médico']],
            'pacientes/buscar-ajax'      => [PacienteController::class, 'buscarAjax', ['Administrador', 'Recepcionista', 'Médico']],
            'pacientes/guardar'          => [PacienteController::class, 'guardar', ['Administrador', 'Recepcionista']],
            'pacientes/actualizar-ficha' => [PacienteController::class, 'actualizarFicha', ['Administrador', 'Recepcionista', 'Médico']],
            'pacientes/actualizar-datos' => [PacienteController::class, 'actualizarDatos', ['Administrador', 'Recepcionista']],
            'pacientes/conmutar-estado'  => [PacienteController::class, 'conmutarEstado', ['Administrador', 'Recepcionista']],

            // PAQUETE 4: GestionCitas
            'citas'                 => [CitaController::class, 'index', ['Administrador', 'Recepcionista', 'Médico', 'Paciente']],
            'citas/programar'       => [CitaController::class, 'programarForm', ['Administrador', 'Recepcionista', 'Paciente']],
            'citas/medicos-ajax'    => [CitaController::class, 'medicosPorEspecialidadAjax', ['Administrador', 'Recepcionista', 'Médico', 'Paciente']],
            'citas/intervalos-ajax' => [CitaController::class, 'intervalosDisponiblesAjax', ['Administrador', 'Recepcionista', 'Médico', 'Paciente']],
            'citas/guardar'         => [CitaController::class, 'guardar', ['Administrador', 'Recepcionista', 'Paciente']],
            'citas/reprogramar'     => [CitaController::class, 'reprogramar', ['Administrador', 'Recepcionista', 'Paciente']],
            'citas/cancelar'        => [CitaController::class, 'cancelar', ['Administrador', 'Recepcionista', 'Paciente']],
            'citas/asistencia'      => [CitaController::class, 'asistencia', ['Administrador', 'Recepcionista']],
            'citas/atender'         => [CitaController::class, 'atender', ['Médico', 'Administrador']],
            'citas/verificar'       => [CitaController::class, 'verificarQr', []],

            'agenda'                => [AgendaController::class, 'index', ['Administrador', 'Recepcionista', 'Médico']],
            'historial'             => [HistorialController::class, 'index', ['Administrador', 'Recepcionista', 'Médico', 'Paciente']],

            // PAQUETE 5: ReportesAnalitica
            'dashboard'             => [DashboardController::class, 'index', ['Administrador', 'Recepcionista', 'Médico', 'Paciente']],
            'reportes'              => [ReporteController::class, 'index', ['Administrador', 'Recepcionista']],
            'exportar/comprobante'  => [ExportacionController::class, 'comprobantePdf', ['Administrador', 'Recepcionista', 'Médico', 'Paciente']],
            'exportar/reporte-pdf'  => [ExportacionController::class, 'reportePdf', ['Administrador', 'Recepcionista']],
            'exportar/reporte-csv'  => [ExportacionController::class, 'reporteCsv', ['Administrador', 'Recepcionista']],
        ];

        if (!isset($routes[$route])) {
            Auth::setFlash('warning', "La ruta solicitada ({$route}) no existe en el sistema.", 'Página no encontrada');
            header('Location: ' . url(Auth::check() ? 'dashboard' : 'seguridad/login'));
            exit;
        }

        [$controllerClass, $action, $allowedRoles] = $routes[$route];

        // Control RBAC por Rol si la ruta define roles específicos
        if (!empty($allowedRoles) && !Auth::hasRole($allowedRoles)) {
            Auth::setFlash('error', 'Su rol actual (' . Auth::role() . ') no cuenta con privilegios para acceder a ese módulo.', 'Control de Acceso RBAC');
            header('Location: ' . url(Auth::homeRouteForRole(Auth::role())));
            exit;
        }

        $controller = new $controllerClass();
        $controller->$action();
    }

    private static function resolveCurrentRoute(): string
    {
        if (!empty($_GET['r'])) {
            return trim((string) $_GET['r'], '/');
        }

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($scriptDir !== '/' && $scriptDir !== '.' && str_starts_with($uri, $scriptDir)) {
            $uri = substr($uri, strlen($scriptDir));
        }
        $uri = trim($uri, '/');
        if ($uri === '' || $uri === 'index.php' || $uri === 'public' || $uri === 'public/index.php') {
            return '';
        }
        return $uri;
    }
}
