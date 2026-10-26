<?php
declare(strict_types=1);
/** @var string $codigo */
/** @var array|null $cita */
/** @var string $qrSvg */
?>
<div class="vs-card overflow-hidden shadow">
    <div class="p-4 text-white d-flex justify-content-between align-items-center" style="background:linear-gradient(135deg,var(--vs-navy),var(--vs-blue));">
        <div class="d-flex align-items-center gap-3">
            <div class="vs-brand-cross"><i class="bi bi-hospital"></i></div>
            <div>
                <h1 class="h5 mb-0 fw-bold">CLÍNICA VIDA SALUD</h1>
                <small class="opacity-75">Verificador Digital de Comprobantes QR</small>
            </div>
        </div>
        <i class="bi bi-patch-check-fill fs-2 text-info"></i>
    </div>

    <div class="p-4">
        <?php if (!$cita): ?>
            <div class="text-center py-4">
                <i class="bi bi-exclamation-octagon text-danger display-4 d-block mb-2"></i>
                <h5 class="fw-bold">Comprobante No Encontrado</h5>
                <p class="text-muted small">El código consultado (<code><?= e($codigo ?: 'N/A') ?></code>) no corresponde a ninguna cita registrada en Clínica Vida Salud.</p>
                <a href="<?= url('seguridad/login') ?>" class="btn btn-vs-primary btn-sm">Ir al Portal Clínico</a>
            </div>
        <?php else: ?>
            <div class="alert alert-success border-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <i class="bi bi-shield-fill-check me-1"></i>
                    <strong>Comprobante Auténtico Verificado:</strong> <code><?= e($cita['codigo_cita']) ?></code>
                </div>
                <?= badge_estado_cita((string) $cita['estado']) ?>
            </div>

            <div class="row g-3 align-items-center">
                <div class="col-12 col-md-8">
                    <dl class="row small mb-0">
                        <dt class="col-5 text-muted">Paciente:</dt>
                        <dd class="col-7 fw-bold"><?= e($cita['paciente_nombre']) ?> (<?= e($cita['codigo_paciente']) ?>)</dd>

                        <dt class="col-5 text-muted">Especialidad:</dt>
                        <dd class="col-7 fw-semibold"><?= e($cita['especialidad_nombre']) ?></dd>

                        <dt class="col-5 text-muted">Médico Asignado:</dt>
                        <dd class="col-7">Dr(a). <?= e($cita['medico_nombre']) ?></dd>

                        <dt class="col-5 text-muted">Fecha Programada:</dt>
                        <dd class="col-7 fw-bold text-primary"><?= date('d/m/Y', strtotime((string) $cita['fecha_cita'])) ?></dd>

                        <dt class="col-5 text-muted">Bloque Horario:</dt>
                        <dd class="col-7 fw-bold"><?= e(substr((string) $cita['hora_inicio'], 0, 5)) ?> a <?= e(substr((string) $cita['hora_fin'], 0, 5)) ?> hrs.</dd>
                    </dl>
                </div>
                <div class="col-12 col-md-4 text-center">
                    <div class="d-inline-block p-2 border rounded-3 bg-white">
                        <?= $qrSvg ?>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top d-flex justify-content-between align-items-center">
                <span class="small text-muted">Verificado el <?= date('d/m/Y H:i:s') ?></span>
                <a href="<?= url('seguridad/login') ?>" class="btn btn-sm btn-vs-secondary">Portal Clínica Vida Salud</a>
            </div>
        <?php endif; ?>
    </div>
</div>
