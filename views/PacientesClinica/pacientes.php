<?php
declare(strict_types=1);
/** @var array $pacientes */
/** @var array $filters */
/** @var bool $canRegister */
/** @var bool $canEditFicha */
/** @var string $csrfToken */

$tiposSangre = ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'Desconocido'];
?>
<div class="vs-card">
    <div class="vs-card-header">
        <div>
            <h6 class="mb-0 fw-bold" style="color:var(--vs-navy);"><i class="bi bi-people-fill me-1"></i> Padrón Clínico de Pacientes y Ficha Médica</h6>
            <small class="text-muted">Gestión integral de expedientes clínicos, antecedentes médicos y contactos de emergencia</small>
        </div>
        <?php if ($canRegister): ?>
            <button type="button" class="btn btn-sm btn-vs-primary px-3" data-bs-toggle="modal" data-bs-target="#modalNuevoPaciente">
                <i class="bi bi-person-plus-fill me-1"></i> Registrar Paciente Completo
            </button>
        <?php endif; ?>
    </div>

    <div class="p-3 border-bottom bg-light bg-opacity-50">
        <form method="GET" action="<?= url('pacientes') ?>" class="row g-2 align-items-end">
            <input type="hidden" name="r" value="pacientes">
            <div class="col-12 col-md-5">
                <label class="form-label small fw-semibold mb-1">Buscador Instantáneo (CI, Código PAC-xxxxx, Nombres o Apellidos)</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-primary"></i></span>
                    <input type="text" name="q" value="<?= e($filters['q'] ?? '') ?>" class="form-control"
                           placeholder="Escriba CI (ej. 5894123), PAC-00001 o nombre..." data-table-filter="#tablaPacientes">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small fw-semibold mb-1">Grupo Sanguíneo</label>
                <select name="tipo_sangre" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($tiposSangre as $ts): ?>
                        <option value="<?= e($ts) ?>" <?= ($filters['tipo_sangre'] ?? '') === $ts ? 'selected' : '' ?>><?= e($ts) ?></option>
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
                <a href="<?= url('pacientes') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-vs table-hover table-mobile-cards mb-0" id="tablaPacientes">
            <thead>
                <tr>
                    <th>Código / CI</th>
                    <th>Paciente</th>
                    <th>Edad / Sexo</th>
                    <th>Perfil Clínico Preventivo</th>
                    <th>Teléfono / Emergencia</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones Clínicas</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pacientes)): ?>
                    <tr data-empty-row><td colspan="7" class="text-center py-4 text-muted">No se encontraron pacientes registrados con ese criterio.</td></tr>
                <?php endif; ?>
                <?php foreach ($pacientes as $p): ?>
                    <?php
                    $tieneAlergia = stripos((string) $p['alergias'], 'ninguna') === false && trim((string) $p['alergias']) !== '';
                    ?>
                    <tr>
                        <td data-label="Código / CI">
                            <div><code class="fw-bold"><?= e($p['codigo_paciente']) ?></code></div>
                            <span class="small text-muted">CI: <strong><?= e($p['ci']) ?></strong></span>
                        </td>
                        <td data-label="Paciente">
                            <div class="fw-bold" style="color:var(--vs-navy);"><?= e($p['nombres'] . ' ' . $p['apellidos']) ?></div>
                            <div class="small text-muted text-truncate" style="max-width:210px;"><?= e($p['correo'] ?: $p['direccion'] ?: '-') ?></div>
                        </td>
                        <td data-label="Edad / Sexo" class="small">
                            <span class="fw-semibold"><?= (int) $p['edad'] ?> años</span> ·
                            <span class="badge bg-light text-dark border"><?= e($p['sexo']) ?></span>
                        </td>
                        <td data-label="Ficha Médica">
                            <span class="badge rounded-pill text-bg-danger me-1"><i class="bi bi-droplet-fill"></i> <?= e($p['tipo_sangre']) ?></span>
                            <?php if ($tieneAlergia): ?>
                                <span class="badge rounded-pill text-bg-warning" title="Alergias: <?= e($p['alergias']) ?>">
                                    <i class="bi bi-exclamation-triangle-fill"></i> Alergia
                                </span>
                            <?php else: ?>
                                <span class="small text-muted">Sin alergias</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Contacto" class="small">
                            <div><i class="bi bi-telephone-fill text-primary"></i> <?= e($p['telefono']) ?></div>
                            <?php if (!empty($p['contacto_nombre'])): ?>
                                <div class="text-muted" style="font-size:0.75rem;">Aux: <?= e($p['contacto_nombre']) ?> (<?= e($p['contacto_telefono']) ?>)</div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Estado">
                            <span class="badge rounded-pill <?= $p['estado'] === 'Activo' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                <?= e($p['estado']) ?>
                            </span>
                        </td>
                        <td data-label="Acciones" class="text-end">
                            <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                <?php if ($p['estado'] === 'Activo' && $canRegister): ?>
                                    <a href="<?= url('citas/programar', ['id_paciente' => $p['id_paciente']]) ?>" class="btn btn-sm btn-vs-primary" title="Programar cita a este paciente">
                                        <i class="bi bi-calendar2-plus"></i>
                                    </a>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-vs-secondary"
                                        onclick='abrirModalFicha(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)' title="Ver / Editar Ficha Médica Preventiva">
                                    <i class="bi bi-heart-pulse-fill text-danger"></i> Ficha
                                </button>
                                <a href="<?= url('historial', ['id_paciente' => $p['id_paciente']]) ?>" class="btn btn-sm btn-outline-dark" title="Historial clínico de citas">
                                    <i class="bi bi-clock-history"></i>
                                </a>
                                <?php if ($canRegister): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            onclick='abrirModalDatosPaciente(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)' title="Editar datos demográficos">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <form method="POST" action="<?= url('pacientes/conmutar-estado') ?>" class="d-inline"
                                          data-confirm="¿Desea cambiar el estado del paciente <?= e($p['codigo_paciente']) ?>?">
                                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="id_paciente" value="<?= (int) $p['id_paciente'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $p['estado'] === 'Activo' ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                            <i class="bi <?= $p['estado'] === 'Activo' ? 'bi-person-slash' : 'bi-person-check' ?>"></i>
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

<?php if ($canRegister): ?>
<!-- MODAL REGISTRO INTEGRAL EN 3 PESTAÑAS (sp_registrar_paciente_completo) -->
<div class="modal fade" id="modalNuevoPaciente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" action="<?= url('pacientes/guardar') ?>" class="modal-content needs-validation" novalidate>
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <div class="modal-header text-white" style="background:var(--vs-navy);">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-person-vcard-fill me-1"></i> Registrar Nuevo Paciente</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-pills nav-fill mb-3 p-1 rounded-3 bg-light" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active small fw-semibold" data-bs-toggle="tab" data-bs-target="#tabDemograficos" type="button">
                            1. Datos Demográficos
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link small fw-semibold" data-bs-toggle="tab" data-bs-target="#tabFichaPreventiva" type="button">
                            2. Ficha Médica Preventiva
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link small fw-semibold" data-bs-toggle="tab" data-bs-target="#tabEmergencia" type="button">
                            3. Contacto de Emergencia
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Pestaña 1 -->
                    <div class="tab-pane fade show active" id="tabDemograficos">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Cédula de Identidad (CI) *</label>
                                <input type="text" name="ci" class="form-control" required pattern="^[0-9]{5,12}(-[A-Za-z0-9]{1,3})?$" placeholder="Ej. 8451230">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Nombres *</label>
                                <input type="text" name="nombres" class="form-control" required minlength="2" placeholder="Ej. Valeria">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Apellidos *</label>
                                <input type="text" name="apellidos" class="form-control" required minlength="2" placeholder="Ej. Saucedo Paz">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Fecha de Nacimiento *</label>
                                <input type="date" name="fecha_nacimiento" max="<?= date('Y-m-d') ?>" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">Sexo *</label>
                                <select name="sexo" class="form-select" required>
                                    <option value="F">Femenino (F)</option>
                                    <option value="M">Masculino (M)</option>
                                    <option value="O">Otro (O)</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small fw-semibold">Teléfono / Celular *</label>
                                <input type="text" name="telefono" class="form-control" required placeholder="70011223">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Correo Electrónico</label>
                                <input type="email" name="correo" class="form-control" placeholder="paciente@gmail.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Dirección Domiciliaria</label>
                                <input type="text" name="direccion" class="form-control" placeholder="Barrio / Avenida / Calle #">
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch mt-1">
                                    <input class="form-check-input" type="checkbox" id="chkPortalPac" name="crear_cuenta_portal" value="1" onchange="document.getElementById('divPortalPac').classList.toggle('d-none', !this.checked)">
                                    <label class="form-check-label small fw-semibold" for="chkPortalPac">Habilitar también credenciales de Autoservicio Web (Rol Paciente)</label>
                                </div>
                                <div id="divPortalPac" class="row g-2 mt-1 p-2 rounded bg-light border d-none">
                                    <div class="col-md-6">
                                        <label class="form-label small mb-1">Usuario Portal</label>
                                        <input type="text" name="portal_usuario" class="form-control form-control-sm" placeholder="valeria.saucedo">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small mb-1">Contraseña Inicial</label>
                                        <input type="password" name="portal_password" class="form-control form-control-sm" placeholder="Mínimo 8 caracteres seguros">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pestaña 2 -->
                    <div class="tab-pane fade" id="tabFichaPreventiva">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Grupo Sanguíneo *</label>
                                <select name="tipo_sangre" class="form-select">
                                    <?php foreach ($tiposSangre as $ts): ?>
                                        <option value="<?= e($ts) ?>" <?= $ts === 'O+' ? 'selected' : '' ?>><?= e($ts) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small fw-semibold text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Alergias Medicamentosas / Alimentarias</label>
                                <input type="text" name="alergias" class="form-control" placeholder="Ninguna conocida (o ej. Penicilina, AINEs)">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Enfermedades Crónicas de Base</label>
                                <textarea name="enfermedades_cronicas" class="form-control" rows="2" placeholder="Ninguna reportada (o ej. Hipertensión, Asma)"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Medicación Habitual Actual</label>
                                <textarea name="medicacion_actual" class="form-control" rows="2" placeholder="Ninguna"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Observaciones Preventivas Adicionales</label>
                                <input type="text" name="observaciones" class="form-control" placeholder="Antecedentes relevantes para el médico...">
                            </div>
                        </div>
                    </div>

                    <!-- Pestaña 3 -->
                    <div class="tab-pane fade" id="tabEmergencia">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label small fw-semibold">Nombre del Contacto de Emergencia</label>
                                <input type="text" name="contacto_nombre" class="form-control" placeholder="Ej. María Paz de Saucedo">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">Parentesco</label>
                                <input type="text" name="contacto_parentesco" class="form-control" placeholder="Madre / Esposo">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Teléfono de Auxilio</label>
                                <input type="text" name="contacto_telefono" class="form-control" placeholder="71122334">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <span class="small text-muted"><i class="bi bi-cpu"></i> Autogenera código <code>PAC-xxxxx</code></span>
                <div>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-vs-primary px-4"><i class="bi bi-check2-circle me-1"></i> Registrar Paciente</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR DATOS DEMOGRÁFICOS Y CONTACTO -->
<div class="modal fade" id="modalEditarPaciente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" action="<?= url('pacientes/actualizar-datos') ?>" class="modal-content needs-validation" novalidate>
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="id_paciente" id="pac_edit_id">
            <div class="modal-header text-white" style="background:var(--vs-blue);">
                <h5 class="modal-title fs-6 fw-bold">Editar Datos Demográficos y Contacto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">CI *</label>
                        <input type="text" name="ci" id="pac_edit_ci" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Nombres *</label>
                        <input type="text" name="nombres" id="pac_edit_nombres" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Apellidos *</label>
                        <input type="text" name="apellidos" id="pac_edit_apellidos" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Fecha Nacimiento *</label>
                        <input type="date" name="fecha_nacimiento" id="pac_edit_fnac" max="<?= date('Y-m-d') ?>" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Sexo *</label>
                        <select name="sexo" id="pac_edit_sexo" class="form-select" required>
                            <option value="M">M</option>
                            <option value="F">F</option>
                            <option value="O">O</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold">Teléfono *</label>
                        <input type="text" name="telefono" id="pac_edit_telefono" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Correo</label>
                        <input type="email" name="correo" id="pac_edit_correo" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Dirección</label>
                        <input type="text" name="direccion" id="pac_edit_direccion" class="form-control">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold">Contacto Emergencia</label>
                        <input type="text" name="contacto_nombre" id="pac_edit_cnom" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Parentesco</label>
                        <input type="text" name="contacto_parentesco" id="pac_edit_cpar" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Tel. Emergencia</label>
                        <input type="text" name="contacto_telefono" id="pac_edit_ctel" class="form-control">
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
<?php endif; ?>

<!-- MODAL FICHA MÉDICA PREVENTIVA (CALL sp_actualizar_ficha_medica) -->
<div class="modal fade" id="modalFichaMedica" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" action="<?= url('pacientes/actualizar-ficha') ?>" class="modal-content">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="id_paciente" id="ficha_id_paciente">
            <div class="modal-header text-white" style="background:var(--vs-navy);">
                <h5 class="modal-title fs-6 fw-bold">
                    <i class="bi bi-heart-pulse-fill text-danger me-1"></i> Ficha Médica Preventiva · <span id="fichaHeaderNombre"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Grupo Sanguíneo *</label>
                        <select name="tipo_sangre" id="ficha_tipo_sangre" class="form-select">
                            <?php foreach ($tiposSangre as $ts): ?>
                                <option value="<?= e($ts) ?>"><?= e($ts) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold text-danger"><i class="bi bi-exclamation-octagon-fill"></i> Alergias Conocidas</label>
                        <input type="text" name="alergias" id="ficha_alergias" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Enfermedades Crónicas</label>
                        <textarea name="enfermedades_cronicas" id="ficha_cronicas" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Medicación Actual</label>
                        <textarea name="medicacion_actual" id="ficha_medicacion" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Observaciones Clínicas</label>
                        <textarea name="observaciones" id="ficha_obs" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <span class="small text-muted">Los datos se guardarán en el expediente clínico del paciente</span>
                <div>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                    <?php if ($canEditFicha): ?>
                        <button type="submit" class="btn btn-vs-primary px-4">Actualizar Ficha</button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalFicha(p) {
    document.getElementById('ficha_id_paciente').value = p.id_paciente;
    document.getElementById('fichaHeaderNombre').textContent = p.codigo_paciente + ' — ' + p.nombres + ' ' + p.apellidos;
    document.getElementById('ficha_tipo_sangre').value = p.tipo_sangre || 'Desconocido';
    document.getElementById('ficha_alergias').value = p.alergias || '';
    document.getElementById('ficha_cronicas').value = p.enfermedades_cronicas || '';
    document.getElementById('ficha_medicacion').value = p.medicacion_actual || '';
    document.getElementById('ficha_obs').value = p.observaciones || '';
    new bootstrap.Modal(document.getElementById('modalFichaMedica')).show();
}

function abrirModalDatosPaciente(p) {
    document.getElementById('pac_edit_id').value = p.id_paciente;
    document.getElementById('pac_edit_ci').value = p.ci || '';
    document.getElementById('pac_edit_nombres').value = p.nombres || '';
    document.getElementById('pac_edit_apellidos').value = p.apellidos || '';
    document.getElementById('pac_edit_fnac').value = p.fecha_nacimiento || '';
    document.getElementById('pac_edit_sexo').value = p.sexo || 'M';
    document.getElementById('pac_edit_telefono').value = p.telefono || '';
    document.getElementById('pac_edit_correo').value = p.correo || '';
    document.getElementById('pac_edit_direccion').value = p.direccion || '';
    document.getElementById('pac_edit_cnom').value = p.contacto_nombre || '';
    document.getElementById('pac_edit_cpar').value = p.contacto_parentesco || '';
    document.getElementById('pac_edit_ctel').value = p.contacto_telefono || '';
    new bootstrap.Modal(document.getElementById('modalEditarPaciente')).show();
}
</script>
