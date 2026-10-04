<?php

namespace Core\Framework\Velo\Nixs\Compilers;

use Core\Foundation\Routing\Router;
use Core\Foundation\Storage\Storage;

/**
 * Handles asset and URL helpers in Nixs templates.
 *
 * Directives:
 *   @asset('css/app.css')
 *   @url('/about')
 *   @route('name', [...])
 *   @storage('images/logo.png')
 *
 * Also supports bare functions after HelperCompiler runs if injected:
 *   asset('...'), storage('...'), url('...')
 */
class AssetCompiler
{
    public static function compile($content)
    {
        // @storage('path') → public/storage URL
        $content = preg_replace_callback(
            '/@storage\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            function ($matches) {
                return '<?= \\Core\\Foundation\\Storage\\Storage::url(\'' . addslashes($matches[1]) . '\') ?>';
            },
            $content
        );

        // @asset('path') — file under public/
        $content = preg_replace_callback(
            '/@asset\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            function ($matches) {
                return '<?= \\Core\\Framework\\Velo\\Nixs\\Compilers\\AssetHelper::asset(\'' . addslashes($matches[1]) . '\') ?>';
            },
            $content
        );

        // @url('path')
        $content = preg_replace_callback(
            '/@url\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            function ($matches) {
                return '<?= \\Core\\Framework\\Velo\\Nixs\\Compilers\\AssetHelper::url(\'' . addslashes($matches[1]) . '\') ?>';
            },
            $content
        );

        // @route('name', [...params]) or @route($name, [...params])
        $content = preg_replace_callback(
            '/@route\s*\(\s*(.+?)\s*(?:,\s*(.+?))?\s*\)/',
            function ($matches) {
                $routeName = trim($matches[1]);
                $params = isset($matches[2]) ? trim($matches[2]) : '[]';
                return '<?= \\Core\\Framework\\Velo\\Nixs\\Compilers\\AssetHelper::route(' . $routeName . ', ' . $params . ') ?>';
            },
            $content
        );

        return $content;
    }
}

