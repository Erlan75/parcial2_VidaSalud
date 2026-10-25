<?php
declare(strict_types=1);
/** @var string $content */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Verificación QR · Clínica Vida Salud') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= asset('css/clinical.css') ?>" rel="stylesheet">
</head>
<body class="py-4 px-3">
    <div class="container" style="max-width:680px;">
        <?= $content ?>
    </div>
</body>
</html>
