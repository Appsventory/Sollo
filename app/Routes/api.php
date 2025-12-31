<?php

use Core\Foundation\Routing\Router;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application.
*/

// Health check
Router::get('/health', function () {
    return ['status' => 'ok', 'message' => 'API is running'];
});
