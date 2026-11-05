<?php
declare(strict_types=1);
/**
 * Vista: Centro de Reportes y Estadísticas Clínicas (CU14 y CU15)
 * Paquete UML: ReportesAnalitica
 *
 * @var string $tipoReporte
 * @var array $filters
 * @var string $filtersDesc
 * @var array $reportData
 * @var array $kpis
 * @var array $chartEstados
 * @var array $chartEspecialidades
 * @var array $medicos
 * @var array $especialidades
 * @var array|null $currentUser
 */

use App\Core\ExcelGenerator;

$catalogoReportes = [
    'citas_medicas' => [
        'num'   => '01',
        'icon'  => 'bi-calendar-range-fill',
        'short' => 'Citas Médicas (General y por Filtros)',
        'desc'  => 'Cronograma detallado por rango y estado',
    ],
    'agenda_diaria' => [
        'num'   => '02',
        'icon'  => 'bi-printer-fill',
        'short' => 'Agenda Médica Diaria',
        'desc'  => 'Hoja diaria de atención por consultorio',
    ],
    'resumen_asistencia' => [
        'num'   => '03',
        'icon'  => 'bi-pie-chart-fill',
        'short' => 'Resumen de Asistencia, Cancelaciones e Inasistencias',
        'desc'  => 'Efectividad clínica y tasa de ausentismo',
    ],
    'padron_pacientes' => [
        'num'   => '04',
        'icon'  => 'bi-people-fill',
        'short' => 'Padrón Completo de Pacientes y Fichas Médicas',
        'desc'  => 'Expedientes, alergias y grupo sanguíneo',
    ],
    'medicos_especialidades' => [
        'num'   => '05',
        'icon'  => 'bi-person-vcard-fill',
        'short' => 'Directorio de Médicos y Especialidades',
        'desc'  => 'Plantel médico, matrículas y productividad',
    ],
    'horarios_vigentes' => [
        'num'   => '06',
        'icon'  => 'bi-clock-history',
        'short' => 'Horarios de Atención Médica Vigentes',
        'desc'  => 'Turnos semanales de lunes a sábado',
    ],
    'usuarios_roles' => [
        'num'   => '07',
        'icon'  => 'bi-shield-lock-fill',
        'short' => 'Usuarios y Roles del Sistema',
        'desc'  => 'Cuentas de acceso y perfiles RBAC',
    ],
];

// Normalizar alias heredados
if ($tipoReporte === 'citas_fecha') {
    $tipoReporte = 'citas_medicas';
} elseif ($tipoReporte === 'agenda_imprimible') {
    $tipoReporte = 'agenda_diaria';
} elseif (in_array($tipoReporte, ['estados_ausentismo', 'demanda_especialidad'], true)) {
    $tipoReporte = 'resumen_asistencia';
} elseif ($tipoReporte === 'citas_medico') {
    $tipoReporte = 'medicos_especialidades';
}

$totalGlobal = max(1, (int) ($kpis['total_citas'] ?? 0));
$tasaEfectividad = round(((int) ($kpis['citas_atendidas'] ?? 0) / $totalGlobal) * 100, 1);
$tasaAusentismo = round(((int) ($kpis['citas_inasistencia'] ?? 0) / $totalGlobal) * 100, 1);
$tasaCancelacion = round(((int) ($kpis['citas_canceladas'] ?? 0) / $totalGlobal) * 100, 1);

$exportParams = array_merge(['tipo' => $tipoReporte], array_filter($filters, static fn($v) => $v !== ''));
$estadosCita = ['Programada', 'Confirmada', 'Atendida', 'Cancelada', 'Inasistencia'];

$fileBase = (string) ($reportData['file_base'] ?? $tipoReporte);
$fechaCompacta = date('Ymd');
$pdfFilename = "Reporte_{$fileBase}_{$fechaCompacta}.pdf";
$xlsxFilename = "Reporte_{$fileBase}_{$fechaCompacta}.xlsx";
$totalsRow = ExcelGenerator::computeTotalsRow($reportData['columns'] ?? [], $reportData['rows'] ?? []);
?>

<!-- CABECERA GERENCIAL Y ACCIONES DE EXPORTACIÓN -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <span class="badge rounded-pill px-3 py-1 mb-1" style="background: var(--vs-sky); color: var(--vs-navy);">
            Gestión Clínica · Estadísticas e Informes Hospitalarios
        </span>
        <h1 class="h4 fw-bold mb-0" style="color: var(--vs-navy);">
            Centro de Reportes y Estadísticas Clínicas
        </h1>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= url('exportar/reporte-pdf', array_merge($exportParams, ['download' => 1])) ?>"
           target="_blank"
           class="btn btn-danger fw-semibold shadow-sm"
           title="<?= e($pdfFilename) ?>">
            <i class="bi bi-file-earmark-pdf-fill me-1"></i> Exportar PDF (<?= e($pdfFilename) ?>)
        </a>
        <a href="<?= url('exportar/reporte-excel', $exportParams) ?>"
           class="btn btn-success fw-semibold shadow-sm"
           title="<?= e($xlsxFilename) ?>">
            <i class="bi bi-file-earmark-excel-fill me-1"></i> Exportar Excel (<?= e($xlsxFilename) ?>)
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

<!-- CATÁLOGO DE LOS 7 REPORTES HOSPITALARIOS -->
<div class="row g-2 mb-4">
    <?php foreach ($catalogoReportes as $key => $meta): ?>
        <?php $active = ($tipoReporte === $key); ?>
        <div class="col-6 col-md-4 col-xl">
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
                <div style="font-size: 0.72rem; opacity: <?= $active ? '0.85' : '0.68' ?>;"><?= e($meta['desc']) ?></div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<!-- BARRA DE FILTROS + GENERADOR RÁPIDO DE COMPROBANTE PDF CON QR -->
<div class="row g-4 mb-4">
    <div class="col-12 col-xl-8">
        <div class="vs-card p-3 p-md-4 h-100">
            <h2 class="h6 fw-bold mb-3" style="color: var(--vs-navy);">
                <i class="bi bi-funnel-fill me-1 text-primary"></i> Catálogo y Filtros de Generación de Reportes
            </h2>
            <form method="GET" action="<?= e(BASE_URL . '/index.php') ?>" class="row g-3 align-items-end" id="formReportesHospitalarios">
                <input type="hidden" name="r" value="reportes">

                <div class="col-12 col-md-6">
                    <label class="form-label small fw-semibold mb-1">Catálogo / Tipo de Reporte</label>
                    <select name="tipo" class="form-select form-select-sm fw-semibold" onchange="this.form.submit()">
                        <?php foreach ($catalogoReportes as $rKey => $rMeta): ?>
                            <option value="<?= e($rKey) ?>" <?= $tipoReporte === $rKey ? 'selected' : '' ?>>
                                <?= e($rMeta['num'] . '. ' . $rMeta['short']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

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

                <div class="col-12 col-sm-6 col-md-4">
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

                <div class="col-12 col-sm-6 col-md-4">
                    <label class="form-label small fw-semibold mb-1">Estado</label>
                    <select name="estado" class="form-select form-select-sm">
                        <option value="">Todos los estados</option>
                        <?php foreach ($estadosCita as $est): ?>
                            <option value="<?= $est ?>" <?= ($filters['estado'] ?? '') === $est ? 'selected' : '' ?>>
                                <?= $est ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="Activo" <?= ($filters['estado'] ?? '') === 'Activo' ? 'selected' : '' ?>>Activo (Catálogo)</option>
                        <option value="Inactivo" <?= ($filters['estado'] ?? '') === 'Inactivo' ? 'selected' : '' ?>>Inactivo (Catálogo)</option>
                    </select>
                </div>

                <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2 pt-2 border-top">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-vs-primary px-3 fw-semibold">
                            <i class="bi bi-search me-1"></i> Aplicar Filtros
                        </button>
                        <a href="<?= url('reportes', ['tipo' => $tipoReporte]) ?>" class="btn btn-sm btn-outline-secondary" title="Limpiar filtros">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Limpiar
                        </a>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?= url('exportar/reporte-pdf', array_merge($exportParams, ['download' => 1])) ?>"
                           target="_blank"
                           class="btn btn-sm btn-danger fw-semibold">
                            <i class="bi bi-file-earmark-pdf-fill me-1"></i> Descargar PDF (.pdf)
                        </a>
                        <a href="<?= url('exportar/reporte-excel', $exportParams) ?>"
                           class="btn btn-sm btn-success fw-semibold">
                            <i class="bi bi-file-earmark-excel-fill me-1"></i> Descargar Excel (.xlsx)
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- EMISOR RÁPIDO DE COMPROBANTE PDF CON CÓDIGO QR -->
    <div class="col-12 col-xl-4">
        <div class="vs-card p-3 p-md-4 h-100" style="background: linear-gradient(145deg, #ffffff 0%, #eef5fc 100%);">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-qr-code-scan fs-4 text-primary"></i>
                <h2 class="h6 fw-bold mb-0" style="color: var(--vs-navy);">Comprobante de Cita PDF + Código QR</h2>
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
            <div class="mt-3 small">
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
            <small class="text-muted">
                Filtros: <?= e($filtersDesc) ?> · Archivos: <code><?= e($pdfFilename) ?></code> / <code><?= e($xlsxFilename) ?></code>
            </small>
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
                    <?php if (in_array($tipoReporte, ['citas_medicas', 'agenda_diaria'], true)): ?>
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

                        <?php if (in_array($tipoReporte, ['citas_medicas', 'agenda_diaria'], true)): ?>
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
                <tfoot class="table-light fw-bold" style="border-top: 2px solid var(--vs-navy);">
                <tr>
                    <?php foreach ($reportData['columns'] as $col): ?>
                        <td style="color: var(--vs-navy); background: #eef5fc;">
                            <?= e($totalsRow[$col['key']] ?? '') ?>
                        </td>
                    <?php endforeach; ?>
                    <?php if (in_array($tipoReporte, ['citas_medicas', 'agenda_diaria'], true)): ?>
                        <td style="background: #eef5fc;"></td>
                    <?php endif; ?>
                </tr>
                </tfoot>
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
