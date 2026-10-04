<?php

use Core\Foundation\Routing\Router;
use Core\Foundation\Http\Request;

require_once __DIR__ . '/FixtureController.php';
require_once __DIR__ . '/FixtureBlock.php';

Router::group(['middleware' => ['web']], function () {
    Router::get('/page', function () { view('page', ['name' => '<b>x</b>']); });
    Router::get('/page2', function () { view('page2'); });
    Router::get('/other', function () { view('page', ['name' => 'Other']); });
    Router::get('/form', function () { view('form'); });
    Router::get('/vars', function () { view('vars', ['path' => '/etc/hostname', 'template' => 'zzz', 'data' => 'ddd']); });
    Router::get('/echo-env', function () { return env('APP_NAME', 'none'); });

    Router::post('/submit', function () { return ['method' => Request::method(), 'all' => Request::all()]; });
    Router::put('/submit', function () { return ['method' => Request::method(), 'all' => Request::all()]; });
    Router::delete('/submit', function () { return ['method' => Request::method()]; });

    Router::get('/boom', function () { throw new \Exception('secret-db-password boom'); });
    Router::get('/typeerr', function () { return strlen([]); });
    Router::get('/user/{id}', function ($id) { return "id={$id}"; });
    Router::get('/num/{id}', function (int $id) { return 'int:' . var_export($id, true); });
    Router::get('/hello/{name}', function ($name) { return "hi {$name}"; });
    Router::get('/files/{b}/{a}', 'FixtureController@byName');
    Router::get('/opt/{x?}', function ($x = 'none') { return "x={$x}"; });

    Router::get('/open', 'FixtureController@open');
    Router::get('/guarded', 'FixtureController@guarded');
    Router::get('/typed', 'FixtureController@typed');
    Router::get('/data', 'FixtureController@data');
    Router::get('/hidden', 'FixtureController@hidden');
    Router::get('/ctrl-view', 'FixtureController@showPage');
    Router::post('/validate', 'FixtureController@store');
    Router::get('/authorize', 'FixtureController@needsAuthorize');

    Router::get('/mw-typo', function () { return 'LEAKED'; })->middleware('doesNotExist');
    Router::get('/mw-block', function () { return 'LEAKED'; })->middleware('fixtureBlock');
    Router::get('/mw-class', function () { return 'LEAKED'; })->middleware('FixtureBlock');

    Router::resource('/posts', 'FixtureController', ['only' => ['index', 'show']]);

    Router::post('/webhook-no-csrf', function () { return 'accepted'; });
});

Router::group(['prefix' => 'api', 'middleware' => ['api']], function () {
    Router::post('/echo', function () { return Request::all(); });
    Router::patch('/echo', function () { return ['patched' => true]; });
    Router::get('/missing-model', function () { throw new \Core\Foundation\ORM\ModelNotFoundException('App\\Models\\X', 9); });
    Router::post('/validate', 'FixtureController@store');
});
