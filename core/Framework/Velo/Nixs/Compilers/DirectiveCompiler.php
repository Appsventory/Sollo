<?php

namespace Core\Framework\Velo\Nixs\Compilers;

use Core\Framework\Velo\Nixs\Support\PathResolver;

/**
 * Handles template directives like @if, @foreach, @section, etc.
 */
class DirectiveCompiler
{
    /**
     * Strip template comments before any other compilation.
     * Supports: {{-- --}}, @** **@, [[ + + ]]
     */
    public static function compileComments(string $content): string
    {
        $content = preg_replace('/\{\{\-\-.*?\-\-\}\}/s', '', $content);
        $content = preg_replace('/@\*\*.*?\*\*@/s', '', $content);
        $content = preg_replace('/\[\[\s*\+.*?\+\s*\]\]/s', '', $content);
        return $content;
    }

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
                    $layoutData = self::parseArrayString($dataString);
                    if (!is_array($layoutData)) {
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
     * Parse a simple array string used by @extends layout data.
     */
    protected static function parseArrayString(string $string): array
    {
        $result = [];
        $string = trim($string);

        if (!str_starts_with($string, '[') || !str_ends_with($string, ']')) {
            return $result;
        }

        $inner = trim(substr($string, 1, -1));
        if ($inner === '') {
            return $result;
        }

        $pairs = self::splitTopLevel($inner, ',', 0);

        foreach ($pairs as $pair) {
            if (strpos($pair, '=>') === false) {
                continue;
            }

            [$keyPart, $valuePart] = self::splitTopLevel($pair, '=>', 2);
            if (!isset($valuePart)) {
                continue;
            }

            $key = self::unquote(trim($keyPart));
            $value = self::parseScalarValue(trim($valuePart));

            if ($key !== '') {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    protected static function splitTopLevel(string $string, string $delimiter, int $limit = 0): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;
        $inSingle = false;
        $inDouble = false;
        $len = strlen($string);
        $dlen = strlen($delimiter);

        for ($i = 0; $i < $len; $i++) {
            $char = $string[$i];

            if ($char === "'" && !$inDouble && ($i === 0 || $string[$i - 1] !== '\\')) {
                $inSingle = !$inSingle;
            } elseif ($char === '"' && !$inSingle && ($i === 0 || $string[$i - 1] !== '\\')) {
                $inDouble = !$inDouble;
            }

            if (!$inSingle && !$inDouble) {
                if ($char === '[' || $char === '(' || $char === '{') {
                    $depth++;
                } elseif ($char === ']' || $char === ')' || $char === '}') {
                    $depth = max(0, $depth - 1);
                }

                if ($depth === 0 && $dlen > 0 && substr($string, $i, $dlen) === $delimiter) {
                    $parts[] = $buffer;
                    $buffer = '';
                    $i += $dlen - 1;
                    continue;
                }

                if ($depth === 0 && $delimiter === ',' && $char === ',') {
                    $parts[] = $buffer;
                    $buffer = '';
                    continue;
                }
            }

            $buffer .= $char;
        }

        if ($buffer !== '' || $string === '') {
            $parts[] = $buffer;
        }

        return $parts;
    }

    protected static function parseScalarValue(string $value)
    {
        if (strcasecmp($value, 'true') === 0) {
            return true;
        }

        if (strcasecmp($value, 'false') === 0) {
            return false;
        }

        if (strcasecmp($value, 'null') === 0) {
            return null;
        }

        if (is_numeric($value)) {
            return $value + 0;
        }

        return self::unquote($value);
    }

    protected static function unquote(string $value): string
    {
        if ((str_starts_with($value, "'") && str_ends_with($value, "'")) || (str_starts_with($value, '"') && str_ends_with($value, '"'))) {
            return stripslashes(substr($value, 1, -1));
        }

        return $value;
    }

    /**
     * Compile control structures: @if, @foreach, @while, etc.
     */
    public static function compileControlStructures($content)
    {
        // 1. Handle @foreach ... @empty ... @endforeach FIRST (before plain @foreach conversion)
        $content = self::compileSmartForeach($content);

        // 2. Plain control structures (line-oriented, condition must end the line)
        $content = preg_replace_callback(
            '/@if\s*\((.+?)\)\s*$/m',
            fn($m) => '<?php if (' . $m[1] . '): ?>',
            $content
        );

        $content = preg_replace_callback(
            '/@elseif\s*\((.+?)\)\s*$/m',
            fn($m) => '<?php elseif (' . $m[1] . '): ?>',
            $content
        );

        $content = preg_replace_callback(
            '/@unless\s*\((.+?)\)\s*$/m',
            fn($m) => '<?php if (!(' . $m[1] . ')): ?>',
            $content
        );

        // Remaining plain @foreach (no @empty)
        $content = preg_replace_callback(
            '/@foreach\s*\((.+?)\)\s*$/m',
            fn($m) => '<?php foreach (' . $m[1] . '): ?>',
            $content
        );

        $content = preg_replace_callback(
            '/@for\s*\((.+?)\)\s*$/m',
            fn($m) => '<?php for (' . $m[1] . '): ?>',
            $content
        );

        $content = preg_replace_callback(
            '/@while\s*\((.+?)\)\s*$/m',
            fn($m) => '<?php while (' . $m[1] . '): ?>',
            $content
        );

        $content = preg_replace_callback(
            '/@switch\s*\((.+?)\)\s*$/m',
            fn($m) => '<?php switch (' . $m[1] . '): ?>',
            $content
        );

        $content = preg_replace_callback(
            '/@case\s*\((.+?)\)\s*$/m',
            fn($m) => '<?php case ' . $m[1] . ': ?>',
            $content
        );

        // @php ... @endphp — simple, same scope as the template include
        $content = preg_replace_callback(
            '/@php\s*(.*?)\s*@endphp/s',
            function ($m) {
                $code = trim($m[1]);
                return $code === '' ? '' : "<?php\n{$code}\n?>";
            },
            $content
        );

        // @continue / @break with condition
        $content = preg_replace_callback(
            '/@continue\s*\((.+?)\)\s*$/m',
            fn($m) => '<?php if (' . $m[1] . ') continue; ?>',
            $content
        );

        $content = preg_replace_callback(
            '/@break\s*\((.+?)\)\s*$/m',
            fn($m) => '<?php if (' . $m[1] . ') break; ?>',
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
            '/@default\b/' => '<?php default: ?>',
            '/@break\b/' => '<?php break; ?>',
            '/@continue\b/' => '<?php continue; ?>',
        ];

        foreach ($directives as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }

        return $content;
    }

    /**
     * Handle @foreach with optional @empty block.
     * Must run BEFORE plain @foreach conversion.
     */
    protected static function compileSmartForeach($content)
    {
        $pattern = '/@foreach\s*\((.*?)\)(.*?)@endforeach/s';

        return preg_replace_callback($pattern, function ($matches) {
            $expression = trim($matches[1]);
            $body = $matches[2];

            if (strpos($body, '@empty') === false) {
                // Leave plain foreach for the later line-oriented converter
                return $matches[0];
            }

            [$foreachContent, $emptyContent] = preg_split('/@empty/', $body, 2);

            // Iterable is the left side of "as" (e.g. $items in "$items as $item")
            $variable = '$items';
            if (preg_match('/^\s*(.+?)\s+as\s+/i', $expression, $varMatch)) {
                $variable = trim($varMatch[1]);
            } elseif (preg_match('/^\s*(\$[A-Za-z_][\w\[\]]*)/', $expression, $varMatch)) {
                $variable = $varMatch[1];
            }

            return "<?php if (!empty({$variable})): foreach ({$expression}): ?>"
                . $foreachContent
                . "<?php endforeach; else: ?>"
                . $emptyContent
                . "<?php endif; ?>";
        }, $content);
    }

    /**
     * Compile @include directives - supports both static and dynamic template names
     * Static: @include('docs.3.x.intro')
     * Dynamic: @include('docs.3.x.' . $page)
     */
    public static function compileIncludes($content)
    {
        $output = '';
        $pos = 0;
        $length = strlen($content);

        while (preg_match('/@include\s*\(/', $content, $matches, PREG_OFFSET_CAPTURE, $pos)) {
            $start = $matches[0][1];
            $output .= substr($content, $pos, $start - $pos);
            $current = $start + strlen($matches[0][0]);
            $parenCount = 1;
            $inSingle = false;
            $inDouble = false;
            $escape = false;

            while ($current < $length && $parenCount > 0) {
                $char = $content[$current];

                if ($escape) {
                    $escape = false;
                } elseif ($char === '\\') {
                    $escape = true;
                } elseif ($char === "'" && !$inDouble) {
                    $inSingle = !$inSingle;
                } elseif ($char === '"' && !$inSingle) {
                    $inDouble = !$inDouble;
                } elseif (!$inSingle && !$inDouble) {
                    if ($char === '(') {
                        $parenCount++;
                    } elseif ($char === ')') {
                        $parenCount--;
                    }
                }

                $current++;
            }

            if ($parenCount !== 0) {
                $output .= substr($content, $start, $current - $start);
                $pos = $current;
                continue;
            }

            $args = substr($content, $start + strlen($matches[0][0]), $current - $start - strlen($matches[0][0]) - 1);
            [$pathExpr, $variables] = self::parseIncludeArguments($args);

            // The rest of processing is handled below
            $pathExprValue = $pathExpr;
            $variablesValue = $variables;

            if (preg_match('/^[\'\"]([^\'\"]+)[\'\"]$/', $pathExprValue, $stringMatch)) {
                $template = $stringMatch[1];
                $path = PathResolver::resolve($template);

                if (!file_exists($path)) {
                    $output .= "<!-- Include not found: {$template} -->";
                    $pos = $current;
                    continue;
                }

                $partialContent = file_get_contents($path);
                $compiled = \Core\Framework\Velo\Nixs\NixsCompiler::compileContent($partialContent);

                $output .= "<?php " .
                    "\$__variables = {$variablesValue}; " .
                    "extract(array_merge(get_defined_vars(), \$__variables)); " .
                    "?>" . $compiled . "<?php ?>";
            } else {
                $output .= "<?php " .
                    "\\Core\\Framework\\Velo\\Nixs\\NixsCompiler::includePartial({$pathExprValue}, {$variablesValue}); " .
                    "?>";
            }

            $pos = $current;
        }

        $output .= substr($content, $pos);
        return $output;
    }

    private static function parseIncludeArguments(string $args): array
    {
        $parts = self::splitTopLevel($args, ',', 2);
        $pathExpr = trim($parts[0] ?? '');
        $variables = isset($parts[1]) ? trim($parts[1]) : '[]';

        if ($variables === '') {
            $variables = '[]';
        }

        return [$pathExpr, $variables];
    }

    /**
     * Compile @nixscomponent('name', [...]) ... @endnixscomponent blocks.
     * Body is captured raw so nested template syntax is not compiled by the parent.
     */
    public static function compileNixsComponents($content)
    {
        $output = '';
        $pos = 0;
        $length = strlen($content);
        $openTag = '@nixscomponent';
        $endTag = '@endnixscomponent';

        while (preg_match('/(?<!@)@nixscomponent\s*\(/', $content, $matches, PREG_OFFSET_CAPTURE, $pos)) {
            $start = $matches[0][1];
            $output .= substr($content, $pos, $start - $pos);

            $current = $start + strlen($matches[0][0]);
            $parenCount = 1;
            $inSingle = false;
            $inDouble = false;
            $escape = false;

            while ($current < $length && $parenCount > 0) {
                $char = $content[$current];

                if ($escape) {
                    $escape = false;
                } elseif ($char === '\\') {
                    $escape = true;
                } elseif ($char === "'" && !$inDouble) {
                    $inSingle = !$inSingle;
                } elseif ($char === '"' && !$inSingle) {
                    $inDouble = !$inDouble;
                } elseif (!$inSingle && !$inDouble) {
                    if ($char === '(') {
                        $parenCount++;
                    } elseif ($char === ')') {
                        $parenCount--;
                    }
                }

                $current++;
            }

            if ($parenCount !== 0) {
                $output .= substr($content, $start, $current - $start);
                $pos = $current;
                continue;
            }

            $end = self::findNixsComponentEnd($content, $current);

            if ($end === null) {
                $output .= substr($content, $start, $current - $start);
                $pos = $current;
                continue;
            }

            $args = substr($content, $start + strlen($matches[0][0]), $current - $start - strlen($matches[0][0]) - 1);
            [$name, $variables] = self::parseIncludeArguments($args);
            $body = self::normalizeComponentBody(substr($content, $current, $end - $current));
            $encodedBody = base64_encode($body);

            $output .= "<?php "
                . "\\Core\\Framework\\Velo\\Nixs\\NixsCompiler::includeComponent({$name}, {$variables}, base64_decode('{$encodedBody}')); "
                . "?>";

            $pos = $end + strlen($endTag);
        }

        $output .= substr($content, $pos);
        return $output;
    }


    private static function findNixsComponentEnd(string $content, int $offset): ?int
    {
        $depth = 1;
        $pos = $offset;

        while ($pos < strlen($content)) {
            $nextOpen = self::strposDirective($content, '@nixscomponent', $pos);
            $nextClose = self::strposDirective($content, '@endnixscomponent', $pos);

            if ($nextClose === false) {
                return null;
            }

            if ($nextOpen !== false && $nextOpen < $nextClose) {
                $depth++;
                $pos = $nextOpen + strlen('@nixscomponent');
                continue;
            }

            $depth--;
            if ($depth === 0) {
                return $nextClose;
            }

            $pos = $nextClose + strlen('@endnixscomponent');
        }

        return null;
    }

    private static function strposDirective(string $content, string $directive, int $offset)
    {
        $pos = $offset;

        while (($found = strpos($content, $directive, $pos)) !== false) {
            if ($found === 0 || $content[$found - 1] !== '@') {
                return $found;
            }
            $pos = $found + strlen($directive);
        }

        return false;
    }

    private static function normalizeComponentBody(string $body): string
    {
        $body = preg_replace('/^\r?\n/', '', $body);
        $body = preg_replace('/\r?\n[ \t]*$/', '', $body);
        return $body;
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
     * Compile directives inside @yield default values (@env, etc.)
     */
    private static function compileDefaultValueContent($content)
    {
        $content = preg_replace_callback(
            '/@env\s*\(\s*[\'"]?([^\'")\s]+)[\'"]?\s*\)/',
            function ($m) {
                return \Core\Support\Env::env($m[1], $m[1]);
            },
            $content
        );

        return $content;
    }
}

