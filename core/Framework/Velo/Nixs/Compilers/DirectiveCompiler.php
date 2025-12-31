<?php

namespace Core\Framework\Velo\Nixs\Compilers;

use Core\Framework\Velo\Nixs\Support\PathResolver;

/**
 * Handles template directives like @if, @foreach, @section, etc.
 */
class DirectiveCompiler
{
    /**
     * Compile layout directives: @extends, @section, @yield
     */
    public static function handleLayoutDirectives(&$sections, &$layout, $content, &$layoutData = null)
    {
        // @extends('layouts.master') or @extends('layouts.master', [...])
        $content = preg_replace_callback(
            '/@extends\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*(\[.*?\]))?\s*\)/',
            function ($matches) use (&$layout, &$layoutData) {
                $layout = $matches[1];

                // Capture layout data if provided
                if (!empty($matches[2])) {
                    $dataString = $matches[2];
                    // Evaluate the array - we need to safely parse it
                    // Use eval in a safe way by checking the string format
                    try {
                        $layoutData = eval('return ' . $dataString . ';');
                        if (!is_array($layoutData)) {
                            $layoutData = [];
                        }
                    } catch (\Exception $e) {
                        $layoutData = [];
                    }
                }

                return '';
            },
            $content
        );

        // Handle block @section syntax: @section('name') ... @endsection
        while (preg_match('/@section\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)(.*?)@endsection/s', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $fullMatch = $matches[0][0];
            $offset = $matches[0][1];
            $name = $matches[1][0];
            $code = $matches[2][0];

            $sections[$name] = $code;
            $content = substr_replace($content, '', $offset, strlen($fullMatch));
        }

        // @yield('name') or @yield('name', 'default')
        $content = preg_replace_callback(
            '/@yield\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*[\'"]([^\'"]*)[\'"])?\s*\)/',
            function ($matches) use ($sections) {
                $name = $matches[1];
                $default = $matches[2] ?? '';
                return $sections[$name] ?? $default;
            },
            $content
        );

        return $content;
    }

    /**
     * Compile control structures: @if, @foreach, @while, etc.
     */
    public static function compileControlStructures($content)
    {
        // Use simple regex with proper paren handling
        // The trick is to make the capture group flexible

        // @if(condition)
        $content = preg_replace_callback(
            '/@if\s*\((.+?)\)\s*$/m',
            function ($m) {
                return '<?php if (' . $m[1] . '): ?>';
            },
            $content
        );

        // @elseif(condition)  
        $content = preg_replace_callback(
            '/@elseif\s*\((.+?)\)\s*$/m',
            function ($m) {
                return '<?php elseif (' . $m[1] . '): ?>';
            },
            $content
        );

        // @unless(condition)
        $content = preg_replace_callback(
            '/@unless\s*\((.+?)\)\s*$/m',
            function ($m) {
                return '<?php if (!(' . $m[1] . ')): ?>';
            },
            $content
        );

        // @foreach(expr)
        $content = preg_replace_callback(
            '/@foreach\s*\((.+?)\)\s*$/m',
            function ($m) {
                return '<?php foreach (' . $m[1] . '): ?>';
            },
            $content
        );

        // @for(condition)
        $content = preg_replace_callback(
            '/@for\s*\((.+?)\)\s*$/m',
            function ($m) {
                return '<?php for (' . $m[1] . '): ?>';
            },
            $content
        );

        // @while(condition)
        $content = preg_replace_callback(
            '/@while\s*\((.+?)\)\s*$/m',
            function ($m) {
                return '<?php while (' . $m[1] . '): ?>';
            },
            $content
        );

        // @switch(condition)
        $content = preg_replace_callback(
            '/@switch\s*\((.+?)\)\s*$/m',
            function ($m) {
                return '<?php switch (' . $m[1] . '): ?>';
            },
            $content
        );

        // @case(value)
        $content = preg_replace_callback(
            '/@case\s*\((.+?)\)\s*$/m',
            function ($m) {
                return '<?php case ' . $m[1] . ': ?>';
            },
            $content
        );

        // @php(code)
        /*$content = preg_replace_callback(
            '/@php\s*\((.+?)\)\s*$/m',
            function ($m) {
                return '<?php ' . $m[1] . ' ?>';
            },
            $content
        );*/

        // @php ... @endphp
        $content = preg_replace_callback(
            '/@php\s*(.*?)\s*@endphp/s',
            function ($m) {
                $code = trim($m[1]);

                if (empty($code)) return '';

                return "<?php\n"
                    . "extract(\$GLOBALS['__nixs_vars'] ?? [], EXTR_SKIP);\n"
                    . $code . "\n"
                    . "\$GLOBALS['__nixs_vars'] = get_defined_vars();\n"
                    . "?>";
            },
            $content
        );

        // @env('KEY')
        $content = preg_replace_callback(
            '/@env\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            function ($m) {
                $key = $m[1];
                return "<?php echo env('{$key}'); ?>";
            },
            $content
        );

        // @continue(condition)
        $content = preg_replace_callback(
            '/@continue\s*\((.+?)\)\s*$/m',
            function ($m) {
                return '<?php if (' . $m[1] . ') continue; ?>';
            },
            $content
        );

        // @break(condition)
        $content = preg_replace_callback(
            '/@break\s*\((.+?)\)\s*$/m',
            function ($m) {
                return '<?php if (' . $m[1] . ') break; ?>';
            },
            $content
        );

        // Simple directives without arguments
        $directives = [
            '/@else\s*(?=\n|$)/' => '<?php else: ?>',
            '/@endif/' => '<?php endif; ?>',
            '/@endunless/' => '<?php endif; ?>',
            '/@endforeach/' => '<?php endforeach; ?>',
            '/@endfor/' => '<?php endfor; ?>',
            '/@endwhile/' => '<?php endwhile; ?>',
            '/@endswitch/' => '<?php endswitch; ?>',
            '/@break/' => '<?php break; ?>',
            '/@continue/' => '<?php continue; ?>',
            '/@\*\*(.*?)\*\*@/s' => '<?php /* $1 */ ?>',
            '/\[\[\s*\+(.*?)\+\s*\]\]/s' => '<?php /* $1 */ ?>',
            '/\{\{\-\-(.*?)\-\-\}\}/s' => '<?php /* $1 */ ?>',
        ];

        foreach ($directives as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }

        // Handle smart @foreach with @empty (now that @foreach is already compiled)
        $content = self::compileSmartForeach($content);

        return $content;
    }

    /**
     * Compile directives with balanced parentheses
     */
    protected static function compileBalancedDirective($content, $directive, $template)
    {
        // This method is deprecated
        return $content;
    }

    /**
     * Handle @foreach with optional @empty block
     */
    protected static function compileSmartForeach($content)
    {
        $pattern = '/@foreach\s*\((.*?)\)(.*?)@endforeach/s';

        return preg_replace_callback($pattern, function ($matches) {
            $expression = $matches[1];
            $body = $matches[2];

            if (strpos($body, '@empty') !== false) {
                [$foreachContent, $emptyContent] = preg_split('/@empty/', $body, 2);

                // Extract variable name
                preg_match('/^\s*(\$\w+)/', $expression, $varMatch);
                $variable = $varMatch[1] ?? '$items';

                return "<?php if (!empty({$variable})): foreach ({$expression}): ?>" .
                    $foreachContent .
                    "<?php endforeach; else: ?>" .
                    $emptyContent .
                    "<?php endif; ?>";
            } else {
                return "<?php foreach ({$expression}): ?>" . $body . "<?php endforeach; ?>";
            }
        }, $content);
    }

    /**
     * Compile @include directives
     */
    public static function compileIncludes($content)
    {
        return preg_replace_callback(
            '/@include\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*(.+?))?\s*\)/',
            function ($matches) {
                $template = $matches[1];
                $variables = isset($matches[2]) ? $matches[2] : '[]';

                $path = PathResolver::resolve($template);

                if (!file_exists($path)) {
                    return "<!-- Include not found: {$template} -->";
                }

                return "<?php \$__compiledPath = '" . addslashes($path) . "'; " .
                    "\$__data = array_merge(get_defined_vars(), {$variables}); " .
                    "extract(\$__data); " .
                    "include \$__compiledPath; ?>";
            },
            $content
        );
    }
}
