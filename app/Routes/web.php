<?php

use Core\Foundation\Routing\Router;
use Core\Framework\Velo\SystemInfo as Sollo;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Register web routes for your application here.
| These routes are loaded by the RouteServiceProvider.
|
*/

// The welcome view reads its values from $data['NAME'], $data['VERSION'], ...
Router::view('/', 'home', ['data' => Sollo::all()]);
