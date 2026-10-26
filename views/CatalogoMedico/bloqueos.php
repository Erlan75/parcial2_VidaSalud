<?php
declare(strict_types=1);
/** @var array $bloqueos */
/** @var array $medicos */
/** @var bool $canManage */
/** @var string $csrfToken */
?>
<div class="vs-card">
    <div class="vs-card-header">
        <div>
            <h6 class="mb-0 fw-bold" style="color:var(--vs-navy);"><i class="bi bi-calendar-x-fill me-1"></i> Feriados Institucionales y Licencias Médicas</h6>
            <small class="text-muted">Gestión de días no laborables, vacaciones y licencias del personal médico</small>
        </div>
        <?php if ($canManage): ?>
            <button type="button" class="btn btn-sm btn-vs-primary px-3" data-bs-toggle="modal" data-bs-target="#modalNuevoBloqueo">
                <i class="bi bi-plus-circle-fill me-1"></i> Registrar Feriado / Licencia
            </button>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="table table-vs table-hover table-mobile-cards mb-0">
            <thead>
                <tr>
                    <th>Tipo de Bloqueo</th>
                    <th>Alcance / Médico</th>
                    <th>Desde</th>
                    <th>Hasta</th>
                    <th>Motivo Oficial</th>
                    <?php if ($canManage): ?><th class="text-end">Acción</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bloqueos)): ?>
                    <tr data-empty-row><td colspan="6" class="text-center py-4 text-muted">No existen feriados ni ausencias médicas programadas.</td></tr>
                <?php endif; ?>
                <?php foreach ($bloqueos as $b): ?>
                    <?php
                    $isFeriado = $b['id_medico'] === null;
                    ?>
                    <tr>
                        <td data-label="Tipo">
                            <span class="badge rounded-pill <?= $isFeriado ? 'text-bg-danger' : 'text-bg-warning' ?>">
                                <?= e($b['tipo_bloqueo']) ?>
                            </span>
                        </td>
                        <td data-label="Alcance" class="fw-semibold" style="color:var(--vs-navy);">
                            <?= e($b['alcance_nombre']) ?>
                        </td>
                        <td data-label="Desde"><?= date('d/m/Y', strtotime((string) $b['fecha_inicio'])) ?></td>
                        <td data-label="Hasta"><?= date('d/m/Y', strtotime((string) $b['fecha_fin'])) ?></td>
                        <td data-label="Motivo" class="small text-muted"><?= e($b['motivo']) ?></td>
                        <?php if ($canManage): ?>
                            <td data-label="Acción" class="text-end">
                                <form method="POST" action="<?= url('bloqueos/eliminar') ?>" class="d-inline"
                                      data-confirm="¿Desea levantar/eliminar este bloqueo de disponibilidad?">
                                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                    <input type="hidden" name="id_bloqueo" value="<?= (int) $b['id_bloqueo'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Levantar bloqueo">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($canManage): ?>
<!-- MODAL REGISTRAR BLOQUEO (sp_registrar_bloqueo_agenda) -->
<div class="modal fade" id="modalNuevoBloqueo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= url('bloqueos/guardar') ?>" class="modal-content needs-validation" novalidate>
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <div class="modal-header text-white" style="background:var(--vs-navy);">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-calendar-x-fill me-1"></i> Nuevo Feriado o Licencia Médica</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Tipo de Bloqueo *</label>
                    <select name="tipo_bloqueo" id="selTipoBloqueo" class="form-select" onchange="toggleSelectorMedicoBloqueo()" required>
                        <option value="Feriado Institucional">Feriado Institucional (Aplica a toda la Clínica)</option>
                        <option value="Vacaciones">Vacaciones (Por Médico)</option>
                        <option value="Licencia Médica">Licencia Médica (Por Médico)</option>
                        <option value="Congreso">Congreso / Capacitación (Por Médico)</option>
                    </select>
                </div>

                <div class="mb-3 d-none" id="divMedicoBloqueo">
                    <label class="form-label small fw-semibold">Profesional Médico *</label>
                    <select name="id_medico" id="selMedicoBloqueo" class="form-select">
                        <option value="">Seleccione al médico...</option>
                        <?php foreach ($medicos as $m): ?>
                            <option value="<?= (int) $m['id_medico'] ?>">Dr(a). <?= e($m['nombres'] . ' ' . $m['apellidos']) ?> (<?= e($m['codigo_medico']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Fecha Inicio *</label>
                        <input type="date" name="fecha_inicio" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" class="form-control" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Fecha Fin *</label>
                        <input type="date" name="fecha_fin" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" class="form-control" required>
                    </div>
                </div>

                <div class="mb-0">
                    <label class="form-label small fw-semibold">Motivo Oficial *</label>
                    <input type="text" name="motivo" class="form-control" required minlength="4" maxlength="200" placeholder="Ej. Feriado Departamental / Congreso Boliviano de Pediatría">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-vs-primary px-4">Registrar Bloqueo</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleSelectorMedicoBloqueo() {
    const tipo = document.getElementById('selTipoBloqueo').value;
    const divMed = document.getElementById('divMedicoBloqueo');
    const selMed = document.getElementById('selMedicoBloqueo');
    if (tipo === 'Feriado Institucional') {
        divMed.classList.add('d-none');
        selMed.removeAttribute('required');
        selMed.value = '';
    } else {
        divMed.classList.remove('d-none');
        selMed.setAttribute('required', 'required');
    }
}
</script>
<?php endif; ?>
