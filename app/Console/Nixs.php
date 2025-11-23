<?php

namespace App\Console;
use App\Console\NixsPluginSystem;

class Nixs
{
    protected static array $sections = [];
    protected static string $layout = '';
    protected static string $currentSection = '';
    protected static array $globalData = [];
    protected static array $compiledCache = [];
    protected static bool $cacheEnabled = true;
    protected static bool $pluginSystemInitialized = false;

    public static function render($template, $data = [])
    {
        // Initialize plugin system once
        if (!self::$pluginSystemInitialized) {
            NixsPluginSystem::init();
            self::$pluginSystemInitialized = true;
        }

        $path = self::getTemplatePath($template);

        if (!file_exists($path)) {
            throw new \Exception("View {$template} not found at {$path}");
        }

        // Merge global data
        $data = array_merge(self::$globalData, $data);
        
        extract($data);

        ob_start();
        include self::compileToTemp($path);
        $content = ob_get_clean();

        // If using layout
        if (self::$layout) {
            $layoutPath = self::getTemplatePath(self::$layout);

            if (!file_exists($layoutPath)) {
                throw new \Exception("Layout " . self::$layout . " not found at {$layoutPath}");
            }

            ob_start();
            include self::compileToTemp($layoutPath);
            $output = ob_get_clean();
            
            // Reset layout for next render
            self::$layout = '';
            self::$sections = [];
            
            echo $output;
        } else {
            echo $content;
        }
    }

    protected static function getTemplatePath($template)
    {
        if (str_starts_with($template, '/') || str_starts_with($template, 'app/')) {
            return $template;
        }
        
        $basePath = dirname(__DIR__);
        return $basePath . "/Views/" . str_replace('.', '/', $template) . ".nixs.php";
    }

    // protected static function getTemplatePath($template)
    // {
    //     $basePath = dirname(__DIR__, 1); // ini ambil /Users/.../NineVerse/app
    //     if (str_starts_with($template, '/') || str_starts_with($template, 'app/')) {
    //         return $template;
    //     }
    //     return $basePath . "/Views/" . str_replace('.', '/', $template) . ".nixs.php";
    // }


    protected static function compileToTemp($path): string
    {
        $cacheKey = md5($path . filemtime($path));
        
        // Check cache
        if (self::$cacheEnabled && isset(self::$compiledCache[$cacheKey])) {
            return self::$compiledCache[$cacheKey];
        }

        $raw = file_get_contents($path);
        $compiled = self::compile($raw);

        $tempPath = sys_get_temp_dir() . '/nixs_' . $cacheKey . '.php';
        file_put_contents($tempPath, $compiled);
        
        // Cache the temp path
        if (self::$cacheEnabled) {
            self::$compiledCache[$cacheKey] = $tempPath;
        }
        
        return $tempPath;
    }

    protected static function compile(string $content): string
    {
        // Apply plugins FIRST (before standard compilation)
        $content = self::applyPlugins($content);
        
        // Then apply standard directives
        $content = self::handleLayoutDirectives($content);
        
        // Compile variables with escaping
        $content = preg_replace('/\{\{\s*(.+?)\s*\}\}/', '<?= htmlspecialchars($1 ?? \'\', ENT_QUOTES, "UTF-8") ?>', $content);
        
        // Compile raw variables (no escaping)
        $content = preg_replace('/\{!!\s*(.+?)\s*!!\}/', '<?= $1 ?? \'\' ?>', $content);

        // Compile control structures
        $content = self::compileControlStructures($content);
        
        // Compile includes
        $content = self::compileIncludes($content);
        
        // Handle custom form methods
        $content = self::parseCustomFormMethods($content);
        
        // Handle CSRF tokens
        $content = self::handleCsrfTokens($content);
        
        // Handle asset helpers
        $content = self::handleAssetHelpers($content);

        return $content;
    }

     /**
     * Apply all enabled plugins
     */
    protected static function applyPlugins(string $content): string
    {
        $directives = NixsPluginSystem::getDirectives();
        
        foreach ($directives as $name => $callback) {
            $content = call_user_func($callback, $content);
        }
        
        return $content;
    }

    protected static function handleLayoutDirectives(string $content): string
    {
        // @extends('layouts.master')
        $content = preg_replace_callback('/@extends\([\'"](.*?)[\'"]\)/', function ($matches) {
            self::$layout = $matches[1];
            return '';
        }, $content);

        // @section('name') ... @endsection
        $content = preg_replace_callback('/@section\([\'"](.*?)[\'"]\)(.*?)@endsection/s', function ($matches) {
            $name = $matches[1];
            $code = $matches[2];
            self::$sections[$name] = $code;
            return '';
        }, $content);

        // @yield('name') or @yield('name', 'default')
        $content = preg_replace_callback('/@yield\([\'"](.*?)[\'"](?:,\s*[\'"](.*?)[\'"]\))?\)/', function ($matches) {
            $name = $matches[1];
            $default = $matches[2] ?? '';
            return self::$sections[$name] ?? $default;
        }, $content);

        return $content;
    }

    protected static function compileControlStructures(string $content): string
    {
        // Handle smart @foreach with optional @empty
        $content = self::compileSmartForeach($content);

        $directives = [
            // Conditionals
            '/@if\s*\((.*?)\)/'          => '<?php if ($1): ?>',
            '/@elseif\s*\((.*?)\)/'      => '<?php elseif ($1): ?>',
            '/@else/'                    => '<?php else: ?>',
            '/@endif/'                   => '<?php endif; ?>',
            '/@unless\s*\((.*?)\)/'      => '<?php if (!($1)): ?>',
            '/@endunless/'               => '<?php endif; ?>',

            // Loops
            '/@endforeach/'              => '<?php endforeach; ?>',
            '/@for\s*\((.*?)\)/'         => '<?php for ($1): ?>',
            '/@endfor/'                  => '<?php endfor; ?>',
            '/@while\s*\((.*?)\)/'       => '<?php while ($1): ?>',
            '/@endwhile/'                => '<?php endwhile; ?>',

            // Switch statements
            '/@switch\s*\((.*?)\)/'      => '<?php switch ($1): ?>',
            '/@case\s*\((.*?)\)/'        => '<?php case $1: ?>',
            '/@break/'                   => '<?php break; ?>',
            '/@default/'                 => '<?php default: ?>',
            '/@endswitch/'               => '<?php endswitch; ?>',

            // PHP blocks
            '/@php(.*?)@endphp/s'        => '<?php $1 ?>',
            '/@php\s*\((.*?)\)/'         => '<?php $1 ?>',

            // Comments
            '/@\*\*(.*?)\*\*@/s'         => '<?php /* $1 */ ?>',

            // Continue and break in loops
            '/@continue\s*\((.*?)\)/'    => '<?php if ($1) continue; ?>',
            '/@break\s*\((.*?)\)/'       => '<?php if ($1) break; ?>',
            '/@continue/'                => '<?php continue; ?>',
        ];

        foreach ($directives as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }

        return $content;
    }
    
    protected static function compileSmartForeach(string $content): string
    {
    // Pattern untuk detect @foreach ... @empty ... @endforeach
    $pattern = '/@foreach\s*\((.*?)\)(.*?)@endforeach/s';
    
    $content = preg_replace_callback($pattern, function ($matches) {
        $expression = $matches[1];  // e.g., "$users as $user"
        $body = $matches[2];        // Everything between @foreach and @endforeach
        
        // Check if body contains @empty
        if (strpos($body, '@empty') !== false) {
            // Split body by @empty
            $parts = preg_split('/@empty/', $body, 2);
            $foreachContent = $parts[0];
            $emptyContent = isset($parts[1]) ? $parts[1] : '';
            
            // Extract variable from expression (e.g., "$users" from "$users as $user")
            preg_match('/^\s*(\$\w+)/', $expression, $varMatch);
            $variable = $varMatch[1] ?? '$items';
            
            // Compile to forelse pattern
            $compiled = "<?php if (!empty({$variable})): foreach ({$expression}): ?>";
            $compiled .= $foreachContent;
            $compiled .= "<?php endforeach; else: ?>";
            $compiled .= $emptyContent;
            $compiled .= "<?php endif; ?>";
            
            return $compiled;
        } else {
            // Regular foreach (no @empty)
            $compiled = "<?php foreach ({$expression}): ?>";
            $compiled .= $body;
            // Note: @endforeach akan di-compile oleh directive biasa
            
            return $compiled . '@endforeach';
        }
    }, $content);
    
    return $content;
}

    protected static function compileIncludes(string $content): string
    {
        // @include('partial.name') or @include('partial.name', ['data' => 'value'])
        $content = preg_replace_callback('/@include\([\'"](.*?)[\'"](?:,\s*(.+?))?\)/', function ($matches) {
            $includePath = self::getTemplatePath($matches[1]);
            $data = isset($matches[2]) ? $matches[2] : '[]';
            
            if (!file_exists($includePath)) {
                return "<!-- Include not found: {$matches[1]} -->";
            }
            
            return "<?php 
                \$__includeData = array_merge(get_defined_vars(), {$data}); 
                extract(\$__includeData);
                include '" . addslashes(self::compileToTemp($includePath)) . "'; 
            ?>";
        }, $content);

        return $content;
    }

    protected static function handleCsrfTokens(string $content): string
    {
        // @csrf directive
        $content = str_replace('@csrf', '<?php echo self::csrfField(); ?>', $content);
        
        // @method('PUT') directive
        $content = preg_replace('/@method\([\'"](.*?)[\'"]\)/', '<?php echo self::methodField(\'$1\'); ?>', $content);

        return $content;
    }

    protected static function handleAssetHelpers(string $content): string
    {
        // @asset('path/to/file.css')
        $content = preg_replace('/@asset\([\'"](.*?)[\'"]\)/', '<?php echo self::asset(\'$1\'); ?>', $content);
        
        // @url('route/path')
        $content = preg_replace('/@url\([\'"](.*?)[\'"]\)/', '<?php echo self::url(\'$1\'); ?>', $content);

        return $content;
    }

    protected static function parseCustomFormMethods(string $content): string
    {
        return preg_replace_callback(
            '/<form([^>]*?)method=["\'](PUT|DELETE|PATCH)["\'](.*?)>/i',
            function ($matches) {
                $attributes = $matches[1] . $matches[3];
                $method = strtoupper($matches[2]);
                $formTag = "<form{$attributes} method=\"POST\">";
                $hiddenInput = "\n    " . self::methodField($method);
                return $formTag . $hiddenInput;
            },
            $content
        );
    }

    // Helper methods for directives
    public static function csrfField(): string
    {
        $token = self::generateCsrfToken();
        return '<input type="hidden" name="_token" value="' . $token . '">';
    }

    public static function methodField(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . strtoupper($method) . '">';
    }

    public static function asset(string $path): string
    {
        $basePath = rtrim($_ENV['ASSET_URL'] ?? '/assets', '/');
        return $basePath . '/' . ltrim($path, '/');
    }

    public static function url(string $path): string
    {
        $basePath = rtrim($_ENV['APP_URL'] ?? '', '/');
        return $basePath . '/' . ltrim($path, '/');
    }

    protected static function generateCsrfToken(): string
    {
        if (!isset($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_token'];
    }

    // Global data methods
    public static function share($key, $value = null)
    {
        if (is_array($key)) {
            self::$globalData = array_merge(self::$globalData, $key);
        } else {
            self::$globalData[$key] = $value;
        }
    }

    public static function getShared($key = null)
    {
        return $key ? (self::$globalData[$key] ?? null) : self::$globalData;
    }

    // Cache control
    public static function enableCache(bool $enabled = true)
    {
        self::$cacheEnabled = $enabled;
    }

    public static function clearCache()
    {
        // Clear compiled cache
        self::$compiledCache = [];
        
        // Clear temp files
        $tempDir = sys_get_temp_dir();
        $files = glob($tempDir . '/nixs_*.php');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    // Component support
    public static function component(string $name, array $data = []): string
    {
        $componentPath = "app/Views/components/{$name}.nixs.php";
        
        if (!file_exists($componentPath)) {
            return "<!-- Component not found: {$name} -->";
        }

        ob_start();
        extract($data);
        include self::compileToTemp($componentPath);
        return ob_get_clean();
    }

    // Slot support for components
    public static function slot(string $name, string $content = ''): string
    {
        return "<?php \$__slots['{$name}'] = '{$content}'; ?>";
    }

    // Error handling
    public static function renderError(string $template, \Throwable $error, array $data = []): string
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
            // Fallback to simple error display
            return "<div style='color: red; font-family: monospace;'>" .
                   "<h3>Template Error</h3>" .
                   "<p><strong>Original:</strong> " . htmlspecialchars($error->getMessage()) . "</p>" .
                   "<p><strong>Template:</strong> " . htmlspecialchars($e->getMessage()) . "</p>" .
                   "</div>";
        }
    }
}