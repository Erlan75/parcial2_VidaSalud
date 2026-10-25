<?php
declare(strict_types=1);

/**
 * Front Controller Principal - Clínica Vida Salud
 * Compatible con el servidor nativo de PHP: php -S localhost:8000 -t public
 */

if (PHP_SAPI === 'cli-server') {
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $staticFile = __DIR__ . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $uriPath), DIRECTORY_SEPARATOR);
    if ($uriPath !== '/' && $uriPath !== '/index.php' && is_file($staticFile)) {
        return false;
    }
}

require_once dirname(__DIR__) . '/config/database.php';

\App\Core\Router::dispatch();
