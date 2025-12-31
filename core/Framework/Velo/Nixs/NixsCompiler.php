<?php

namespace Core\Framework\Velo\Nixs;

use Core\Framework\Velo\Nixs\Support\PathResolver;
use Core\Framework\Velo\Nixs\Compilers\DirectiveCompiler;
use Core\Framework\Velo\Nixs\Compilers\ExpressionCompiler;
use Core\Framework\Velo\Nixs\Compilers\FormCompiler;
use Core\Framework\Velo\Nixs\Compilers\AssetCompiler;
use Core\Framework\Velo\Nixs\Compilers\HelperCompiler;
use Core\Framework\Velo\Nixs\Support\TemplateCache;
use Core\Framework\Velo\NixsPluginSystem;

class NixsCompiler
{
    protected static $sections = [];
    protected static $layout = '';
    protected static $layoutData = [];
    protected static $globalData = [];
    protected static $pluginSystemInitialized = false;

    /**
     * Render a template with data
     */
    public static function render($template, $data = [])
    {
        // Initialize plugin system once
        if (!self::$pluginSystemInitialized) {
            NixsPluginSystem::init();
            self::$pluginSystemInitialized = true;
        }

        $path = PathResolver::resolve($template);

        if (!file_exists($path)) {
            throw new \Exception("View '{$template}' not found at '{$path}'");
        }

        // Merge data and add helper functions
        $data = array_merge(self::$globalData, $data);

        // Add global helper functions
        if (!isset($data['session'])) {
            $data['session'] = function ($key = null, $default = null) {
                return \Core\Foundation\Http\Session::get($key, $default);
            };
        }

        if (!isset($data['env'])) {
            $data['env'] = function ($key, $default = null) {
                return \Core\Support\Env::env($key, $default);
            };
        }

        if (!isset($data['auth'])) {
            $data['auth'] = function () {
                return \Core\Foundation\Http\Session::get('user');
            };
        }

        // Compile and include template
        extract($data);
        ob_start();
        include self::getCompiledPath($path);
        $content = ob_get_clean();

        // Handle layout
        if (self::$layout) {
            $layoutPath = PathResolver::resolve(self::$layout);

            if (!file_exists($layoutPath)) {
                throw new \Exception("Layout '{$layoutPath}' not found");
            }

            // Merge layout data with original data
            $layoutData = array_merge($data, self::$layoutData);
            extract($layoutData);
            ob_start();
            include self::getCompiledPath($layoutPath);
            $output = ob_get_clean();

            // Reset state
            self::$layout = '';
            self::$layoutData = [];
            self::$sections = [];

            echo $output;
        } else {
            echo $content;
        }
    }

    /**
     * Get compiled template path, compiling if necessary
     */
    protected static function getCompiledPath($sourcePath)
    {
        $cacheKey = md5($sourcePath . filemtime($sourcePath));

        // Check cache
        if (TemplateCache::has($cacheKey)) {
            return TemplateCache::get($cacheKey);
        }

        // Compile template
        $raw = file_get_contents($sourcePath);
        $compiled = self::compile($raw);

        // Write to temp file
        $tempPath = TemplateCache::getCompiledPath($sourcePath);
        file_put_contents($tempPath, $compiled);

        // Cache path
        TemplateCache::set($cacheKey, $tempPath);

        return $tempPath;
    }

    /**
     * Compile template content
     */
    protected static function compile($content)
    {
        // 1. Apply plugins first
        $content = self::applyPlugins($content);

        // 2. Handle layout directives (@extends, @section, @yield)
        $content = DirectiveCompiler::handleLayoutDirectives(
            self::$sections,
            self::$layout,
            $content,
            self::$layoutData
        );

        // 3. Compile form helpers (@csrf, @method) BEFORE expressions
        // This must happen before expressions so {{ }} inside form tags don't break the regex
        $content = FormCompiler::compile($content);

        // 4. Compile expressions ({{ $var }}, {!! $var !!})
        $content = ExpressionCompiler::compile($content);

        // 5. Compile control structures (@if, @foreach, etc.)
        $content = DirectiveCompiler::compileControlStructures($content);

        // 6. Compile helper function calls (session() -> $session(), etc.)
        $content = HelperCompiler::compile($content);

        // 7. Compile includes (@include)
        $content = DirectiveCompiler::compileIncludes($content);

        // 8. Compile asset/URL helpers (@asset, @url, @route)
        $content = AssetCompiler::compile($content);

        return $content;
    }

    /**
     * Apply all enabled plugins
     */
    protected static function applyPlugins($content)
    {
        $directives = NixsPluginSystem::getDirectives();

        foreach ($directives as $name => $callback) {
            $content = call_user_func($callback, $content);
        }

        return $content;
    }

    /**
     * Share data globally across all templates
     */
    public static function share($key, $value = null)
    {
        if (is_array($key)) {
            self::$globalData = array_merge(self::$globalData, $key);
        } else {
            self::$globalData[$key] = $value;
        }
    }

    /**
     * Get shared global data
     */
    public static function getShared($key = null)
    {
        return $key ? (self::$globalData[$key] ?? null) : self::$globalData;
    }

    /**
     * Enable/disable cache
     */
    public static function enableCache($enabled = true)
    {
        TemplateCache::enable($enabled);
    }

    /**
     * Clear compiled template cache
     */
    public static function clearCache()
    {
        TemplateCache::clear();
    }

    /**
     * Render error template
     */
    public static function renderError($template, \Throwable $error, $data = [])
    {
        $errorData = array_merge($data, [
            'error' => $error,
            'message' => $error->getMessage(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'trace' => $error->getTraceAsString()
        ]);

        try {
            ob_start();
            self::render($template, $errorData);
            return ob_get_clean();
        } catch (\Exception $e) {
            return self::fallbackError($error, $e);
        }
    }

    /**
     * Fallback error display
     */
    protected static function fallbackError($original, $e)
    {
        return '<div style="color: #d32f2f; font-family: monospace; padding: 20px; background: #f5f5f5; border-left: 4px solid #d32f2f;">' .
            '<h3 style="margin-top: 0;">Template Compilation Error</h3>' .
            '<p><strong>Original:</strong> ' . htmlspecialchars($original->getMessage()) . '</p>' .
            '<p><strong>Template Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>' .
            '</div>';
    }
}
