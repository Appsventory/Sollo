<?php

namespace Core\Framework\Velo\Nixs\Compilers;

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

        // @route('name', [...params])
        $content = preg_replace_callback(
            '/@route\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*(.+?))?\s*\)/',
            function ($matches) {
                $params = isset($matches[2]) ? trim($matches[2]) : '[]';
                return '<?php echo Core\Framework\Velo\Nixs\Compilers\AssetHelper::route("' . $matches[1] . '", ' . $params . '); ?>';
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
        // TODO: Implement route() helper via Router
        $path = '/' . ltrim($name, '/');
        return self::url($path);
    }
}
