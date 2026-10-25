<?php
declare(strict_types=1);
/** @var string $csrfToken */
/** @var array $mailCfg */
/** @var array|null $mailInfo */
?>
<div class="vs-card p-4 p-md-5">
    <div class="mb-3">
        <a href="<?= url('seguridad/login') ?>" class="small text-decoration-none fw-semibold" style="color:var(--vs-blue);">
            <i class="bi bi-arrow-left"></i> Volver al Inicio de Sesión
        </a>
        <h2 class="h4 fw-bold mt-2 mb-1" style="color:var(--vs-navy);">Recuperación de Contraseña</h2>
        <p class="text-muted small mb-0">Ingrese su correo registrado para recibir un enlace seguro de restablecimiento vía SMTP Gmail (vigencia 30 min).</p>
    </div>

    <?php if (!empty($mailInfo['reset_url'])): ?>
        <div class="alert alert-info border-0 small">
            <div class="fw-bold mb-1">
                <i class="bi bi-envelope-check-fill"></i>
                <?= !empty($mailInfo['sent_smtp']) ? 'Despachado vía SMTP Gmail' : 'Respaldo Local de Laboratorio Generado' ?>
            </div>
            <p class="mb-2"><?= e($mailInfo['message'] ?? '') ?></p>
            <?php if (!empty($mailInfo['simulated'])): ?>
                <a href="<?= e($mailInfo['reset_url']) ?>" class="btn btn-sm btn-vs-primary">
                    <i class="bi bi-key-fill"></i> Abrir Enlace de Restablecimiento Ahora
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= url('seguridad/forgot') ?>" class="needs-validation" novalidate>
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

        <div class="mb-3">
            <label for="correo_electronico" class="form-label fw-semibold small">Correo Electrónico Registrado</label>
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-envelope-at-fill text-primary"></i></span>
                <input type="email" class="form-control" id="correo_electronico" name="correo_electronico"
                       placeholder="ejemplo@gmail.com" required autofocus>
                <div class="invalid-feedback">Ingrese un correo electrónico válido.</div>
            </div>
        </div>

        <button type="submit" class="btn btn-vs-primary w-100 py-2">
            <i class="bi bi-send-fill me-1"></i> Enviar Enlace de Recuperación
        </button>
    </form>

    <div class="mt-4 pt-3 border-top small text-muted">
        <i class="bi bi-info-circle"></i> Servidor SMTP configurado: <code><?= e($mailCfg['host'] ?? 'smtp.gmail.com') ?>:<?= e($mailCfg['port'] ?? 587) ?></code>
        (Estado: <strong><?= !empty($mailCfg['enabled']) ? 'Envío Real Activo' : 'Bandeja Local / Configurable en .env' ?></strong>).
    </div>
</div>
