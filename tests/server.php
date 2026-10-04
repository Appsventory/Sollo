<?php

/**
 * Router script for the HTTP integration tests (php -S ... tests/server.php).
 * Same boot sequence as public/index.php, plus fixture views and routes.
 */

use Core\Foundation\Routing\Router;
use Core\Framework\Exceptions\ErrorHandler as Handler;
use Core\Framework\Velo\Nixs\Support\PathResolver;

// Let the PHP built-in server serve existing static files itself
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($path !== '/' && is_file(dirname(__DIR__) . '/public' . $path)) {
    return false;
}

require dirname(__DIR__) . '/core/bootstrap.php';

PathResolver::setBasePath(__DIR__ . '/fixtures');

Handler::register();
(new Core\Providers\RouteServiceProvider())->boot();
require __DIR__ . '/fixtures/routes.php';

Router::dispatch();
