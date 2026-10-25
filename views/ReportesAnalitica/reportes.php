<?php
declare(strict_types=1);
/**
 * Vista: Módulo Gerencial de Reportes Administrativos y Exportación PDF/QR/CSV (CU14 y CU15)
 * Paquete UML: ReportesAnalitica
 *
 * @var string $tipoReporte
 * @var array $filters
 * @var array $reportData
 * @var array $kpis
 * @var array $chartEstados
 * @var array $chartEspecialidades
 * @var array $medicos
 * @var array $especialidades
 */

$catalogoReportes = [
    'citas_fecha' => [
        'num'   => '01',
        'icon'  => 'bi-calendar-range-fill',
        'short' => 'Citas por Fecha',
        'desc'  => 'Cronograma detallado por rango',
    ],
    'citas_medico' => [
        'num'   => '02',
        'icon'  => 'bi-person-vcard-fill',
        'short' => 'Productividad Médica',
        'desc'  => 'Rendimiento por profesional',
    ],
    'demanda_especialidad' => [
        'num'   => '03',
        'icon'  => 'bi-hospital-fill',
        'short' => 'Demanda Especialidad',
        'desc'  => 'Participación por servicio',
    ],
    'estados_ausentismo' => [
        'num'   => '04',
        'icon'  => 'bi-pie-chart-fill',
        'short' => 'Estados y Ausentismo',
        'desc'  => 'Atendidas vs Canceladas vs Faltas',
    ],
    'padron_pacientes' => [
        'num'   => '05',
        'icon'  => 'bi-people-fill',
        'short' => 'Padrón de Pacientes',
        'desc'  => 'Expedientes y grupo sanguíneo',
    ],
    'agenda_imprimible' => [
        'num'   => '06',
        'icon'  => 'bi-printer-fill',
        'short' => 'Agenda Imprimible',
        'desc'  => 'Hoja diaria por consultorio',
    ],
];

$totalGlobal = max(1, (int) ($kpis['total_citas'] ?? 0));
$tasaEfectividad = round(((int) ($kpis['citas_atendidas'] ?? 0) / $totalGlobal) * 100, 1);
$tasaAusentismo = round(((int) ($kpis['citas_inasistencia'] ?? 0) / $totalGlobal) * 100, 1);
$tasaCancelacion = round(((int) ($kpis['citas_canceladas'] ?? 0) / $totalGlobal) * 100, 1);

$exportParams = array_merge(['tipo' => $tipoReporte], array_filter($filters, static fn($v) => $v !== ''));
$estadosCita = ['Programada', 'Confirmada', 'Atendida', 'Cancelada', 'Inasistencia'];
?>

<!-- CABECERA GERENCIAL Y ACCIONES DE EXPORTACIÓN (CU14 / CU15) -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <span class="badge rounded-pill px-3 py-1 mb-1" style="background: var(--vs-sky); color: var(--vs-navy);">
            Paquete 5 · ReportesAnalitica · CU14 &amp; CU15
        </span>
        <h1 class="h4 fw-bold mb-0" style="color: var(--vs-navy);">
            Centro Ejecutivo de Reportes, Analítica y Exportación PDF / QR
        </h1>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= url('exportar/reporte-pdf', $exportParams) ?>"
           target="_blank"
           class="btn btn-danger fw-semibold shadow-sm">
            <i class="bi bi-file-earmark-pdf-fill me-1"></i> Exportar PDF Membretado
        </a>
        <a href="<?= url('exportar/reporte-csv', $exportParams) ?>"
           class="btn btn-success fw-semibold shadow-sm">
            <i class="bi bi-file-earmark-spreadsheet-fill me-1"></i> Exportar Excel (CSV)
        </a>
        <button type="button" onclick="window.print()" class="btn btn-outline-secondary fw-semibold">
            <i class="bi bi-printer me-1"></i> Imprimir
        </button>
    </div>
</div>

<!-- INDICADORES GERENCIALES KPI -->
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="vs-kpi-card h-100">
            <div class="text-muted small fw-semibold text-uppercase">Volumen Consolidado</div>
            <div class="fs-3 fw-bold mt-1" style="color: var(--vs-navy);"><?= (int) ($kpis['total_citas'] ?? 0) ?> citas</div>
            <div class="small text-muted">
                <?= (int) ($kpis['pacientes_activos'] ?? 0) ?> pacientes · <?= (int) ($kpis['medicos_activos'] ?? 0) ?> médicos activos
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="vs-kpi-card h-100" style="border-left-color: #198754;">
            <div class="text-muted small fw-semibold text-uppercase">Efectividad de Atención</div>
            <div class="fs-3 fw-bold mt-1 text-success"><?= $tasaEfectividad ?>%</div>
            <div class="small text-muted"><?= (int) ($kpis['citas_atendidas'] ?? 0) ?> consultas cerradas con evolución</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="vs-kpi-card h-100" style="border-left-color: #6c757d;">
            <div class="text-muted small fw-semibold text-uppercase">Tasa de Ausentismo</div>
            <div class="fs-3 fw-bold mt-1 text-secondary"><?= $tasaAusentismo ?>%</div>
            <div class="small text-muted"><?= (int) ($kpis['citas_inasistencia'] ?? 0) ?> inasistencias registradas</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="vs-kpi-card h-100" style="border-left-color: #dc3545;">
            <div class="text-muted small fw-semibold text-uppercase">Tasa de Cancelación</div>
            <div class="fs-3 fw-bold mt-1 text-danger"><?= $tasaCancelacion ?>%</div>
            <div class="small text-muted"><?= (int) ($kpis['citas_canceladas'] ?? 0) ?> cancelaciones auditadas</div>
        </div>
    </div>
</div>

<!-- SELECTOR DE LOS 6 REPORTES EJECUTIVOS OFICIALES -->
<div class="row g-2 mb-4">
    <?php foreach ($catalogoReportes as $key => $meta): ?>
        <?php $active = ($tipoReporte === $key); ?>
        <div class="col-6 col-md-4 col-xl-2">
            <a href="<?= url('reportes', array_merge($filters, ['tipo' => $key])) ?>"
               class="text-decoration-none d-block p-3 rounded-3 h-100 border transition"
               style="<?= $active
                   ? 'background: var(--vs-navy); color: #fff; border-color: var(--vs-navy) !important; box-shadow: 0 6px 16px rgba(0,45,98,0.2);'
                   : 'background: #fff; color: var(--vs-navy); border-color: #dbe4f0;' ?>">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <i class="bi <?= $meta['icon'] ?> fs-5 <?= $active ? 'text-info' : 'text-primary' ?>"></i>
                    <span class="badge <?= $active ? 'bg-light text-dark' : 'bg-secondary-subtle text-secondary-emphasis' ?>">
                        #<?= $meta['num'] ?>
                    </span>
                </div>
                <div class="fw-bold small lh-sm mb-1"><?= e($meta['short']) ?></div>
                <div style="font-size: 0.73rem; opacity: <?= $active ? '0.85' : '0.68' ?>;"><?= e($meta['desc']) ?></div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<!-- BARRA DE FILTROS PARAMÉTRICOS + GENERADOR RÁPIDO DE COMPROBANTE PDF CON QR (CU15) -->
<div class="row g-4 mb-4">
    <div class="col-12 col-xl-8">
        <div class="vs-card p-3 p-md-4 h-100">
            <h2 class="h6 fw-bold mb-3" style="color: var(--vs-navy);">
                <i class="bi bi-funnel-fill me-1 text-primary"></i> Parámetros de Filtrado Dinámico (Consultas PDO Seguras)
            </h2>
            <form method="GET" action="<?= e(BASE_URL . '/index.php') ?>" class="row g-3 align-items-end">
                <input type="hidden" name="r" value="reportes">
                <input type="hidden" name="tipo" value="<?= e($tipoReporte) ?>">

                <?php if ($tipoReporte === 'padron_pacientes'): ?>
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold mb-1">Estado del Expediente de Paciente</label>
                        <select name="estado_paciente" class="form-select form-select-sm">
                            <option value="">Todos los pacientes (Activos e Inactivos)</option>
                            <option value="Activo" <?= ($filters['estado_paciente'] ?? '') === 'Activo' ? 'selected' : '' ?>>Solo Activos</option>
                            <option value="Inactivo" <?= ($filters['estado_paciente'] ?? '') === 'Inactivo' ? 'selected' : '' ?>>Solo Inactivos</option>
                        </select>
                    </div>
                <?php elseif ($tipoReporte === 'agenda_imprimible'): ?>
                    <div class="col-12 col-sm-6 col-md-4">
                        <label class="form-label small fw-semibold mb-1">Fecha de Agenda Imprimible</label>
                        <input type="date" name="fecha_desde" class="form-control form-control-sm"
                               value="<?= e($filters['fecha_desde'] ?: '2026-10-26') ?>">
                    </div>
                    <div class="col-12 col-sm-6 col-md-5">
                        <label class="form-label small fw-semibold mb-1">Filtrar por Médico</label>
                        <select name="id_medico" class="form-select form-select-sm">
                            <option value="">Todos los consultorios</option>
                            <?php foreach ($medicos as $m): ?>
                                <option value="<?= (int) $m['id_medico'] ?>"
                                    <?= (string) ($filters['id_medico'] ?? '') === (string) $m['id_medico'] ? 'selected' : '' ?>>
                                    Dr(a). <?= e($m['nombres'] . ' ' . $m['apellidos']) ?> (<?= e($m['codigo_medico']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <div class="col-6 col-md-3">
                        <label class="form-label small fw-semibold mb-1">Fecha Desde</label>
                        <input type="date" name="fecha_desde" class="form-control form-control-sm"
                               value="<?= e($filters['fecha_desde'] ?? '') ?>">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small fw-semibold mb-1">Fecha Hasta</label>
                        <input type="date" name="fecha_hasta" class="form-control form-control-sm"
                               value="<?= e($filters['fecha_hasta'] ?? '') ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold mb-1">Médico Tratante</label>
                        <select name="id_medico" class="form-select form-select-sm">
                            <option value="">Todos los médicos</option>
                            <?php foreach ($medicos as $m): ?>
                                <option value="<?= (int) $m['id_medico'] ?>"
                                    <?= (string) ($filters['id_medico'] ?? '') === (string) $m['id_medico'] ? 'selected' : '' ?>>
                                    Dr(a). <?= e($m['nombres'] . ' ' . $m['apellidos']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-md-5">
                        <label class="form-label small fw-semibold mb-1">Especialidad Médica</label>
                        <select name="id_especialidad" class="form-select form-select-sm">
                            <option value="">Todas las especialidades</option>
                            <?php foreach ($especialidades as $esp): ?>
                                <option value="<?= (int) $esp['id_especialidad'] ?>"
                                    <?= (string) ($filters['id_especialidad'] ?? '') === (string) $esp['id_especialidad'] ? 'selected' : '' ?>>
                                    <?= e($esp['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-md-4">
                        <label class="form-label small fw-semibold mb-1">Estado de Cita</label>
                        <select name="estado" class="form-select form-select-sm">
                            <option value="">Todos los estados</option>
                            <?php foreach ($estadosCita as $est): ?>
                                <option value="<?= $est ?>" <?= ($filters['estado'] ?? '') === $est ? 'selected' : '' ?>>
                                    <?= $est ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-vs-primary flex-fill">
                        <i class="bi bi-search me-1"></i> Aplicar
                    </button>
                    <a href="<?= url('reportes', ['tipo' => $tipoReporte]) ?>" class="btn btn-sm btn-outline-secondary" title="Limpiar filtros">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- EMISOR RÁPIDO DE COMPROBANTE PDF CON CÓDIGO QR AUTOGENERADO (CU15) -->
    <div class="col-12 col-xl-4">
        <div class="vs-card p-3 p-md-4 h-100" style="background: linear-gradient(145deg, #ffffff 0%, #eef5fc 100%);">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-qr-code-scan fs-4 text-primary"></i>
                <h2 class="h6 fw-bold mb-0" style="color: var(--vs-navy);">Ticket Oficial PDF + Código QR (CU15)</h2>
            </div>
            <p class="small text-muted mb-3">
                Genere al instante el comprobante clínico membretado con <strong>Código QR vectorial ISO 18004</strong> verificable ingresando el código de cita (ej. <code>CM-00051</code>).
            </p>
            <form method="GET" action="<?= e(BASE_URL . '/index.php') ?>" target="_blank" class="d-flex gap-2">
                <input type="hidden" name="r" value="exportar/comprobante">
                <input type="text"
                       name="codigo"
                       class="form-control form-control-sm fw-semibold text-uppercase"
                       placeholder="Ej: CM-00051"
                       value="CM-00051"
                       required>
                <button type="submit" class="btn btn-sm btn-vs-primary text-nowrap px-3">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Generar Ticket QR
                </button>
            </form>
            <div class="mt-2 small">
                <a href="<?= url('citas/verificar', ['codigo' => 'CM-00051']) ?>" target="_blank" class="text-decoration-none">
                    <i class="bi bi-patch-check-fill text-success me-1"></i> Probar validador público de QR (CM-00051) →
                </a>
            </div>
        </div>
    </div>
</div>

<!-- GRÁFICOS ESTADÍSTICOS SINCRONIZADOS CON FILTROS (CHART.JS) -->
<div class="row g-4 mb-4">
    <div class="col-12 col-lg-5">
        <div class="vs-card p-4 h-100">
            <h3 class="h6 fw-bold mb-1" style="color: var(--vs-navy);">Distribución por Estado y Ausentismo</h3>
            <p class="text-muted small mb-3">Proporción visual sincronizada con los filtros activos</p>
            <div style="position: relative; height: 240px;">
                <canvas id="repChartEstados"></canvas>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-7">
        <div class="vs-card p-4 h-100">
            <h3 class="h6 fw-bold mb-1" style="color: var(--vs-navy);">Curva de Demanda por Especialidad Médica</h3>
            <p class="text-muted small mb-3">Comparativa de turnos solicitados por servicio clínico</p>
            <div style="position: relative; height: 240px;">
                <canvas id="repChartEspecialidades"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- TABLA DINÁMICA DEL REPORTE SELECCIONADO -->
<div class="vs-card p-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pb-2 border-bottom">
        <div>
            <h2 class="h6 fw-bold mb-0" style="color: var(--vs-navy);">
                <i class="bi bi-table me-1 text-primary"></i> <?= e($reportData['title'] ?? 'Reporte') ?>
            </h2>
            <small class="text-muted">Orientación sugerida de exportación PDF: <?= ($reportData['orientation'] ?? 'L') === 'L' ? 'Horizontal (Landscape)' : 'Vertical (Portrait)' ?></small>
        </div>
        <span class="badge rounded-pill px-3 py-2" style="background: var(--vs-navy);">
            <?= count($reportData['rows'] ?? []) ?> registros obtenidos
        </span>
    </div>

    <?php if (empty($reportData['rows'])): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-inbox display-5 d-block mb-2"></i>
            <p class="mb-0 fw-semibold">No se encontraron registros que coincidan con los criterios seleccionados.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table vs-table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <?php foreach ($reportData['columns'] as $col): ?>
                        <th><?= e($col['label']) ?></th>
                    <?php endforeach; ?>
                    <?php if ($tipoReporte === 'citas_fecha' || $tipoReporte === 'agenda_imprimible'): ?>
                        <th class="text-end">Ticket QR</th>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($reportData['rows'] as $row): ?>
                    <tr>
                        <?php foreach ($reportData['columns'] as $col): ?>
                            <?php
                            $k = $col['key'];
                            $val = (string) ($row[$k] ?? '');
                            ?>
                            <td>
                                <?php if ($k === 'estado' && in_array($val, $estadosCita, true)): ?>
                                    <?= badge_estado_cita($val) ?>
                                <?php elseif ($k === 'estado' && in_array($val, ['Activo', 'Inactivo'], true)): ?>
                                    <span class="badge <?= $val === 'Activo' ? 'bg-success' : 'bg-secondary' ?>"><?= e($val) ?></span>
                                <?php elseif (in_array($k, ['codigo_cita', 'codigo_medico', 'codigo_paciente', 'codigo_especialidad'], true)): ?>
                                    <span class="fw-bold" style="color: var(--vs-navy);"><?= e($val) ?></span>
                                <?php else: ?>
                                    <?= e($val) ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>

                        <?php if ($tipoReporte === 'citas_fecha' || $tipoReporte === 'agenda_imprimible'): ?>
                            <td class="text-end">
                                <a href="<?= url('exportar/comprobante', ['codigo' => $row['codigo_cita']]) ?>"
                                   target="_blank"
                                   class="btn btn-sm btn-outline-primary"
                                   title="Descargar Comprobante PDF con QR">
                                    <i class="bi bi-qr-code"></i>
                                </a>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;

    const estadosData = <?= json_encode($chartEstados, JSON_UNESCAPED_UNICODE) ?>;
    const espData = <?= json_encode($chartEspecialidades, JSON_UNESCAPED_UNICODE) ?>;

    const coloresEstado = {
        'Programada':   '#0d6efd',
        'Confirmada':   '#ffc107',
        'Atendida':     '#198754',
        'Cancelada':    '#dc3545',
        'Inasistencia': '#6c757d'
    };

    const c1 = document.getElementById('repChartEstados');
    if (c1 && estadosData.length > 0) {
        new Chart(c1, {
            type: 'doughnut',
            data: {
                labels: estadosData.map(x => x.estado),
                datasets: [{
                    data: estadosData.map(x => Number(x.total)),
                    backgroundColor: estadosData.map(x => coloresEstado[x.estado] || '#2B579A'),
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 12 } }
                },
                cutout: '58%'
            }
        });
    }

    const c2 = document.getElementById('repChartEspecialidades');
    if (c2 && espData.length > 0) {
        new Chart(c2, {
            type: 'bar',
            data: {
                labels: espData.map(x => x.especialidad),
                datasets: [{
                    label: 'Total de Citas',
                    data: espData.map(x => Number(x.total)),
                    backgroundColor: ['#002D62', '#2B579A', '#198754', '#0d6efd', '#5a8dee', '#6c757d'],
                    borderRadius: 6,
                    maxBarThickness: 44
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
