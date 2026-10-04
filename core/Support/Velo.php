<?php

use Core\Support\Env;

/**
 * Ambil environment variable dengan default value
 */
if (!function_exists('env')) {
    function env(string $key, $default = null)
    {
        return Env::env($key, $default);
    }
}

function jump(bool $condition, string $url): void
{
    if ($condition) {
        header('Location: ' . $url);
        exit;
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
