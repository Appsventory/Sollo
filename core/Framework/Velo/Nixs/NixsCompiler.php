<?php

namespace Core\Framework\Velo\Nixs;

use Core\Framework\Velo\Nixs\Support\PathResolver;
use Core\Framework\Velo\Nixs\Compilers\DirectiveCompiler;
use Core\Framework\Velo\Nixs\Compilers\ExpressionCompiler;
use Core\Framework\Velo\Nixs\Compilers\FormCompiler;
use Core\Framework\Velo\Nixs\Compilers\AssetCompiler;
use Core\Framework\Velo\Nixs\Compilers\HelperCompiler;
use Core\Framework\Velo\Nixs\Support\TemplateCache;

class NixsCompiler
{
    protected static $sections = [];
    protected static $layout = '';
    protected static $layoutData = [];
    protected static $globalData = [];
    protected static $maskedCodeBlocks = [];

    /**
     * Render a template with data
     */
    public static function render($template, $data = [])
    {
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

    protected static function maskCodeBlocks($content)
    {
        self::$maskedCodeBlocks = [];

        return preg_replace_callback('/<pre.*?>.*?<code.*?>.*?<\/code>.*?<\/pre>/si', function ($matches) {
            $block = $matches[0];

            // Keep code blocks that contain template expressions so card partials and
            // similar reusable components can still compile their own placeholders.
            if (preg_match('/\{\{\s*.*?\}\}|\{!\!\s*.*?!!\}/s', $block)) {
                return $block;
            }

            $key = '__MASKED_CODE_BLOCK_' . count(self::$maskedCodeBlocks) . '__';
            self::$maskedCodeBlocks[$key] = $matches[0];
            return $key;
        }, $content);
    }

    protected static function restoreCodeBlocks($content)
    {
        return str_replace(array_keys(self::$maskedCodeBlocks), array_values(self::$maskedCodeBlocks), $content);
    }

    /**
     * Compile template content (public method for use in @include)
     */
    public static function compileContent($content)
    {
        // Mask code blocks so directives inside examples do not get processed
        $content = self::maskCodeBlocks($content);

        // 1. Apply plugins (no-op currently)
        $content = self::applyPlugins($content);

        // 2. Strip template comments early so they never reach expression/control compilers
        $content = DirectiveCompiler::compileComments($content);

        // 3. Compile @nixscomponent blocks (body/slot captured raw)
        $content = DirectiveCompiler::compileNixsComponents($content);

        // 4. Layout directives (@extends, @section — not @yield yet)
        $content = DirectiveCompiler::handleLayoutDirectives(
            self::$sections,
            self::$layout,
            $content,
            self::$layoutData
        );

        // 5. Compile @yield (so defaults can still contain @env etc.)
        $content = DirectiveCompiler::compileYield(self::$sections, $content);

        // 6. Form helpers (@csrf, @method) before expressions
        $content = FormCompiler::compile($content);

        // 7. Expressions ({{ $var }}, {!! $var !!})
        $content = ExpressionCompiler::compile($content);

        // 8. Control structures (@if, @foreach/@empty, @php, @default, …)
        $content = DirectiveCompiler::compileControlStructures($content);

        // 9. Helpers (@env, @session, @auth, bare env()/session())
        $content = HelperCompiler::compile($content);

        // 10. Includes (@include)
        $content = DirectiveCompiler::compileIncludes($content);

        // 11. Asset / URL helpers (@asset, @url, @route)
        $content = AssetCompiler::compile($content);

        // Restore masked code blocks after all directive compilation
        $content = self::restoreCodeBlocks($content);

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
     * Resolve component template key.
     * Prefers resources/Views/components/{name}, falls back to resources/Views/card/{name}.
     */
    public static function resolveComponentTemplate(string $name): string
    {
        $name = str_replace(['/', '\\'], '.', trim($name, "/\\ \t\n\r\0\x0B."));
        $name = preg_replace('/^(components|card)\./', '', $name) ?? $name;

        $candidates = [
            'components.' . $name,
            'card.' . $name,
        ];

        foreach ($candidates as $template) {
            $path = PathResolver::resolve($template);
            if (file_exists($path)) {
                return $template;
            }
        }

        // Default target (error message will show this path)
        return 'components.' . $name;
    }

    /**
     * Render a reusable component (returns HTML string).
     *
     * Looks in resources/Views/components/ then resources/Views/card/.
     * Optional $data['body'] / $data['slot'] for slot content (used by @nixscomponent).
     *
     * Example:
     *   echo Nixs::component('Button', ['text' => 'Save']);
     *   {!! Nixs::component('Alert', ['type' => 'warn', 'body' => 'Hi']) !!}
     */
    public static function component(string $name, array $data = []): string
    {
        $template = self::resolveComponentTemplate($name);

        // Normalize slot aliases
        if (isset($data['body']) && !isset($data['slot'])) {
            $data['slot'] = $data['body'];
        } elseif (isset($data['slot']) && !isset($data['body'])) {
            $data['body'] = $data['slot'];
        }

        ob_start();
        try {
            self::render($template, $data);
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            return '<!-- Component not found or error: ' . htmlspecialchars($name . ' — ' . $e->getMessage()) . ' -->';
        }
    }


    /**
     * Apply plugins (no-op; plugin system removed as unused)
     */
    protected static function applyPlugins($content)
    {
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
     * Echo a component with optional body/slot (used by compiled @nixscomponent).
     */
    public static function includeComponent($name, $variables = [], $body = '')
    {
        $variables = array_merge((array) $variables, [
            'body' => $body,
            'slot' => $body,
        ]);
        echo self::component((string) $name, $variables);
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
