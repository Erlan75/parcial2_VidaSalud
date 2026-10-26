<?php
declare(strict_types=1);
/** @var array|null $currentUser */
/** @var string $csrfToken */
/** @var array $flashes */
/** @var string $content */

$rolActual = $currentUser['rol'] ?? '';
$activeMenu = $activeMenu ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Clínica Vida Salud') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= asset('css/clinical.css') ?>" rel="stylesheet">
</head>
<body>
<div class="vs-backdrop" id="vsBackdrop"></div>

<div class="vs-wrapper">
    <!-- SIDEBAR CLÍNICO -->
    <aside class="vs-sidebar" id="vsSidebar">
        <div class="vs-sidebar-brand">
            <div class="vs-brand-cross"><i class="bi bi-hospital"></i></div>
            <div>
                <div class="fw-bold lh-sm" style="font-size:1.03rem;">CLÍNICA VIDA SALUD</div>
                <div style="font-size:0.74rem;color:var(--vs-sky);opacity:0.9;">Gestión de Citas Médicas</div>
            </div>
        </div>

        <nav class="vs-sidebar-nav">
            <div class="vs-nav-pkg">PANEL Y ANALÍTICA</div>
            <a href="<?= url('dashboard') ?>" class="vs-nav-link <?= $activeMenu === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill"></i>
                <span>Panel Principal</span>
            </a>
            <?php if (in_array($rolActual, ['Administrador', 'Recepcionista'], true)): ?>
                <a href="<?= url('reportes') ?>" class="vs-nav-link <?= $activeMenu === 'reportes' ? 'active' : '' ?>">
                    <i class="bi bi-bar-chart-line-fill"></i>
                    <span>Reportes y Estadísticas</span>
                </a>
            <?php endif; ?>

            <div class="vs-nav-pkg">GESTIÓN DE CITAS</div>
            <?php if (in_array($rolActual, ['Administrador', 'Recepcionista', 'Paciente'], true)): ?>
                <a href="<?= url('citas/programar') ?>" class="vs-nav-link <?= $activeMenu === 'citas_programar' ? 'active' : '' ?>">
                    <i class="bi bi-calendar2-plus-fill"></i>
                    <span>Programar Cita</span>
                </a>
            <?php endif; ?>
            <a href="<?= url('citas') ?>" class="vs-nav-link <?= $activeMenu === 'citas_listado' ? 'active' : '' ?>">
                <i class="bi bi-journal-medical"></i>
                <span><?= $rolActual === 'Paciente' ? 'Mis Citas' : 'Control de Citas' ?></span>
            </a>
            <?php if (in_array($rolActual, ['Administrador', 'Recepcionista', 'Médico'], true)): ?>
                <a href="<?= url('agenda') ?>" class="vs-nav-link <?= $activeMenu === 'agenda' ? 'active' : '' ?>">
                    <i class="bi bi-clock-history"></i>
                    <span>Agenda Médica Diaria</span>
                </a>
            <?php endif; ?>
            <a href="<?= url('historial') ?>" class="vs-nav-link <?= $activeMenu === 'historial' ? 'active' : '' ?>">
                <i class="bi bi-clipboard2-pulse-fill"></i>
                <span><?= $rolActual === 'Paciente' ? 'Mi Historial Clínico' : 'Historial por Paciente' ?></span>
            </a>

            <?php if (in_array($rolActual, ['Administrador', 'Recepcionista', 'Médico'], true)): ?>
                <div class="vs-nav-pkg">PACIENTES</div>
                <a href="<?= url('pacientes') ?>" class="vs-nav-link <?= $activeMenu === 'pacientes' ? 'active' : '' ?>">
                    <i class="bi bi-people-fill"></i>
                    <span>Pacientes y Ficha Médica</span>
                </a>
            <?php endif; ?>

            <?php if (in_array($rolActual, ['Administrador', 'Recepcionista', 'Médico'], true)): ?>
                <div class="vs-nav-pkg">CATÁLOGO MÉDICO</div>
                <?php if (in_array($rolActual, ['Administrador', 'Recepcionista'], true)): ?>
                    <a href="<?= url('especialidades') ?>" class="vs-nav-link <?= $activeMenu === 'especialidades' ? 'active' : '' ?>">
                        <i class="bi bi-award-fill"></i>
                        <span>Especialidades</span>
                    </a>
                    <a href="<?= url('medicos') ?>" class="vs-nav-link <?= $activeMenu === 'medicos' ? 'active' : '' ?>">
                        <i class="bi bi-person-badge-fill"></i>
                        <span>Médicos</span>
                    </a>
                <?php endif; ?>
                <a href="<?= url('horarios') ?>" class="vs-nav-link <?= $activeMenu === 'horarios' ? 'active' : '' ?>">
                    <i class="bi bi-calendar-week-fill"></i>
                    <span><?= $rolActual === 'Médico' ? 'Mis Horarios' : 'Horarios de Atención' ?></span>
                </a>
                <a href="<?= url('bloqueos') ?>" class="vs-nav-link <?= $activeMenu === 'bloqueos' ? 'active' : '' ?>">
                    <i class="bi bi-calendar-x-fill"></i>
                    <span>Feriados y Bloqueos</span>
                </a>
            <?php endif; ?>

            <div class="vs-nav-pkg">SEGURIDAD Y ACCESO</div>
            <?php if ($rolActual === 'Administrador'): ?>
                <a href="<?= url('usuarios') ?>" class="vs-nav-link <?= $activeMenu === 'usuarios' ? 'active' : '' ?>">
                    <i class="bi bi-shield-lock-fill"></i>
                    <span>Usuarios y Roles</span>
                </a>
                <a href="<?= url('auditorias') ?>" class="vs-nav-link <?= $activeMenu === 'auditorias' ? 'active' : '' ?>">
                    <i class="bi bi-eye-fill"></i>
                    <span>Bitácora de Auditoría</span>
                </a>
            <?php endif; ?>
            <a href="<?= url('seguridad/perfil') ?>" class="vs-nav-link <?= $activeMenu === 'perfil' ? 'active' : '' ?>">
                <i class="bi bi-person-gear"></i>
                <span>Mi Perfil / Contraseña</span>
            </a>
        </nav>

        <div class="vs-sidebar-footer">
            <div class="d-flex align-items-center justify-content-between gap-2">
                <div class="text-truncate">
                    <div class="fw-semibold small text-white text-truncate"><?= e($currentUser['nombre_completo'] ?? '') ?></div>
                    <span class="badge bg-light text-dark" style="font-size:0.7rem;"><?= e($rolActual) ?></span>
                </div>
                <button type="button" class="btn btn-sm btn-danger flex-shrink-0" data-bs-toggle="modal" data-bs-target="#modalConfirmLogout" title="Cerrar Sesión">
                    <i class="bi bi-box-arrow-right"></i>
                </button>
            </div>
        </div>
    </aside>

    <!-- CONTENEDOR PRINCIPAL -->
    <div class="vs-main">
        <header class="vs-topbar">
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-secondary d-lg-none" data-vs-toggle="sidebar" aria-label="Abrir menú">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <div>
                    <h1 class="h5 mb-0 fw-bold" style="color:var(--vs-navy);"><?= e($pageTitle ?? 'Clínica Vida Salud') ?></h1>
                    <div class="text-muted d-none d-sm-block" style="font-size:0.77rem;">
                        <i class="bi bi-geo-alt-fill text-primary"></i> Santa Cruz, Bolivia · <?= date('d/m/Y H:i') ?>
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <?php if (!empty($currentUser['codigo_medico'])): ?>
                    <span class="badge rounded-pill text-bg-primary d-none d-md-inline-block"><?= e($currentUser['codigo_medico']) ?></span>
                <?php elseif (!empty($currentUser['codigo_paciente'])): ?>
                    <span class="badge rounded-pill text-bg-info d-none d-md-inline-block"><?= e($currentUser['codigo_paciente']) ?></span>
                <?php endif; ?>
                <a href="<?= url('seguridad/perfil') ?>" class="btn btn-sm btn-vs-secondary d-none d-sm-inline-flex align-items-center gap-1">
                    <i class="bi bi-person-circle"></i> <?= e($currentUser['nombre_usuario'] ?? '') ?>
                </a>
                <button type="button" class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#modalConfirmLogout">
                    <i class="bi bi-power"></i> <span class="d-none d-md-inline">Salir</span>
                </button>
            </div>
        </header>

        <main class="vs-content">
            <?php if (!empty($flashes)): ?>
                <?php foreach ($flashes as $f): ?>
                    <?php
                    $bsClass = match ($f['type'] ?? 'info') {
                        'success' => 'alert-success',
                        'error'   => 'alert-danger',
                        'warning' => 'alert-warning',
                        default   => 'alert-info',
                    };
                    ?>
                    <div class="alert <?= $bsClass ?> alert-dismissible fade show shadow-sm border-0 mb-3" role="alert">
                        <?php if (!empty($f['title'])): ?>
                            <strong><?= e($f['title']) ?>:</strong>
                        <?php endif; ?>
                        <?= e($f['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?= $content ?>
        </main>
    </div>
</div>

<!-- MODAL DE CONFIRMACIÓN PARA CERRAR SESIÓN -->
<div class="modal fade" id="modalConfirmLogout" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white" style="background:var(--vs-navy);">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-shield-exclamation me-1"></i> Cerrar Sesión</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-box-arrow-right text-danger display-5 d-block mb-2"></i>
                <p class="mb-1 fw-semibold">¿Desea finalizar su sesión actual?</p>
                <p class="text-muted small mb-0">Su sesión se cerrará de forma segura en este dispositivo.</p>
            </div>
            <div class="modal-footer justify-content-center border-0 pt-0">
                <button type="button" class="btn btn-light px-3" data-bs-dismiss="modal">Cancelar</button>
                <a href="<?= url('seguridad/logout') ?>" class="btn btn-danger px-4 fw-semibold">Sí, Cerrar Sesión</a>
            </div>
        </div>
    </div>
</div>

<script>
    window.APP_BASE_URL = <?= json_encode(BASE_URL) ?>;
    window.CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
    window.VS_FLASHES = <?= json_encode($flashes, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
