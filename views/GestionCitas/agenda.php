<?php
declare(strict_types=1);
/** @var array $citas */
/** @var string $fecha */
/** @var string $estado */
/** @var int|null $selectedMedico */
/** @var array $medicos */
/** @var array $proximasFechas */
/** @var array|null $currentUser */
/** @var string $csrfToken */

$rol = $currentUser['rol'] ?? '';
$esMedico = ($rol === 'Médico');
$puedeAsistencia = in_array($rol, ['Administrador', 'Recepcionista'], true);
$puedeAtender = in_array($rol, ['Médico', 'Administrador'], true);
?>
<?php if (!empty($proximasFechas)): ?>
    <div class="vs-card p-3 mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="small fw-semibold" style="color:var(--vs-navy);">
            <i class="bi bi-calendar-event-fill text-primary me-1"></i> Fechas próximas con citas programadas:
        </div>
        <div class="d-flex flex-wrap gap-1">
            <?php foreach ($proximasFechas as $pf): ?>
                <a href="<?= url('agenda', ['fecha' => $pf['fecha_cita'], 'id_medico' => $selectedMedico]) ?>"
                   class="btn btn-sm <?= $pf['fecha_cita'] === $fecha ? 'btn-vs-primary' : 'btn-vs-secondary' ?>">
                    <?= date('d/m/Y', strtotime((string) $pf['fecha_cita'])) ?>
                    <span class="badge bg-white text-dark ms-1"><?= (int) $pf['total_citas'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="vs-card">
    <div class="vs-card-header">
        <div>
            <h6 class="mb-0 fw-bold" style="color:var(--vs-navy);">
                <i class="bi bi-clock-history me-1"></i> Agenda Médica Cronológica del Día: <?= date('d/m/Y', strtotime($fecha)) ?>
            </h6>
            <small class="text-muted">Control de sala de espera y cierre clínico de consultas</small>
        </div>
        <a href="<?= url('exportar/reporte-pdf', ['tipo' => 'agenda_imprimible', 'fecha_desde' => $fecha, 'id_medico' => $selectedMedico]) ?>"
           target="_blank" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-printer-fill me-1"></i> Imprimir Agenda PDF
        </a>
    </div>

    <div class="p-3 border-bottom bg-light bg-opacity-50">
        <form method="GET" action="<?= url('agenda') ?>" class="row g-2 align-items-end">
            <input type="hidden" name="r" value="agenda">
            <div class="col-12 col-md-3">
                <label class="form-label small fw-semibold mb-1">Fecha de Agenda</label>
                <input type="date" name="fecha" value="<?= e($fecha) ?>" class="form-control form-control-sm" onchange="this.form.submit()">
            </div>
            <?php if (!$esMedico): ?>
                <div class="col-12 col-md-5">
                    <label class="form-label small fw-semibold mb-1">Profesional Médico</label>
                    <select name="id_medico" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Todos los médicos de la clínica</option>
                        <?php foreach ($medicos as $m): ?>
                            <option value="<?= (int) $m['id_medico'] ?>" <?= $selectedMedico === (int) $m['id_medico'] ? 'selected' : '' ?>>
                                Dr(a). <?= e($m['nombres'] . ' ' . $m['apellidos']) ?> (<?= e($m['especialidades_nombres']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-8 col-md-2">
                <label class="form-label small fw-semibold mb-1">Estado</label>
                <select name="estado" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <?php foreach (['Programada', 'Confirmada', 'Atendida', 'Cancelada', 'Inasistencia'] as $est): ?>
                        <option value="<?= $est ?>" <?= $estado === $est ? 'selected' : '' ?>><?= $est ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-4 col-md-2">
                <button type="submit" class="btn btn-sm btn-vs-primary w-100"><i class="bi bi-funnel-fill"></i> Consultar</button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-vs table-hover table-mobile-cards mb-0">
            <thead>
                <tr>
                    <th>Hora</th>
                    <th>Código</th>
                    <th>Paciente y Alertas Clínicas</th>
                    <th>Médico / Especialidad</th>
                    <th>Motivo / Notas</th>
                    <th>Estado</th>
                    <th class="text-end">Operación</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($citas)): ?>
                    <tr data-empty-row>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-calendar2-check display-6 d-block mb-2 opacity-50"></i>
                            No existen citas agendadas para el <strong><?= date('d/m/Y', strtotime($fecha)) ?></strong> con este filtro.
                            Use los botones superiores de fechas próximas (ej. 26/10/2026) para revisar los turnos activos.
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($citas as $c): ?>
                    <?php
                    $esActiva = in_array($c['estado'], ['Programada', 'Confirmada'], true);
                    $alergiaCritica = stripos((string) $c['alergias'], 'ninguna') === false && trim((string) $c['alergias']) !== '';
                    ?>
                    <tr>
                        <td data-label="Horario">
                            <span class="badge text-bg-dark fs-6"><?= e(substr((string) $c['hora_inicio'], 0, 5)) ?></span>
                            <div class="small text-muted">a <?= e(substr((string) $c['hora_fin'], 0, 5)) ?></div>
                        </td>
                        <td data-label="Código"><code><?= e($c['codigo_cita']) ?></code></td>
                        <td data-label="Paciente">
                            <div class="fw-bold" style="color:var(--vs-navy);"><?= e($c['paciente_nombre']) ?></div>
                            <div class="small">
                                <span class="badge text-bg-danger"><?= e($c['tipo_sangre']) ?></span>
                                <?php if ($alergiaCritica): ?>
                                    <span class="badge text-bg-warning"><i class="bi bi-exclamation-triangle-fill"></i> Alergia: <?= e($c['alergias']) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td data-label="Médico">
                            <div class="small fw-semibold">Dr(a). <?= e($c['medico_nombre']) ?></div>
                            <span class="small text-muted"><?= e($c['especialidad_nombre']) ?></span>
                        </td>
                        <td data-label="Motivo / Evolución" class="small" style="max-width:260px;">
                            <div><?= e($c['motivo_consulta'] ?: '-') ?></div>
                            <?php if (!empty($c['notas_medicas'])): ?>
                                <div class="text-success fw-semibold mt-1"><i class="bi bi-check2-all"></i> <?= e($c['notas_medicas']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Estado"><?= badge_estado_cita((string) $c['estado']) ?></td>
                        <td data-label="Operación" class="text-end">
                            <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                <?php if ($esActiva && $puedeAsistencia): ?>
                                    <?php if ($c['estado'] === 'Programada'): ?>
                                        <form method="POST" action="<?= url('citas/asistencia') ?>" class="d-inline">
                                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                            <input type="hidden" name="id_cita" value="<?= (int) $c['id_cita'] ?>">
                                            <input type="hidden" name="nuevo_estado" value="Confirmada">
                                            <input type="hidden" name="return_to" value="agenda&fecha=<?= urlencode($fecha) ?>">
                                            <button type="submit" class="btn btn-sm btn-warning fw-semibold">
                                                <i class="bi bi-person-check-fill"></i> Confirmar Sala
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" action="<?= url('citas/asistencia') ?>" class="d-inline"
                                          data-confirm="¿Registrar Inasistencia del paciente?">
                                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="id_cita" value="<?= (int) $c['id_cita'] ?>">
                                        <input type="hidden" name="nuevo_estado" value="Inasistencia">
                                        <input type="hidden" name="return_to" value="agenda&fecha=<?= urlencode($fecha) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Marcar Inasistencia">
                                            <i class="bi bi-person-x"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($esActiva && $puedeAtender && ($rol === 'Administrador' || (int) $c['id_medico'] === (int) ($currentUser['id_medico'] ?? 0))): ?>
                                    <button type="button" class="btn btn-sm btn-success fw-semibold"
                                            onclick='abrirConsultorioAgenda(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'>
                                        <i class="bi bi-stethoscope"></i> Atender Consulta
                                    </button>
                                <?php endif; ?>

                                <a href="<?= url('historial', ['id_paciente' => $c['id_paciente']]) ?>" class="btn btn-sm btn-outline-dark" title="Ver Historial Clínico del Paciente">
                                    <i class="bi bi-clock-history"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL CONSULTORIO EN AGENDA DIARIA -->
<div class="modal fade" id="modalAgendaConsultorio" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" action="<?= url('citas/atender') ?>" class="modal-content needs-validation" novalidate>
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="id_cita" id="ag_id_cita">
            <input type="hidden" name="return_to" value="agenda&fecha=<?= urlencode($fecha) ?>">
            <div class="modal-header text-white" style="background:#198754;">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-clipboard2-pulse-fill me-1"></i> Cierre de Atención Médica en Consultorio</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger border-0 mb-3">
                    <div class="d-flex justify-content-between">
                        <strong id="agPacNombre"></strong>
                        <span class="badge text-bg-danger" id="agPacSangre"></span>
                    </div>
                    <div class="small mt-1"><strong>Alergias reportadas:</strong> <span id="agPacAlergias"></span></div>
                    <div class="small"><strong>Enfermedades Crónicas:</strong> <span id="agPacCronicas"></span> · <strong>Medicación:</strong> <span id="agPacMed"></span></div>
                </div>
                <div class="small text-muted mb-2"><strong>Motivo de la cita:</strong> <span id="agMotivo"></span></div>
                <div>
                    <label class="form-label small fw-semibold">Diagnóstico, Evolución y Tratamiento Prescrito *</label>
                    <textarea name="notas_medicas" class="form-control" rows="4" required minlength="5"
                              placeholder="Escriba la evolución médica detallada para el expediente clínico del paciente..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                <button type="submit" class="btn btn-success px-4 fw-semibold">Guardar Notas y Sellar como Atendida</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirConsultorioAgenda(c) {
    document.getElementById('ag_id_cita').value = c.id_cita;
    document.getElementById('agPacNombre').textContent = c.paciente_nombre + ' (' + c.codigo_paciente + ')';
    document.getElementById('agPacSangre').textContent = 'Tipo Sangre: ' + (c.tipo_sangre || 'Desconocido');
    document.getElementById('agPacAlergias').textContent = c.alergias || 'Ninguna conocida';
    document.getElementById('agPacCronicas').textContent = c.enfermedades_cronicas || 'Ninguna reportada';
    document.getElementById('agPacMed').textContent = c.medicacion_actual || 'Ninguna';
    document.getElementById('agMotivo').textContent = c.motivo_consulta || '-';
    new bootstrap.Modal(document.getElementById('modalAgendaConsultorio')).show();
}
</script>
