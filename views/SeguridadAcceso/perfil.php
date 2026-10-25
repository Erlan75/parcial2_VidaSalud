<?php
declare(strict_types=1);
/** @var array|null $userDetails */
/** @var string $csrfToken */
?>
<div class="row g-4">
    <div class="col-12 col-lg-5">
        <div class="vs-card p-4 h-100">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="vs-kpi-icon" style="width:58px;height:58px;font-size:1.75rem;">
                    <i class="bi bi-person-vcard-fill"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0" style="color:var(--vs-navy);"><?= e($userDetails['nombre_completo'] ?? '') ?></h5>
                    <span class="badge rounded-pill text-bg-primary"><?= e($userDetails['rol_nombre'] ?? '') ?></span>
                </div>
            </div>
            <hr>
            <dl class="row small mb-0">
                <dt class="col-5 text-muted">Nombre de Usuario:</dt>
                <dd class="col-7 fw-semibold"><code><?= e($userDetails['nombre_usuario'] ?? '') ?></code></dd>

                <dt class="col-5 text-muted">Correo Electrónico:</dt>
                <dd class="col-7"><?= e($userDetails['correo_electronico'] ?? '') ?></dd>

                <dt class="col-5 text-muted">Teléfono / Celular:</dt>
                <dd class="col-7"><?= e($userDetails['telefono'] ?: '-') ?></dd>

                <?php if (!empty($userDetails['codigo_medico'])): ?>
                    <dt class="col-5 text-muted">Código Médico:</dt>
                    <dd class="col-7"><span class="badge text-bg-info"><?= e($userDetails['codigo_medico']) ?></span></dd>
                <?php endif; ?>

                <?php if (!empty($userDetails['codigo_paciente'])): ?>
                    <dt class="col-5 text-muted">Código Paciente:</dt>
                    <dd class="col-7"><span class="badge text-bg-info"><?= e($userDetails['codigo_paciente']) ?></span></dd>
                <?php endif; ?>

                <dt class="col-5 text-muted">Último Acceso:</dt>
                <dd class="col-7 mb-0"><?= !empty($userDetails['ultimo_acceso']) ? date('d/m/Y H:i:s', strtotime((string) $userDetails['ultimo_acceso'])) : 'Sesión actual' ?></dd>
            </dl>
        </div>
    </div>

    <div class="col-12 col-lg-7">
        <div class="vs-card">
            <div class="vs-card-header">
                <h6 class="mb-0 fw-bold" style="color:var(--vs-navy);"><i class="bi bi-key-fill me-1"></i> Actualizar Contraseña de Acceso</h6>
            </div>
            <div class="p-4">
                <form method="POST" action="<?= url('seguridad/cambiar-password') ?>" class="needs-validation" novalidate>
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Contraseña Actual</label>
                        <input type="password" name="password_actual" class="form-control" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nueva Contraseña</label>
                            <input type="password" name="password_nueva" class="form-control" required
                                   data-strict-password data-pwd-meter="perfilPwdMeter">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Confirmar Nueva Contraseña</label>
                            <input type="password" name="password_confirmar" class="form-control" required>
                        </div>
                    </div>

                    <div id="perfilPwdMeter" class="d-flex flex-wrap gap-1 mb-4">
                        <span class="pwd-rule-pill" data-rule="len">8+ caract.</span>
                        <span class="pwd-rule-pill" data-rule="upper">1 Mayúscula</span>
                        <span class="pwd-rule-pill" data-rule="lower">1 Minúscula</span>
                        <span class="pwd-rule-pill" data-rule="num">1 Número</span>
                        <span class="pwd-rule-pill" data-rule="spec">1 Especial</span>
                    </div>

                    <button type="submit" class="btn btn-vs-primary px-4">
                        <i class="bi bi-shield-check me-1"></i> Actualizar Contraseña
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
