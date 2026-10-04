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
    /** Sections captured while rendering the current view (runtime state). */
    protected static array $sections = [];
    protected static array $sectionStack = [];
    /** Pending layout: [templateName, data] or null. */
    protected static ?array $layout = null;
    protected static array $globalData = [];

    /**
     * Render a template with data and echo it.
     *
     * render() is re-entrant: components rendered from inside a view get their
     * own layout/section state, and the parent's state is restored afterwards.
     */
    public static function render($template, $data = [])
    {
        $path = PathResolver::resolve($template);

        if (!file_exists($path)) {
            throw new \Exception("View '{$template}' not found at '{$path}'");
        }

        $data = self::withHelpers(array_merge(self::$globalData, (array) $data));

        $saved = [self::$sections, self::$sectionStack, self::$layout];
        self::$sections = [];
        self::$sectionStack = [];
        self::$layout = null;
        $obLevel = ob_get_level();

        try {
            $output = self::evaluate(self::getCompiledPath($path), $data);

            $depth = 0;
            while (self::$layout !== null) {
                if (++$depth > 10) {
                    throw new \RuntimeException('Layout nesting is too deep (possible @extends loop).');
                }

                [$layoutName, $layoutData] = self::$layout;
                self::$layout = null;

                $layoutPath = PathResolver::resolve($layoutName);
                if (!file_exists($layoutPath)) {
                    throw new \Exception("Layout '{$layoutName}' not found at '{$layoutPath}'");
                }

                $data = array_merge($data, $layoutData);
                $output = self::evaluate(self::getCompiledPath($layoutPath), $data);
            }

            echo $output;
        } catch (\Throwable $e) {
            while (ob_get_level() > $obLevel) {
                ob_end_clean();
            }
            throw $e;
        } finally {
            [self::$sections, self::$sectionStack, self::$layout] = $saved;
        }
    }

    /**
     * Evaluate a compiled template in an isolated scope and return its output.
     * View data can never overwrite the internal variables (EXTR_SKIP).
     */
    private static function evaluate(string $__compiledPath, array $__data): string
    {
        extract($__data, EXTR_SKIP);
        ob_start();
        try {
            include $__compiledPath;
        } catch (\Throwable $__e) {
            ob_end_clean();
            throw $__e;
        }
        return (string) ob_get_clean();
    }

    /**
     * Built-in template helpers ($session, $env, $auth, $asset, $storage).
     */
    protected static function withHelpers(array $data): array
    {
        $data['session'] ??= fn($key = null, $default = null) => \Core\Foundation\Http\Session::get($key, $default);
        $data['env'] ??= fn($key, $default = null) => \Core\Support\Env::env($key, $default);
        $data['auth'] ??= fn() => \Core\Foundation\Http\Session::get('user');
        $data['asset'] ??= fn(string $path) => \Core\Framework\Velo\Nixs\Compilers\AssetHelper::asset($path);
        $data['storage'] ??= fn(string $path) => \Core\Foundation\Storage\Storage::url($path);

        return $data;
    }

    // ------------------------------------------------------------------
    // Layout runtime API (called from compiled templates)
    // ------------------------------------------------------------------

    public static function extend(string $layout, array $data = []): void
    {
        self::$layout = [$layout, $data];
    }

    public static function startSection(string $name): void
    {
        self::$sectionStack[] = $name;
        ob_start();
    }

    public static function endSection(): void
    {
        if (!self::$sectionStack) {
            throw new \LogicException('@endsection without a matching @section.');
        }

        $name = array_pop(self::$sectionStack);
        // Surrounding blank lines are not content ( <title>@yield('title')</title> stays tidy )
        $content = trim((string) ob_get_clean());

        // The first definition wins: a child view overrides defaults set by its layout.
        if (!array_key_exists($name, self::$sections)) {
            self::$sections[$name] = $content;
        }
    }

    public static function hasSection(string $name): bool
    {
        return array_key_exists($name, self::$sections);
    }

    public static function section(string $name): string
    {
        return self::$sections[$name] ?? '';
    }

    /**
     * Get the compiled file for a source template, compiling when needed.
     */
    protected static function getCompiledPath($sourcePath)
    {
        clearstatcache(true, $sourcePath);
        $mtime = (int) filemtime($sourcePath);

        $cached = TemplateCache::find($sourcePath, $mtime);
        if ($cached !== null) {
            return $cached;
        }

        $compiled = self::compileContent((string) file_get_contents($sourcePath));

        return TemplateCache::store($sourcePath, $mtime, $compiled);
    }

    /**
     * Compile template source to PHP. Pure function of its input (re-entrant).
     */
    public static function compileContent($content)
    {
        // Mask code blocks so directives inside examples do not get processed
        $masked = [];
        $content = self::maskCodeBlocks($content, $masked);

        // 1. Strip template comments early so they never reach expression/control compilers
        $content = DirectiveCompiler::compileComments($content);

        // 2. @nixscomponent blocks (body compiled and captured at runtime)
        $content = DirectiveCompiler::compileNixsComponents($content);

        // 3. Layout directives (@extends, @section, @endsection) -> runtime calls
        $content = DirectiveCompiler::handleLayoutDirectives($content);

        // 4. @yield -> runtime section lookup (default text stays inline)
        $content = DirectiveCompiler::compileYield($content);

        // 5. Form helpers (@csrf, @method) before expressions
        $content = FormCompiler::compile($content);

        // 6. Expressions ({{ $var }}, {!! $var !!})
        $content = ExpressionCompiler::compile($content);

        // 7. Control structures (@if, @foreach/@empty, @php, @default, ...)
        $content = DirectiveCompiler::compileControlStructures($content);

        // 8. Asset / URL / storage directives (@asset, @url, @route, @storage)
        //    Must run before HelperCompiler so @storage is not rewritten to @$storage
        $content = AssetCompiler::compile($content);

        // 9. Helpers (@env, @session, @auth, bare env()/session()/asset()/storage())
        $content = HelperCompiler::compile($content);

        // 10. Includes (@include) -> runtime includePartial()
        $content = DirectiveCompiler::compileIncludes($content);

        // Restore masked code blocks after all directive compilation
        return str_replace(array_keys($masked), array_values($masked), $content);
    }

    protected static function maskCodeBlocks($content, array &$masked)
    {
        $mask = function ($block) use (&$masked) {
            // Keep blocks that intentionally use live template expressions
            if (preg_match('/\{\{\s*.*?\}\}|\{!\!\s*.*?!!\}/s', $block)) {
                return $block;
            }
            $key = '__MASKED_CODE_BLOCK_' . count($masked) . '_' . bin2hex(random_bytes(3)) . '__';
            $masked[$key] = $block;
            return $key;
        };

        // HTML <pre><code> ... </code></pre>
        $content = preg_replace_callback('/<pre\b[^>]*>\s*<code\b[^>]*>.*?<\/code>\s*<\/pre>/si', function ($matches) use ($mask) {
            return $mask($matches[0]);
        }, $content);

        // Markdown fenced code blocks ``` ... ```
        $content = preg_replace_callback('/```[\w+-]*\r?\n.*?```/s', function ($matches) use ($mask) {
            return $mask($matches[0]);
        }, $content);

        return $content;
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
            throw $e;
        }
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
    public static function clearCache(): int
    {
        return TemplateCache::clear();
    }

    /**
     * Include a partial (compiled @include). $variables already contains the
     * parent scope, so partials inside @foreach can see the loop variable.
     */
    public static function includePartial($templatePath, $variables = [])
    {
        $path = PathResolver::resolve((string) $templatePath);

        if (!file_exists($path)) {
            throw new \Exception("Included view '{$templatePath}' not found at '{$path}'");
        }

        $vars = self::withHelpers(array_merge(self::$globalData, is_array($variables) ? $variables : []));

        echo self::evaluate(self::getCompiledPath($path), $vars);
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
}
