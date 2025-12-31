<?php

use Core\Foundation\Routing\Router;
use Core\Framework\Velo\SystemInfo as Sollo;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------

*/

Router::view('/', 'home', Sollo::all());
Router::get('/docs/{version?}', function ($version = null) {
    jump(!$version, '/docs/3.x');
    return "Welcome to docs V$version";
});
