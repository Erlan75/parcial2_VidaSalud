<?php
declare(strict_types=1);
/** @var string $csrfToken */
/** @var int $idReset */
/** @var string $token */
/** @var array $resetUser */
?>
<div class="vs-card p-4 p-md-5">
    <h2 class="h4 fw-bold mb-1" style="color:var(--vs-navy);">Definir Nueva Contraseña</h2>
    <p class="text-muted small mb-3">
        Cuenta verificada: <strong><?= e($resetUser['nombre_completo'] ?? '') ?></strong> (<code><?= e($resetUser['nombre_usuario'] ?? '') ?></code>)
    </p>

    <form method="POST" action="<?= url('seguridad/reset') ?>" class="needs-validation" novalidate>
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="rid" value="<?= (int) $idReset ?>">
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <div class="mb-3">
            <label class="form-label fw-semibold small">Nueva Contraseña Segura</label>
            <input type="password" class="form-control" name="password" required
                   data-strict-password data-pwd-meter="resetPwdMeter" placeholder="Mínimo 8 caracteres complejos">
            <div id="resetPwdMeter" class="d-flex flex-wrap gap-1 mt-2">
                <span class="pwd-rule-pill" data-rule="len">8+ caract.</span>
                <span class="pwd-rule-pill" data-rule="upper">1 Mayúscula</span>
                <span class="pwd-rule-pill" data-rule="lower">1 Minúscula</span>
                <span class="pwd-rule-pill" data-rule="num">1 Número</span>
                <span class="pwd-rule-pill" data-rule="spec">1 Especial</span>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-semibold small">Confirmar Nueva Contraseña</label>
            <input type="password" class="form-control" name="password_confirm" required placeholder="Repita la contraseña">
        </div>

        <button type="submit" class="btn btn-vs-primary w-100 py-2">
            <i class="bi bi-check2-circle me-1"></i> Guardar Contraseña y Acceder
        </button>
    </form>
</div>
