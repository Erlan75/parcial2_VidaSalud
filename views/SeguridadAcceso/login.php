<?php
declare(strict_types=1);
/** @var array $dbHealth */
/** @var string $csrfToken */
/** @var bool|null $openRegisterModal */
?>
<div class="vs-card p-4 p-md-5">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="d-flex align-items-center gap-2">
            <div class="vs-brand-cross d-lg-none" style="background:var(--vs-navy);color:#fff;">
                <i class="bi bi-hospital"></i>
            </div>
            <div>
                <h2 class="h4 fw-bold mb-0" style="color:var(--vs-navy);">Iniciar Sesión</h2>
                <p class="text-muted small mb-0">Portal Clínico de Atención y Citas Médicas</p>
            </div>
        </div>
    </div>

    <?php if (empty($dbHealth['migrated'])): ?>
        <div class="alert alert-warning border-0 small mb-4">
            <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill"></i> Servicio en configuración inicial</div>
            <div class="mb-2"><?= e($dbHealth['error'] ?? 'La base de datos se encuentra pendiente de inicialización.') ?></div>
            <a href="<?= url('sistema/migracion') ?>" class="btn btn-sm btn-vs-primary">
                <i class="bi bi-play-circle-fill"></i> Configurar Base de Datos
            </a>
        </div>
    <?php endif; ?>

    <!-- Resumen institucional visible en pantallas móviles -->
    <div class="d-lg-none p-3 rounded-3 mb-4" style="background:var(--vs-sky-soft);border:1px solid var(--vs-sky);">
        <div class="fw-bold small mb-1" style="color:var(--vs-navy);">
            Cuidamos tu salud y la de tu familia con excelencia médica
        </div>
        <div class="text-muted" style="font-size:0.78rem;">
            Reserva tus consultas médicas en línea sin filas ni esperas en Medicina General, Pediatría, Ginecología, Odontología y Cardiología.
        </div>
    </div>

    <form method="POST" action="<?= url('seguridad/login') ?>" class="needs-validation" novalidate autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

        <div class="mb-3">
            <label for="identificador" class="form-label fw-semibold small">Usuario o Correo Electrónico</label>
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-person-fill text-primary"></i></span>
                <input type="text" class="form-control" id="identificador" name="identificador"
                       value="" placeholder="Ingresa tu usuario o correo electrónico" required autofocus>
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
                       value="" placeholder="Ingresa tu contraseña" required>
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

    <div class="mt-4 pt-3 border-top text-center">
        <p class="small text-muted mb-2">¿Eres paciente nuevo?</p>
        <button type="button" class="btn btn-outline-primary w-100 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalRegistroPaciente">
            <i class="bi bi-person-plus-fill me-1"></i> Crear una cuenta para agendar tu cita
        </button>
    </div>
</div>

<!-- MODAL DE REGISTRO DE PACIENTE NUEVO -->
<div class="modal fade" id="modalRegistroPaciente" tabindex="-1" aria-labelledby="modalRegistroPacienteLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form method="POST" action="<?= url('registro-paciente') ?>" class="modal-content border-0 shadow needs-validation" novalidate>
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <div class="modal-header text-white" style="background:var(--vs-navy);">
                <div>
                    <h5 class="modal-title fs-6 fw-bold mb-0" id="modalRegistroPacienteLabel">
                        <i class="bi bi-person-plus-fill me-1"></i> Crear Cuenta de Paciente · Clínica Vida Salud
                    </h5>
                    <small class="opacity-75" style="font-size:0.75rem;">Completa tus datos personales para reservar tus citas médicas en línea</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-12">
                        <h6 class="fw-bold small text-uppercase mb-0 pb-1 border-bottom" style="color:var(--vs-blue);">
                            <i class="bi bi-person-vcard me-1"></i> Datos Personales
                        </h6>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold">Cédula de Identidad (CI) *</label>
                        <input type="text" name="ci" class="form-control" required
                               pattern="^[0-9]{5,12}(-[A-Za-z0-9]{1,3})?$"
                               placeholder="Ej. 7845120">
                        <div class="invalid-feedback">Ingrese un número de CI válido (5 a 12 dígitos).</div>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold">Nombres *</label>
                        <input type="text" name="nombres" class="form-control" required minlength="2"
                               placeholder="Tus nombres">
                        <div class="invalid-feedback">Ingrese sus nombres.</div>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold">Apellidos *</label>
                        <input type="text" name="apellidos" class="form-control" required minlength="2"
                               placeholder="Tus apellidos">
                        <div class="invalid-feedback">Ingrese sus apellidos.</div>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold">Fecha de Nacimiento *</label>
                        <input type="date" name="fecha_nacimiento" max="<?= date('Y-m-d') ?>" class="form-control" required>
                        <div class="invalid-feedback">Seleccione su fecha de nacimiento.</div>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold">Sexo *</label>
                        <select name="sexo" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <option value="F">Femenino</option>
                            <option value="M">Masculino</option>
                            <option value="O">Otro</option>
                        </select>
                        <div class="invalid-feedback">Seleccione una opción.</div>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold">Teléfono / Celular *</label>
                        <input type="text" name="telefono" class="form-control" required
                               placeholder="Ej. 70012345">
                        <div class="invalid-feedback">Ingrese un número de teléfono válido.</div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Correo Electrónico *</label>
                        <input type="email" name="correo" class="form-control" required
                               placeholder="tucorreo@ejemplo.com">
                        <div class="invalid-feedback">Ingrese un correo electrónico válido.</div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Dirección Domiciliaria *</label>
                        <input type="text" name="direccion" class="form-control" required minlength="4"
                               placeholder="Barrio, avenida o calle">
                        <div class="invalid-feedback">Ingrese su dirección.</div>
                    </div>

                    <div class="col-12 mt-4">
                        <h6 class="fw-bold small text-uppercase mb-0 pb-1 border-bottom" style="color:var(--vs-blue);">
                            <i class="bi bi-shield-lock me-1"></i> Credenciales de Acceso al Portal
                        </h6>
                    </div>

                    <div class="col-12 col-md-5">
                        <label class="form-label small fw-semibold">Nombre de Usuario *</label>
                        <input type="text" name="nombre_usuario" class="form-control" required minlength="3"
                               placeholder="Ej. maria.lopez">
                        <div class="invalid-feedback">Elija un usuario de al menos 3 caracteres.</div>
                    </div>

                    <div class="col-12 col-md-7">
                        <label class="form-label small fw-semibold">Contraseña *</label>
                        <div class="input-group">
                            <input type="password" name="password" id="reg_password" class="form-control" required
                                   data-strict-password data-pwd-meter="regPwdMeter"
                                   placeholder="Mínimo 8 caracteres (Mayúscula, número y símbolo)">
                            <button class="btn btn-outline-secondary" type="button" onclick="const p=document.getElementById('reg_password');p.type=p.type==='password'?'text':'password';">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div id="regPwdMeter" class="d-flex flex-wrap gap-1 mt-2">
                            <span class="pwd-rule-pill" data-rule="len">8+ caract.</span>
                            <span class="pwd-rule-pill" data-rule="upper">1 Mayúscula</span>
                            <span class="pwd-rule-pill" data-rule="lower">1 Minúscula</span>
                            <span class="pwd-rule-pill" data-rule="num">1 Número</span>
                            <span class="pwd-rule-pill" data-rule="spec">1 Símbolo</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-vs-primary px-4 fw-semibold">
                    <i class="bi bi-check2-circle me-1"></i> Crear Cuenta e Ingresar
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($openRegisterModal)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('modalRegistroPaciente');
    if (modalEl && typeof bootstrap !== 'undefined') {
        new bootstrap.Modal(modalEl).show();
    }
});
</script>
<?php endif; ?>
