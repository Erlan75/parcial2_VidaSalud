<?php
declare(strict_types=1);
/** @var array $horarios */
/** @var array $grillaSemanal */
/** @var array $medicos */
/** @var int|null $selectedMedico */
/** @var bool $canManage */
/** @var string $csrfToken */

$diasMap = dias_semana_map();
?>
<div class="vs-card mb-4">
    <div class="vs-card-header">
        <div>
            <h6 class="mb-0 fw-bold" style="color:var(--vs-navy);"><i class="bi bi-calendar-week-fill me-1"></i> Grilla Semanal de Turnos Médicos (Lunes a Sábado)</h6>
            <small class="text-muted">Configuración vía <code>CALL sp_asignar_horario_medico</code> y validación anti-solapamiento <code>trg_validar_horario_medico_solapado</code></small>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if (!(\App\Core\Auth::hasRole('Médico'))): ?>
                <form method="GET" action="<?= url('horarios') ?>" class="d-flex gap-2">
                    <input type="hidden" name="r" value="horarios">
                    <select name="id_medico" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Todos los médicos</option>
                        <?php foreach ($medicos as $med): ?>
                            <option value="<?= (int) $med['id_medico'] ?>" <?= $selectedMedico === (int) $med['id_medico'] ? 'selected' : '' ?>>
                                Dr(a). <?= e($med['nombres'] . ' ' . $med['apellidos']) ?> (<?= e($med['codigo_medico']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php endif; ?>
            <?php if ($canManage): ?>
                <button type="button" class="btn btn-sm btn-vs-primary px-3" data-bs-toggle="modal" data-bs-target="#modalNuevoHorario">
                    <i class="bi bi-plus-circle-fill me-1"></i> Asignar Turno
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- GRILLA VISUAL SEMANAL LUNES (1) A SÁBADO (6) -->
    <div class="p-3">
        <div class="row g-3">
            <?php for ($d = 1; $d <= 6; $d++): ?>
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="border rounded-3 h-100 bg-light bg-opacity-50 p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                            <span class="fw-bold" style="color:var(--vs-navy);"><i class="bi bi-calendar2-day text-primary me-1"></i> <?= e($diasMap[$d]) ?></span>
                            <span class="badge rounded-pill bg-white text-dark border"><?= count($grillaSemanal[$d] ?? []) ?> turnos</span>
                        </div>

                        <?php if (empty($grillaSemanal[$d])): ?>
                            <div class="text-muted small py-2 text-center">Sin turnos asignados</div>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-2">
                                <?php foreach ($grillaSemanal[$d] as $turno): ?>
                                    <div class="p-2 rounded-3 bg-white border d-flex justify-content-between align-items-center gap-2 <?= $turno['estado'] !== 'Activo' ? 'opacity-50' : '' ?>">
                                        <div>
                                            <div class="fw-semibold small" style="color:var(--vs-navy);">Dr(a). <?= e($turno['medico_nombre']) ?></div>
                                            <div class="small text-muted">
                                                <i class="bi bi-clock"></i>
                                                <strong><?= e(substr((string) $turno['hora_inicio'], 0, 5)) ?> - <?= e(substr((string) $turno['hora_fin'], 0, 5)) ?></strong>
                                                · Bloques de <?= (int) $turno['duracion_minutos'] ?> min
                                            </div>
                                            <div style="font-size:0.72rem;" class="text-primary"><?= e($turno['especialidades']) ?></div>
                                        </div>
                                        <?php if ($canManage): ?>
                                            <form method="POST" action="<?= url('horarios/conmutar-estado') ?>">
                                                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                                <input type="hidden" name="id_horario" value="<?= (int) $turno['id_horario'] ?>">
                                                <input type="hidden" name="id_medico_filtro" value="<?= (int) ($selectedMedico ?? 0) ?>">
                                                <button type="submit" class="btn btn-sm <?= $turno['estado'] === 'Activo' ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                                                        title="<?= $turno['estado'] === 'Activo' ? 'Desactivar turno' : 'Activar turno' ?>">
                                                    <i class="bi <?= $turno['estado'] === 'Activo' ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<?php if ($canManage): ?>
<!-- MODAL ASIGNAR HORARIO (sp_asignar_horario_medico) -->
<div class="modal fade" id="modalNuevoHorario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= url('horarios/guardar') ?>" class="modal-content needs-validation" novalidate id="formHorario">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <div class="modal-header text-white" style="background:var(--vs-navy);">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-clock-fill me-1"></i> Configurar Turno Semanal (CU06)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Médico Activo *</label>
                    <select name="id_medico" class="form-select" required>
                        <?php foreach ($medicos as $med): ?>
                            <option value="<?= (int) $med['id_medico'] ?>" <?= $selectedMedico === (int) $med['id_medico'] ? 'selected' : '' ?>>
                                Dr(a). <?= e($med['nombres'] . ' ' . $med['apellidos']) ?> — <?= e($med['especialidades_nombres']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-7">
                        <label class="form-label small fw-semibold">Día de la Semana (Lun-Sáb) *</label>
                        <select name="dia_semana" class="form-select" required>
                            <?php for ($d = 1; $d <= 6; $d++): ?>
                                <option value="<?= $d ?>"><?= $d ?> — <?= e($diasMap[$d]) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold">Duración Consulta *</label>
                        <select name="duracion_minutos" class="form-select" required>
                            <option value="30" selected>30 minutos (Estándar)</option>
                            <option value="15">15 minutos</option>
                            <option value="20">20 minutos</option>
                            <option value="45">45 minutos</option>
                            <option value="60">60 minutos</option>
                        </select>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Hora Inicio *</label>
                        <input type="time" name="hora_inicio" id="h_inicio" value="08:00" class="form-control" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Hora Fin *</label>
                        <input type="time" name="hora_fin" id="h_fin" value="12:00" class="form-control" required>
                    </div>
                </div>
                <div class="form-text mt-2">
                    <i class="bi bi-info-circle"></i> El motor validará automáticamente que <code>hora_fin &gt; hora_inicio</code> y que no exista solapamiento en el mismo día.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-vs-primary px-4">Registrar Turno</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('formHorario')?.addEventListener('submit', function (e) {
    const ini = document.getElementById('h_inicio').value;
    const fin = document.getElementById('h_fin').value;
    if (ini && fin && fin <= ini) {
        e.preventDefault();
        VidaSalud.alert('warning', 'Horario Inválido', 'La hora de finalización debe ser estrictamente posterior a la hora de inicio.');
    }
});
</script>
<?php endif; ?>
