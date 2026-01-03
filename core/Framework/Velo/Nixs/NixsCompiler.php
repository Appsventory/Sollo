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
     * Compile template content (public method for use in @include)
     */
    public static function compileContent($content)
    {
        // 1. Apply plugins first
        $content = self::applyPlugins($content);

        // 2. Handle layout directives (@extends, @section - but NOT @yield yet)
        $content = DirectiveCompiler::handleLayoutDirectives(
            self::$sections,
            self::$layout,
            $content,
            self::$layoutData
        );

        // 2.5 COMPILE @yield DEFAULT VALUES EARLY (before HelperCompiler)
        // This ensures @env() in defaults are compiled to actual values, not PHP code
        $content = DirectiveCompiler::compileYield(self::$sections, $content);

        // 3. Compile form helpers (@csrf, @method) BEFORE expressions
        // This must happen before expressions so {{ }} inside form tags don't break the regex
        $content = FormCompiler::compile($content);

        // 4. Compile expressions ({{ $var }}, {!! $var !!})
        $content = ExpressionCompiler::compile($content);

        // 5. Compile control structures (@if, @foreach, etc.)
        $content = DirectiveCompiler::compileControlStructures($content);

        // 6. Compile helper function calls (session() -> $session(), @env() directives, etc.)
        $content = HelperCompiler::compile($content);

        // 7. Compile includes (@include)
        $content = DirectiveCompiler::compileIncludes($content);

        // 8. Compile asset/URL helpers (@asset, @url, @route)
        $content = AssetCompiler::compile($content);

        return $content;
    }

    /**
     * Compile template content (protected method for internal use)
     */
    protected static function compile($content)
    {
        return self::compileContent($content);
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
     * Include a partial template dynamically (for dynamic @include paths)
     */
    public static function includePartial($templatePath, $variables = [])
    {
        try {
            // Resolve the template path
            $path = PathResolver::resolve($templatePath);

            if (!file_exists($path)) {
                echo "<!-- Include not found: {$templatePath} (resolved to: {$path}) -->";
                return;
            }

            // Merge with global data and helper functions
            $mergedVariables = array_merge(self::$globalData, $variables);

            // Add global helper functions if not already present
            if (!isset($mergedVariables['session'])) {
                $mergedVariables['session'] = function ($key = null, $default = null) {
                    return \Core\Foundation\Http\Session::get($key, $default);
                };
            }

            if (!isset($mergedVariables['env'])) {
                $mergedVariables['env'] = function ($key, $default = null) {
                    return \Core\Support\Env::env($key, $default);
                };
            }

            if (!isset($mergedVariables['auth'])) {
                $mergedVariables['auth'] = function () {
                    return \Core\Foundation\Http\Session::get('user');
                };
            }

            // Extract variables into current scope
            extract($mergedVariables, EXTR_SKIP);

            // Load and compile the partial content
            $partialContent = file_get_contents($path);
            $compiled = self::compileContent($partialContent);

            // Create a temporary file to include
            $tempPath = sys_get_temp_dir() . '/nixs_' . md5($path . microtime()) . '.php';

            // Write the compiled content as-is (it's already processed PHP and HTML mixed)
            if (!file_put_contents($tempPath, $compiled)) {
                echo "<!-- Error writing compiled partial to temp file -->";
                return;
            }

            // Include the compiled partial with output buffering to capture any output
            ob_start();
            include $tempPath;
            $output = ob_get_clean();
            echo $output;

            // Clean up temp file
            @unlink($tempPath);
        } catch (\Throwable $e) {
            echo "<!-- Error including partial '{$templatePath}': " . htmlspecialchars($e->getMessage()) . " -->";
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
