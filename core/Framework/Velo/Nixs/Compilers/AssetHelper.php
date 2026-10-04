<?php

namespace Core\Framework\Velo\Nixs\Compilers;

use Core\Foundation\Routing\Router;
use Core\Foundation\Storage\Storage;

class AssetHelper
{
    /**
     * URL to a file under public/.
     * asset('css/app.css')           => /css/app.css
     * asset('storage/images/x.png')  => /storage/images/x.png
     */
    public static function asset(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $base = rtrim((string) (\Core\Support\Env::raw('APP_URL', '')), '/');
        $rel = '/' . $path;

        return $base === '' ? $rel : $base . $rel;
    }

    public static function url(string $path): string
    {
        $path = '/' . ltrim(str_replace('\\', '/', $path), '/');
        $base = rtrim((string) (\Core\Support\Env::raw('APP_URL', '')), '/');

        return $base === '' ? $path : $base . $path;
    }

    public static function storage(string $path): string
    {
        return Storage::url($path);
    }

    public static function route($name, $params = [])
    {
        try {
            if (Router::hasNamedRoute($name)) {
                return Router::url($name, $params);
            }
        } catch (\Exception $e) {
            // fall through
        }

        if (is_string($name) && (str_starts_with($name, '/') || str_starts_with($name, 'http://') || str_starts_with($name, 'https://'))) {
            return self::url($name);
        }

        return self::url((string) $name);
    }
}
