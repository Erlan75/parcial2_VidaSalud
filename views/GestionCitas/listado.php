<?php
declare(strict_types=1);
/** @var array $citas */
/** @var array $especialidades */
/** @var array $medicos */
/** @var array $filters */
/** @var array|null $currentUser */
/** @var string $csrfToken */

$rol = $currentUser['rol'] ?? '';
$puedeAgendar = in_array($rol, ['Administrador', 'Recepcionista', 'Paciente'], true);
$puedeAsistencia = in_array($rol, ['Administrador', 'Recepcionista'], true);
$puedeAtender = in_array($rol, ['Médico', 'Administrador'], true);
?>
<div class="vs-card">
    <div class="vs-card-header">
        <div>
            <h6 class="mb-0 fw-bold" style="color:var(--vs-navy);"><i class="bi bi-journal-medical me-1"></i> <?= $rol === 'Paciente' ? 'Mis Citas Reservadas y Comprobantes QR' : 'Gestión Integral de Citas Médicas (CU09, CU10, CU11, CU15)' ?></h6>
            <small class="text-muted">Reprogramación libre de colisiones, cancelación documentada y descarga de Ticket PDF con Código QR</small>
        </div>
        <?php if ($puedeAgendar): ?>
            <a href="<?= url('citas/programar') ?>" class="btn btn-sm btn-vs-primary px-3">
                <i class="bi bi-calendar2-plus-fill me-1"></i> Nueva Cita Médica
            </a>
        <?php endif; ?>
    </div>

    <div class="p-3 border-bottom bg-light bg-opacity-50">
        <form method="GET" action="<?= url('citas') ?>" class="row g-2 align-items-end">
            <input type="hidden" name="r" value="citas">
            <div class="col-12 col-md-3">
                <label class="form-label small fw-semibold mb-1">Buscar</label>
                <input type="text" name="q" value="<?= e($filters['q'] ?? '') ?>" class="form-control form-control-sm"
                       placeholder="CM-xxxxx, paciente o CI..." data-table-filter="#tablaCitas">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Desde</label>
                <input type="date" name="fecha_desde" value="<?= e($filters['fecha_desde'] ?? '') ?>" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Hasta</label>
                <input type="date" name="fecha_hasta" value="<?= e($filters['fecha_hasta'] ?? '') ?>" class="form-control form-control-sm">
            </div>
            <?php if ($rol !== 'Paciente'): ?>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold mb-1">Especialidad</label>
                    <select name="id_especialidad" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <?php foreach ($especialidades as $esp): ?>
                            <option value="<?= (int) $esp['id_especialidad'] ?>" <?= ((string) ($filters['id_especialidad'] ?? '') === (string) $esp['id_especialidad']) ? 'selected' : '' ?>>
                                <?= e($esp['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach (['Programada', 'Confirmada', 'Atendida', 'Cancelada', 'Inasistencia'] as $est): ?>
                        <option value="<?= $est ?>" <?= ($filters['estado'] ?? '') === $est ? 'selected' : '' ?>><?= $est ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-vs-primary w-100" title="Filtrar"><i class="bi bi-search"></i></button>
                <a href="<?= url('citas') ?>" class="btn btn-sm btn-outline-secondary" title="Limpiar"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-vs table-hover table-mobile-cards mb-0" id="tablaCitas">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Fecha / Hora</th>
                    <th>Paciente</th>
                    <th>Médico / Especialidad</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones y Ticket PDF/QR</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($citas)): ?>
                    <tr data-empty-row><td colspan="6" class="text-center py-4 text-muted">No se encontraron citas médicas registradas con los filtros aplicados.</td></tr>
                <?php endif; ?>
                <?php foreach ($citas as $c): ?>
                    <?php
                    $esActiva = in_array($c['estado'], ['Programada', 'Confirmada'], true);
                    ?>
                    <tr>
                        <td data-label="Código">
                            <code class="fw-bold"><?= e($c['codigo_cita']) ?></code>
                        </td>
                        <td data-label="Fecha y Hora">
                            <div class="fw-bold" style="color:var(--vs-navy);"><?= date('d/m/Y', strtotime((string) $c['fecha_cita'])) ?></div>
                            <span class="small text-muted"><i class="bi bi-clock"></i> <?= e(substr((string) $c['hora_inicio'], 0, 5)) ?> - <?= e(substr((string) $c['hora_fin'], 0, 5)) ?></span>
                        </td>
                        <td data-label="Paciente">
                            <div class="fw-semibold"><?= e($c['paciente_nombre']) ?></div>
                            <span class="small text-muted"><?= e($c['codigo_paciente']) ?> · CI: <?= e($c['paciente_ci']) ?></span>
                        </td>
                        <td data-label="Médico / Especialidad">
                            <div class="fw-semibold small">Dr(a). <?= e($c['medico_nombre']) ?></div>
                            <span class="badge rounded-pill" style="background:var(--vs-sky);color:var(--vs-navy);font-size:0.72rem;"><?= e($c['especialidad_nombre']) ?></span>
                        </td>
                        <td data-label="Estado">
                            <?= badge_estado_cita((string) $c['estado']) ?>
                        </td>
                        <td data-label="Acciones" class="text-end">
                            <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                <!-- CU15: Ticket PDF con Código QR -->
                                <a href="<?= url('exportar/comprobante', ['id_cita' => $c['id_cita']]) ?>" target="_blank"
                                   class="btn btn-sm btn-vs-secondary" title="Descargar Comprobante PDF con Código QR (CU15)">
                                    <i class="bi bi-qr-code"></i> PDF
                                </a>

                                <?php if ($esActiva && $puedeAsistencia): ?>
                                    <?php if ($c['estado'] === 'Programada'): ?>
                                        <form method="POST" action="<?= url('citas/asistencia') ?>" class="d-inline">
                                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                            <input type="hidden" name="id_cita" value="<?= (int) $c['id_cita'] ?>">
                                            <input type="hidden" name="nuevo_estado" value="Confirmada">
                                            <input type="hidden" name="return_to" value="citas">
                                            <button type="submit" class="btn btn-sm btn-warning" title="Confirmar presencia en sala (CU11)">
                                                <i class="bi bi-person-check-fill"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" action="<?= url('citas/asistencia') ?>" class="d-inline"
                                          data-confirm="¿Marcar Inasistencia del paciente para la cita <?= e($c['codigo_cita']) ?>?">
                                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="id_cita" value="<?= (int) $c['id_cita'] ?>">
                                        <input type="hidden" name="nuevo_estado" value="Inasistencia">
                                        <input type="hidden" name="return_to" value="citas">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Marcar Inasistencia (CU11)">
                                            <i class="bi bi-person-x"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($esActiva && $puedeAtender && ($rol === 'Administrador' || (int) $c['id_medico'] === (int) ($currentUser['id_medico'] ?? 0))): ?>
                                    <button type="button" class="btn btn-sm btn-success"
                                            onclick='abrirModalConsultorio(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>, "citas")'
                                            title="Atender consulta médica (CU11)">
                                        <i class="bi bi-clipboard2-pulse-fill"></i> Atender
                                    </button>
                                <?php endif; ?>

                                <?php if ($esActiva && $puedeAgendar): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            onclick='abrirModalReprogramar(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>, "citas")'
                                            title="Reprogramar fecha/hora (CU09)">
                                        <i class="bi bi-calendar2-range"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            onclick='abrirModalCancelar(<?= (int) $c["id_cita"] ?>, <?= json_encode($c["codigo_cita"]) ?>, "citas")'
                                            title="Cancelar cita con motivo (CU10)">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL REPROGRAMAR CITA (CU09 · sp_reprogramar_cita + fn_obtener_intervalos_disponibles) -->
<div class="modal fade" id="modalReprogramarCita" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" action="<?= url('citas/reprogramar') ?>" class="modal-content" id="formReprogramar">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="id_cita" id="rep_id_cita">
            <input type="hidden" name="nueva_hora_inicio" id="rep_nueva_hora">
            <input type="hidden" name="return_to" id="rep_return_to" value="citas">
            <div class="modal-header text-white" style="background:var(--vs-navy);">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-calendar2-range me-1"></i> Reprogramar Cita Médica (CU09 · sp_reprogramar_cita)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="p-2 px-3 rounded-3 bg-light border mb-3 small" id="repInfoActual"></div>
                <div class="row g-3 mb-3">
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold">Seleccione Nueva Fecha *</label>
                        <input type="date" name="nueva_fecha" id="rep_nueva_fecha" min="<?= date('Y-m-d') ?>" class="form-control" required onchange="cargarSlotsReprogramacion()">
                    </div>
                    <div class="col-md-7 d-flex align-items-end">
                        <div class="small text-muted" id="repSlotElegido">Seleccione un bloque verde de 30 minutos para confirmar el cambio.</div>
                    </div>
                </div>
                <div id="repSlotsContainer" class="p-3 border rounded-3 bg-light bg-opacity-50" style="min-height:140px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                <button type="submit" id="btnSubmitReprogramar" class="btn btn-vs-primary px-4" disabled>Confirmar Reprogramación</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL CANCELAR CITA (CU10 · sp_cancelar_cita) -->
<div class="modal fade" id="modalCancelarCita" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= url('citas/cancelar') ?>" class="modal-content needs-validation" novalidate>
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="id_cita" id="canc_id_cita">
            <input type="hidden" name="return_to" id="canc_return_to" value="citas">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-x-octagon-fill me-1"></i> Cancelar Cita Médica <span id="cancCodigoLbl"></span> (CU10)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">
                    De acuerdo con <strong>RN09 y RN11</strong>, la cita no se elimina físicamente: se documenta la causa en <code>cancelaciones_cita</code>, se audita el evento <code>CANCEL</code> y se libera inmediatamente el horario del médico.
                </p>
                <div class="mb-0">
                    <label class="form-label small fw-semibold">Motivo Obligatorio de Cancelación (mínimo 5 caracteres) *</label>
                    <textarea name="motivo_cancelacion" class="form-control" rows="3" required minlength="5"
                              placeholder="Ej. Paciente solicita cancelación por viaje laboral impostergable..."></textarea>
                    <div class="invalid-feedback">Ingrese al menos 5 caracteres explicando el motivo.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Volver</button>
                <button type="submit" class="btn btn-danger px-4">Confirmar Cancelación</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL ATENCIÓN EN CONSULTORIO MÉDICO (CU11 · sp_registrar_atencion_medica) -->
<div class="modal fade" id="modalConsultorio" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" action="<?= url('citas/atender') ?>" class="modal-content needs-validation" novalidate>
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="id_cita" id="at_id_cita">
            <input type="hidden" name="return_to" id="at_return_to" value="citas">
            <div class="modal-header text-white" style="background:#198754;">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-clipboard2-pulse-fill me-1"></i> Atención Médica en Consultorio (CU11 · sp_registrar_atencion_medica)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger border-0 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong id="atPacHeader"></strong>
                        <span class="badge text-bg-danger" id="atPacSangre"></span>
                    </div>
                    <div class="small"><strong><i class="bi bi-exclamation-triangle-fill"></i> Alergias:</strong> <span id="atPacAlergias"></span></div>
                    <div class="small"><strong>Enfermedades Crónicas:</strong> <span id="atPacCronicas"></span> · <strong>Medicación Habitual:</strong> <span id="atPacMedicacion"></span></div>
                </div>

                <div class="mb-2 small text-muted"><strong>Motivo de consulta registrado:</strong> <span id="atMotivoConsulta"></span></div>

                <div class="mb-0">
                    <label class="form-label small fw-semibold">Diagnóstico Clínico, Evolución e Indicaciones Terapéuticas (<code>notas_medicas</code>) *</label>
                    <textarea name="notas_medicas" class="form-control" rows="4" required minlength="5"
                              placeholder="Registre hallazgos del examen físico, diagnóstico CIE/clínico y tratamiento indicado..."></textarea>
                    <div class="form-text text-muted">
                        <i class="bi bi-lock-fill"></i> Al sellar la consulta como <strong>Atendida</strong>, el registro quedará protegido de forma irreversible (RN10).
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success px-4 fw-semibold"><i class="bi bi-check2-circle me-1"></i> Sellar Consulta como Atendida</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentRepMedicoId = 0;

function abrirModalReprogramar(c, returnTo) {
    currentRepMedicoId = c.id_medico;
    document.getElementById('rep_id_cita').value = c.id_cita;
    document.getElementById('rep_return_to').value = returnTo || 'citas';
    document.getElementById('rep_nueva_fecha').value = c.fecha_cita;
    document.getElementById('rep_nueva_hora').value = '';
    document.getElementById('btnSubmitReprogramar').disabled = true;
    document.getElementById('repInfoActual').innerHTML =
        '<strong>' + c.codigo_cita + '</strong> · Paciente: <strong>' + c.paciente_nombre + '</strong> · Médico: <strong>Dr(a). ' + c.medico_nombre + '</strong> (Actual: ' + c.fecha_cita + ' ' + String(c.hora_inicio).substring(0,5) + ')';
    new bootstrap.Modal(document.getElementById('modalReprogramarCita')).show();
    cargarSlotsReprogramacion();
}

async function cargarSlotsReprogramacion() {
    const fecha = document.getElementById('rep_nueva_fecha').value;
    const cont = document.getElementById('repSlotsContainer');
    document.getElementById('rep_nueva_hora').value = '';
    document.getElementById('btnSubmitReprogramar').disabled = true;

    if (!currentRepMedicoId || !fecha) return;
    cont.innerHTML = '<div class="text-center py-3 text-muted">Consultando intervalos libres...</div>';

    const r = await fetch('<?= url('citas/intervalos-ajax') ?>&id_medico=' + currentRepMedicoId + '&fecha=' + encodeURIComponent(fecha), {
        headers: { 'Accept': 'application/json' }
    });
    const data = await r.json();
    if (!data.ok || !data.slots || data.slots.length === 0) {
        cont.innerHTML = '<div class="alert alert-warning mb-0 small">' + (data.motivo_vacio || 'Sin turnos libres en esta fecha.') + '</div>';
        return;
    }

    const grid = document.createElement('div');
    grid.className = 'slot-grid';
    data.slots.forEach(s => {
        const free = s.disponible === true || s.disponible === 't' || s.disponible === 1;
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'slot-btn ' + (free ? 'available' : 'unavailable');
        b.disabled = !free;
        b.innerHTML = '<span>' + s.hora_inicio_fmt + ' - ' + s.hora_fin_fmt + '</span><small style="font-size:0.7rem;">' + (free ? 'Libre' : s.motivo_no_disponible) + '</small>';
        if (free) {
            b.onclick = () => {
                grid.querySelectorAll('.slot-btn.selected').forEach(x => x.classList.remove('selected'));
                b.classList.add('selected');
                document.getElementById('rep_nueva_hora').value = s.hora_inicio;
                document.getElementById('repSlotElegido').innerHTML = '<strong class="text-success">Nuevo horario: ' + fecha + ' a las ' + s.hora_inicio_fmt + '</strong>';
                document.getElementById('btnSubmitReprogramar').disabled = false;
            };
        }
        grid.appendChild(b);
    });
    cont.innerHTML = '';
    cont.appendChild(grid);
}

function abrirModalCancelar(idCita, codigo, returnTo) {
    document.getElementById('canc_id_cita').value = idCita;
    document.getElementById('cancCodigoLbl').textContent = codigo;
    document.getElementById('canc_return_to').value = returnTo || 'citas';
    new bootstrap.Modal(document.getElementById('modalCancelarCita')).show();
}

function abrirModalConsultorio(c, returnTo) {
    document.getElementById('at_id_cita').value = c.id_cita;
    document.getElementById('at_return_to').value = returnTo || 'citas';
    document.getElementById('atPacHeader').textContent = c.paciente_nombre + ' (' + c.codigo_paciente + ' · ' + (c.paciente_edad || '') + ' años)';
    document.getElementById('atPacSangre').textContent = 'Sangre: ' + (c.tipo_sangre || 'Desconocido');
    document.getElementById('atPacAlergias').textContent = c.alergias || 'Ninguna conocida';
    document.getElementById('atPacCronicas').textContent = c.enfermedades_cronicas || 'Ninguna reportada';
    document.getElementById('atPacMedicacion').textContent = c.medicacion_actual || 'Ninguna';
    document.getElementById('atMotivoConsulta').textContent = c.motivo_consulta || '-';
    new bootstrap.Modal(document.getElementById('modalConsultorio')).show();
}
</script>
