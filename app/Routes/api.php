<?php

use Core\Foundation\Routing\Router;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Register API routes for your application here.
|
*/

Router::get('/health', function () {
    return ['status' => 'ok', 'message' => 'API is running'];
});
