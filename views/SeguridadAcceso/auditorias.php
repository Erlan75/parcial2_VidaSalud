<?php
declare(strict_types=1);
/** @var array $logs */
/** @var array $modulos */
/** @var array $usuarios */
/** @var array $stats */
/** @var array $filters */
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="vs-kpi-card">
            <div class="small text-muted fw-semibold">Eventos Totales</div>
            <div class="h4 fw-bold mb-0" style="color:var(--vs-navy);"><?= (int) ($stats['total'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="vs-kpi-card" style="border-left-color:#198754;">
            <div class="small text-muted fw-semibold">Altas (INSERT)</div>
            <div class="h4 fw-bold mb-0 text-success"><?= (int) ($stats['inserts'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="vs-kpi-card" style="border-left-color:#0d6efd;">
            <div class="small text-muted fw-semibold">Modificaciones (UPDATE)</div>
            <div class="h4 fw-bold mb-0 text-primary"><?= (int) ($stats['updates'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="vs-kpi-card" style="border-left-color:#dc3545;">
            <div class="small text-muted fw-semibold">Cancelaciones (CANCEL)</div>
            <div class="h4 fw-bold mb-0 text-danger"><?= (int) ($stats['cancels'] ?? 0) ?></div>
        </div>
    </div>
</div>

<div class="vs-card">
    <div class="vs-card-header">
        <div>
            <h6 class="mb-0 fw-bold" style="color:var(--vs-navy);"><i class="bi bi-journal-code me-1"></i> Registro Inmutable de Auditoría (RF17 · Solo Lectura)</h6>
            <small class="text-muted">Trazabilidad automática generada por Triggers PL/pgSQL y Procedimientos Almacenados</small>
        </div>
        <span class="badge text-bg-dark"><i class="bi bi-lock-fill"></i> Inmutable JSONB</span>
    </div>

    <div class="p-3 border-bottom bg-light bg-opacity-50">
        <form method="GET" action="<?= url('auditorias') ?>" class="row g-2 align-items-end">
            <input type="hidden" name="r" value="auditorias">
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Desde</label>
                <input type="date" name="fecha_desde" value="<?= e($filters['fecha_desde'] ?? '') ?>" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Hasta</label>
                <input type="date" name="fecha_hasta" value="<?= e($filters['fecha_hasta'] ?? '') ?>" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Módulo</label>
                <select name="modulo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($modulos as $m): ?>
                        <option value="<?= e($m) ?>" <?= ($filters['modulo'] ?? '') === $m ? 'selected' : '' ?>><?= e($m) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Operación</label>
                <select name="operacion" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <?php foreach (['LOGIN', 'LOGOUT', 'INSERT', 'UPDATE', 'CANCEL'] as $op): ?>
                        <option value="<?= $op ?>" <?= ($filters['operacion'] ?? '') === $op ? 'selected' : '' ?>><?= $op ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label small fw-semibold mb-1">Usuario</label>
                <select name="id_usuario" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?= (int) $u['id_usuario'] ?>" <?= ((string) ($filters['id_usuario'] ?? '') === (string) $u['id_usuario']) ? 'selected' : '' ?>>
                            <?= e($u['nombre_usuario']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-vs-primary w-100"><i class="bi bi-search"></i> Filtrar</button>
                <a href="<?= url('auditorias') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-vs table-hover table-mobile-cards mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha y Hora</th>
                    <th>Usuario / Rol</th>
                    <th>Módulo</th>
                    <th>Operación</th>
                    <th>Reg. Afectado</th>
                    <th>IP Origen</th>
                    <th class="text-end">Visor JSON</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr data-empty-row><td colspan="8" class="text-center py-4 text-muted">No se encontraron eventos de auditoría con los filtros seleccionados.</td></tr>
                <?php endif; ?>
                <?php foreach ($logs as $log): ?>
                    <?php
                    $opClass = match ($log['operacion']) {
                        'INSERT' => 'text-bg-success',
                        'UPDATE' => 'text-bg-primary',
                        'CANCEL' => 'text-bg-danger',
                        'LOGIN'  => 'text-bg-info',
                        default  => 'text-bg-secondary',
                    };
                    ?>
                    <tr>
                        <td data-label="ID" class="text-muted fw-semibold">#<?= (int) $log['id_auditoria'] ?></td>
                        <td data-label="Fecha/Hora" class="small"><?= date('d/m/Y H:i:s', strtotime((string) $log['fecha_hora'])) ?></td>
                        <td data-label="Usuario">
                            <div class="fw-semibold small"><?= e($log['usuario_nombre']) ?></div>
                            <span class="text-muted" style="font-size:0.75rem;"><code><?= e($log['nombre_usuario']) ?></code> · <?= e($log['rol_nombre']) ?></span>
                        </td>
                        <td data-label="Módulo"><span class="fw-semibold"><?= e($log['modulo']) ?></span></td>
                        <td data-label="Operación"><span class="badge rounded-pill <?= $opClass ?>"><?= e($log['operacion']) ?></span></td>
                        <td data-label="ID Registro"><code><?= e($log['id_registro_afectado']) ?></code></td>
                        <td data-label="IP Origen" class="small text-muted"><?= e($log['ip_origen']) ?></td>
                        <td data-label="Detalle JSON" class="text-end">
                            <button type="button" class="btn btn-sm btn-vs-secondary"
                                    onclick='verComparativoJson(<?= json_encode([
                                        'id' => $log['id_auditoria'],
                                        'modulo' => $log['modulo'],
                                        'operacion' => $log['operacion'],
                                        'fecha' => date('d/m/Y H:i:s', strtotime((string) $log['fecha_hora'])),
                                        'usuario' => $log['usuario_nombre'],
                                        'previos' => $log['datos_previos'] ? json_decode((string) $log['datos_previos'], true) : null,
                                        'nuevos' => $log['datos_nuevos'] ? json_decode((string) $log['datos_nuevos'], true) : null,
                                    ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'>
                                <i class="bi bi-braces"></i> Comparar
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL VISOR COMPARATIVO JSON (datos_previos vs datos_nuevos) -->
<div class="modal fade" id="modalJsonDiff" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white" style="background:var(--vs-navy);">
                <h5 class="modal-title fs-6 fw-bold">
                    <i class="bi bi-file-earmark-diff-fill me-1"></i> Visor Comparativo de Auditoría <span id="diffHeaderMeta"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <div class="fw-bold small text-secondary mb-2"><i class="bi bi-clock-history"></i> DATOS PREVIOS (Estado Anterior)</div>
                        <pre class="json-viewer-box mb-0" id="jsonPrevBox"></pre>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="fw-bold small text-success mb-2"><i class="bi bi-check2-square"></i> DATOS NUEVOS (Estado Resultante)</div>
                        <pre class="json-viewer-box mb-0" id="jsonNewBox"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function verComparativoJson(item) {
    document.getElementById('diffHeaderMeta').textContent = '#' + item.id + ' · ' + item.modulo + ' [' + item.operacion + '] · ' + item.fecha;
    document.getElementById('jsonPrevBox').textContent = item.previos
        ? JSON.stringify(item.previos, null, 2)
        : '// Sin estado previo (Evento inicial / INSERT / LOGIN)';
    document.getElementById('jsonNewBox').textContent = item.nuevos
        ? JSON.stringify(item.nuevos, null, 2)
        : '// Sin payload adicional';
    new bootstrap.Modal(document.getElementById('modalJsonDiff')).show();
}
</script>
