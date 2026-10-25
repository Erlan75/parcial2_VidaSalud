<?php
declare(strict_types=1);
/** @var array $dbHealth */
/** @var string $csrfToken */
?>
<div class="vs-card p-4 p-md-5">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="d-flex align-items-center gap-2">
            <div class="vs-brand-cross d-lg-none" style="background:var(--vs-navy);color:#fff;">
                <i class="bi bi-hospital"></i>
            </div>
            <div>
                <h2 class="h4 fw-bold mb-0" style="color:var(--vs-navy);">Iniciar Sesión</h2>
                <p class="text-muted small mb-0">Portal Clínico Integrado · CU01</p>
            </div>
        </div>
        <?php if (!empty($dbHealth['migrated'])): ?>
            <span class="badge rounded-pill text-bg-success"><i class="bi bi-database-check"></i> PostgreSQL Activo</span>
        <?php else: ?>
            <a href="<?= url('sistema/migracion') ?>" class="badge rounded-pill text-bg-warning text-decoration-none">
                <i class="bi bi-gear-fill"></i> Configurar / Migrar BD
            </a>
        <?php endif; ?>
    </div>

    <?php if (empty($dbHealth['migrated'])): ?>
        <div class="alert alert-warning border-0 small mb-4">
            <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill"></i> Base de Datos pendiente de inicialización</div>
            <div class="mb-2"><?= e($dbHealth['error'] ?? 'Las 13 tablas aún no han sido migradas en PostgreSQL.') ?></div>
            <a href="<?= url('sistema/migracion') ?>" class="btn btn-sm btn-vs-primary">
                <i class="bi bi-play-circle-fill"></i> Abrir Asistente de Migración Smart-DB
            </a>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= url('seguridad/login') ?>" class="needs-validation" novalidate>
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

        <div class="mb-3">
            <label for="identificador" class="form-label fw-semibold small">Usuario o Correo Electrónico</label>
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-person-fill text-primary"></i></span>
                <input type="text" class="form-control" id="identificador" name="identificador"
                       placeholder="Ej. Erlan o erlan.garcia75@gmail.com" required autofocus>
                <div class="invalid-feedback">Ingrese su usuario o correo electrónico.</div>
            </div>
        </div>

        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center">
                <label for="password" class="form-label fw-semibold small mb-1">Contraseña</label>
                <a href="<?= url('seguridad/forgot') ?>" class="small text-decoration-none fw-semibold" style="color:var(--vs-blue);">
                    ¿Olvidaste tu contraseña?
                </a>
            </div>
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-lock-fill text-primary"></i></span>
                <input type="password" class="form-control" id="password" name="password"
                       placeholder="••••••••" required>
                <button class="btn btn-outline-secondary" type="button" onclick="const p=document.getElementById('password');p.type=p.type==='password'?'text':'password';">
                    <i class="bi bi-eye"></i>
                </button>
                <div class="invalid-feedback">Ingrese su contraseña.</div>
            </div>
        </div>

        <button type="submit" class="btn btn-vs-primary w-100 py-2 mt-2 shadow-sm">
            <i class="bi bi-box-arrow-in-right me-1"></i> Ingresar al Sistema
        </button>
    </form>

    <!-- Selector rápido de cuentas reales de poblacion.sql -->
    <div class="mt-4 pt-3 border-top">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-muted fw-semibold" style="font-size:0.76rem;">
                USUARIOS OFICIALES (<code>poblacion.sql</code> · Contraseña: <code>Cher_4mi</code>)
            </span>
            <a href="<?= url('sistema/migracion') ?>" class="small text-muted text-decoration-none" style="font-size:0.75rem;">
                <i class="bi bi-sliders"></i> BD/SMTP
            </a>
        </div>
        <div class="row g-2">
            <div class="col-12 col-sm-6">
                <button type="button" class="btn btn-sm btn-outline-primary w-100 text-start py-2" onclick="fillDemo('Erlan','Cher_4mi')">
                    <div class="fw-bold"><i class="bi bi-shield-lock-fill me-1"></i> Administrador 1: "Erlan"</div>
                    <div class="text-muted" style="font-size:0.72rem;">erlan.garcia75@gmail.com | Clave: <code>Cher_4mi</code></div>
                </button>
            </div>
            <div class="col-12 col-sm-6">
                <button type="button" class="btn btn-sm btn-outline-primary w-100 text-start py-2" onclick="fillDemo('Edilson','Cher_4mi')">
                    <div class="fw-bold"><i class="bi bi-shield-lock-fill me-1"></i> Administrador 2: "Edilson"</div>
                    <div class="text-muted" style="font-size:0.72rem;">erickmaturano04@gmail.com | Clave: <code>Cher_4mi</code></div>
                </button>
            </div>
            <div class="col-12 col-sm-6">
                <button type="button" class="btn btn-sm btn-outline-primary w-100 text-start py-2" onclick="fillDemo('mariana.vaca','Cher_4mi')">
                    <div class="fw-bold"><i class="bi bi-headset me-1"></i> Recepcionista: "mariana.vaca"</div>
                    <div class="text-muted" style="font-size:0.72rem;">Clave: <code>Cher_4mi</code></div>
                </button>
            </div>
            <div class="col-12 col-sm-6">
                <button type="button" class="btn btn-sm btn-outline-primary w-100 text-start py-2" onclick="fillDemo('juan.perez','Cher_4mi')">
                    <div class="fw-bold"><i class="bi bi-heart-pulse-fill me-1"></i> Médico: "juan.perez"</div>
                    <div class="text-muted" style="font-size:0.72rem;">Clave: <code>Cher_4mi</code></div>
                </button>
            </div>
            <div class="col-12">
                <button type="button" class="btn btn-sm btn-outline-primary w-100 text-start py-2" onclick="fillDemo('lucas.gutierrez','Cher_4mi')">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <span class="fw-bold"><i class="bi bi-person-check-fill me-1"></i> Paciente: "lucas.gutierrez"</span>
                        <span class="text-muted" style="font-size:0.72rem;">Clave: <code>Cher_4mi</code></span>
                    </div>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function fillDemo(user, pass) {
    document.getElementById('identificador').value = user;
    document.getElementById('password').value = pass;
}
</script>
