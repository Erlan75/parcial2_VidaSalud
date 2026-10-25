<?php
declare(strict_types=1);
/** @var array $medicos */
/** @var array $especialidades */
/** @var array $usuariosSinMedico */
/** @var array $filters */
/** @var bool $canEdit */
/** @var string $csrfToken */
?>
<div class="vs-card">
    <div class="vs-card-header">
        <div>
            <h6 class="mb-0 fw-bold" style="color:var(--vs-navy);"><i class="bi bi-person-badge-fill me-1"></i> Cuerpo Médico y Especialidades Asociadas</h6>
            <small class="text-muted">Alta integral con <code>CALL sp_registrar_medico_completo</code> y relación N:M en <code>medico_especialidad</code> (CU05)</small>
        </div>
        <?php if ($canEdit): ?>
            <button type="button" class="btn btn-sm btn-vs-primary px-3" data-bs-toggle="modal" data-bs-target="#modalNuevoMedico">
                <i class="bi bi-plus-circle-fill me-1"></i> Registrar Médico
            </button>
        <?php endif; ?>
    </div>

    <div class="p-3 border-bottom bg-light bg-opacity-50">
        <form method="GET" action="<?= url('medicos') ?>" class="row g-2 align-items-end">
            <input type="hidden" name="r" value="medicos">
            <div class="col-12 col-md-5">
                <label class="form-label small fw-semibold mb-1">Buscar Médico</label>
                <input type="text" name="q" value="<?= e($filters['q'] ?? '') ?>" class="form-control form-control-sm"
                       placeholder="Nombre, código MED-xxxx o matrícula..." data-table-filter="#tablaMedicos">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small fw-semibold mb-1">Especialidad</label>
                <select name="id_especialidad" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <?php foreach ($especialidades as $e): ?>
                        <option value="<?= (int) $e['id_especialidad'] ?>" <?= ((string) ($filters['id_especialidad'] ?? '') === (string) $e['id_especialidad']) ? 'selected' : '' ?>>
                            <?= e($e['nombre']) ?>
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
                <a href="<?= url('medicos') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-vs table-hover table-mobile-cards mb-0" id="tablaMedicos">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Profesional Médico</th>
                    <th>Matrícula</th>
                    <th>Especialidades Asignadas</th>
                    <th>Contacto</th>
                    <th class="text-center">Turnos / Citas</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($medicos as $m): ?>
                    <tr>
                        <td data-label="Código"><code><?= e($m['codigo_medico']) ?></code></td>
                        <td data-label="Médico">
                            <div class="fw-bold" style="color:var(--vs-navy);">Dr(a). <?= e($m['nombres'] . ' ' . $m['apellidos']) ?></div>
                            <span class="text-muted small">Usuario: <code><?= e($m['nombre_usuario']) ?></code></span>
                        </td>
                        <td data-label="Matrícula"><span class="badge bg-light text-dark border"><?= e($m['registro_profesional']) ?></span></td>
                        <td data-label="Especialidades">
                            <?php foreach (explode(', ', (string) $m['especialidades_nombres']) as $espName): ?>
                                <span class="badge rounded-pill mb-1" style="background:var(--vs-sky);color:var(--vs-navy);"><?= e($espName) ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td data-label="Contacto" class="small">
                            <div><?= e($m['correo']) ?></div>
                            <div class="text-muted"><?= e($m['telefono']) ?></div>
                        </td>
                        <td data-label="Turnos / Citas" class="text-center small">
                            <a href="<?= url('horarios', ['id_medico' => $m['id_medico']]) ?>" class="badge text-bg-info text-decoration-none">
                                <?= (int) $m['total_turnos'] ?> días
                            </a>
                            <span class="badge bg-light text-dark border"><?= (int) $m['total_citas'] ?> citas</span>
                        </td>
                        <td data-label="Estado">
                            <span class="badge rounded-pill <?= $m['estado'] === 'Activo' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                <?= e($m['estado']) ?>
                            </span>
                        </td>
                        <td data-label="Acciones" class="text-end">
                            <div class="d-inline-flex gap-1">
                                <a href="<?= url('agenda', ['id_medico' => $m['id_medico'], 'fecha' => '2026-10-26']) ?>" class="btn btn-sm btn-vs-secondary" title="Ver agenda médica">
                                    <i class="bi bi-calendar-check"></i>
                                </a>
                                <?php if ($canEdit): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            onclick='editarMedico(<?= json_encode($m, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)' title="Editar médico">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <form method="POST" action="<?= url('medicos/conmutar-estado') ?>" class="d-inline"
                                          data-confirm="¿Cambiar estado del médico <?= e($m['nombres'] . ' ' . $m['apellidos']) ?>? (Un médico inactivo no recibe nuevas citas pero conserva su historial).">
                                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="id_medico" value="<?= (int) $m['id_medico'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $m['estado'] === 'Activo' ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                            <i class="bi <?= $m['estado'] === 'Activo' ? 'bi-person-slash' : 'bi-person-check' ?>"></i>
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

<?php if ($canEdit): ?>
<!-- MODAL NUEVO MÉDICO (sp_registrar_medico_completo) -->
<div class="modal fade" id="modalNuevoMedico" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" action="<?= url('medicos/guardar') ?>" class="modal-content needs-validation" novalidate>
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <div class="modal-header text-white" style="background:var(--vs-navy);">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-person-badge-fill me-1"></i> Registrar Médico y Especialidades (sp_registrar_medico_completo)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Matrícula Profesional (RN02) *</label>
                        <input type="text" name="registro_profesional" class="form-control text-uppercase" required placeholder="MP-SC-19850">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Nombres *</label>
                        <input type="text" name="nombres" class="form-control" required placeholder="Ej. Gabriel">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Apellidos *</label>
                        <input type="text" name="apellidos" class="form-control" required placeholder="Ej. Ortiz Roca">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold">Correo Institucional *</label>
                        <input type="email" name="correo" class="form-control" required placeholder="g.ortiz@vidasalud.com.bo">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Teléfono Celular *</label>
                        <input type="text" name="telefono" class="form-control" required placeholder="71099887">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Cuenta de Acceso *</label>
                        <select name="modo_cuenta" id="modoCuentaMed" class="form-select" onchange="toggleModoCuentaMedico()">
                            <option value="nueva">Crear nueva cuenta</option>
                            <?php if (!empty($usuariosSinMedico)): ?>
                                <option value="existente">Vincular existente</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div id="bloqueCuentaNuevaMed" class="col-12">
                        <div class="p-3 rounded-3 bg-light border">
                            <div class="row g-2">
                                <div class="col-md-5">
                                    <label class="form-label small fw-semibold">Nombre de Usuario *</label>
                                    <input type="text" name="nombre_usuario" id="med_new_user" class="form-control form-control-sm" required placeholder="gabriel.ortiz">
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label small fw-semibold">Contraseña Inicial *</label>
                                    <input type="password" name="password" id="med_new_pwd" class="form-control form-control-sm" required
                                           data-strict-password data-pwd-meter="medPwdMeter" value="Cher_4mi">
                                    <div id="medPwdMeter" class="d-flex flex-wrap gap-1 mt-1">
                                        <span class="pwd-rule-pill" data-rule="len">8+</span>
                                        <span class="pwd-rule-pill" data-rule="upper">A-Z</span>
                                        <span class="pwd-rule-pill" data-rule="lower">a-z</span>
                                        <span class="pwd-rule-pill" data-rule="num">0-9</span>
                                        <span class="pwd-rule-pill" data-rule="spec">Especial</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($usuariosSinMedico)): ?>
                        <div id="bloqueCuentaExistenteMed" class="col-12 d-none">
                            <label class="form-label small fw-semibold">Seleccionar Usuario con Rol Médico Libre</label>
                            <select name="id_usuario_existente" class="form-select">
                                <?php foreach ($usuariosSinMedico as $usm): ?>
                                    <option value="<?= (int) $usm['id_usuario'] ?>"><?= e($usm['nombre_completo']) ?> (<?= e($usm['nombre_usuario']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="col-12">
                        <label class="form-label small fw-semibold d-block">Especialidades Médicas Asignadas (Selección Múltiple) *</label>
                        <div class="row g-2">
                            <?php foreach ($especialidades as $esp): ?>
                                <div class="col-6 col-md-4">
                                    <label class="form-check p-2 border rounded-2 d-flex align-items-center gap-2 bg-white w-100">
                                        <input class="form-check-input ms-0 mt-0" type="checkbox" name="especialidades[]" value="<?= (int) $esp['id_especialidad'] ?>">
                                        <span class="small fw-semibold"><?= e($esp['nombre']) ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-vs-primary px-4">Registrar Médico</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR MÉDICO -->
<div class="modal fade" id="modalEditarMedico" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" action="<?= url('medicos/actualizar') ?>" class="modal-content needs-validation" novalidate>
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="id_medico" id="med_edit_id">
            <div class="modal-header text-white" style="background:var(--vs-blue);">
                <h5 class="modal-title fs-6 fw-bold">Editar Datos Profesionales y Especialidades</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Matrícula Profesional *</label>
                        <input type="text" name="registro_profesional" id="med_edit_registro" class="form-control text-uppercase" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Nombres *</label>
                        <input type="text" name="nombres" id="med_edit_nombres" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Apellidos *</label>
                        <input type="text" name="apellidos" id="med_edit_apellidos" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Correo Institucional *</label>
                        <input type="email" name="correo" id="med_edit_correo" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Teléfono *</label>
                        <input type="text" name="telefono" id="med_edit_telefono" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold d-block">Especialidades Asignadas *</label>
                        <div class="row g-2">
                            <?php foreach ($especialidades as $esp): ?>
                                <div class="col-6 col-md-4">
                                    <label class="form-check p-2 border rounded-2 d-flex align-items-center gap-2 bg-white w-100">
                                        <input class="form-check-input ms-0 mt-0 med-edit-esp-chk" type="checkbox" name="especialidades[]" value="<?= (int) $esp['id_especialidad'] ?>">
                                        <span class="small fw-semibold"><?= e($esp['nombre']) ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
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
function toggleModoCuentaMedico() {
    const modo = document.getElementById('modoCuentaMed').value;
    const bNueva = document.getElementById('bloqueCuentaNuevaMed');
    const bExist = document.getElementById('bloqueCuentaExistenteMed');
    const usrInp = document.getElementById('med_new_user');
    const pwdInp = document.getElementById('med_new_pwd');

    if (modo === 'existente') {
        bNueva.classList.add('d-none');
        if (bExist) bExist.classList.remove('d-none');
        usrInp.removeAttribute('required');
        pwdInp.removeAttribute('required');
        pwdInp.setAttribute('data-optional', '1');
    } else {
        bNueva.classList.remove('d-none');
        if (bExist) bExist.classList.add('d-none');
        usrInp.setAttribute('required', 'required');
        pwdInp.setAttribute('required', 'required');
        pwdInp.removeAttribute('data-optional');
    }
}

function editarMedico(m) {
    document.getElementById('med_edit_id').value = m.id_medico;
    document.getElementById('med_edit_registro').value = m.registro_profesional || '';
    document.getElementById('med_edit_nombres').value = m.nombres || '';
    document.getElementById('med_edit_apellidos').value = m.apellidos || '';
    document.getElementById('med_edit_correo').value = m.correo || '';
    document.getElementById('med_edit_telefono').value = m.telefono || '';

    const assigned = String(m.especialidades_ids_csv || '').split(',').map(s => s.trim());
    document.querySelectorAll('.med-edit-esp-chk').forEach(chk => {
        chk.checked = assigned.includes(chk.value);
    });

    new bootstrap.Modal(document.getElementById('modalEditarMedico')).show();
}
</script>
<?php endif; ?>
