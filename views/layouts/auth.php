<?php
declare(strict_types=1);
/** @var array $flashes */
/** @var string $content */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Acceso · Clínica Vida Salud') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= asset('css/clinical.css') ?>" rel="stylesheet">
</head>
<body>
<div class="auth-split-wrapper">
    <!-- Panel Institucional Izquierdo (Desktop) -->
    <div class="auth-hero-panel">
        <div>
            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="vs-brand-cross" style="width:52px;height:52px;font-size:1.75rem;">
                    <i class="bi bi-hospital"></i>
                </div>
                <div>
                    <h2 class="h4 mb-0 fw-bold">CLÍNICA VIDA SALUD</h2>
                    <span class="small" style="color:var(--vs-sky);">Excelencia Médica y Atención Preventiva</span>
                </div>
            </div>
            <h1 class="display-6 fw-bold mb-3">Cuidamos tu salud y la de tu familia con excelencia médica</h1>
            <p class="lead opacity-90" style="font-size:1.02rem;">
                Reserva tus consultas médicas en línea sin filas ni esperas. Contamos con especialistas altamente calificados en Medicina General, Pediatría, Ginecología, Odontología y Cardiología.
            </p>
        </div>

        <div class="p-3 rounded-4" style="background:rgba(255,255,255,0.10);border:1px solid rgba(212,230,247,0.22);">
            <div class="row g-2 text-center small">
                <div class="col-4">
                    <div class="fw-bold fs-5">+5 Especialidades</div>
                    <div class="opacity-75">Atención Médica Integral</div>
                </div>
                <div class="col-4 border-start border-end border-light border-opacity-25">
                    <div class="fw-bold fs-5">Reserva en Línea</div>
                    <div class="opacity-75">Horarios sin esperas</div>
                </div>
                <div class="col-4">
                    <div class="fw-bold fs-5">Ficha Digital</div>
                    <div class="opacity-75">Historial clínico seguro</div>
                </div>
            </div>
        </div>

        <div class="small opacity-75">
            © <?= date('Y') ?> Clínica Vida Salud · Av. San Martín, Santa Cruz de la Sierra - Bolivia
        </div>
    </div>

    <!-- Panel de Formulario Responsivo -->
    <div class="auth-form-panel">
        <div class="w-100" style="max-width: 540px;">
            <?php if (!empty($flashes)): ?>
                <?php foreach ($flashes as $f): ?>
                    <?php
                    $bsClass = match ($f['type'] ?? 'info') {
                        'success' => 'alert-success',
                        'error'   => 'alert-danger',
                        'warning' => 'alert-warning',
                        default   => 'alert-info',
                    };
                    ?>
                    <div class="alert <?= $bsClass ?> alert-dismissible fade show shadow-sm border-0 mb-3" role="alert">
                        <?php if (!empty($f['title'])): ?>
                            <strong><?= e($f['title']) ?>:</strong>
                        <?php endif; ?>
                        <?= e($f['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?= $content ?>
        </div>
    </div>
</div>

<script>
    window.VS_FLASHES = <?= json_encode($flashes ?? [], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
