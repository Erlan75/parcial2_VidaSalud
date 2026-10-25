<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

return [
    'enabled'      => (bool) env('MAIL_ENABLED', false),
    'host'         => (string) env('MAIL_HOST', 'smtp.gmail.com'),
    'port'         => (int) env('MAIL_PORT', 587),
    'encryption'   => (string) env('MAIL_ENCRYPTION', 'tls'),
    'username'     => (string) env('MAIL_USERNAME', ''),
    'password'     => (string) env('MAIL_PASSWORD', ''),
    'from_address' => (string) env('MAIL_FROM_ADDRESS', 'no-reply@vidasalud.com.bo'),
    'from_name'    => (string) env('MAIL_FROM_NAME', 'Clínica Vida Salud'),
];
