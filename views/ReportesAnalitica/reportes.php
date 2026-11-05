<?php
declare(strict_types=1);
/**
 * Vista: Módulo Integral de Reportes Ejecutivos y Analítica con IA (CU14 y CU15 Evolucionado)
 * Cumple los 3 Tipos de Generación de Reportes exigidos en defensa académica:
 *   - Tipo 1: Filtrado por Parámetros (.pdf y .xlsx / .csv)
 *   - Tipo 2: Selección Visual e Interactiva (Tarjetas, Chips, Columnas y Filas)
 *   - Tipo 3: Reporte Inteligente por Voz / IA (Voice-to-SQL + Síntesis de Voz TTS)
 *
 * @var string $modoTab
 * @var string $tipoReporte
 * @var array $filters
 * @var string $filtersDesc
 * @var array $reportData
 * @var array $kpis
 * @var array $chartEstados
 * @var array $chartEspecialidades
 * @var array $medicos
 * @var array $especialidades
 * @var array $interactiveDataset
 * @var array $interactiveColumns
 * @var array|null $lastAiReport
 * @var array|null $currentUser
 */

use App\Core\ExcelGenerator;

$catalogoReportes = [
    'citas_fecha' => [
        'num'   => '01',
        'icon'  => 'bi-calendar-range-fill',
        'short' => 'Citas por Fecha',
        'desc'  => 'Cronograma por rango',
    ],
    'citas_medico' => [
        'num'   => '02',
        'icon'  => 'bi-person-vcard-fill',
        'short' => 'Citas por Médico',
        'desc'  => 'Productividad médica',
    ],
    'demanda_especialidad' => [
        'num'   => '03',
        'icon'  => 'bi-hospital-fill',
        'short' => 'Citas por Especialidad',
        'desc'  => 'Demanda por servicio',
    ],
    'citas_estado' => [
        'num'   => '04',
        'icon'  => 'bi-tag-fill',
        'short' => 'Citas por Estado',
        'desc'  => 'Detalle por estado clínico',
    ],
    'padron_pacientes' => [
        'num'   => '05',
        'icon'  => 'bi-people-fill',
        'short' => 'Padrón de Pacientes',
        'desc'  => 'Expedientes y sangre',
    ],
    'estados_ausentismo' => [
        'num'   => '06',
        'icon'  => 'bi-pie-chart-fill',
        'short' => 'Resumen de Asistencia e Inasistencias',
        'desc'  => 'Consolidado y % ausentismo',
    ],
    'agenda_imprimible' => [
        'num'   => '07',
        'icon'  => 'bi-printer-fill',
        'short' => 'Agenda Diaria',
        'desc'  => 'Hoja diaria consultorio',
    ],
];

$totalGlobal = max(1, (int) ($kpis['total_citas'] ?? 0));
$tasaEfectividad = round(((int) ($kpis['citas_atendidas'] ?? 0) / $totalGlobal) * 100, 1);
$tasaAusentismo = round(((int) ($kpis['citas_inasistencia'] ?? 0) / $totalGlobal) * 100, 1);
$tasaCancelacion = round(((int) ($kpis['citas_canceladas'] ?? 0) / $totalGlobal) * 100, 1);

$exportParams = array_merge(['tipo' => $tipoReporte], array_filter($filters, static fn($v) => $v !== ''));
$estadosCita = ['Programada', 'Confirmada', 'Atendida', 'Cancelada', 'Inasistencia'];

$fileBaseTipo1 = (string) ($reportData['file_base'] ?? $tipoReporte);
$tsNow = date('Ymd_Hi');
$pdfFilenameTipo1 = ExcelGenerator::buildFilename($fileBaseTipo1, 'pdf', $tsNow);
$xlsxFilenameTipo1 = ExcelGenerator::buildFilename($fileBaseTipo1, 'xlsx', $tsNow);
$csvFilenameTipo1 = ExcelGenerator::buildFilename($fileBaseTipo1, 'csv', $tsNow);
$totalsRowTipo1 = ExcelGenerator::computeTotalsRow($reportData['columns'] ?? [], $reportData['rows'] ?? []);

$emisorNombre = ($currentUser['nombre_completo'] ?? 'Administrador') . ' (' . ($currentUser['rol'] ?? 'Administrador') . ')';

// Conteos por estado para las tarjetas visuales del Tipo 2
$conteoEstadosInteractivo = [
    'Programada'   => 0,
    'Confirmada'   => 0,
    'Atendida'     => 0,
    'Cancelada'    => 0,
    'Inasistencia' => 0,
];
foreach ($interactiveDataset as $item) {
    $st = (string) ($item['estado'] ?? '');
    if (isset($conteoEstadosInteractivo[$st])) {
        $conteoEstadosInteractivo[$st]++;
    }
}
?>

<style>
.vs-report-tabs .nav-link {
    border: 1px solid #d4e3f5;
    background: #ffffff;
    color: var(--vs-navy);
    font-weight: 600;
    border-radius: 10px;
    padding: 0.7rem 1.15rem;
    transition: all 0.2s ease;
}
.vs-report-tabs .nav-link.active {
    background: linear-gradient(135deg, var(--vs-navy) 0%, var(--vs-blue) 100%);
    color: #ffffff;
    border-color: var(--vs-navy);
    box-shadow: 0 6px 16px rgba(0, 45, 98, 0.22);
}
.vs-state-card {
    cursor: pointer;
    user-select: none;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 0.85rem 1rem;
    background: #ffffff;
    transition: all 0.18s ease;
}
.vs-state-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 45, 98, 0.1);
}
.vs-state-card.active {
    border-color: var(--vs-navy);
    background: #eef6ff;
    box-shadow: 0 4px 14px rgba(0, 45, 98, 0.16);
}
.vs-chip-filter {
    cursor: pointer;
    user-select: none;
    border: 1.5px solid #cbd5e1;
    background: #ffffff;
    color: #1e293b;
    border-radius: 50rem;
    padding: 0.35rem 0.85rem;
    font-size: 0.82rem;
    font-weight: 600;
    transition: all 0.15s ease;
}
.vs-chip-filter.active {
    background: var(--vs-navy);
    color: #ffffff;
    border-color: var(--vs-navy);
}
@keyframes vsPulseMic {
    0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.65); }
    70% { box-shadow: 0 0 0 16px rgba(220, 53, 69, 0); }
    100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
}
.vs-mic-recording {
    animation: vsPulseMic 1.4s infinite;
    background-color: #dc3545 !important;
    border-color: #dc3545 !important;
    color: #ffffff !important;
}
</style>

<!-- CABECERA GERENCIAL Y ACCIONES RÁPIDAS -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <span class="badge rounded-pill px-3 py-1 mb-1" style="background: var(--vs-sky); color: var(--vs-navy);">
            Módulo ReportesAnalitica · 3 Modalidades de Generación (PDF .pdf y Excel .xlsx / .csv)
        </span>
        <h1 class="h4 fw-bold mb-0" style="color: var(--vs-navy);">
            Centro Ejecutivo de Reportes y Asistente Analítico por Voz con IA
        </h1>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= url('exportar/reporte-pdf', array_merge($exportParams, ['download' => 1])) ?>"
           target="_blank"
           class="btn btn-danger fw-semibold shadow-sm"
           title="<?= e($pdfFilenameTipo1) ?>">
            <i class="bi bi-file-earmark-pdf-fill me-1"></i> Descargar PDF (.pdf)
        </a>
        <a href="<?= url('exportar/reporte-excel', $exportParams) ?>"
           class="btn btn-success fw-semibold shadow-sm"
           title="<?= e($xlsxFilenameTipo1) ?>">
            <i class="bi bi-file-earmark-excel-fill me-1"></i> Descargar Excel (.xlsx)
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

<!-- PESTAÑAS DE LOS 3 TIPOS DE GENERACIÓN DE REPORTES -->
<ul class="nav vs-report-tabs gap-2 mb-4" id="reportTypeTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $modoTab === 'tipo1' ? 'active' : '' ?>"
                id="tab-btn-tipo1"
                data-bs-toggle="tab"
                data-bs-target="#pane-tipo1"
                type="button"
                role="tab">
            <i class="bi bi-sliders2-vertical me-1"></i> Tipo 1: Filtrado por Parámetros
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $modoTab === 'tipo2' ? 'active' : '' ?>"
                id="tab-btn-tipo2"
                data-bs-toggle="tab"
                data-bs-target="#pane-tipo2"
                type="button"
                role="tab">
            <i class="bi bi-hand-index-thumb-fill me-1"></i> Tipo 2: Selección Visual e Interactiva
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $modoTab === 'tipo3' ? 'active' : '' ?>"
                id="tab-btn-tipo3"
                data-bs-toggle="tab"
                data-bs-target="#pane-tipo3"
                type="button"
                role="tab">
            <i class="bi bi-mic-fill text-danger me-1"></i> Tipo 3: Reporte Inteligente por Voz / IA
        </button>
    </li>
</ul>

<div class="tab-content" id="reportTypeTabsContent">

    <!-- ======================================================================= -->
    <!-- SECCIÓN TIPO 1: REPORTE PARAMETRIZADO POR FILTROS DE DATOS              -->
    <!-- ======================================================================= -->
    <div class="tab-pane fade <?= $modoTab === 'tipo1' ? 'show active' : '' ?>" id="pane-tipo1" role="tabpanel">

        <!-- TARJETAS RÁPIDAS DEL CATÁLOGO DE REPORTES TIPO 1 -->
        <div class="row g-2 mb-4">
            <?php foreach ($catalogoReportes as $key => $meta): ?>
                <?php $active = ($tipoReporte === $key); ?>
                <div class="col-6 col-md-4 col-xl">
                    <a href="<?= url('reportes', array_merge($filters, ['tipo' => $key, 'modo' => 'tipo1'])) ?>"
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

        <!-- FORMULARIO COMBINADO DE PARÁMETROS + TICKET QR -->
        <div class="row g-4 mb-4">
            <div class="col-12 col-xl-8">
                <div class="vs-card p-3 p-md-4 h-100">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h2 class="h6 fw-bold mb-0" style="color: var(--vs-navy);">
                            <i class="bi bi-funnel-fill me-1 text-primary"></i> Tipo 1: Filtrado Combinado por Parámetros
                        </h2>
                        <span class="badge bg-primary-subtle text-primary-emphasis border px-3 py-1">
                            <i class="bi bi-check2-all me-1"></i> Filtros combinables activos
                        </span>
                    </div>

                    <form method="GET" action="<?= e(BASE_URL . '/index.php') ?>" class="row g-3 align-items-end" id="formTipo1">
                        <input type="hidden" name="r" value="reportes">
                        <input type="hidden" name="modo" value="tipo1">

                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-semibold mb-1">Tipo de Reporte</label>
                            <select name="tipo" class="form-select form-select-sm fw-semibold" id="selectTipoReporte1">
                                <?php foreach ($catalogoReportes as $rKey => $rMeta): ?>
                                    <option value="<?= e($rKey) ?>" <?= $tipoReporte === $rKey ? 'selected' : '' ?>>
                                        <?= e($rMeta['num'] . ' - ' . $rMeta['short']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-6 col-md-4">
                            <label class="form-label small fw-semibold mb-1">Fecha Desde</label>
                            <input type="date" name="fecha_desde" class="form-control form-control-sm"
                                   value="<?= e($filters['fecha_desde'] ?? '') ?>">
                        </div>

                        <div class="col-6 col-md-4">
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

                        <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2 pt-2 border-top">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-sm btn-vs-primary px-3 fw-semibold">
                                    <i class="bi bi-funnel me-1"></i> Filtrar Reporte
                                </button>
                                <a href="<?= url('reportes', ['tipo' => $tipoReporte, 'modo' => 'tipo1']) ?>"
                                   class="btn btn-sm btn-outline-secondary"
                                   title="Limpiar filtros">
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
                                <a href="<?= url('exportar/reporte-csv', $exportParams) ?>"
                                   class="btn btn-sm btn-outline-success fw-semibold"
                                   title="<?= e($csvFilenameTipo1) ?>">
                                    <i class="bi bi-filetype-csv me-1"></i> .csv
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

        <!-- BADGE SUPERIOR DE NOMENCLATURA EXPLÍCITA DE ARCHIVOS GENERADOS (TIPO 1) -->
        <div class="vs-card p-3 mb-4 border-start border-4" style="border-left-color: var(--vs-navy) !important; background: #f8fbff;">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <div class="small fw-bold text-uppercase" style="color: var(--vs-navy);">
                        <i class="bi bi-file-earmark-check-fill text-primary me-1"></i>
                        Nomenclatura Explícita de Archivos Listos para Descarga:
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-1">
                        <span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle px-3 py-2 font-monospace">
                            <i class="bi bi-filetype-pdf me-1"></i> <?= e($pdfFilenameTipo1) ?>
                        </span>
                        <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle px-3 py-2 font-monospace">
                            <i class="bi bi-filetype-xlsx me-1"></i> <?= e($xlsxFilenameTipo1) ?>
                        </span>
                        <span class="badge bg-secondary-subtle text-secondary-emphasis border px-3 py-2 font-monospace">
                            <i class="bi bi-filetype-csv me-1"></i> <?= e($csvFilenameTipo1) ?>
                        </span>
                    </div>
                </div>
                <div class="small text-muted text-md-end">
                    <div><strong>Filtros Aplicados:</strong> <?= e($filtersDesc) ?></div>
                    <div><strong>Emisor:</strong> <?= e($emisorNombre) ?> · <strong>Fecha:</strong> <?= date('d/m/Y H:i') ?></div>
                </div>
            </div>
        </div>

        <!-- GRÁFICOS ESTADÍSTICOS SINCRONIZADOS CON FILTROS (CHART.JS) -->
        <div class="row g-4 mb-4">
            <div class="col-12 col-lg-5">
                <div class="vs-card p-4 h-100">
                    <h3 class="h6 fw-bold mb-1" style="color: var(--vs-navy);">Distribución por Estado y Ausentismo</h3>
                    <p class="text-muted small mb-3">Proporción visual sincronizada con los filtros activos</p>
                    <div style="position: relative; height: 230px;">
                        <canvas id="repChartEstados"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-7">
                <div class="vs-card p-4 h-100">
                    <h3 class="h6 fw-bold mb-1" style="color: var(--vs-navy);">Curva de Demanda por Especialidad Médica</h3>
                    <p class="text-muted small mb-3">Comparativa de turnos solicitados por servicio clínico</p>
                    <div style="position: relative; height: 230px;">
                        <canvas id="repChartEspecialidades"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABLA DINÁMICA DEL REPORTE SELECCIONADO (TIPO 1) -->
        <div class="vs-card p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pb-2 border-bottom">
                <div>
                    <h2 class="h6 fw-bold mb-0" style="color: var(--vs-navy);">
                        <i class="bi bi-table me-1 text-primary"></i> <?= e($reportData['title'] ?? 'Reporte') ?>
                    </h2>
                    <small class="text-muted">
                        Filtros: <?= e($filtersDesc) ?> · Orientación PDF: <?= ($reportData['orientation'] ?? 'L') === 'L' ? 'Horizontal (Landscape)' : 'Vertical (Portrait)' ?>
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
                            <?php if (in_array($tipoReporte, ['citas_fecha', 'citas_estado', 'agenda_imprimible'], true)): ?>
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

                                <?php if (in_array($tipoReporte, ['citas_fecha', 'citas_estado', 'agenda_imprimible'], true)): ?>
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
                                    <?= e($totalsRowTipo1[$col['key']] ?? '') ?>
                                </td>
                            <?php endforeach; ?>
                            <?php if (in_array($tipoReporte, ['citas_fecha', 'citas_estado', 'agenda_imprimible'], true)): ?>
                                <td style="background: #eef5fc;"></td>
                            <?php endif; ?>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ======================================================================= -->
    <!-- SECCIÓN TIPO 2: SELECCIÓN VISUAL E INTERACTIVA POR INTERFAZ             -->
    <!-- ======================================================================= -->
    <div class="tab-pane fade <?= $modoTab === 'tipo2' ? 'show active' : '' ?>" id="pane-tipo2" role="tabpanel">
        <div class="vs-card p-4 mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3 pb-3 border-bottom">
                <div>
                    <span class="badge bg-info-subtle text-info-emphasis border px-3 py-1 mb-1">
                        Constructor Visual Directo · Sin Formularios Clásicos
                    </span>
                    <h2 class="h5 fw-bold mb-0" style="color: var(--vs-navy);">
                        <i class="bi bi-hand-index-thumb-fill text-primary me-1"></i>
                        Tipo 2: Selección Visual e Interactiva de Columnas y Registros
                    </h2>
                    <small class="text-muted">
                        Haga clic sobre las tarjetas de Estado, chips de Especialidades/Médicos y marque las columnas y filas exactas que desea exportar.
                    </small>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" id="btnExportInteractivePdf" class="btn btn-danger fw-semibold shadow-sm">
                        <i class="bi bi-file-earmark-pdf-fill me-1"></i> Exportar Selección a PDF (.pdf)
                    </button>
                    <button type="button" id="btnExportInteractiveExcel" class="btn btn-success fw-semibold shadow-sm">
                        <i class="bi bi-file-earmark-excel-fill me-1"></i> Exportar Selección a Excel (.xlsx)
                    </button>
                </div>
            </div>

            <!-- BADGE DINÁMICO DE NOMENCLATURA TIPO 2 -->
            <div class="p-3 rounded-3 mb-4 border" style="background: #f8fbff;">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="small fw-bold text-uppercase" style="color: var(--vs-navy);">
                            <i class="bi bi-file-earmark-code me-1"></i> Archivos de Selección Personalizada:
                        </span>
                        <span id="badgeTipo2Pdf" class="badge bg-danger-subtle text-danger-emphasis border px-3 py-2 font-monospace">
                            Reporte_Seleccion_Interactiva_<?= $tsNow ?>.pdf
                        </span>
                        <span id="badgeTipo2Xlsx" class="badge bg-success-subtle text-success-emphasis border px-3 py-2 font-monospace">
                            Reporte_Seleccion_Interactiva_<?= $tsNow ?>.xlsx
                        </span>
                    </div>
                    <button type="button" id="btnResetInteractive" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Restablecer Selección Visual
                    </button>
                </div>
            </div>

            <!-- 1. TARJETAS VISUALES INTERACTIVAS DE ESTADO -->
            <div class="mb-4">
                <label class="form-label small fw-bold text-uppercase mb-2" style="color: var(--vs-navy);">
                    1. Clic en Tarjetas Visuales de Estado (Filtro instantáneo):
                </label>
                <div class="row g-2">
                    <?php
                    $metaCardsEstado = [
                        'Programada'   => ['color' => '#0d6efd', 'icon' => 'bi-calendar-event'],
                        'Confirmada'   => ['color' => '#d97706', 'icon' => 'bi-person-check-fill'],
                        'Atendida'     => ['color' => '#198754', 'icon' => 'bi-check-circle-fill'],
                        'Cancelada'    => ['color' => '#dc3545', 'icon' => 'bi-x-circle-fill'],
                        'Inasistencia' => ['color' => '#6c757d', 'icon' => 'bi-slash-circle-fill'],
                    ];
                    foreach ($metaCardsEstado as $estName => $cfgEst):
                        $cantEst = $conteoEstadosInteractivo[$estName] ?? 0;
                        $pctEst = round(($cantEst / max(1, count($interactiveDataset))) * 100);
                    ?>
                        <div class="col-6 col-md-4 col-xl">
                            <div class="vs-state-card" data-state="<?= e($estName) ?>">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold small" style="color: <?= $cfgEst['color'] ?>;">
                                        <i class="bi <?= $cfgEst['icon'] ?> me-1"></i> <?= e($estName) ?>
                                    </span>
                                    <span class="badge rounded-pill text-white" style="background: <?= $cfgEst['color'] ?>;">
                                        <?= $cantEst ?>
                                    </span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar" style="width: <?= $pctEst ?>%; background-color: <?= $cfgEst['color'] ?>;"></div>
                                </div>
                                <div class="text-muted mt-1" style="font-size: 0.72rem;"><?= $pctEst ?>% del total · Clic para filtrar</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 2. CHIPS INTERACTIVOS DE ESPECIALIDADES Y MÉDICOS -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-lg-5">
                    <label class="form-label small fw-bold text-uppercase mb-2" style="color: var(--vs-navy);">
                        2. Chips Interactivos de Especialidades:
                    </label>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($especialidades as $esp): ?>
                            <span class="vs-chip-filter vs-chip-esp" data-esp-id="<?= (int) $esp['id_especialidad'] ?>">
                                <i class="bi bi-hospital me-1"></i> <?= e($esp['nombre']) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-12 col-lg-7">
                    <label class="form-label small fw-bold text-uppercase mb-2" style="color: var(--vs-navy);">
                        3. Chips Interactivos de Médicos:
                    </label>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($medicos as $med): ?>
                            <span class="vs-chip-filter vs-chip-med" data-med-id="<?= (int) $med['id_medico'] ?>">
                                <i class="bi bi-person-badge me-1"></i> Dr(a). <?= e($med['apellidos']) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- 4. CHECKBOXES DE SELECCIÓN DE COLUMNAS -->
            <div class="p-3 rounded-3 mb-4" style="background: #eef5fc; border: 1px solid #cfe0f5;">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <label class="form-label small fw-bold text-uppercase mb-0" style="color: var(--vs-navy);">
                        <i class="bi bi-layout-three-columns me-1"></i>
                        4. Checkboxes de Selección de Columnas a Incluir en el Reporte:
                    </label>
                    <span class="small text-muted">Desmarque cualquier columna para ocultarla en pantalla y excluirla del PDF/Excel</span>
                </div>
                <div class="d-flex flex-wrap gap-3">
                    <?php foreach ($interactiveColumns as $colKey => $colMeta): ?>
                        <div class="form-check form-check-inline m-0 bg-white px-3 py-1 rounded-pill border">
                            <input class="form-check-input vs-col-checkbox"
                                   type="checkbox"
                                   id="chkCol_<?= e($colKey) ?>"
                                   value="<?= e($colKey) ?>"
                                   checked>
                            <label class="form-check-label small fw-semibold" for="chkCol_<?= e($colKey) ?>">
                                <?= e($colMeta['label']) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- TABLA CON CHECKBOXES POR FILA -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <div class="d-flex align-items-center gap-2">
                    <button type="button" id="btnSelectVisibleRows" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-check2-square me-1"></i> Marcar Filas Visibles
                    </button>
                    <button type="button" id="btnUnselectAllRows" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-square me-1"></i> Desmarcar Todas
                    </button>
                </div>
                <div>
                    <span class="badge rounded-pill px-3 py-2" style="background: var(--vs-navy);" id="interactiveCounterBadge">
                        <?= count($interactiveDataset) ?> filas seleccionadas
                    </span>
                </div>
            </div>

            <div class="table-responsive" style="max-height: 460px; overflow-y: auto;">
                <table class="table vs-table table-hover align-middle mb-0" id="interactiveTable">
                    <thead style="position: sticky; top: 0; z-index: 2;">
                    <tr>
                        <th style="width: 42px;">
                            <input type="checkbox" class="form-check-input" id="chkAllInteractiveRows" checked>
                        </th>
                        <?php foreach ($interactiveColumns as $colKey => $colMeta): ?>
                            <th data-col-key="<?= e($colKey) ?>"><?= e($colMeta['label']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($interactiveDataset as $row): ?>
                        <tr class="vs-interactive-row"
                            data-id="<?= (int) $row['id_cita'] ?>"
                            data-estado="<?= e($row['estado']) ?>"
                            data-esp-id="<?= (int) $row['id_especialidad'] ?>"
                            data-med-id="<?= (int) $row['id_medico'] ?>">
                            <td>
                                <input type="checkbox"
                                       class="form-check-input vs-row-checkbox"
                                       value="<?= (int) $row['id_cita'] ?>"
                                       checked>
                            </td>
                            <td data-col-key="codigo_cita" class="fw-bold" style="color: var(--vs-navy);"><?= e($row['codigo_cita']) ?></td>
                            <td data-col-key="fecha_hora"><?= e($row['fecha_hora']) ?></td>
                            <td data-col-key="paciente" class="fw-semibold"><?= e($row['paciente']) ?></td>
                            <td data-col-key="paciente_ci"><?= e($row['paciente_ci']) ?></td>
                            <td data-col-key="medico"><?= e($row['medico']) ?></td>
                            <td data-col-key="especialidad"><span class="badge bg-light text-dark border"><?= e($row['especialidad']) ?></span></td>
                            <td data-col-key="estado"><?= badge_estado_cita((string) $row['estado']) ?></td>
                            <td data-col-key="motivo" class="small text-muted"><?= e($row['motivo']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Formulario oculto para exportar exactamente las columnas y filas marcadas -->
            <form id="formInteractiveExport" method="POST" target="_blank" class="d-none">
                <input type="hidden" name="cols" id="inputInteractiveCols" value="">
                <input type="hidden" name="ids" id="inputInteractiveIds" value="">
                <input type="hidden" name="estados" id="inputInteractiveEstados" value="">
                <input type="hidden" name="especialidades" id="inputInteractiveEsp" value="">
                <input type="hidden" name="medicos" id="inputInteractiveMed" value="">
            </form>
        </div>
    </div>

    <!-- ======================================================================= -->
    <!-- SECCIÓN TIPO 3: REPORTE POR NOTA DE VOZ / LENGUAJE NATURAL CON IA       -->
    <!-- ======================================================================= -->
    <div class="tab-pane fade <?= $modoTab === 'tipo3' ? 'show active' : '' ?>" id="pane-tipo3" role="tabpanel">
        <div class="vs-card p-4 mb-4" style="background: linear-gradient(145deg, #ffffff 0%, #f3f8ff 100%);">
            <div class="row g-4 align-items-center">
                <div class="col-12 col-lg-8">
                    <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-2"
                         style="background: #e0ecff; color: var(--vs-navy); font-size: 0.8rem; font-weight: 700;">
                        <i class="bi bi-stars text-primary"></i>
                        <span>Asistente Analítico Multimodal · Voice-to-SQL + Síntesis de Voz (TTS)</span>
                    </div>
                    <h2 class="h5 fw-bold mb-1" style="color: var(--vs-navy);">
                        Tipo 3: Generación de Reportes por Nota de Voz o Lenguaje Natural con IA
                    </h2>
                    <p class="text-muted small mb-3">
                        Presione <strong>«🎙️ Dictar Reporte por Voz»</strong> y hable naturalmente en español, o elija un ejemplo rápido. El motor híbrido IA (Gemini + Parser Semántico PostgreSQL Read-Only) construirá la consulta SQL, mostrará la tabla, hablará el resumen en voz alta y habilitará la descarga en <code>.pdf</code> y <code>.xlsx</code>.
                    </p>

                    <!-- BOTONES DE EJEMPLOS RÁPIDOS DE VOZ / IA -->
                    <div class="mb-3">
                        <span class="small fw-bold text-secondary d-block mb-1">
                            <i class="bi bi-lightning-charge-fill text-warning me-1"></i> Ejemplos rápidos en 1 clic:
                        </span>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary vs-ai-example">
                                Mostrar todas las citas canceladas o inasistencias agrupadas por médico
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary vs-ai-example">
                                Listar citas atendidas de pediatría y medicina general de este mes
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary vs-ai-example">
                                Pacientes con más citas programadas
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary vs-ai-example">
                                Demanda de citas agrupadas por especialidad
                            </button>
                        </div>
                    </div>

                    <!-- CAMPO DE TEXTO EDITABLE Y BOTONES DE VOZ / EJECUCIÓN -->
                    <div class="input-group mb-2">
                        <span class="input-group-text bg-white">
                            <i class="bi bi-chat-dots-fill text-primary"></i>
                        </span>
                        <input type="text"
                               id="aiVoicePromptInput"
                               class="form-control fw-semibold"
                               placeholder="Dicte con el micrófono o escriba su reporte (ej: Mostrar citas canceladas agrupadas por médico)..."
                               value="<?= e($lastAiReport['prompt'] ?? 'Mostrar todas las citas canceladas o inasistencias agrupadas por médico') ?>">
                        <button type="button" id="btnStartVoiceDictation" class="btn btn-outline-danger fw-bold px-3">
                            🎙️ Dictar Reporte por Voz
                        </button>
                        <button type="button" id="btnGenerateAiReport" class="btn btn-vs-primary fw-bold px-4">
                            <i class="bi bi-cpu-fill me-1"></i> Generar Reporte con IA
                        </button>
                    </div>
                    <div id="voiceStatusIndicator" class="small text-muted">
                        <i class="bi bi-shield-lock-fill text-success me-1"></i>
                        Seguridad Activa: Transacción PostgreSQL <code>READ ONLY</code> (Solo sentencias <code>SELECT</code> autorizadas).
                    </div>
                </div>

                <!-- PANEL DE CONTROL DE RESPUESTA HABLADA (TEXT-TO-SPEECH) -->
                <div class="col-12 col-lg-4">
                    <div class="p-3 rounded-4 border bg-white shadow-sm h-100">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold small" style="color: var(--vs-navy);">
                                <i class="bi bi-volume-up-fill text-primary fs-5 me-1"></i> Síntesis de Voz Ejecutiva
                            </span>
                            <span class="badge bg-success-subtle text-success-emphasis" id="ttsStatusBadge">Lista (es-ES / es-BO)</span>
                        </div>
                        <p class="small text-muted mb-3" id="aiVoiceSummaryText">
                            <?= e($lastAiReport['resumen_voz'] ?? 'El asistente leerá en voz alta el resumen ejecutivo del reporte generado indicando la cantidad de registros encontrados.') ?>
                        </p>
                        <div class="d-flex gap-2">
                            <button type="button" id="btnRepeatSpeech" class="btn btn-sm btn-outline-primary flex-fill fw-semibold">
                                <i class="bi bi-play-circle-fill me-1"></i> Escuchar Resumen
                            </button>
                            <button type="button" id="btnMuteSpeech" class="btn btn-sm btn-outline-secondary fw-semibold">
                                <i class="bi bi-volume-mute-fill me-1"></i> Silenciar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CONTENEDOR DE SALIDA MULTIMODAL DEL REPORTE IA -->
        <div id="aiReportOutputContainer" class="vs-card p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3 pb-3 border-bottom">
                <div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <span class="badge rounded-pill px-3 py-1" style="background: var(--vs-navy);" id="aiMotorBadge">
                            <?= e($lastAiReport['motor'] ?? 'Motor Híbrido IA · Voice-to-SQL') ?>
                        </span>
                        <span class="badge bg-primary-subtle text-primary-emphasis border" id="aiTotalBadge">
                            <?= (int) ($lastAiReport['total_registros'] ?? 0) ?> registros en PostgreSQL
                        </span>
                    </div>
                    <h3 class="h5 fw-bold mb-0" style="color: var(--vs-navy);" id="aiReportTitle">
                        <?= e($lastAiReport['titulo_reporte'] ?? 'Reporte IA: Presione "Generar Reporte con IA" o dicte por voz') ?>
                    </h3>
                    <small class="text-muted" id="aiReportExplanation">
                        <?= e($lastAiReport['explicacion_corta'] ?? 'Los resultados de su consulta en lenguaje natural aparecerán aquí en tiempo real.') ?>
                    </small>
                </div>

                <!-- BOTONES DE DESCARGA DEL REPORTE IA CON EXTENSIÓN EXPLÍCITA -->
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= url('reportes/ia-exportar-pdf', ['download' => 1]) ?>"
                       id="btnDownloadAiPdf"
                       target="_blank"
                       class="btn btn-danger fw-semibold shadow-sm">
                        <i class="bi bi-file-earmark-pdf-fill me-1"></i> Descargar Reporte IA (.pdf)
                    </a>
                    <a href="<?= url('reportes/ia-exportar-excel') ?>"
                       id="btnDownloadAiExcel"
                       class="btn btn-success fw-semibold shadow-sm">
                        <i class="bi bi-file-earmark-excel-fill me-1"></i> Descargar Reporte IA (.xlsx)
                    </a>
                </div>
            </div>

            <!-- NOMENCLATURA EXPLÍCITA DE ARCHIVOS DEL REPORTE IA -->
            <div class="p-3 rounded-3 mb-3 border" style="background: #f8fbff;">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="small fw-bold text-uppercase" style="color: var(--vs-navy);">
                            Nombre de Archivo Generado:
                        </span>
                        <span class="badge bg-danger-subtle text-danger-emphasis border px-3 py-2 font-monospace" id="aiPdfFilenameBadge">
                            <?= e($lastAiReport['archivo_pdf'] ?? ('Reporte_IA_Voz_' . $tsNow . '.pdf')) ?>
                        </span>
                        <span class="badge bg-success-subtle text-success-emphasis border px-3 py-2 font-monospace" id="aiXlsxFilenameBadge">
                            <?= e($lastAiReport['archivo_xlsx'] ?? ('Reporte_IA_Voz_' . $tsNow . '.xlsx')) ?>
                        </span>
                    </div>
                    <button class="btn btn-sm btn-outline-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAiSql">
                        <i class="bi bi-code-slash me-1"></i> Ver Consulta SQL SELECT Generada
                    </button>
                </div>

                <!-- BLOQUE COLAPSABLE CON LA CONSULTA SQL GENERADA -->
                <div class="collapse show mt-3" id="collapseAiSql">
                    <div class="p-3 rounded-3 text-white font-monospace small" style="background: #0f172a;">
                        <div class="d-flex justify-content-between align-items-center text-info mb-1" style="font-size: 0.75rem;">
                            <span>POSTGRESQL READ-ONLY TRANSACTION · SELECT QUERY</span>
                            <span>VALIDACIÓN DE SEGURIDAD: OK</span>
                        </div>
                        <pre class="mb-0 text-light" id="aiGeneratedSqlCode" style="white-space: pre-wrap;"><?= e($lastAiReport['sql_generado'] ?? "SELECT m.codigo_medico, 'Dr(a). ' || m.nombres || ' ' || m.apellidos AS medico_tratante, COUNT(c.id_cita) AS total_citas\n  FROM medicos m\n  JOIN citas c ON c.id_medico = m.id_medico\n WHERE c.estado IN ('Cancelada', 'Inasistencia')\n GROUP BY m.id_medico\n ORDER BY total_citas DESC") ?></pre>
                    </div>
                </div>
            </div>

            <!-- TABLA DINÁMICA DE RESULTADOS DE POSTGRESQL (TIPO 3) -->
            <div class="table-responsive" id="aiTableWrapper">
                <?php if (!empty($lastAiReport['rows']) && !empty($lastAiReport['columns'])): ?>
                    <table class="table vs-table table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <?php foreach ($lastAiReport['columns'] as $col): ?>
                                <th><?= e($col['label']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lastAiReport['rows'] as $r): ?>
                            <tr>
                                <?php foreach ($lastAiReport['columns'] as $col): ?>
                                    <td><?= e((string) ($r[$col['key']] ?? '-')) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="text-center py-4 text-muted" id="aiEmptyPlaceholder">
                        <i class="bi bi-mic display-6 text-primary d-block mb-2"></i>
                        <p class="mb-1 fw-semibold">Ejecutando consulta inteligente inicial...</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // =========================================================================
    // 1. GRÁFICOS CHART.JS (TIPO 1)
    // =========================================================================
    if (typeof Chart !== 'undefined') {
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
                    plugins: { legend: { position: 'right', labels: { boxWidth: 12 } } },
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
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });
        }
    }

    // Cambio automático en selector Tipo 1
    const selectTipo1 = document.getElementById('selectTipoReporte1');
    const formTipo1 = document.getElementById('formTipo1');
    if (selectTipo1 && formTipo1) {
        selectTipo1.addEventListener('change', function () {
            formTipo1.submit();
        });
    }

    // =========================================================================
    // 2. CONSTRUCTOR INTERACTIVO TIPO 2 (TARJETAS, CHIPS, COLUMNAS Y FILAS)
    // =========================================================================
    const stateCards = document.querySelectorAll('.vs-state-card');
    const espChips = document.querySelectorAll('.vs-chip-esp');
    const medChips = document.querySelectorAll('.vs-chip-med');
    const colCheckboxes = document.querySelectorAll('.vs-col-checkbox');
    const rowElements = document.querySelectorAll('.vs-interactive-row');
    const chkAllRows = document.getElementById('chkAllInteractiveRows');
    const counterBadge = document.getElementById('interactiveCounterBadge');
    const badgeTipo2Pdf = document.getElementById('badgeTipo2Pdf');
    const badgeTipo2Xlsx = document.getElementById('badgeTipo2Xlsx');

    const activeStates = new Set();
    const activeEsps = new Set();
    const activeMeds = new Set();

    function refreshInteractiveTable(autoCheckVisible = true) {
        let visibleCount = 0;
        let checkedCount = 0;

        rowElements.forEach(tr => {
            const st = tr.getAttribute('data-estado');
            const espId = tr.getAttribute('data-esp-id');
            const medId = tr.getAttribute('data-med-id');

            const matchState = activeStates.size === 0 || activeStates.has(st);
            const matchEsp = activeEsps.size === 0 || activeEsps.has(espId);
            const matchMed = activeMeds.size === 0 || activeMeds.has(medId);

            const isVisible = matchState && matchEsp && matchMed;
            const chk = tr.querySelector('.vs-row-checkbox');

            if (isVisible) {
                tr.classList.remove('d-none');
                visibleCount++;
                if (autoCheckVisible && chk) {
                    chk.checked = true;
                }
            } else {
                tr.classList.add('d-none');
                if (autoCheckVisible && chk) {
                    chk.checked = false;
                }
            }

            if (chk && chk.checked && !tr.classList.contains('d-none')) {
                checkedCount++;
            }
        });

        if (counterBadge) {
            counterBadge.textContent = `${checkedCount} de ${visibleCount} filas marcadas para exportar`;
        }

        const now = new Date();
        const ts = now.getFullYear().toString() +
            String(now.getMonth() + 1).padStart(2, '0') +
            String(now.getDate()).padStart(2, '0') + '_' +
            String(now.getHours()).padStart(2, '0') +
            String(now.getMinutes()).padStart(2, '0');

        let slug = 'Reporte_Seleccion_Interactiva_Citas';
        if (activeStates.size > 0 && activeStates.size <= 2) {
            slug = 'Reporte_Seleccion_' + Array.from(activeStates).join('_');
        }
        if (badgeTipo2Pdf) badgeTipo2Pdf.textContent = `${slug}_${ts}.pdf`;
        if (badgeTipo2Xlsx) badgeTipo2Xlsx.textContent = `${slug}_${ts}.xlsx`;
    }

    stateCards.forEach(card => {
        card.addEventListener('click', function () {
            const st = this.getAttribute('data-state');
            if (activeStates.has(st)) {
                activeStates.delete(st);
                this.classList.remove('active');
            } else {
                activeStates.add(st);
                this.classList.add('active');
            }
            refreshInteractiveTable(true);
        });
    });

    espChips.forEach(chip => {
        chip.addEventListener('click', function () {
            const id = this.getAttribute('data-esp-id');
            if (activeEsps.has(id)) {
                activeEsps.delete(id);
                this.classList.remove('active');
            } else {
                activeEsps.add(id);
                this.classList.add('active');
            }
            refreshInteractiveTable(true);
        });
    });

    medChips.forEach(chip => {
        chip.addEventListener('click', function () {
            const id = this.getAttribute('data-med-id');
            if (activeMeds.has(id)) {
                activeMeds.delete(id);
                this.classList.remove('active');
            } else {
                activeMeds.add(id);
                this.classList.add('active');
            }
            refreshInteractiveTable(true);
        });
    });

    colCheckboxes.forEach(chk => {
        chk.addEventListener('change', function () {
            const checkedCols = Array.from(colCheckboxes).filter(c => c.checked);
            if (checkedCols.length === 0) {
                this.checked = true;
                return;
            }
            const colKey = this.value;
            const cells = document.querySelectorAll(`#interactiveTable [data-col-key="${colKey}"]`);
            cells.forEach(cell => {
                cell.classList.toggle('d-none', !this.checked);
            });
        });
    });

    document.querySelectorAll('.vs-row-checkbox').forEach(chk => {
        chk.addEventListener('change', function () {
            refreshInteractiveTable(false);
        });
    });

    if (chkAllRows) {
        chkAllRows.addEventListener('change', function () {
            const state = this.checked;
            rowElements.forEach(tr => {
                if (!tr.classList.contains('d-none')) {
                    const c = tr.querySelector('.vs-row-checkbox');
                    if (c) c.checked = state;
                }
            });
            refreshInteractiveTable(false);
        });
    }

    const btnSelectVisible = document.getElementById('btnSelectVisibleRows');
    if (btnSelectVisible) {
        btnSelectVisible.addEventListener('click', function () {
            rowElements.forEach(tr => {
                if (!tr.classList.contains('d-none')) {
                    const c = tr.querySelector('.vs-row-checkbox');
                    if (c) c.checked = true;
                }
            });
            if (chkAllRows) chkAllRows.checked = true;
            refreshInteractiveTable(false);
        });
    }

    const btnUnselectAll = document.getElementById('btnUnselectAllRows');
    if (btnUnselectAll) {
        btnUnselectAll.addEventListener('click', function () {
            rowElements.forEach(tr => {
                const c = tr.querySelector('.vs-row-checkbox');
                if (c) c.checked = false;
            });
            if (chkAllRows) chkAllRows.checked = false;
            refreshInteractiveTable(false);
        });
    }

    const btnResetInteractive = document.getElementById('btnResetInteractive');
    if (btnResetInteractive) {
        btnResetInteractive.addEventListener('click', function () {
            activeStates.clear();
            activeEsps.clear();
            activeMeds.clear();
            stateCards.forEach(c => c.classList.remove('active'));
            espChips.forEach(c => c.classList.remove('active'));
            medChips.forEach(c => c.classList.remove('active'));
            colCheckboxes.forEach(c => {
                c.checked = true;
                const cells = document.querySelectorAll(`#interactiveTable [data-col-key="${c.value}"]`);
                cells.forEach(cell => cell.classList.remove('d-none'));
            });
            if (chkAllRows) chkAllRows.checked = true;
            refreshInteractiveTable(true);
        });
    }

    function submitInteractiveExport(targetUrl) {
        const selectedCols = Array.from(colCheckboxes).filter(c => c.checked).map(c => c.value);
        const selectedIds = [];
        rowElements.forEach(tr => {
            if (!tr.classList.contains('d-none')) {
                const c = tr.querySelector('.vs-row-checkbox');
                if (c && c.checked) {
                    selectedIds.push(c.value);
                }
            }
        });

        if (selectedIds.length === 0) {
            alert('Por favor marque al menos una fila en la tabla para exportar su selección.');
            return;
        }

        const form = document.getElementById('formInteractiveExport');
        document.getElementById('inputInteractiveCols').value = selectedCols.join(',');
        document.getElementById('inputInteractiveIds').value = selectedIds.join(',');
        document.getElementById('inputInteractiveEstados').value = Array.from(activeStates).join(',');
        document.getElementById('inputInteractiveEsp').value = Array.from(activeEsps).join(',');
        document.getElementById('inputInteractiveMed').value = Array.from(activeMeds).join(',');

        form.action = targetUrl;
        form.submit();
    }

    const btnExpPdf2 = document.getElementById('btnExportInteractivePdf');
    if (btnExpPdf2) {
        btnExpPdf2.addEventListener('click', function () {
            submitInteractiveExport('<?= url('exportar/seleccion-pdf', ['download' => 1]) ?>');
        });
    }

    const btnExpExcel2 = document.getElementById('btnExportInteractiveExcel');
    if (btnExpExcel2) {
        btnExpExcel2.addEventListener('click', function () {
            submitInteractiveExport('<?= url('exportar/seleccion-excel') ?>');
        });
    }

    // =========================================================================
    // 3. ASISTENTE DE VOZ CON IA (VOICE-TO-SQL + SÍNTESIS DE VOZ TTS)
    // =========================================================================
    const promptInput = document.getElementById('aiVoicePromptInput');
    const btnDictate = document.getElementById('btnStartVoiceDictation');
    const btnGenerateAi = document.getElementById('btnGenerateAiReport');
    const voiceStatus = document.getElementById('voiceStatusIndicator');
    const btnRepeatSpeech = document.getElementById('btnRepeatSpeech');
    const btnMuteSpeech = document.getElementById('btnMuteSpeech');
    const ttsStatusBadge = document.getElementById('ttsStatusBadge');

    let lastSpokenSummary = document.getElementById('aiVoiceSummaryText')?.textContent?.trim() || '';

    function speakExecutiveSummary(text) {
        if (!('speechSynthesis' in window) || !text) {
            return;
        }
        window.speechSynthesis.cancel();
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'es-ES';
        utterance.rate = 1.02;
        utterance.pitch = 1.0;

        const voices = window.speechSynthesis.getVoices();
        const spanishVoice = voices.find(v => v.lang && v.lang.toLowerCase().startsWith('es'));
        if (spanishVoice) {
            utterance.voice = spanishVoice;
        }

        utterance.onstart = () => {
            if (ttsStatusBadge) {
                ttsStatusBadge.textContent = '🔊 Hablando...';
                ttsStatusBadge.className = 'badge bg-primary text-white';
            }
        };
        utterance.onend = () => {
            if (ttsStatusBadge) {
                ttsStatusBadge.textContent = 'Audio Finalizado';
                ttsStatusBadge.className = 'badge bg-success-subtle text-success-emphasis';
            }
        };

        window.speechSynthesis.speak(utterance);
    }

    if (btnRepeatSpeech) {
        btnRepeatSpeech.addEventListener('click', function () {
            speakExecutiveSummary(lastSpokenSummary);
        });
    }

    if (btnMuteSpeech) {
        btnMuteSpeech.addEventListener('click', function () {
            if ('speechSynthesis' in window) {
                window.speechSynthesis.cancel();
            }
            if (ttsStatusBadge) {
                ttsStatusBadge.textContent = 'Silenciado';
                ttsStatusBadge.className = 'badge bg-secondary-subtle text-secondary-emphasis';
            }
        });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    async function executeAiVoiceReport(speakResult = true) {
        const prompt = (promptInput?.value || '').trim();
        if (!prompt) {
            alert('Por favor dicte o escriba una instrucción para el reporte.');
            return;
        }

        if (btnGenerateAi) {
            btnGenerateAi.disabled = true;
            btnGenerateAi.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Procesando SQL...';
        }
        if (voiceStatus) {
            voiceStatus.innerHTML = '<i class="bi bi-hourglass-split text-primary me-1"></i> Analizando instrucción con IA y ejecutando consulta <code>SELECT</code> en PostgreSQL (Read-Only)...';
        }

        try {
            const response = await fetch('<?= url('reportes/ia-voz') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ prompt: prompt })
            });

            const data = await response.json();
            if (!data.ok) {
                throw new Error(data.message || 'No se pudo procesar la consulta.');
            }

            document.getElementById('aiMotorBadge').textContent = data.motor || 'Motor Híbrido IA';
            document.getElementById('aiTotalBadge').textContent = `${data.total_registros} registros en PostgreSQL`;
            document.getElementById('aiReportTitle').textContent = data.titulo_reporte;
            document.getElementById('aiReportExplanation').textContent = data.explicacion_corta;
            document.getElementById('aiPdfFilenameBadge').textContent = data.archivo_pdf;
            document.getElementById('aiXlsxFilenameBadge').textContent = data.archivo_xlsx;
            document.getElementById('aiGeneratedSqlCode').textContent = data.sql_generado;
            document.getElementById('aiVoiceSummaryText').textContent = data.resumen_voz;
            lastSpokenSummary = data.resumen_voz;

            // Actualizar URLs de exportación con el prompt actual para respaldo directo
            const encodedPrompt = encodeURIComponent(prompt);
            document.getElementById('btnDownloadAiPdf').href = '<?= url('reportes/ia-exportar-pdf', ['download' => 1]) ?>&prompt=' + encodedPrompt;
            document.getElementById('btnDownloadAiExcel').href = '<?= url('reportes/ia-exportar-excel') ?>&prompt=' + encodedPrompt;

            // Renderizar tabla dinámica
            const tableWrapper = document.getElementById('aiTableWrapper');
            if (!data.rows || data.rows.length === 0) {
                tableWrapper.innerHTML = `
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox display-5 d-block mb-2"></i>
                        <p class="mb-0 fw-semibold">La consulta SQL se ejecutó correctamente pero no devolvió registros para ese criterio.</p>
                    </div>`;
            } else {
                const cols = data.columns || [];
                let thead = '<thead><tr>';
                cols.forEach(c => {
                    thead += `<th>${escapeHtml(c.label)}</th>`;
                });
                thead += '</tr></thead>';

                let tbody = '<tbody>';
                data.rows.forEach(r => {
                    tbody += '<tr>';
                    cols.forEach(c => {
                        const val = r[c.key] != null ? String(r[c.key]) : '-';
                        tbody += `<td>${escapeHtml(val)}</td>`;
                    });
                    tbody += '</tr>';
                });
                tbody += '</tbody>';

                let tfoot = `<tfoot class="table-light fw-bold" style="border-top: 2px solid var(--vs-navy);"><tr>`;
                cols.forEach((c, idx) => {
                    if (idx === 0) {
                        tfoot += `<td style="color: var(--vs-navy); background: #eef5fc;">TOTAL REGISTROS: ${data.rows.length}</td>`;
                    } else {
                        tfoot += `<td style="background: #eef5fc;"></td>`;
                    }
                });
                tfoot += `</tr></tfoot>`;

                tableWrapper.innerHTML = `<table class="table vs-table table-hover align-middle mb-0">${thead}${tbody}${tfoot}</table>`;
            }

            if (voiceStatus) {
                voiceStatus.innerHTML = `<i class="bi bi-check-circle-fill text-success me-1"></i> Consulta ejecutada en PostgreSQL (<code>READ ONLY</code>) · Emitido a las ${escapeHtml(data.emitido_en)}`;
            }

            if (speakResult) {
                speakExecutiveSummary(data.resumen_voz);
            }
        } catch (err) {
            if (voiceStatus) {
                voiceStatus.innerHTML = `<i class="bi bi-exclamation-triangle-fill text-danger me-1"></i> ${escapeHtml(err.message)}`;
            }
        } finally {
            if (btnGenerateAi) {
                btnGenerateAi.disabled = false;
                btnGenerateAi.innerHTML = '<i class="bi bi-cpu-fill me-1"></i> Generar Reporte con IA';
            }
        }
    }

    if (btnGenerateAi) {
        btnGenerateAi.addEventListener('click', function () {
            executeAiVoiceReport(true);
        });
    }

    if (promptInput) {
        promptInput.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') {
                ev.preventDefault();
                executeAiVoiceReport(true);
            }
        });
    }

    document.querySelectorAll('.vs-ai-example').forEach(btn => {
        btn.addEventListener('click', function () {
            if (promptInput) {
                promptInput.value = this.textContent.trim();
                executeAiVoiceReport(true);
            }
        });
    });

    // Reconocimiento de Voz Nativo del Navegador (Web Speech API)
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (SpeechRecognition && btnDictate) {
        const recognition = new SpeechRecognition();
        recognition.lang = 'es-BO';
        recognition.interimResults = true;
        recognition.continuous = false;

        let isRecording = false;

        btnDictate.addEventListener('click', function () {
            if (isRecording) {
                recognition.stop();
                return;
            }
            try {
                recognition.start();
            } catch (e) {
                recognition.stop();
            }
        });

        recognition.onstart = function () {
            isRecording = true;
            btnDictate.classList.add('vs-mic-recording');
            btnDictate.innerHTML = '🔴 Escuchando voz... (Clic para terminar)';
            if (voiceStatus) {
                voiceStatus.innerHTML = '<i class="bi bi-mic-fill text-danger me-1"></i> <strong>Grabando nota de voz:</strong> Hable ahora para dictar su reporte...';
            }
        };

        recognition.onresult = function (event) {
            let transcript = '';
            for (let i = event.resultIndex; i < event.results.length; i++) {
                transcript += event.results[i][0].transcript;
            }
            if (promptInput && transcript.trim() !== '') {
                promptInput.value = transcript.trim();
            }
        };

        recognition.onerror = function (event) {
            isRecording = false;
            btnDictate.classList.remove('vs-mic-recording');
            btnDictate.innerHTML = '🎙️ Dictar Reporte por Voz';
            if (voiceStatus) {
                voiceStatus.innerHTML = '<i class="bi bi-info-circle text-warning me-1"></i> Micrófono finalizado (' + escapeHtml(event.error) + '). Puede ajustar el texto o presionar Generar Reporte con IA.';
            }
        };

        recognition.onend = function () {
            isRecording = false;
            btnDictate.classList.remove('vs-mic-recording');
            btnDictate.innerHTML = '🎙️ Dictar Reporte por Voz';
            if (promptInput && promptInput.value.trim() !== '') {
                executeAiVoiceReport(true);
            }
        };
    } else if (btnDictate) {
        btnDictate.addEventListener('click', function () {
            alert('Su navegador actual no expone Web Speech API (use Google Chrome o Microsoft Edge para dictado por micrófono, o escriba directamente en el cuadro de texto).');
            if (promptInput) promptInput.focus();
        });
    }

    // Si aún no hay reporte IA precargado o se abre la pestaña Tipo 3, cargar el primero sin hablar automáticamente hasta interacción
    <?php if (empty($lastAiReport)): ?>
    executeAiVoiceReport(false);
    <?php endif; ?>
});
</script>
