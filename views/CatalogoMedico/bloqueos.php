<?php
declare(strict_types=1);
/** @var array $bloqueos */
/** @var array $medicos */
/** @var bool $canManage */
/** @var string $csrfToken */
/** @var array|null $resumenContingencia */
/** @var array $historialContingencia */

$resumenContingencia = $resumenContingencia ?? null;
$historialContingencia = $historialContingencia ?? [];
?>

<?php if (is_array($resumenContingencia)): ?>
<div class="vs-card mb-4 border-0 shadow-sm overflow-hidden" style="border-left: 5px solid #002D62 !important;">
    <div class="vs-card-header" style="background: linear-gradient(90deg, #F0F7FF 0%, #FFFFFF 100%);">
        <div>
            <h6 class="mb-0 fw-bold" style="color:var(--vs-navy);">
                <i class="bi bi-arrow-repeat text-primary me-1"></i>
                Resumen de Reprogramación Automática Masiva por Contingencia (#<?= (int) ($resumenContingencia['id_bloqueo'] ?? 0) ?>)
            </h6>
            <small class="text-muted">
                <?= e((string) ($resumenContingencia['tipo_bloqueo'] ?? '')) ?>: <?= e((string) ($resumenContingencia['motivo_bloqueo'] ?? '')) ?>
                · Rango bloqueado: <?= date('d/m/Y', strtotime((string) $resumenContingencia['fecha_inicio'])) ?> al <?= date('d/m/Y', strtotime((string) $resumenContingencia['fecha_fin'])) ?>
            </small>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge rounded-pill text-bg-primary px-3 py-2">
                Afectadas: <?= (int) ($resumenContingencia['total_afectadas'] ?? 0) ?>
            </span>
            <span class="badge rounded-pill text-bg-success px-3 py-2">
                Reubicadas: <?= (int) ($resumenContingencia['total_reubicadas'] ?? 0) ?>
            </span>
        </div>
    </div>

    <div class="p-3">
        <?php if (empty($resumenContingencia['reubicadas']) && empty($resumenContingencia['no_reubicadas'])): ?>
            <div class="alert alert-info mb-0 py-2 small">
                <i class="bi bi-info-circle-fill me-1"></i>
                No se encontraron citas en estado <strong>Programada</strong> o <strong>Confirmada</strong> dentro del rango de fechas bloqueado.
            </div>
        <?php else: ?>
            <?php if (!empty($resumenContingencia['reubicadas'])): ?>
                <div class="table-responsive">
                    <table class="table table-vs table-hover table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Código Cita</th>
                                <th>Paciente / Correo</th>
                                <th>Médico / Especialidad</th>
                                <th>Horario Anterior</th>
                                <th>Nuevo Horario Asignado</th>
                                <th>Estado de Envío de Correo / Notificación</th>
                                <th class="text-end">Ticket QR</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resumenContingencia['reubicadas'] as $rc): ?>
                                <tr>
                                    <td><code class="fw-bold"><?= e($rc['codigo_cita']) ?></code></td>
                                    <td>
                                        <div class="fw-semibold small"><?= e($rc['paciente_nombre']) ?></div>
                                        <small class="text-muted"><?= e($rc['paciente_correo'] ?: 'Sin correo registrado') ?></small>
                                    </td>
                                    <td>
                                        <div class="small fw-semibold">Dr(a). <?= e($rc['medico_nombre']) ?></div>
                                        <span class="badge rounded-pill" style="background:var(--vs-sky);color:var(--vs-navy);font-size:0.7rem;"><?= e($rc['especialidad']) ?></span>
                                    </td>
                                    <td>
                                        <span class="text-danger text-decoration-line-through small">
                                            <?= date('d/m/Y', strtotime((string) $rc['fecha_anterior'])) ?> · <?= e($rc['hora_anterior']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge text-bg-success">
                                            <i class="bi bi-calendar-check me-1"></i>
                                            <?= date('d/m/Y', strtotime((string) $rc['nueva_fecha'])) ?> · <?= e($rc['nueva_hora']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($rc['correo_enviado_smtp'])): ?>
                                            <span class="badge text-bg-success"><i class="bi bi-envelope-check-fill me-1"></i> <?= e($rc['correo_estado_label']) ?></span>
                                        <?php else: ?>
                                            <span class="badge text-bg-info text-dark" title="<?= e($rc['correo_mensaje'] ?? '') ?>">
                                                <i class="bi bi-bell-fill me-1"></i> <?= e($rc['correo_estado_label']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= e($rc['ticket_url']) ?>" target="_blank" class="btn btn-sm btn-vs-secondary" title="Descargar nuevo comprobante PDF con QR">
                                            <i class="bi bi-qr-code"></i> PDF
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (!empty($resumenContingencia['no_reubicadas'])): ?>
                <div class="alert alert-warning mt-3 mb-0 small">
                    <strong><i class="bi bi-exclamation-triangle-fill me-1"></i> Citas que requieren atención manual:</strong>
                    <ul class="mb-0 mt-1">
                        <?php foreach ($resumenContingencia['no_reubicadas'] as $nr): ?>
                            <li>
                                <code><?= e($nr['codigo_cita']) ?></code> — <?= e($nr['paciente_nombre']) ?>
                                (<?= date('d/m/Y', strtotime((string) $nr['fecha_anterior'])) ?> <?= e($nr['hora_anterior']) ?>):
                                <?= e($nr['motivo_fallo']) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

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

<?php if (!empty($historialContingencia)): ?>
<div class="vs-card mt-4">
    <div class="vs-card-header">
        <div>
            <h6 class="mb-0 fw-bold" style="color:var(--vs-navy);">
                <i class="bi bi-clock-history me-1"></i> Citas Activas Reprogramadas Automáticamente por Contingencia
            </h6>
            <small class="text-muted">Trazabilidad auditada de turnos reubicados por feriados o bloqueos de disponibilidad médica</small>
        </div>
        <span class="badge rounded-pill text-bg-primary"><?= count($historialContingencia) ?> reubicada(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-vs table-hover table-mobile-cards mb-0">
            <thead>
                <tr>
                    <th>Código Cita</th>
                    <th>Paciente</th>
                    <th>Médico / Especialidad</th>
                    <th>Fecha/Hora Anterior</th>
                    <th>Nueva Fecha/Hora</th>
                    <th>Contingencia / Bloqueo</th>
                    <th>Notificación</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historialContingencia as $hc): ?>
                    <tr>
                        <td data-label="Código">
                            <code class="fw-bold"><?= e($hc['codigo_cita']) ?></code>
                        </td>
                        <td data-label="Paciente">
                            <div class="fw-semibold small"><?= e($hc['paciente_nombre']) ?></div>
                            <small class="text-muted"><?= e($hc['codigo_paciente']) ?></small>
                        </td>
                        <td data-label="Médico">
                            <div class="small fw-semibold">Dr(a). <?= e($hc['medico_nombre']) ?></div>
                            <span class="badge rounded-pill" style="background:var(--vs-sky);color:var(--vs-navy);font-size:0.7rem;"><?= e($hc['especialidad_nombre']) ?></span>
                        </td>
                        <td data-label="Horario Anterior">
                            <?php if (!empty($hc['fecha_anterior'])): ?>
                                <span class="text-danger text-decoration-line-through small">
                                    <?= date('d/m/Y', strtotime((string) $hc['fecha_anterior'])) ?> · <?= e((string) $hc['hora_anterior']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">Reubicada</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Nuevo Horario">
                            <span class="badge text-bg-success">
                                <?= date('d/m/Y', strtotime((string) $hc['nueva_fecha'])) ?> · <?= e((string) $hc['nueva_hora']) ?>
                            </span>
                        </td>
                        <td data-label="Contingencia" class="small text-muted">
                            <strong><?= e((string) $hc['tipo_bloqueo']) ?>:</strong> <?= e((string) $hc['motivo_bloqueo']) ?>
                        </td>
                        <td data-label="Notificación">
                            <span class="badge text-bg-info text-dark">
                                <i class="bi bi-check2-all me-1"></i> Correo + Portal Web
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

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

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Motivo Oficial *</label>
                    <input type="text" name="motivo" class="form-control" required minlength="4" maxlength="200" placeholder="Ej. Feriado Departamental / Congreso Boliviano de Pediatría">
                </div>

                <div class="form-check p-3 rounded-3 border mb-0" style="background:#F0F7FF;border-color:#B6D4FE !important;">
                    <input class="form-check-input ms-0 me-2" type="checkbox" name="reprogramar_afectadas" id="chkReprogramarAfectadas" value="1" checked>
                    <label class="form-check-label small fw-semibold" for="chkReprogramarAfectadas" style="color:var(--vs-navy);">
                        Reprogramar automáticamente las citas activas afectadas al próximo turno disponible y notificar a los pacientes.
                    </label>
                    <div class="small text-muted mt-1 ps-4">
                        <i class="bi bi-shield-check me-1"></i> Reubica libre de colisiones al siguiente turno hábil y envía notificación híbrida (Correo SMTP + Alerta en Portal Web).
                    </div>
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
