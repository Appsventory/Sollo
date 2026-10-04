<?php

use Core\Support\Env;

/**
 * Environment variable (true/false/null/empty literals are converted).
 */
if (!function_exists('env')) {
    function env(string $key, $default = null)
    {
        return Env::env($key, $default);
    }
}

/**
 * Configuration value from config/<file>.php using dot notation.
 * config('app.name', 'Sollo')  => config/app.php  ['name' => ...]
 */
if (!function_exists('config')) {
    function config(string $key, $default = null)
    {
        static $loaded = [];

        $segments = explode('.', $key);
        $file = array_shift($segments);

        if (!preg_match('/^[A-Za-z0-9_\-]+$/', $file)) {
            return $default;
        }

        if (!array_key_exists($file, $loaded)) {
            $path = dirname(__DIR__, 2) . '/config/' . $file . '.php';
            $loaded[$file] = is_file($path) ? (require $path) : null;
        }

        $value = $loaded[$file];
        if ($value === null) {
            return $default;
        }

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

/**
 * Render a Nixs view (global helper)
 */
if (!function_exists('view')) {
    function view(string $template, array $data = []): void
    {
        \Core\Framework\Velo\Nixs\NixsCompiler::render($template, $data);
    }
}

/**
 * Previous form input (flashed by a failed validation).
 */
if (!function_exists('old')) {
    function old(?string $key = null, $default = null)
    {
        return \Core\Foundation\Http\Request::old($key, $default);
    }
}

/**
 * Stop the request with an HTTP error (404, 403, ...).
 */
if (!function_exists('abort')) {
    function abort(int $status, string $message = ''): void
    {
        throw new \Core\Foundation\Http\HttpException($status, $message);
    }
}

/**
 * Public asset URL (file under public/).
 * asset('css/app.css') => /css/app.css
 */
if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return \Core\Framework\Velo\Nixs\Compilers\AssetHelper::asset($path);
    }
}

/**
 * Public storage URL (file under public/storage/).
 * storage('images/logo.png') => /storage/images/logo.png
 * No symlink: works on shared hosting without storage:link.
 */
if (!function_exists('storage')) {
    function storage(string $path): string
    {
        return \Core\Foundation\Storage\Storage::url($path);
    }
}

// Short class alias for templates / app code: Storage::url(...)
if (!class_exists('Storage', false)) {
    class_alias(\Core\Foundation\Storage\Storage::class, 'Storage');
}
