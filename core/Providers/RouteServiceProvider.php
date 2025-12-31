<?php

namespace Core\Providers;

use Core\Foundation\Routing\Router;

class RouteServiceProvider
{
    /**
     * Define route model bindings, pattern filters, etc.
     */
    public function boot()
    {
        $this->routes();
    }

    /**
     * Define the routes for the application.
     */
    protected function routes()
    {
        $this->apiRoutes();
        $this->webRoutes();
    }

    /**
     * Define the "api" routes for the application.
     */
    protected function apiRoutes()
    {
        Router::group([
            'prefix' => 'api',
            'middleware' => ['api']
        ], function () {
            require $this->basePath('app/Routes/api.php');
        });
    }

    /**
     * Define the "web" routes for the application.
     */
    protected function webRoutes()
    {
        Router::group([
            'middleware' => ['web']
        ], function () {
            require $this->basePath('app/Routes/web.php');
        });
    }

    /**
     * Get the base path of the application.
     */
    protected function basePath($path = '')
    {
        return dirname(__DIR__, 2) . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
    }
}
