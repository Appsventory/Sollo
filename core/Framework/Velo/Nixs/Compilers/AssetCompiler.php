<?php

namespace Core\Framework\Velo\Nixs\Compilers;

use Core\Foundation\Routing\Router;

/**
 * Handles asset and URL helpers
 */
class AssetCompiler
{
    /**
     * Compile asset and URL directives
     */
    public static function compile($content)
    {
        // @asset('path/to/file.css')
        $content = preg_replace_callback(
            '/@asset\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            function ($matches) {
                return '<?php echo Core\Framework\Velo\Nixs\Compilers\AssetHelper::asset("' . $matches[1] . '"); ?>';
            },
            $content
        );

        // @url('route/path')
        $content = preg_replace_callback(
            '/@url\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            function ($matches) {
                return '<?php echo Core\Framework\Velo\Nixs\Compilers\AssetHelper::url("' . $matches[1] . '"); ?>';
            },
            $content
        );

        // @route('name', [...params]) or @route($name, [...params])
        $content = preg_replace_callback(
            '/@route\s*\(\s*(.+?)\s*(?:,\s*(.+?))?\s*\)/',
            function ($matches) {
                $routeName = trim($matches[1]);
                $params = isset($matches[2]) ? trim($matches[2]) : '[]';
                return '<?php echo Core\Framework\Velo\Nixs\Compilers\AssetHelper::route(' . $routeName . ', ' . $params . '); ?>';
            },
            $content
        );

        return $content;
    }
}

/**
 * Asset and URL helper functions
 */
class AssetHelper
{
    public static function asset($path)
    {
        $basePath = rtrim($_ENV['ASSET_URL'] ?? '/assets', '/');
        return $basePath . '/' . ltrim($path, '/');
    }

    public static function url($path)
    {
        $basePath = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8000', '/');
        return $basePath . '/' . ltrim($path, '/');
    }

    public static function route($name, $params = [])
    {
        try {
            // If a named route exists, generate its URL with parameters
            if (Router::hasNamedRoute($name)) {
                return Router::url($name, $params);
            }
        } catch (\Exception $e) {
            // Fallback below if route name is not found
        }

        // Support direct path values as fallback
        if (strpos($name, '/') === 0 || strpos($name, 'http://') === 0 || strpos($name, 'https://') === 0) {
            return self::url($name);
        }

        // Fallback to asset-style behavior for unrecognized entries
        return self::asset($name);
    }
}
