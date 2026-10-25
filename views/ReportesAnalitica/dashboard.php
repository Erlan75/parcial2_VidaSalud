<?php
declare(strict_types=1);
/**
 * Vista: Panel Clínico Principal Adaptativo por Rol (RBAC)
 * Paquete UML: ReportesAnalitica
 *
 * @var array $currentUser
 * @var array $kpis
 * @var array $proximasCitas
 * @var array $chartEstados
 * @var array $chartEspecialidades
 * @var array|null $fichaPaciente
 */

$rol = $currentUser['rol'] ?? 'Invitado';
$nombreUsuario = $currentUser['nombre_completo'] ?? ($currentUser['nombre_usuario'] ?? 'Usuario');
?>

<!-- ENCABEZADO INSTITUCIONAL ADAPTATIVO -->
<div class="vs-card p-4 mb-4 border-0 text-white position-relative overflow-hidden"
     style="background: linear-gradient(125deg, var(--vs-navy) 0%, var(--vs-blue) 100%);">
    <div class="row align-items-center g-3 position-relative" style="z-index: 2;">
        <div class="col-12 col-lg-7">
            <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-2"
                 style="background: rgba(212, 230, 247, 0.18); font-size: 0.8rem;">
                <i class="bi bi-shield-check"></i>
                <span>Rol activo: <strong><?= e($rol) ?></strong> · Arquitectura Smart-DB PostgreSQL</span>
            </div>
            <h1 class="h3 fw-bold mb-1">Bienvenido(a), <?= e($nombreUsuario) ?></h1>
            <p class="mb-0 opacity-75 small">
                Clínica Vida Salud · Sistema Integral de Gestión de Citas Médicas · <?= date('d/m/Y H:i') ?> (America/La_Paz)
            </p>
        </div>
        <div class="col-12 col-lg-5 d-flex flex-wrap gap-2 justify-content-lg-end">
            <?php if (in_array($rol, ['Administrador', 'Recepcionista', 'Paciente'], true)): ?>
                <a href="<?= url('citas/programar') ?>" class="btn btn-light fw-semibold shadow-sm" style="color: var(--vs-navy);">
                    <i class="bi bi-calendar2-plus-fill me-1"></i> Programar Cita
                </a>
            <?php endif; ?>

            <?php if (in_array($rol, ['Administrador', 'Recepcionista', 'Médico'], true)): ?>
                <a href="<?= url('agenda') ?>" class="btn btn-outline-light fw-semibold">
                    <i class="bi bi-clock-history me-1"></i> Agenda Diaria
                </a>
            <?php endif; ?>

            <?php if (in_array($rol, ['Administrador', 'Recepcionista'], true)): ?>
                <a href="<?= url('reportes') ?>" class="btn btn-outline-light fw-semibold">
                    <i class="bi bi-file-earmark-bar-graph me-1"></i> Reportes PDF
                </a>
            <?php elseif ($rol === 'Paciente'): ?>
                <a href="<?= url('historial') ?>" class="btn btn-outline-light fw-semibold">
                    <i class="bi bi-clipboard2-pulse me-1"></i> Mi Historial
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- TARJETAS KPI ADAPTATIVAS SEGÚN ROL -->
<?php if ($rol === 'Paciente'): ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="vs-kpi-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Próximas Citas</div>
                        <div class="fs-2 fw-bold mt-1" style="color: var(--vs-navy);"><?= (int) ($kpis['proximas'] ?? 0) ?></div>
                        <span class="badge bg-primary-subtle text-primary-emphasis">En agenda activa</span>
                    </div>
                    <div class="vs-kpi-icon" style="background: var(--vs-sky); color: var(--vs-navy);">
                        <i class="bi bi-calendar-event-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="vs-kpi-card h-100" style="border-left-color: #198754;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Consultas Atendidas</div>
                        <div class="fs-2 fw-bold mt-1 text-success"><?= (int) ($kpis['atendidas'] ?? 0) ?></div>
                        <span class="badge bg-success-subtle text-success-emphasis">Con evolución médica</span>
                    </div>
                    <div class="vs-kpi-icon bg-success-subtle text-success">
                        <i class="bi bi-check2-circle"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="vs-kpi-card h-100" style="border-left-color: var(--vs-blue);">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Total Histórico</div>
                        <div class="fs-2 fw-bold mt-1" style="color: var(--vs-blue);"><?= (int) ($kpis['total_citas'] ?? 0) ?></div>
                        <span class="text-muted small">Expediente acumulado</span>
                    </div>
                    <div class="vs-kpi-icon" style="background: #eef4fc; color: var(--vs-blue);">
                        <i class="bi bi-journal-medical"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="vs-kpi-card h-100" style="border-left-color: #dc3545;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Canceladas</div>
                        <div class="fs-2 fw-bold mt-1 text-danger"><?= (int) ($kpis['canceladas'] ?? 0) ?></div>
                        <span class="text-muted small">Cupos liberados</span>
                    </div>
                    <div class="vs-kpi-icon bg-danger-subtle text-danger">
                        <i class="bi bi-x-octagon"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($fichaPaciente)): ?>
        <div class="vs-card p-4 mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pb-2 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge rounded-pill text-bg-primary px-3 py-2"><?= e($fichaPaciente['codigo_paciente']) ?></span>
                    <h2 class="h6 fw-bold mb-0" style="color: var(--vs-navy);">Mi Ficha Médica Preventiva Registrada</h2>
                </div>
                <span class="badge bg-danger text-white px-3 py-2">
                    <i class="bi bi-droplet-fill me-1"></i> Grupo Sanguíneo: <?= e($fichaPaciente['tipo_sangre'] ?? 'Desconocido') ?>
                </span>
            </div>
            <div class="row g-3 small">
                <div class="col-12 col-md-4">
                    <div class="p-3 rounded-3 h-100" style="background: #fff5f5; border: 1px solid #f8d7da;">
                        <div class="fw-bold text-danger mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Alergias Conocidas</div>
                        <div><?= e($fichaPaciente['alergias'] ?? 'Ninguna conocida') ?></div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="p-3 rounded-3 h-100" style="background: #f8fafd; border: 1px solid #dee6f2;">
                        <div class="fw-bold mb-1" style="color: var(--vs-navy);"><i class="bi bi-heart-pulse-fill me-1"></i> Enfermedades Crónicas</div>
                        <div><?= e($fichaPaciente['enfermedades_cronicas'] ?? 'Ninguna reportada') ?></div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="p-3 rounded-3 h-100" style="background: #f8fafd; border: 1px solid #dee6f2;">
                        <div class="fw-bold mb-1" style="color: var(--vs-blue);"><i class="bi bi-capsule-pill me-1"></i> Medicación Habitual</div>
                        <div><?= e($fichaPaciente['medicacion_actual'] ?? 'Ninguna') ?></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

<?php elseif ($rol === 'Médico'): ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="vs-kpi-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Turnos de Hoy</div>
                        <div class="fs-2 fw-bold mt-1" style="color: var(--vs-navy);"><?= (int) ($kpis['citas_hoy'] ?? 0) ?></div>
                        <a href="<?= url('agenda') ?>" class="small text-decoration-none fw-semibold">Ver agenda de hoy →</a>
                    </div>
                    <div class="vs-kpi-icon" style="background: var(--vs-sky); color: var(--vs-navy);">
                        <i class="bi bi-calendar-day-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="vs-kpi-card h-100" style="border-left-color: #0d6efd;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">En Agenda Futura</div>
                        <div class="fs-2 fw-bold mt-1 text-primary"><?= (int) ($kpis['pendientes_futuras'] ?? 0) ?></div>
                        <span class="text-muted small">Programadas / Confirmadas</span>
                    </div>
                    <div class="vs-kpi-icon bg-primary-subtle text-primary">
                        <i class="bi bi-clock-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="vs-kpi-card h-100" style="border-left-color: #198754;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Pacientes Atendidos</div>
                        <div class="fs-2 fw-bold mt-1 text-success"><?= (int) ($kpis['total_atendidas'] ?? 0) ?></div>
                        <span class="text-muted small">Consultas cerradas (CU11)</span>
                    </div>
                    <div class="vs-kpi-icon bg-success-subtle text-success">
                        <i class="bi bi-clipboard2-check-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="vs-kpi-card h-100" style="border-left-color: var(--vs-blue);">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Total Asignadas</div>
                        <div class="fs-2 fw-bold mt-1" style="color: var(--vs-blue);"><?= (int) ($kpis['total_citas'] ?? 0) ?></div>
                        <span class="text-muted small">Histórico en consultorio</span>
                    </div>
                    <div class="vs-kpi-icon" style="background: #eef4fc; color: var(--vs-blue);">
                        <i class="bi bi-person-vcard-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- KPIs EJECUTIVOS PARA ADMINISTRADOR Y RECEPCIONISTA -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="vs-kpi-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Pacientes Activos</div>
                        <div class="fs-2 fw-bold mt-1" style="color: var(--vs-navy);"><?= (int) ($kpis['pacientes_activos'] ?? 0) ?></div>
                        <a href="<?= url('pacientes') ?>" class="small text-decoration-none fw-semibold">Padrón clínico (CU07) →</a>
                    </div>
                    <div class="vs-kpi-icon" style="background: var(--vs-sky); color: var(--vs-navy);">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="vs-kpi-card h-100" style="border-left-color: var(--vs-blue);">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Plantel Médico</div>
                        <div class="fs-2 fw-bold mt-1" style="color: var(--vs-blue);"><?= (int) ($kpis['medicos_activos'] ?? 0) ?></div>
                        <span class="text-muted small"><?= (int) ($kpis['especialidades_activas'] ?? 0) ?> especialidades activas</span>
                    </div>
                    <div class="vs-kpi-icon" style="background: #eef4fc; color: var(--vs-blue);">
                        <i class="bi bi-person-badge-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="vs-kpi-card h-100" style="border-left-color: #0d6efd;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Citas en Agenda</div>
                        <div class="fs-2 fw-bold mt-1 text-primary"><?= (int) ($kpis['citas_futuras'] ?? 0) ?></div>
                        <span class="text-muted small">De <?= (int) ($kpis['total_citas'] ?? 0) ?> citas registradas</span>
                    </div>
                    <div class="vs-kpi-icon bg-primary-subtle text-primary">
                        <i class="bi bi-calendar2-week-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="vs-kpi-card h-100" style="border-left-color: #198754;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Consultas Atendidas</div>
                        <div class="fs-2 fw-bold mt-1 text-success"><?= (int) ($kpis['citas_atendidas'] ?? 0) ?></div>
                        <span class="text-muted small">
                            Canc: <?= (int) ($kpis['citas_canceladas'] ?? 0) ?> · Aus: <?= (int) ($kpis['citas_inasistencia'] ?? 0) ?>
                        </span>
                    </div>
                    <div class="vs-kpi-icon bg-success-subtle text-success">
                        <i class="bi bi-heart-pulse-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- GRÁFICOS ANALÍTICOS (ADMINISTRADOR, RECEPCIONISTA Y MÉDICO) -->
<?php if (in_array($rol, ['Administrador', 'Recepcionista', 'Médico'], true)): ?>
    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-5">
            <div class="vs-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="h6 fw-bold mb-0" style="color: var(--vs-navy);">Distribución por Estado Clínico</h2>
                        <small class="text-muted">Semáforo oficial RN10 (Ciclo de vida de citas)</small>
                    </div>
                    <span class="badge rounded-pill text-bg-light border">Tiempo Real</span>
                </div>
                <div style="position: relative; height: 250px;">
                    <canvas id="dashChartEstados"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-7">
            <div class="vs-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="h6 fw-bold mb-0" style="color: var(--vs-navy);">Demanda de Citas por Especialidad Médica</h2>
                        <small class="text-muted">Volumen consolidado por servicio clínico</small>
                    </div>
                    <?php if (in_array($rol, ['Administrador', 'Recepcionista'], true)): ?>
                        <a href="<?= url('reportes', ['tipo' => 'demanda_especialidad']) ?>" class="btn btn-sm btn-outline-primary">
                            Detalle CU14
                        </a>
                    <?php endif; ?>
                </div>
                <div style="position: relative; height: 250px;">
                    <canvas id="dashChartEspecialidades"></canvas>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- TABLA / TARJETAS DE PRÓXIMAS CITAS EN AGENDA -->
<div class="vs-card p-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="h6 fw-bold mb-0" style="color: var(--vs-navy);">
                <i class="bi bi-calendar2-check me-1"></i>
                <?= $rol === 'Paciente' ? 'Mis Próximas Citas Médicas' : 'Próximos Turnos Médicos en Agenda' ?>
            </h2>
            <small class="text-muted">Orden cronológico a partir de la fecha actual (<?= date('d/m/Y') ?>)</small>
        </div>
        <a href="<?= url('citas') ?>" class="btn btn-sm btn-vs-secondary">
            Ver bandeja completa <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>

    <?php if (empty($proximasCitas)): ?>
        <div class="text-center py-5">
            <i class="bi bi-calendar2-x display-5 text-muted d-block mb-2"></i>
            <p class="fw-semibold text-secondary mb-1">No existen turnos programados desde hoy en adelante.</p>
            <?php if (in_array($rol, ['Administrador', 'Recepcionista', 'Paciente'], true)): ?>
                <a href="<?= url('citas/programar') ?>" class="btn btn-sm btn-vs-primary mt-2">
                    <i class="bi bi-plus-circle me-1"></i> Agendar nueva cita médica
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Vista Escritorio -->
        <div class="table-responsive d-none d-lg-block">
            <table class="table vs-table align-middle mb-0">
                <thead>
                <tr>
                    <th>Código</th>
                    <th>Fecha y Bloque</th>
                    <th>Paciente</th>
                    <th>Médico / Especialidad</th>
                    <th>Estado</th>
                    <th class="text-end">Comprobante QR / Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($proximasCitas as $c): ?>
                    <tr>
                        <td class="fw-bold" style="color: var(--vs-navy);"><?= e($c['codigo_cita']) ?></td>
                        <td>
                            <div class="fw-semibold"><?= date('d/m/Y', strtotime((string) $c['fecha_cita'])) ?></div>
                            <small class="text-muted">
                                <i class="bi bi-clock me-1"></i><?= substr((string) $c['hora_inicio'], 0, 5) ?> - <?= substr((string) $c['hora_fin'], 0, 5) ?>
                            </small>
                        </td>
                        <td>
                            <div class="fw-semibold"><?= e($c['paciente_nombre']) ?></div>
                            <small class="text-muted">CI: <?= e($c['paciente_ci']) ?> · Sangre: <?= e($c['tipo_sangre']) ?></small>
                        </td>
                        <td>
                            <div class="fw-semibold">Dr(a). <?= e($c['medico_nombre']) ?></div>
                            <span class="badge bg-light text-dark border"><?= e($c['especialidad_nombre']) ?></span>
                        </td>
                        <td><?= badge_estado_cita((string) $c['estado']) ?></td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="<?= url('exportar/comprobante', ['id_cita' => $c['id_cita']]) ?>"
                                   target="_blank"
                                   class="btn btn-outline-primary"
                                   title="Ticket PDF + Código QR (CU15)">
                                    <i class="bi bi-qr-code"></i> PDF QR
                                </a>
                                <a href="<?= url('historial', ['id_paciente' => $c['id_paciente']]) ?>"
                                   class="btn btn-outline-secondary"
                                   title="Historial Clínico (CU13)">
                                    <i class="bi bi-clipboard2-pulse"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Vista Móvil (Tarjetas apiladas sin desbordamiento) -->
        <div class="d-lg-none d-flex flex-column gap-3">
            <?php foreach ($proximasCitas as $c): ?>
                <div class="vs-mobile-item">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold" style="color: var(--vs-navy);"><?= e($c['codigo_cita']) ?></span>
                        <?= badge_estado_cita((string) $c['estado']) ?>
                    </div>
                    <div class="small mb-1">
                        <i class="bi bi-calendar-event text-primary me-1"></i>
                        <strong><?= date('d/m/Y', strtotime((string) $c['fecha_cita'])) ?></strong> ·
                        <?= substr((string) $c['hora_inicio'], 0, 5) ?> a <?= substr((string) $c['hora_fin'], 0, 5) ?>
                    </div>
                    <div class="small mb-1">
                        <i class="bi bi-person me-1 text-secondary"></i>
                        <strong><?= e($c['paciente_nombre']) ?></strong> (CI: <?= e($c['paciente_ci']) ?>)
                    </div>
                    <div class="small text-muted mb-3">
                        <i class="bi bi-hospital me-1"></i> Dr(a). <?= e($c['medico_nombre']) ?> · <?= e($c['especialidad_nombre']) ?>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?= url('exportar/comprobante', ['id_cita' => $c['id_cita']]) ?>"
                           target="_blank"
                           class="btn btn-sm btn-outline-primary flex-fill">
                            <i class="bi bi-qr-code me-1"></i> Ticket PDF + QR
                        </a>
                        <a href="<?= url('historial', ['id_paciente' => $c['id_paciente']]) ?>"
                           class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-clipboard2-pulse"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (in_array($rol, ['Administrador', 'Recepcionista', 'Médico'], true)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;

    const estadosRaw = <?= json_encode($chartEstados, JSON_UNESCAPED_UNICODE) ?>;
    const espRaw = <?= json_encode($chartEspecialidades, JSON_UNESCAPED_UNICODE) ?>;

    const paletteByEstado = {
        'Programada':   '#0d6efd',
        'Confirmada':   '#ffc107',
        'Atendida':     '#198754',
        'Cancelada':    '#dc3545',
        'Inasistencia': '#6c757d'
    };

    const ctxEst = document.getElementById('dashChartEstados');
    if (ctxEst && estadosRaw.length > 0) {
        new Chart(ctxEst, {
            type: 'doughnut',
            data: {
                labels: estadosRaw.map(r => r.estado),
                datasets: [{
                    data: estadosRaw.map(r => Number(r.total)),
                    backgroundColor: estadosRaw.map(r => paletteByEstado[r.estado] || '#2B579A'),
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 13, font: { size: 12 } } }
                },
                cutout: '62%'
            }
        });
    }

    const ctxEsp = document.getElementById('dashChartEspecialidades');
    if (ctxEsp && espRaw.length > 0) {
        new Chart(ctxEsp, {
            type: 'bar',
            data: {
                labels: espRaw.map(r => r.especialidad),
                datasets: [{
                    label: 'Citas registradas',
                    data: espRaw.map(r => Number(r.total)),
                    backgroundColor: '#2B579A',
                    hoverBackgroundColor: '#002D62',
                    borderRadius: 6,
                    maxBarThickness: 42
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }
});
</script>
<?php endif; ?>
