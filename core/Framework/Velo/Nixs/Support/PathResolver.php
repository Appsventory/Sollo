<?php

namespace Core\Framework\Velo\Nixs\Support;

/**
 * Template path resolver
 */
class PathResolver
{
    protected static $basePath = null;

    public static function setBasePath($path)
    {
        self::$basePath = $path;
    }

    public static function resolve($template)
    {
        if (!self::$basePath) {
            self::$basePath = dirname(__DIR__, 5);
        }

        // If absolute path
        if (str_starts_with($template, '/')) {
            return $template;
        }

        // If resources/ prefix
        if (str_starts_with($template, 'resources/')) {
            return self::$basePath . '/' . $template;
        }

        // Convert dot notation to path
        $path = str_replace('.', '/', $template);

        return self::$basePath . '/resources/Views/' . $path . '.nixs.php';
    }
}
