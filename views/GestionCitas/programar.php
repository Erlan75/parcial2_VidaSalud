<?php
declare(strict_types=1);
/** @var array $especialidades */
/** @var array|null $preselectedPaciente */
/** @var array $pacientesIniciales */
/** @var array|null $currentUser */
/** @var string $csrfToken */

$esPaciente = ($currentUser['rol'] ?? '') === 'Paciente';
$defaultDate = (date('N') === '7') ? date('Y-m-d', strtotime('+1 day')) : ((date('N') === '6') ? date('Y-m-d', strtotime('+2 days')) : date('Y-m-d', strtotime('+1 day')));
?>
<form method="POST" action="<?= url('citas/guardar') ?>" id="formProgramarCita" class="needs-validation" novalidate>
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="id_paciente" id="inpIdPaciente" value="<?= (int) ($preselectedPaciente['id_paciente'] ?? 0) ?>">
    <input type="hidden" name="hora_inicio" id="inpHoraInicio" value="">

    <div class="row g-4">
        <!-- COLUMNA IZQUIERDA: PASOS 1, 2 Y 3 -->
        <div class="col-12 col-lg-5">
            <!-- PASO 1: PACIENTE -->
            <div class="vs-card p-4 mb-3">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="badge rounded-circle p-2" style="background:var(--vs-navy);width:28px;height:28px;">1</span>
                    <h6 class="mb-0 fw-bold" style="color:var(--vs-navy);">Identificación del Paciente</h6>
                </div>

                <?php if ($esPaciente && $preselectedPaciente): ?>
                    <div class="p-3 rounded-3" style="background:var(--vs-sky-soft);border:1px solid var(--vs-sky);">
                        <div class="fw-bold" style="color:var(--vs-navy);"><?= e($preselectedPaciente['nombres'] . ' ' . $preselectedPaciente['apellidos']) ?></div>
                        <div class="small text-muted">
                            Código: <code><?= e($preselectedPaciente['codigo_paciente']) ?></code> · CI: <strong><?= e($preselectedPaciente['ci']) ?></strong> · Sangre: <span class="badge text-bg-danger"><?= e($preselectedPaciente['tipo_sangre']) ?></span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Buscar Paciente Activo (CI, PAC-xxxxx o Nombre) *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-search text-primary"></i></span>
                            <input type="text" id="buscadorPacienteAjax" class="form-control" placeholder="Escriba CI o nombre para buscar...">
                        </div>
                    </div>

                    <select id="selectPacienteRapido" class="form-select form-select-sm mb-2" onchange="seleccionarPacienteDesdeSelect(this)">
                        <option value="">-- O elija directamente del listado activo --</option>
                        <?php foreach ($pacientesIniciales as $pi): ?>
                            <option value="<?= (int) $pi['id_paciente'] ?>"
                                    data-label="<?= e($pi['codigo_paciente'] . ' · ' . $pi['nombres'] . ' ' . $pi['apellidos'] . ' (CI: ' . $pi['ci'] . ')') ?>"
                                    data-sangre="<?= e($pi['tipo_sangre']) ?>"
                                    data-alergias="<?= e($pi['alergias']) ?>"
                                    <?= ((int) ($preselectedPaciente['id_paciente'] ?? 0) === (int) $pi['id_paciente']) ? 'selected' : '' ?>>
                                <?= e($pi['codigo_paciente']) ?> — <?= e($pi['nombres'] . ' ' . $pi['apellidos']) ?> (CI: <?= e($pi['ci']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div id="resultadosPacienteAjax" class="list-group small mb-2"></div>

                    <div id="cardPacienteSeleccionado" class="p-3 rounded-3 <?= $preselectedPaciente ? '' : 'd-none' ?>" style="background:var(--vs-sky-soft);border:1px solid var(--vs-sky);">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-bold small" style="color:var(--vs-navy);" id="lblPacNombre">
                                    <?= $preselectedPaciente ? e($preselectedPaciente['codigo_paciente'] . ' · ' . $preselectedPaciente['nombres'] . ' ' . $preselectedPaciente['apellidos']) : '' ?>
                                </div>
                                <div class="small text-muted mt-1" id="lblPacExtra">
                                    <?= $preselectedPaciente ? 'Sangre: ' . e($preselectedPaciente['tipo_sangre']) . ' · Alergias: ' . e($preselectedPaciente['alergias']) : '' ?>
                                </div>
                            </div>
                            <span class="badge text-bg-success"><i class="bi bi-check-lg"></i></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- PASO 2 Y 3: ESPECIALIDAD Y MÉDICO -->
            <div class="vs-card p-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="badge rounded-circle p-2" style="background:var(--vs-navy);width:28px;height:28px;">2</span>
                    <h6 class="mb-0 fw-bold" style="color:var(--vs-navy);">Especialidad y Médico Tratante</h6>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Especialidad Médica *</label>
                    <select name="id_especialidad" id="selEspecialidad" class="form-select" required onchange="cargarMedicosPorEspecialidad()">
                        <option value="">Seleccione especialidad...</option>
                        <?php foreach ($especialidades as $esp): ?>
                            <option value="<?= (int) $esp['id_especialidad'] ?>"><?= e($esp['nombre']) ?> (<?= e($esp['codigo_especialidad']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Médico Activo Asignado *</label>
                    <select name="id_medico" id="selMedico" class="form-select" required disabled onchange="onMedicoChange()">
                        <option value="">Primero elija una especialidad...</option>
                    </select>
                    <div id="infoTurnosMedico" class="form-text text-primary fw-semibold mt-1"></div>
                </div>

                <div class="mb-0">
                    <label class="form-label small fw-semibold">Motivo de Consulta *</label>
                    <textarea name="motivo_consulta" id="inpMotivo" class="form-control" rows="2" required minlength="4"
                              placeholder="Describa brevemente los síntomas o motivo del control médico..."></textarea>
                </div>
            </div>
        </div>

        <!-- COLUMNA DERECHA: PASO 4 (CALENDARIO + GRILLA DE 30 MIN fn_obtener_intervalos_disponibles) -->
        <div class="col-12 col-lg-7">
            <div class="vs-card p-4 h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge rounded-circle p-2" style="background:var(--vs-navy);width:28px;height:28px;">3</span>
                            <div>
                                <h6 class="mb-0 fw-bold" style="color:var(--vs-navy);">Fecha y Bloques de 30 Minutos Disponibles</h6>
                                <small class="text-muted">Consultado en vivo desde <code>fn_obtener_intervalos_disponibles(:id_medico, :fecha)</code></small>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-semibold mb-1">Fecha de la Cita *</label>
                            <input type="date" name="fecha_cita" id="inpFechaCita" min="<?= date('Y-m-d') ?>" value="<?= e($defaultDate) ?>"
                                   class="form-control" required onchange="cargarIntervalosDisponibles()">
                        </div>
                        <div class="col-12 col-sm-6">
                            <div class="d-flex gap-2 small">
                                <span class="badge bg-success-subtle text-success border border-success"><i class="bi bi-check-circle"></i> Libre (30m)</span>
                                <span class="badge bg-secondary-subtle text-secondary border"><i class="bi bi-lock"></i> Ocupado / Pasado</span>
                            </div>
                        </div>
                    </div>

                    <div id="contenedorIntervalos" class="p-3 rounded-3 border bg-light bg-opacity-50" style="min-height:230px;">
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-calendar2-week display-6 d-block mb-2 opacity-50"></i>
                            Seleccione una <strong>Especialidad</strong> y un <strong>Médico</strong> para consultar su grilla de intervalos de 30 minutos.
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div class="small" id="resumenSeleccionHorario">
                        <span class="text-muted">Ningún bloque horario seleccionado aún.</span>
                    </div>
                    <button type="submit" id="btnConfirmarCita" class="btn btn-vs-primary px-4 py-2 shadow-sm" disabled>
                        <i class="bi bi-calendar2-check-fill me-1"></i> Confirmar y Programar Cita
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
const URL_PAC_AJAX = '<?= url('pacientes/buscar-ajax') ?>';
const URL_MED_AJAX = '<?= url('citas/medicos-ajax') ?>';
const URL_SLOTS_AJAX = '<?= url('citas/intervalos-ajax') ?>';

let debouncePac = null;
const inpBuscarPac = document.getElementById('buscadorPacienteAjax');
if (inpBuscarPac) {
    inpBuscarPac.addEventListener('input', function () {
        clearTimeout(debouncePac);
        const q = this.value.trim();
        const box = document.getElementById('resultadosPacienteAjax');
        if (q.length < 2) {
            box.innerHTML = '';
            return;
        }
        debouncePac = setTimeout(async () => {
            const r = await fetch(URL_PAC_AJAX + '&q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json' }
            });
            const data = await r.json();
            box.innerHTML = '';
            if (data.ok && data.pacientes.length > 0) {
                data.pacientes.forEach(p => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
                    btn.innerHTML = '<div><strong>' + p.codigo_paciente + '</strong> · ' + p.nombres + ' ' + p.apellidos + ' <span class="text-muted">(CI: ' + p.ci + ')</span></div><span class="badge text-bg-danger">' + p.tipo_sangre + '</span>';
                    btn.onclick = () => fijarPaciente(p.id_paciente, p.codigo_paciente + ' · ' + p.nombres + ' ' + p.apellidos + ' (CI: ' + p.ci + ')', 'Sangre: ' + p.tipo_sangre + ' · Alergias: ' + p.alergias);
                    box.appendChild(btn);
                });
            } else {
                box.innerHTML = '<div class="list-group-item text-muted">Sin coincidencias activas.</div>';
            }
        }, 240);
    });
}

function seleccionarPacienteDesdeSelect(sel) {
    const opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.value) return;
    fijarPaciente(opt.value, opt.getAttribute('data-label'), 'Sangre: ' + opt.getAttribute('data-sangre') + ' · Alergias: ' + opt.getAttribute('data-alergias'));
}

function fijarPaciente(id, label, extra) {
    document.getElementById('inpIdPaciente').value = id;
    document.getElementById('lblPacNombre').textContent = label;
    document.getElementById('lblPacExtra').textContent = extra;
    document.getElementById('cardPacienteSeleccionado').classList.remove('d-none');
    const box = document.getElementById('resultadosPacienteAjax');
    if (box) box.innerHTML = '';
    actualizarEstadoBotonConfirmar();
}

async function cargarMedicosPorEspecialidad() {
    const idEsp = document.getElementById('selEspecialidad').value;
    const selMed = document.getElementById('selMedico');
    const infoTurnos = document.getElementById('infoTurnosMedico');
    limpiarSeleccionSlot();

    if (!idEsp) {
        selMed.innerHTML = '<option value="">Primero elija una especialidad...</option>';
        selMed.disabled = true;
        infoTurnos.textContent = '';
        return;
    }

    selMed.innerHTML = '<option value="">Cargando médicos activos...</option>';
    selMed.disabled = true;

    const r = await fetch(URL_MED_AJAX + '&id_especialidad=' + encodeURIComponent(idEsp), {
        headers: { 'Accept': 'application/json' }
    });
    const data = await r.json();
    selMed.innerHTML = '<option value="">Seleccione al médico...</option>';

    if (data.ok && data.medicos.length > 0) {
        data.medicos.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.id_medico;
            opt.textContent = 'Dr(a). ' + m.nombres + ' ' + m.apellidos + ' (' + m.codigo_medico + ')';
            opt.setAttribute('data-horarios', m.resumen_horarios || '');
            selMed.appendChild(opt);
        });
        selMed.disabled = false;
        if (data.medicos.length === 1) {
            selMed.selectedIndex = 1;
            onMedicoChange();
        }
    } else {
        selMed.innerHTML = '<option value="">No hay médicos activos en esta especialidad</option>';
    }
}

function onMedicoChange() {
    const selMed = document.getElementById('selMedico');
    const opt = selMed.options[selMed.selectedIndex];
    const info = document.getElementById('infoTurnosMedico');
    info.textContent = opt && opt.value ? ('Turnos del médico: ' + (opt.getAttribute('data-horarios') || '')) : '';
    cargarIntervalosDisponibles();
}

async function cargarIntervalosDisponibles() {
    limpiarSeleccionSlot();
    const idMedico = document.getElementById('selMedico').value;
    const fecha = document.getElementById('inpFechaCita').value;
    const cont = document.getElementById('contenedorIntervalos');

    if (!idMedico || !fecha) {
        return;
    }

    cont.innerHTML = '<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm text-primary me-2"></div> Consultando disponibilidad en PostgreSQL...</div>';

    try {
        const r = await fetch(URL_SLOTS_AJAX + '&id_medico=' + encodeURIComponent(idMedico) + '&fecha=' + encodeURIComponent(fecha), {
            headers: { 'Accept': 'application/json' }
        });
        const data = await r.json();

        if (!data.ok) {
            cont.innerHTML = '<div class="alert alert-danger mb-0">' + (data.message || 'Error al obtener intervalos') + '</div>';
            return;
        }

        if (!data.slots || data.slots.length === 0) {
            cont.innerHTML = '<div class="alert alert-warning border-0 mb-0"><i class="bi bi-calendar-x-fill me-1"></i> <strong>Sin intervalos habilitados para esta fecha:</strong><br>' + (data.motivo_vacio || 'Elija otro día en el calendario.') + '</div>';
            return;
        }

        const grid = document.createElement('div');
        grid.className = 'slot-grid';

        data.slots.forEach(s => {
            const isFree = s.disponible === true || s.disponible === 't' || s.disponible === 1;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'slot-btn ' + (isFree ? 'available' : 'unavailable');
            btn.disabled = !isFree;
            btn.innerHTML = '<span>' + s.hora_inicio_fmt + ' - ' + s.hora_fin_fmt + '</span>' +
                '<small style="font-size:0.7rem;opacity:0.85;">' + (isFree ? 'Disponible' : s.motivo_no_disponible) + '</small>';

            if (isFree) {
                btn.onclick = () => {
                    grid.querySelectorAll('.slot-btn.selected').forEach(b => b.classList.remove('selected'));
                    btn.classList.add('selected');
                    document.getElementById('inpHoraInicio').value = s.hora_inicio;
                    document.getElementById('resumenSeleccionHorario').innerHTML =
                        '<span class="text-success fw-bold"><i class="bi bi-check-circle-fill"></i> Bloque elegido: ' + fecha + ' de ' + s.hora_inicio_fmt + ' a ' + s.hora_fin_fmt + ' hrs.</span>';
                    actualizarEstadoBotonConfirmar();
                };
            }
            grid.appendChild(btn);
        });

        cont.innerHTML = '';
        cont.appendChild(grid);
    } catch (err) {
        cont.innerHTML = '<div class="alert alert-danger mb-0">Error de comunicación al consultar horarios.</div>';
    }
}

function limpiarSeleccionSlot() {
    document.getElementById('inpHoraInicio').value = '';
    document.getElementById('resumenSeleccionHorario').innerHTML = '<span class="text-muted">Ningún bloque horario seleccionado aún.</span>';
    actualizarEstadoBotonConfirmar();
}

function actualizarEstadoBotonConfirmar() {
    const pac = parseInt(document.getElementById('inpIdPaciente').value || '0', 10);
    const hora = document.getElementById('inpHoraInicio').value;
    document.getElementById('btnConfirmarCita').disabled = !(pac > 0 && hora !== '');
}

document.getElementById('formProgramarCita')?.addEventListener('submit', async function (ev) {
    ev.preventDefault();
    if (!this.checkValidity()) {
        this.classList.add('was-validated');
        VidaSalud.alert('warning', 'Datos requeridos', 'Verifique haber completado paciente, especialidad, médico, motivo de consulta y bloque horario.');
        return;
    }

    const btn = document.getElementById('btnConfirmarCita');
    btn.disabled = true;
    const prevHtml = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Procesando en PostgreSQL...';

    try {
        const fd = new FormData(this);
        const resp = await fetch(this.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: fd
        });
        const res = await resp.json();

        if (res.ok) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: '¡Cita Programada: ' + res.codigo_cita + '!',
                    html: '<p class="mb-2">' + res.message + '</p><p class="small text-muted mb-0">Puede descargar de inmediato el Comprobante Oficial PDF con Código QR.</p>',
                    showCancelButton: true,
                    confirmButtonColor: '#002D62',
                    cancelButtonColor: '#198754',
                    confirmButtonText: '<i class="bi bi-qr-code"></i> Abrir Ticket PDF con QR',
                    cancelButtonText: 'Ir al Listado de Citas'
                }).then(r => {
                    if (r.isConfirmed) {
                        window.open(res.ticket_url, '_blank');
                    }
                    window.location.href = '<?= url('citas') ?>&q=' + encodeURIComponent(res.codigo_cita);
                });
            } else {
                window.location.href = '<?= url('citas') ?>&q=' + encodeURIComponent(res.codigo_cita);
            }
        } else {
            VidaSalud.alert('error', 'Operación Rechazada por PostgreSQL', res.message || 'No fue posible agendar la cita.');
            cargarIntervalosDisponibles();
        }
    } catch (e) {
        VidaSalud.alert('error', 'Error', 'No se pudo completar la transacción.');
    } finally {
        btn.innerHTML = prevHtml;
        actualizarEstadoBotonConfirmar();
    }
});
</script>
