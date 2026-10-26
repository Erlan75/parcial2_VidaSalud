<?php
declare(strict_types=1);
/** @var array|null $paciente */
/** @var array $historial */
/** @var array $resumen */
/** @var array $pacientesLista */
/** @var array|null $currentUser */

$esPaciente = ($currentUser['rol'] ?? '') === 'Paciente';
?>
<?php if (!$esPaciente): ?>
    <div class="vs-card p-3 mb-4">
        <form method="GET" action="<?= url('historial') ?>" class="row g-2 align-items-end">
            <input type="hidden" name="r" value="historial">
            <div class="col-12 col-md-9">
                <label class="form-label small fw-semibold mb-1">Seleccionar Paciente para Consultar Expediente e Historial Clínico</label>
                <select name="id_paciente" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($pacientesLista as $pl): ?>
                        <option value="<?= (int) $pl['id_paciente'] ?>" <?= ((int) ($paciente['id_paciente'] ?? 0) === (int) $pl['id_paciente']) ? 'selected' : '' ?>>
                            <?= e($pl['codigo_paciente']) ?> — <?= e($pl['nombres'] . ' ' . $pl['apellidos']) ?> (CI: <?= e($pl['ci']) ?> · Sangre: <?= e($pl['tipo_sangre']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <button type="submit" class="btn btn-vs-primary w-100"><i class="bi bi-folder2-open me-1"></i> Cargar Expediente</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php if ($paciente): ?>
    <!-- CABECERA DEL EXPEDIENTE PREVENTIVO DEL PACIENTE -->
    <div class="vs-card p-4 mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-5">
                <div class="d-flex align-items-center gap-3">
                    <div class="vs-kpi-icon" style="width:56px;height:56px;font-size:1.6rem;">
                        <i class="bi bi-person-heart"></i>
                    </div>
                    <div>
                        <span class="badge text-bg-primary mb-1"><?= e($paciente['codigo_paciente']) ?></span>
                        <h5 class="fw-bold mb-0" style="color:var(--vs-navy);"><?= e($paciente['nombres'] . ' ' . $paciente['apellidos']) ?></h5>
                        <div class="small text-muted">CI: <strong><?= e($paciente['ci']) ?></strong> · <?= (int) $paciente['edad'] ?> años · Sexo: <?= e($paciente['sexo']) ?> · Tel: <?= e($paciente['telefono']) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-7">
                <div class="p-3 rounded-3" style="background:#FFF5F5;border:1px solid #FECACA;">
                    <div class="row g-2 small">
                        <div class="col-sm-4">
                            <span class="text-muted d-block">Grupo Sanguíneo</span>
                            <strong class="text-danger fs-6"><i class="bi bi-droplet-fill"></i> <?= e($paciente['tipo_sangre']) ?></strong>
                        </div>
                        <div class="col-sm-8">
                            <span class="text-muted d-block">Alergias Conocidas</span>
                            <strong class="text-dark"><?= e($paciente['alergias']) ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block">Enfermedades Crónicas</span>
                            <strong><?= e($paciente['enfermedades_cronicas']) ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block">Medicación Actual</span>
                            <strong><?= e($paciente['medicacion_actual']) ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-2 mt-3 pt-3 border-top text-center">
            <div class="col-6 col-md-3">
                <div class="p-2 rounded bg-light">
                    <div class="h5 fw-bold mb-0" style="color:var(--vs-navy);"><?= (int) $resumen['total'] ?></div>
                    <small class="text-muted">Total Histórico</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-2 rounded bg-light">
                    <div class="h5 fw-bold mb-0 text-success"><?= (int) $resumen['atendidas'] ?></div>
                    <small class="text-muted">Consultas Atendidas</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-2 rounded bg-light">
                    <div class="h5 fw-bold mb-0 text-primary"><?= (int) $resumen['programadas'] ?></div>
                    <small class="text-muted">Turnos Activos</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-2 rounded bg-light">
                    <div class="h5 fw-bold mb-0 text-danger"><?= (int) ($resumen['canceladas'] + $resumen['inasistencias']) ?></div>
                    <small class="text-muted">Canceladas / Ausencias</small>
                </div>
            </div>
        </div>
    </div>

    <!-- PESTAÑAS: LÍNEA DE TIEMPO CLÍNICA Y TABLA DETALLADA -->
    <div class="vs-card p-4">
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item">
                <button class="nav-link active fw-semibold" data-bs-toggle="tab" data-bs-target="#vistaTimeline" type="button">
                    <i class="bi bi-diagram-3-fill me-1"></i> Línea de Tiempo Clínica
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-semibold" data-bs-toggle="tab" data-bs-target="#vistaTablaHist" type="button">
                    <i class="bi bi-table me-1"></i> Tabla Histórica Completa
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="vistaTimeline">
                <?php if (empty($historial)): ?>
                    <p class="text-muted text-center py-4 mb-0">Este paciente aún no registra citas médicas en su historial.</p>
                <?php else: ?>
                    <div class="clinical-timeline">
                        <?php foreach ($historial as $item): ?>
                            <div class="timeline-item">
                                <div class="timeline-dot"></div>
                                <div class="p-3 rounded-3 border bg-white shadow-sm">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                        <div>
                                            <code class="fw-bold me-2"><?= e($item['codigo_cita']) ?></code>
                                            <strong><?= date('d/m/Y', strtotime((string) $item['fecha_cita'])) ?></strong>
                                            <span class="text-muted small">(<?= e(substr((string) $item['hora_inicio'], 0, 5)) ?> - <?= e(substr((string) $item['hora_fin'], 0, 5)) ?>)</span>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <?= badge_estado_cita((string) $item['estado']) ?>
                                            <a href="<?= url('exportar/comprobante', ['id_cita' => $item['id_cita']]) ?>" target="_blank" class="btn btn-sm btn-vs-secondary">
                                                <i class="bi bi-qr-code"></i> Ticket
                                            </a>
                                        </div>
                                    </div>
                                    <div class="small mb-1">
                                        <strong>Especialidad:</strong> <?= e($item['especialidad_nombre']) ?> ·
                                        <strong>Médico:</strong> Dr(a). <?= e($item['medico_nombre']) ?>
                                    </div>
                                    <div class="small text-muted">
                                        <strong>Motivo de consulta:</strong> <?= e($item['motivo_consulta'] ?: '-') ?>
                                    </div>

                                    <?php if (!empty($item['notas_medicas'])): ?>
                                        <div class="mt-2 p-2 rounded bg-success-subtle text-success-emphasis small">
                                            <strong><i class="bi bi-clipboard2-check-fill"></i> Diagnóstico y Evolución Médica:</strong><br>
                                            <?= e($item['notas_medicas']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($item['motivo_cancelacion'])): ?>
                                        <div class="mt-2 p-2 rounded bg-danger-subtle text-danger-emphasis small">
                                            <strong><i class="bi bi-x-octagon-fill"></i> Motivo de Cancelación:</strong>
                                            <?= e($item['motivo_cancelacion']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="tab-pane fade" id="vistaTablaHist">
                <div class="table-responsive">
                    <table class="table table-vs table-hover table-mobile-cards mb-0">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Fecha / Hora</th>
                                <th>Especialidad / Médico</th>
                                <th>Estado</th>
                                <th>Diagnóstico / Observación</th>
                                <th class="text-end">PDF</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historial as $h): ?>
                                <tr>
                                    <td data-label="Código"><code><?= e($h['codigo_cita']) ?></code></td>
                                    <td data-label="Fecha"><?= date('d/m/Y', strtotime((string) $h['fecha_cita'])) ?> <?= e(substr((string) $h['hora_inicio'], 0, 5)) ?></td>
                                    <td data-label="Especialidad">
                                        <div class="fw-semibold small"><?= e($h['especialidad_nombre']) ?></div>
                                        <div class="small text-muted">Dr(a). <?= e($h['medico_nombre']) ?></div>
                                    </td>
                                    <td data-label="Estado"><?= badge_estado_cita((string) $h['estado']) ?></td>
                                    <td data-label="Detalle Clínico" class="small">
                                        <?php if (!empty($h['notas_medicas'])): ?>
                                            <span class="text-success fw-semibold"><?= e($h['notas_medicas']) ?></span>
                                        <?php elseif (!empty($h['motivo_cancelacion'])): ?>
                                            <span class="text-danger">Cancelada: <?= e($h['motivo_cancelacion']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted"><?= e($h['motivo_consulta'] ?: '-') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Ticket" class="text-end">
                                        <a href="<?= url('exportar/comprobante', ['id_cita' => $h['id_cita']]) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
