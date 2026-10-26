<?php
declare(strict_types=1);
/** @var array $dbHealth */
/** @var array $dbCfg */
/** @var array $mailCfg */
/** @var array|null $migrationResult */
/** @var string $csrfToken */
?>
<div class="vs-card p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h4 class="h5 fw-bold mb-1" style="color:var(--vs-navy);">
                <i class="bi bi-database-fill-gear me-1"></i> Configuración de Base de Datos y Entorno
            </h4>
            <p class="text-muted small mb-0">
                Inicialización de tablas, funciones y configuración de correo electrónico institucional
            </p>
        </div>
        <div class="d-flex gap-2">
            <?php if (!empty($dbHealth['migrated'])): ?>
                <span class="badge rounded-pill text-bg-success px-3 py-2"><i class="bi bi-check-circle-fill"></i> 13/13 Tablas Activas</span>
            <?php else: ?>
                <span class="badge rounded-pill text-bg-warning px-3 py-2"><i class="bi bi-exclamation-circle-fill"></i> Pendiente de Migración</span>
            <?php endif; ?>
            <a href="<?= url('seguridad/login') ?>" class="btn btn-sm btn-vs-secondary"><i class="bi bi-box-arrow-in-right"></i> Ir al Login</a>
        </div>
    </div>

    <?php if (!empty($dbHealth['error'])): ?>
        <div class="alert alert-danger border-0 small">
            <strong>Diagnóstico actual PostgreSQL:</strong> <?= e($dbHealth['error']) ?>
        </div>
    <?php endif; ?>

    <!-- Botón de Ejecución Directa de la Migración -->
    <div class="p-3 rounded-3 mb-4" style="background:var(--vs-sky-soft);border:1px solid var(--vs-sky);">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <div class="fw-bold" style="color:var(--vs-navy);">Ejecutar / Reinicializar Base de Datos Completa</div>
                <div class="small text-muted">Crea automáticamente la base de datos si no existe, estructura las 13 tablas en 3FN, instala los 8 triggers, los procedimientos almacenados y carga la semilla oficial.</div>
            </div>
            <form method="POST" action="<?= url('sistema/ejecutar-migracion') ?>"
                  data-confirm="Se reconstruirán las tablas y datos iniciales de Clínica Vida Salud. ¿Desea continuar?"
                  data-confirm-title="Ejecutar Migración">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <button type="submit" class="btn btn-vs-primary px-4 py-2 shadow-sm">
                    <i class="bi bi-rocket-takeoff-fill me-1"></i> Ejecutar Migración Completa Ahora
                </button>
            </form>
        </div>
    </div>

    <?php if (!empty($migrationResult['steps'])): ?>
        <div class="alert alert-success border-0 mb-4">
            <h6 class="fw-bold mb-2"><i class="bi bi-check2-all"></i> Resultado de Ejecución</h6>
            <ul class="small mb-2">
                <?php foreach ($migrationResult['steps'] as $st): ?>
                    <li><strong><?= e($st['step']) ?>:</strong> <?= e($st['message']) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if (!empty($migrationResult['summary'])): ?>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <?php foreach ($migrationResult['summary'] as $k => $v): ?>
                        <span class="badge bg-white text-dark border"><?= e(ucfirst($k)) ?>: <strong><?= (int) $v ?></strong></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Formulario para editar parámetros de PostgreSQL y Gmail SMTP en .env -->
    <form method="POST" action="<?= url('sistema/guardar-config') ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <div class="row g-3">
            <div class="col-12">
                <h6 class="fw-bold border-bottom pb-2 mb-0" style="color:var(--vs-blue);"><i class="bi bi-hdd-network-fill me-1"></i> Parámetros PostgreSQL (PDO pdo_pgsql)</h6>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small fw-semibold">Host</label>
                <input type="text" name="db_host" value="<?= e($dbCfg['host'] ?? '127.0.0.1') ?>" class="form-control form-control-sm" required>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold">Puerto</label>
                <input type="text" name="db_port" value="<?= e($dbCfg['port'] ?? '5432') ?>" class="form-control form-control-sm" required>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small fw-semibold">Base de Datos</label>
                <input type="text" name="db_database" value="<?= e($dbCfg['dbname'] ?? 'vidasalud_db') ?>" class="form-control form-control-sm" required>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold">Usuario BD</label>
                <input type="text" name="db_username" value="<?= e($dbCfg['user'] ?? 'postgres') ?>" class="form-control form-control-sm" required>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold">Contraseña BD</label>
                <input type="password" name="db_password" value="<?= e($dbCfg['password'] ?? '') ?>" class="form-control form-control-sm">
            </div>

            <div class="col-12 mt-4">
                <h6 class="fw-bold border-bottom pb-2 mb-0" style="color:var(--vs-blue);"><i class="bi bi-envelope-paper-fill me-1"></i> Servidor Gmail SMTP (Recuperación de Contraseña)</h6>
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="mail_enabled" name="mail_enabled" value="1" <?= !empty($mailCfg['enabled']) ? 'checked' : '' ?>>
                    <label class="form-check-label small fw-semibold" for="mail_enabled">Habilitar envío real por Gmail SMTP (requiere Contraseña de Aplicación de Google)</label>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small fw-semibold">Host SMTP</label>
                <input type="text" name="mail_host" value="<?= e($mailCfg['host'] ?? 'smtp.gmail.com') ?>" class="form-control form-control-sm">
            </div>
            <div class="col-3 col-md-2">
                <label class="form-label small fw-semibold">Puerto</label>
                <input type="text" name="mail_port" value="<?= e((string) ($mailCfg['port'] ?? 587)) ?>" class="form-control form-control-sm">
            </div>
            <div class="col-3 col-md-2">
                <label class="form-label small fw-semibold">Cifrado</label>
                <select name="mail_encryption" class="form-select form-select-sm">
                    <option value="tls" <?= ($mailCfg['encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS (587)</option>
                    <option value="ssl" <?= ($mailCfg['encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL (465)</option>
                </select>
            </div>
            <div class="col-12 col-md-5">
                <label class="form-label small fw-semibold">Correo Gmail Emisor</label>
                <input type="email" name="mail_username" value="<?= e($mailCfg['username'] ?? '') ?>" class="form-control form-control-sm" placeholder="tu.correo@gmail.com">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold">Contraseña de Aplicación Gmail (16 caract.)</label>
                <input type="password" name="mail_password" value="<?= e($mailCfg['password'] ?? '') ?>" class="form-control form-control-sm" placeholder="xxxx xxxx xxxx xxxx">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold">Correo Remitente Visible (From)</label>
                <input type="email" name="mail_from_address" value="<?= e($mailCfg['from_address'] ?? 'no-reply@vidasalud.com.bo') ?>" class="form-control form-control-sm">
            </div>

            <div class="col-12 text-end mt-3">
                <button type="submit" class="btn btn-outline-primary px-4">
                    <i class="bi bi-floppy-fill me-1"></i> Guardar Parámetros en .env
                </button>
            </div>
        </div>
    </form>
</div>
