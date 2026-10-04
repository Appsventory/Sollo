<?php

namespace Core\Framework\Velo\Nixs\Compilers;

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
    /**
     * Layout directives are resolved at RUNTIME (not at compile time), so the
     * compiled file only depends on its own source and can be cached safely.
     *
     *   @extends('layouts.app', [...])  -> NixsCompiler::extend(...)
     *   @section('name')                -> NixsCompiler::startSection('name')
     *   @endsection                     -> NixsCompiler::endSection()
     */
    public static function handleLayoutDirectives(string $content): string
    {
        $ns = '\\Core\\Framework\\Velo\\Nixs\\NixsCompiler';

        $content = preg_replace_callback(
            '/@extends\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*(\[.*?\]))?\s*\)/s',
            function ($m) use ($ns) {
                $data = !empty($m[2]) ? $m[2] : '[]';
                return "<?php {$ns}::extend(" . var_export($m[1], true) . ", {$data}); ?>";
            },
            $content
        );

        $content = preg_replace_callback(
            '/@section\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            fn($m) => "<?php {$ns}::startSection(" . var_export($m[1], true) . "); ?>",
            $content
        );

        return preg_replace('/@endsection\b/', "<?php {$ns}::endSection(); ?>", $content);
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
            '/@if\s*\((.+?)\)/',
            fn($m) => '<?php if (' . $m[1] . '): ?>',
            $content
        );

        $content = preg_replace_callback(
            '/@elseif\s*\((.+?)\)/',
            fn($m) => '<?php elseif (' . $m[1] . '): ?>',
            $content
        );

        $content = preg_replace_callback(
            '/@unless\s*\((.+?)\)/',
            fn($m) => '<?php if (!(' . $m[1] . ')): ?>',
            $content
        );

        // Remaining plain @foreach (no @empty)
        $content = preg_replace_callback(
            '/@foreach\s*\((.+?)\)/',
            fn($m) => '<?php foreach (' . $m[1] . '): ?>',
            $content
        );

        $content = preg_replace_callback(
            '/@for\s*\((.+?)\)/',
            fn($m) => '<?php for (' . $m[1] . '): ?>',
            $content
        );

        $content = preg_replace_callback(
            '/@while\s*\((.+?)\)/',
            fn($m) => '<?php while (' . $m[1] . '): ?>',
            $content
        );

        $content = preg_replace_callback(
            '/@switch\s*\((.+?)\)/',
            fn($m) => '<?php switch (' . $m[1] . '): ?>',
            $content
        );

        $content = preg_replace_callback(
            '/@case\s*\((.+?)\)/',
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
            '/@continue\s*\((.+?)\)/',
            fn($m) => '<?php if (' . $m[1] . ') continue; ?>',
            $content
        );

        $content = preg_replace_callback(
            '/@break\s*\((.+?)\)/',
            fn($m) => '<?php if (' . $m[1] . ') break; ?>',
            $content
        );

        // Simple directives without arguments
        $directives = [
            '/@else\b/' => '<?php else: ?>',
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

            // Always resolved at runtime: the compiled file never embeds another
            // template, so editing a partial can never leave a stale cache behind.
            // The parent scope is passed along so partials inside @foreach see $item.
            $output .= "<?php \\Core\\Framework\\Velo\\Nixs\\NixsCompiler::includePartial("
                . "{$pathExprValue}, array_merge(get_defined_vars(), {$variablesValue})); ?>";

            $pos = $current;
        }

        $output .= substr($content, $pos);
        return $output;
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
            $compiledBody = \Core\Framework\Velo\Nixs\NixsCompiler::compileContent($body);
            $ns = '\\Core\\Framework\\Velo\\Nixs\\NixsCompiler';

            $output .= "<?php ob_start(); ?>" . $compiledBody
                . "<?php {$ns}::includeComponent({$name}, array_merge(get_defined_vars(), {$variables}), ob_get_clean()); ?>";

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
    public static function compileYield($content)
    {
        $ns = '\\Core\\Framework\\Velo\\Nixs\\NixsCompiler';
        $output = '';
        $pos = 0;

        while (preg_match('/@yield\s*\(/', $content, $matches, PREG_OFFSET_CAPTURE, $pos)) {
            $yieldStart = $matches[0][1];
            $output .= substr($content, $pos, $yieldStart - $pos);

            $depth = 1;
            $cur = $yieldStart + strlen($matches[0][0]);
            $len = strlen($content);
            $inSingle = $inDouble = false;
            while ($cur < $len && $depth > 0) {
                $ch = $content[$cur];
                if ($ch === "'" && !$inDouble) {
                    $inSingle = !$inSingle;
                } elseif ($ch === '"' && !$inSingle) {
                    $inDouble = !$inDouble;
                } elseif (!$inSingle && !$inDouble) {
                    if ($ch === '(') {
                        $depth++;
                    } elseif ($ch === ')') {
                        $depth--;
                    }
                }
                $cur++;
            }

            $args = substr($content, $yieldStart + strlen($matches[0][0]), $cur - $yieldStart - strlen($matches[0][0]) - 1);
            $parts = self::parseYieldArgs($args);
            $name = var_export($parts['name'], true);

            if (isset($parts['default']) && $parts['default'] !== '') {
                // Default stays inline template text; later compile steps handle {{ }} / @env in it.
                $output .= "<?php if ({$ns}::hasSection({$name})): echo {$ns}::section({$name}); else: ?>"
                    . $parts['default'] . "<?php endif; ?>";
            } else {
                $output .= "<?php echo {$ns}::section({$name}); ?>";
            }

            $pos = $cur;
        }

        return $output . substr($content, $pos);
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
}
