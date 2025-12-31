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
