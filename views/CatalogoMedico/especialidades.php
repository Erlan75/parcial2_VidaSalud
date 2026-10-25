<?php
declare(strict_types=1);
/** @var array $especialidades */
/** @var array|null $currentUser */
/** @var string $csrfToken */

$esAdmin = ($currentUser['rol'] ?? '') === 'Administrador';
?>
<div class="vs-card">
    <div class="vs-card-header">
        <div>
            <h6 class="mb-0 fw-bold" style="color:var(--vs-navy);"><i class="bi bi-award-fill me-1"></i> Catálogo Oficial de Especialidades Médicas</h6>
            <small class="text-muted">Validación de nombres normalizados únicos y baja lógica conservando integridad histórica (CU04)</small>
        </div>
        <div class="d-flex gap-2">
            <input type="text" class="form-control form-control-sm" style="max-width:220px;" placeholder="Filtrar especialidad..." data-table-filter="#tablaEspecialidades">
            <?php if ($esAdmin): ?>
                <button type="button" class="btn btn-sm btn-vs-primary px-3" data-bs-toggle="modal" data-bs-target="#modalNuevaEsp">
                    <i class="bi bi-plus-circle-fill me-1"></i> Nueva Especialidad
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-vs table-hover table-mobile-cards mb-0" id="tablaEspecialidades">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Especialidad Médica</th>
                    <th>Descripción Clínica</th>
                    <th class="text-center">Médicos Activos</th>
                    <th class="text-center">Total Citas</th>
                    <th>Estado</th>
                    <?php if ($esAdmin): ?><th class="text-end">Acciones</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($especialidades as $esp): ?>
                    <tr>
                        <td data-label="Código"><code><?= e($esp['codigo_especialidad']) ?></code></td>
                        <td data-label="Especialidad" class="fw-bold" style="color:var(--vs-navy);"><?= e($esp['nombre']) ?></td>
                        <td data-label="Descripción" class="small text-muted"><?= e($esp['descripcion'] ?: 'Sin descripción') ?></td>
                        <td data-label="Médicos Activos" class="text-center">
                            <span class="badge rounded-pill text-bg-primary"><?= (int) $esp['medicos_activos'] ?></span>
                        </td>
                        <td data-label="Total Citas" class="text-center">
                            <span class="badge rounded-pill bg-light text-dark border"><?= (int) $esp['total_citas'] ?></span>
                        </td>
                        <td data-label="Estado">
                            <span class="badge rounded-pill <?= $esp['estado'] === 'Activo' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                <?= e($esp['estado']) ?>
                            </span>
                        </td>
                        <?php if ($esAdmin): ?>
                            <td data-label="Acciones" class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            onclick='editarEspecialidad(<?= json_encode($esp, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'>
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <form method="POST" action="<?= url('especialidades/conmutar-estado') ?>" class="d-inline"
                                          data-confirm="¿Cambiar el estado de la especialidad <?= e($esp['nombre']) ?>?">
                                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="id_especialidad" value="<?= (int) $esp['id_especialidad'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $esp['estado'] === 'Activo' ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                            <i class="bi <?= $esp['estado'] === 'Activo' ? 'bi-toggle-on' : 'bi-toggle-off' ?>"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($esAdmin): ?>
<!-- MODAL NUEVA ESPECIALIDAD -->
<div class="modal fade" id="modalNuevaEsp" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= url('especialidades/guardar') ?>" class="modal-content needs-validation" novalidate>
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <div class="modal-header text-white" style="background:var(--vs-navy);">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-award-fill me-1"></i> Registrar Especialidad Médica</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Código de Especialidad *</label>
                    <input type="text" name="codigo_especialidad" class="form-control text-uppercase" required minlength="3" maxlength="15" placeholder="Ej. ESP-TRA">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Nombre de Especialidad *</label>
                    <input type="text" name="nombre" class="form-control" required minlength="3" maxlength="80" placeholder="Ej. Traumatología">
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-semibold">Descripción Clínica</label>
                    <textarea name="descripcion" class="form-control" rows="3" placeholder="Alcance asistencial de la especialidad..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-vs-primary px-4">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR ESPECIALIDAD -->
<div class="modal fade" id="modalEditarEsp" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= url('especialidades/actualizar') ?>" class="modal-content needs-validation" novalidate>
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="id_especialidad" id="esp_edit_id">
            <div class="modal-header text-white" style="background:var(--vs-blue);">
                <h5 class="modal-title fs-6 fw-bold">Editar Especialidad Médica</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Código *</label>
                    <input type="text" name="codigo_especialidad" id="esp_edit_codigo" class="form-control text-uppercase" required minlength="3" maxlength="15">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Nombre *</label>
                    <input type="text" name="nombre" id="esp_edit_nombre" class="form-control" required minlength="3" maxlength="80">
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-semibold">Descripción</label>
                    <textarea name="descripcion" id="esp_edit_desc" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-vs-primary px-4">Actualizar</button>
            </div>
        </form>
    </div>
</div>

<script>
function editarEspecialidad(esp) {
    document.getElementById('esp_edit_id').value = esp.id_especialidad;
    document.getElementById('esp_edit_codigo').value = esp.codigo_especialidad || '';
    document.getElementById('esp_edit_nombre').value = esp.nombre || '';
    document.getElementById('esp_edit_desc').value = esp.descripcion || '';
    new bootstrap.Modal(document.getElementById('modalEditarEsp')).show();
}
</script>
<?php endif; ?>
