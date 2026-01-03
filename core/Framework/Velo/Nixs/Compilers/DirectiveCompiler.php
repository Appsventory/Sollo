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

        // Do NOT handle @yield here - handle it later after all other compilations
        // to ensure default values are properly compiled

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
     * Compile @include directives - supports both static and dynamic template names
     * Static: @include('docs.3.x.intro')
     * Dynamic: @include('docs.3.x.' . $page)
     */
    public static function compileIncludes($content)
    {
        // Pattern to match @include with parentheses - handles both static and dynamic paths
        // Capture everything inside parentheses, then manually parse it
        $pattern = '/@include\s*\(\s*(.+?)\s*(?:,\s*(.+?))?\s*\)(?=[;\s\n]|$)/';

        return preg_replace_callback($pattern, function ($matches) {
            $pathExpr = trim($matches[1]);
            $variables = isset($matches[2]) ? trim($matches[2]) : '[]';

            // Check if path is a literal string (starts with quote)
            if (preg_match('/^[\'"]([^\'"]+)[\'"]$/', $pathExpr, $stringMatch)) {
                // Static path - compile at compile time
                $template = $stringMatch[1];
                $path = PathResolver::resolve($template);

                if (!file_exists($path)) {
                    return "<!-- Include not found: {$template} -->";
                }

                // Load and compile the partial content inline
                $partialContent = file_get_contents($path);

                // Compile the partial content through the full pipeline
                $compiled = \Core\Framework\Velo\Nixs\NixsCompiler::compileContent($partialContent);

                // Output the compiled partial directly with access to parent variables
                return "<?php " .
                    "\$__variables = {$variables}; " .
                    "extract(array_merge(get_defined_vars(), \$__variables)); " .
                    "?>" . $compiled . "<?php ?>";
            } else {
                // Dynamic path - compile at runtime using a helper function
                return "<?php " .
                    "\\Core\\Framework\\Velo\\Nixs\\NixsCompiler::includePartial({$pathExpr}, {$variables}); " .
                    "?>";
            }
        }, $content);
    }

    /**
     * Compile @yield directives with compiled default values
     */
    public static function compileYield($sections, $content)
    {
        // Parse manually to properly handle parentheses in default values
        $output = '';
        $pos = 0;

        while (preg_match('/@yield\s*\(/', $content, $matches, PREG_OFFSET_CAPTURE, $pos)) {
            $yieldStart = $matches[0][1];
            $output .= substr($content, $pos, $yieldStart - $pos);

            // Find matching closing paren
            $parenCount = 1;
            $currentPos = $yieldStart + strlen($matches[0][0]);

            while ($currentPos < strlen($content) && $parenCount > 0) {
                if ($content[$currentPos] === '(') {
                    $parenCount++;
                } elseif ($content[$currentPos] === ')') {
                    $parenCount--;
                }
                $currentPos++;
            }

            // Extract yield args
            $yieldArgs = substr($content, $yieldStart + strlen($matches[0][0]), $currentPos - $yieldStart - strlen($matches[0][0]) - 1);

            // Parse name and default
            $parts = self::parseYieldArgs($yieldArgs);
            $name = $parts['name'];
            $default = $parts['default'] ?? '';

            // Output: use section if exists, else compile and output default
            $output .= "@yield('" . $name . "'";
            if ($default) {
                // Compile the default value
                $compiled = self::compileDefaultValueContent($default);
                $output .= ", '" . addslashes($compiled) . "'";
            }
            $output .= ")";

            $pos = $currentPos;
        }

        $output .= substr($content, $pos);

        // Now actually replace @yield with section content or compiled default
        return preg_replace_callback(
            '/@yield\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*[\'"]([^\'"]*)[\'"])?\s*\)/',
            function ($m) use ($sections) {
                $name = $m[1];
                $default = $m[2] ?? '';

                if (isset($sections[$name])) {
                    return $sections[$name];
                }
                return $default;
            },
            $output
        );
    }

    /**
     * Parse @yield arguments
     */
    private static function parseYieldArgs($args)
    {
        $args = trim($args);

        // Find first comma at top level
        $inSingle = false;
        $inDouble = false;
        $parenDepth = 0;
        $commaPos = -1;

        for ($i = 0; $i < strlen($args); $i++) {
            $char = $args[$i];

            if ($char === "'" && !$inDouble && ($i === 0 || $args[$i - 1] !== '\\')) {
                $inSingle = !$inSingle;
            } elseif ($char === '"' && !$inSingle && ($i === 0 || $args[$i - 1] !== '\\')) {
                $inDouble = !$inDouble;
            } elseif (!$inSingle && !$inDouble) {
                if ($char === '(') $parenDepth++;
                elseif ($char === ')') $parenDepth--;
                elseif ($char === ',' && $parenDepth === 0) {
                    $commaPos = $i;
                    break;
                }
            }
        }

        if ($commaPos === -1) {
            // Only name
            $name = trim($args);
            $name = preg_replace('/^[\'"]|[\'"]$/', '', $name);
            return ['name' => $name];
        } else {
            // Name and default
            $name = trim(substr($args, 0, $commaPos));
            $name = preg_replace('/^[\'"]|[\'"]$/', '', $name);
            $default = trim(substr($args, $commaPos + 1));
            $default = preg_replace('/^[\'"]|[\'"]$/', '', $default);
            return ['name' => $name, 'default' => $default];
        }
    }

    /**
     * Pre-compile @yield default values so they go through the compilation pipeline
     */
    public static function precompileYieldDefaults($content)
    {
        // Parse @yield directives manually to handle complex default values
        $output = '';
        $pos = 0;

        while (preg_match('/@yield\s*\(/', $content, $matches, PREG_OFFSET_CAPTURE, $pos)) {
            $yieldStart = $matches[0][1];

            // Copy everything before @yield
            $output .= substr($content, $pos, $yieldStart - $pos);

            // Find the matching closing parenthesis
            $parenPos = $yieldStart + strlen($matches[0][0]) - 1; // Position after 'yield('
            $parenCount = 1;
            $currentPos = $parenPos + 1;

            while ($currentPos < strlen($content) && $parenCount > 0) {
                if ($content[$currentPos] === '(') {
                    $parenCount++;
                } elseif ($content[$currentPos] === ')') {
                    $parenCount--;
                }
                $currentPos++;
            }

            // Extract the yield arguments
            $yieldContent = substr($content, $parenPos + 1, $currentPos - $parenPos - 2);

            // Parse the arguments: first arg is section name, second (if exists) is default
            $parts = self::parseYieldArguments($yieldContent);
            $output .= "@yield('" . $parts['name'] . "'";

            if (isset($parts['default'])) {
                // Compile the default value
                $compiledDefault = self::compileDefaultValueContent($parts['default']);
                $output .= ", '" . addslashes($compiledDefault) . "'";
            }

            $output .= ")";
            $pos = $currentPos;
        }

        // Append remaining content
        $output .= substr($content, $pos);

        return $output;
    }

    /**
     * Parse @yield arguments to extract name and default value
     */
    private static function parseYieldArguments($yieldContent)
    {
        // Find first comma at top level (not inside quotes or parens)
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $parenDepth = 0;
        $firstCommaPos = -1;

        for ($i = 0; $i < strlen($yieldContent); $i++) {
            $char = $yieldContent[$i];

            if (!$inSingleQuote && !$inDoubleQuote) {
                if ($char === "'") {
                    $inSingleQuote = true;
                } elseif ($char === '"') {
                    $inDoubleQuote = true;
                } elseif ($char === '(') {
                    $parenDepth++;
                } elseif ($char === ')') {
                    $parenDepth--;
                } elseif ($char === ',' && $parenDepth === 0) {
                    $firstCommaPos = $i;
                    break;
                }
            } elseif ($inSingleQuote && $char === "'") {
                $inSingleQuote = false;
            } elseif ($inDoubleQuote && $char === '"') {
                $inDoubleQuote = false;
            }
        }

        $result = ['name' => '', 'default' => null];

        if ($firstCommaPos === -1) {
            // Only name, no default
            $nameWithQuotes = trim($yieldContent);
            $result['name'] = self::removeQuotes($nameWithQuotes);
        } else {
            // Both name and default
            $nameWithQuotes = trim(substr($yieldContent, 0, $firstCommaPos));
            $defaultWithQuotes = trim(substr($yieldContent, $firstCommaPos + 1));

            $result['name'] = self::removeQuotes($nameWithQuotes);
            $result['default'] = self::removeQuotes($defaultWithQuotes);
        }

        return $result;
    }

    /**
     * Remove quotes from string
     */
    private static function removeQuotes($str)
    {
        $str = trim($str);
        if ((substr($str, 0, 1) === "'" && substr($str, -1) === "'") ||
            (substr($str, 0, 1) === '"' && substr($str, -1) === '"')
        ) {
            return substr($str, 1, -1);
        }
        return $str;
    }

    /**
     * Compile just the directives within default values (@env, etc)
     * This converts @env('KEY') or @env(KEY) to actual value at compile time
     */
    private static function compileDefaultValueContent($content)
    {
        // @env('KEY') or @env(KEY) - compile to actual env value at compile time
        $content = preg_replace_callback(
            '/@env\s*\(\s*[\'"]?([^\'")\s]+)[\'"]?\s*\)/',
            function ($m) {
                $key = $m[1];
                // Get the actual env value NOW
                $value = \Core\Support\Env::env($key, $key);
                return $value;
            },
            $content
        );

        return $content;
    }
}
