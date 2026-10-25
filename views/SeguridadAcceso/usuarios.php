<?php
declare(strict_types=1);
/** @var array $usuarios */
/** @var array $roles */
/** @var array $filters */
/** @var array|null $currentUser */
/** @var string $csrfToken */
?>
<div class="vs-card mb-4">
    <div class="vs-card-header">
        <div>
            <h6 class="mb-0 fw-bold" style="color:var(--vs-navy);"><i class="bi bi-people-fill me-1"></i> Directorio RBAC de Usuarios del Sistema</h6>
            <small class="text-muted">Alta transaccional vía <code>CALL sp_crear_usuario</code> y baja lógica con <code>CALL sp_conmutar_estado_usuario</code></small>
        </div>
        <button type="button" class="btn btn-vs-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#modalNuevoUsuario">
            <i class="bi bi-person-plus-fill me-1"></i> Nuevo Usuario
        </button>
    </div>

    <div class="p-3 border-bottom bg-light bg-opacity-50">
        <form method="GET" action="<?= url('usuarios') ?>" class="row g-2 align-items-end">
            <input type="hidden" name="r" value="usuarios">
            <div class="col-12 col-md-5">
                <label class="form-label small fw-semibold mb-1">Buscar</label>
                <input type="text" name="q" value="<?= e($filters['q'] ?? '') ?>" class="form-control form-control-sm"
                       placeholder="Nombre, usuario o correo..." data-table-filter="#tablaUsuarios">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small fw-semibold mb-1">Rol</label>
                <select name="id_rol" class="form-select form-select-sm">
                    <option value="">Todos los roles</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= (int) $r['id_rol'] ?>" <?= ((string) ($filters['id_rol'] ?? '') === (string) $r['id_rol']) ? 'selected' : '' ?>>
                            <?= e($r['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="Activo" <?= ($filters['estado'] ?? '') === 'Activo' ? 'selected' : '' ?>>Activo</option>
                    <option value="Inactivo" <?= ($filters['estado'] ?? '') === 'Inactivo' ? 'selected' : '' ?>>Inactivo</option>
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-vs-primary w-100"><i class="bi bi-funnel-fill"></i> Filtrar</button>
                <a href="<?= url('usuarios') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-vs table-hover table-mobile-cards mb-0" id="tablaUsuarios">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre Completo</th>
                    <th>Usuario</th>
                    <th>Rol RBAC</th>
                    <th>Correo / Teléfono</th>
                    <th>Último Acceso</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td data-label="ID" class="fw-bold text-muted">#<?= (int) $u['id_usuario'] ?></td>
                        <td data-label="Nombre">
                            <div class="fw-semibold"><?= e($u['nombre_completo']) ?></div>
                            <?php if (!empty($u['codigo_medico'])): ?>
                                <span class="badge bg-info text-dark" style="font-size:0.7rem;"><?= e($u['codigo_medico']) ?></span>
                            <?php elseif (!empty($u['codigo_paciente'])): ?>
                                <span class="badge bg-secondary" style="font-size:0.7rem;"><?= e($u['codigo_paciente']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Usuario"><code><?= e($u['nombre_usuario']) ?></code></td>
                        <td data-label="Rol">
                            <span class="badge rounded-pill" style="background:var(--vs-sky);color:var(--vs-navy);">
                                <?= e($u['rol_nombre']) ?>
                            </span>
                        </td>
                        <td data-label="Contacto">
                            <div class="small"><?= e($u['correo_electronico']) ?></div>
                            <div class="small text-muted"><?= e($u['telefono'] ?: '-') ?></div>
                        </td>
                        <td data-label="Último Acceso" class="small text-muted">
                            <?= !empty($u['ultimo_acceso']) ? date('d/m/Y H:i', strtotime((string) $u['ultimo_acceso'])) : 'Nunca' ?>
                        </td>
                        <td data-label="Estado">
                            <?php if ($u['estado'] === 'Activo'): ?>
                                <span class="badge rounded-pill text-bg-success">Activo</span>
                            <?php else: ?>
                                <span class="badge rounded-pill text-bg-secondary">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Acciones" class="text-end">
                            <div class="d-inline-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-primary"
                                        onclick='abrirModalEditarUsuario(<?= json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'
                                        title="Editar usuario">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <?php if ((int) $u['id_usuario'] !== (int) ($currentUser['id_usuario'] ?? 0)): ?>
                                    <form method="POST" action="<?= url('usuarios/conmutar-estado') ?>" class="d-inline"
                                          data-confirm="¿Desea conmutar el estado de acceso del usuario <?= e($u['nombre_usuario']) ?>?">
                                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="id_usuario" value="<?= (int) $u['id_usuario'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $u['estado'] === 'Activo' ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                                                title="<?= $u['estado'] === 'Activo' ? 'Desactivar cuenta' : 'Reactivar cuenta' ?>">
                                            <i class="bi <?= $u['estado'] === 'Activo' ? 'bi-person-slash' : 'bi-person-check' ?>"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL ALTA USUARIO (sp_crear_usuario) -->
<div class="modal fade" id="modalNuevoUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" action="<?= url('usuarios/guardar') ?>" class="modal-content needs-validation" novalidate>
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <div class="modal-header text-white" style="background:var(--vs-navy);">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-person-plus-fill me-1"></i> Registrar Nuevo Usuario (CU03 · sp_crear_usuario)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Nombre Completo *</label>
                        <input type="text" name="nombre_completo" class="form-control" required minlength="4" placeholder="Ej. Ana Lucia Mendez">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Rol de Acceso (RBAC) *</label>
                        <select name="id_rol" class="form-select" required>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= (int) $r['id_rol'] ?>"><?= e($r['nombre']) ?> — <?= e($r['descripcion']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Nombre de Usuario *</label>
                        <input type="text" name="nombre_usuario" class="form-control" required minlength="3" placeholder="ana.mendez">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold">Correo Electrónico *</label>
                        <input type="email" name="correo_electronico" class="form-control" required placeholder="a.mendez@vidasalud.com.bo">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Teléfono</label>
                        <input type="text" name="telefono" class="form-control" placeholder="70012345">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Contraseña Segura (Cifrado BCRYPT) *</label>
                        <input type="password" name="password" class="form-control" required
                               data-strict-password data-pwd-meter="newUsrPwdMeter" placeholder="Ej. Cher_4mi">
                        <div id="newUsrPwdMeter" class="d-flex flex-wrap gap-1 mt-2">
                            <span class="pwd-rule-pill" data-rule="len">8+ caract.</span>
                            <span class="pwd-rule-pill" data-rule="upper">1 Mayúscula</span>
                            <span class="pwd-rule-pill" data-rule="lower">1 Minúscula</span>
                            <span class="pwd-rule-pill" data-rule="num">1 Número</span>
                            <span class="pwd-rule-pill" data-rule="spec">1 Especial</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-vs-primary px-4"><i class="bi bi-save me-1"></i> Crear Usuario</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR USUARIO -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" action="<?= url('usuarios/actualizar') ?>" class="modal-content needs-validation" novalidate>
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="id_usuario" id="edit_id_usuario">
            <div class="modal-header text-white" style="background:var(--vs-blue);">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-pencil-square me-1"></i> Editar Usuario y Rol</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Nombre Completo *</label>
                        <input type="text" name="nombre_completo" id="edit_nombre_completo" class="form-control" required minlength="4">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Rol RBAC *</label>
                        <select name="id_rol" id="edit_id_rol" class="form-select" required>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= (int) $r['id_rol'] ?>"><?= e($r['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Nombre de Usuario *</label>
                        <input type="text" name="nombre_usuario" id="edit_nombre_usuario" class="form-control" required minlength="3">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold">Correo Electrónico *</label>
                        <input type="email" name="correo_electronico" id="edit_correo" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Teléfono</label>
                        <input type="text" name="telefono" id="edit_telefono" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Nueva Contraseña (Opcional — deje en blanco para mantener la actual)</label>
                        <input type="password" name="password_opcional" class="form-control"
                               data-strict-password data-optional data-pwd-meter="editUsrPwdMeter" placeholder="Dejar vacío si no desea cambiarla">
                        <div id="editUsrPwdMeter" class="d-flex flex-wrap gap-1 mt-2">
                            <span class="pwd-rule-pill" data-rule="len">8+ caract.</span>
                            <span class="pwd-rule-pill" data-rule="upper">1 Mayúscula</span>
                            <span class="pwd-rule-pill" data-rule="lower">1 Minúscula</span>
                            <span class="pwd-rule-pill" data-rule="num">1 Número</span>
                            <span class="pwd-rule-pill" data-rule="spec">1 Especial</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-vs-primary px-4">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalEditarUsuario(u) {
    document.getElementById('edit_id_usuario').value = u.id_usuario;
    document.getElementById('edit_nombre_completo').value = u.nombre_completo || '';
    document.getElementById('edit_id_rol').value = u.id_rol;
    document.getElementById('edit_nombre_usuario').value = u.nombre_usuario || '';
    document.getElementById('edit_correo').value = u.correo_electronico || '';
    document.getElementById('edit_telefono').value = u.telefono || '';
    new bootstrap.Modal(document.getElementById('modalEditarUsuario')).show();
}
</script>
