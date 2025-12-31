<?php

use Core\Support\Env;

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

if (file_exists(__DIR__ . '/../storage/framework/down/maintenance.html')) {
    require __DIR__ . '/../storage/framework/down/maintenance.html';
    exit;
}


/*
|--------------------------------------------------------------------------
| Autoloader Registration
|--------------------------------------------------------------------------
|
| Composer automatically generates a PSR-4 compliant class loader.
| By requiring it here, all of our classes in 'core/' and 'app/' 
| directories can be autoloaded without manually including files.
|
*/

require __DIR__ . '/../vendor/autoload.php';


/*
|--------------------------------------------------------------------------
| Environment Variables Loader
|--------------------------------------------------------------------------
|
| Load configuration values from the '.env' file into the runtime environment.
| These variables are accessible via Env::get() and are globally available 
| through $_ENV and putenv(), making configuration centralized and secure.
|
*/

Env::load(__DIR__ . '/../.env');


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

require __DIR__ . '/../core/Support/Velo.php';


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
