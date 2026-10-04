<?php

/*
| Application configuration. Read values anywhere with config('app.name').
| Values come from .env (env() converts "true"/"false"/"null" to PHP values).
*/

return [
    'name' => env('APP_NAME', 'Sollo'),
    'env' => env('APP_ENV', 'production'),
    'debug' => env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost:8000'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
];
