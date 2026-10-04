<?php

use Core\Foundation\Routing\Router;
use Core\Framework\Exceptions\ErrorHandler as Handler;

/*
|--------------------------------------------------------------------------
| Check If Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| If the application is maintenance / demo mode via the "down" command we
| will require this file so that any prerendered template can be shown
| instead of starting the framework, which could cause an exception.
|
*/

$maintenanceFile = __DIR__ . '/../storage/framework/down/maintenance.html';

if (is_file($maintenanceFile)) {
    http_response_code(503);
    header('Retry-After: 3600');
    header('Content-Type: text/html; charset=utf-8');
    readfile($maintenanceFile);
    exit;
}


/*
|--------------------------------------------------------------------------
| Bootstrap (autoloader, helpers, .env)
|--------------------------------------------------------------------------
|
| core/bootstrap.php registers the autoloader (Composer when vendor/ exists,
| a built-in PSR-4 loader otherwise), loads the helper functions and the
| .env file, so env() is already available when route files are loaded.
|
*/

require __DIR__ . '/../core/bootstrap.php';


/*
|--------------------------------------------------------------------------
| Error & Exception Handler Registration
|--------------------------------------------------------------------------
|
| Register the application's global exception and error handler.
| This ensures that any uncaught exceptions or PHP errors are captured,
| formatted, and rendered consistently across the framework.
|
*/

Handler::register();


/*
|--------------------------------------------------------------------------
| Service Providers
|--------------------------------------------------------------------------
|
| Register the service providers that bootstrap the application.
| This includes routing configuration, middleware setup, etc.
|
*/

$serviceProvider = new Core\Providers\RouteServiceProvider();
$serviceProvider->boot();


/*
|--------------------------------------------------------------------------
| Request Dispatching
|--------------------------------------------------------------------------
|
| Hand over the incoming HTTP request to the Router.
| The Router matches the request against the defined routes,
| executes the appropriate controller or closure, and sends
| the HTTP response back to the client.
|
*/

Router::dispatch();
