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

        // Short form: @section('title', $expr)  OR  block form: @section('content')
        $content = self::compileSectionDirectives($content, $ns);

        return preg_replace('/@endsection\b/', "<?php {$ns}::endSection(); ?>", $content);
    }

    /**
     * Compile @section('name') blocks and @section('name', $value) short form.
     */
    protected static function compileSectionDirectives(string $content, string $ns): string
    {
        $offset = 0;
        $length = strlen($content);
        $output = '';
        $needle = '@section';

        while (($pos = strpos($content, $needle, $offset)) !== false) {
            $afterName = $pos + strlen($needle);
            if ($afterName < $length) {
                $next = $content[$afterName];
                if (ctype_alnum($next) || $next === '_') {
                    $output .= substr($content, $offset, $afterName - $offset);
                    $offset = $afterName;
                    continue;
                }
            }

            $i = $afterName;
            while ($i < $length && ctype_space($content[$i])) {
                $i++;
            }
            if ($i >= $length || $content[$i] !== '(') {
                $output .= substr($content, $offset, $afterName - $offset);
                $offset = $afterName;
                continue;
            }

            $extracted = self::extractBalancedParentheses($content, $i);
            if ($extracted === null) {
                $output .= substr($content, $offset, $afterName - $offset);
                $offset = $afterName;
                continue;
            }

            [$inner, $closeIndex] = $extracted;
            $inner = trim($inner);

            // Split name and optional second argument at top-level comma
            $name = null;
            $valueExpr = null;
            $inSingle = $inDouble = false;
            $depth = 0;
            $comma = -1;
            for ($j = 0; $j < strlen($inner); $j++) {
                $ch = $inner[$j];
                if ($ch === '\\' && ($inSingle || $inDouble)) {
                    $j++;
                    continue;
                }
                if (!$inDouble && $ch === "'") {
                    $inSingle = !$inSingle;
                    continue;
                }
                if (!$inSingle && $ch === '"') {
                    $inDouble = !$inDouble;
                    continue;
                }
                if ($inSingle || $inDouble) {
                    continue;
                }
                if ($ch === '(') {
                    $depth++;
                } elseif ($ch === ')') {
                    $depth--;
                } elseif ($ch === ',' && $depth === 0) {
                    $comma = $j;
                    break;
                }
            }

            if ($comma >= 0) {
                $nameRaw = trim(substr($inner, 0, $comma));
                $valueExpr = trim(substr($inner, $comma + 1));
            } else {
                $nameRaw = $inner;
            }

            if (preg_match('/^[\'"](.+)[\'"]$/', $nameRaw, $nm)) {
                $name = $nm[1];
            } else {
                // leave unchanged if unparseable
                $output .= substr($content, $offset, $closeIndex + 1 - $offset);
                $offset = $closeIndex + 1;
                continue;
            }

            $output .= substr($content, $offset, $pos - $offset);
            if ($valueExpr !== null && $valueExpr !== '') {
                $output .= "<?php {$ns}::setSection(" . var_export($name, true) . ", {$valueExpr}); ?>";
            } else {
                $output .= "<?php {$ns}::startSection(" . var_export($name, true) . "); ?>";
            }
            $offset = $closeIndex + 1;
        }

        $output .= substr($content, $offset);
        return $output;
    }

    /**
     * Compile control structures: @if, @foreach, @while, etc.
     *
     * Conditions use balanced-parenthesis parsing so nested calls work:
     *   @if (count($channels) > 0)
     *   @if (!empty($user['name']))
     *   @foreach ($groups as $group)
     */
    public static function compileControlStructures($content)
    {
        // 1. @foreach ... @empty ... @endforeach before plain @foreach
        $content = self::compileSmartForeach($content);

        // 2. Directives that take a parenthetical expression (balanced)
        $content = self::replaceDirectiveExpression($content, 'if', static function (string $expr): string {
            return '<?php if (' . $expr . '): ?>';
        });
        $content = self::replaceDirectiveExpression($content, 'elseif', static function (string $expr): string {
            return '<?php elseif (' . $expr . '): ?>';
        });
        $content = self::replaceDirectiveExpression($content, 'unless', static function (string $expr): string {
            return '<?php if (!(' . $expr . ')): ?>';
        });
        $content = self::replaceDirectiveExpression($content, 'foreach', static function (string $expr): string {
            return '<?php foreach (' . $expr . '): ?>';
        });
        $content = self::replaceDirectiveExpression($content, 'for', static function (string $expr): string {
            return '<?php for (' . $expr . '): ?>';
        });
        $content = self::replaceDirectiveExpression($content, 'while', static function (string $expr): string {
            return '<?php while (' . $expr . '): ?>';
        });
        $content = self::replaceDirectiveExpression($content, 'switch', static function (string $expr): string {
            return '<?php switch (' . $expr . '): ?>';
        });
        $content = self::replaceDirectiveExpression($content, 'case', static function (string $expr): string {
            return '<?php case ' . $expr . ': ?>';
        });
        $content = self::replaceDirectiveExpression($content, 'continue', static function (string $expr): string {
            return '<?php if (' . $expr . ') continue; ?>';
        });
        $content = self::replaceDirectiveExpression($content, 'break', static function (string $expr): string {
            return '<?php if (' . $expr . ') break; ?>';
        });

        // @php ... @endphp
        $content = preg_replace_callback(
            '/@php\s*(.*?)\s*@endphp/s',
            function ($m) {
                $code = trim($m[1]);
                return $code === '' ? '' : "<?php\n{$code}\n?>";
            },
            $content
        );

        // Simple directives without arguments
        $directives = [
            '/@else\b/' => '<?php else: ?>',
            '/@endif\b/' => '<?php endif; ?>',
            '/@endunless\b/' => '<?php endif; ?>',
            '/@endforeach\b/' => '<?php endforeach; ?>',
            '/@endfor\b/' => '<?php endfor; ?>',
            '/@endwhile\b/' => '<?php endwhile; ?>',
            '/@endswitch\b/' => '<?php endswitch; ?>',
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
     * Replace @name(...) using balanced parentheses (supports nested () inside the condition).
     *
     * @param callable(string): string $build
     */
    protected static function replaceDirectiveExpression(string $content, string $name, callable $build): string
    {
        $needle = '@' . $name;
        $offset = 0;
        $length = strlen($content);
        $output = '';

        while (($pos = strpos($content, $needle, $offset)) !== false) {
            $afterName = $pos + strlen($needle);

            // Avoid matching a longer directive name (@if vs @ifdef, @for vs @foreach already handled by order)
            if ($afterName < $length) {
                $next = $content[$afterName];
                if (ctype_alnum($next) || $next === '_') {
                    $output .= substr($content, $offset, $afterName - $offset);
                    $offset = $afterName;
                    continue;
                }
            }

            $i = $afterName;
            while ($i < $length && ctype_space($content[$i])) {
                $i++;
            }

            if ($i >= $length || $content[$i] !== '(') {
                // Bare @break / @continue without condition — leave for simple-directive pass
                $output .= substr($content, $offset, $afterName - $offset);
                $offset = $afterName;
                continue;
            }

            $extracted = self::extractBalancedParentheses($content, $i);
            if ($extracted === null) {
                $output .= substr($content, $offset, $afterName - $offset);
                $offset = $afterName;
                continue;
            }

            [$inner, $closeIndex] = $extracted;
            $output .= substr($content, $offset, $pos - $offset);
            $output .= $build(trim($inner));
            $offset = $closeIndex + 1;
        }

        $output .= substr($content, $offset);

        return $output;
    }

    /**
     * Given index of '(', return [innerContents, indexOfClosingParen] or null.
     * Respects single/double quoted strings and escapes.
     *
     * @return array{0:string,1:int}|null
     */
    protected static function extractBalancedParentheses(string $content, int $openIndex): ?array
    {
        $length = strlen($content);
        if ($openIndex >= $length || $content[$openIndex] !== '(') {
            return null;
        }

        $depth = 0;
        $inSingle = false;
        $inDouble = false;
        $escaped = false;

        for ($i = $openIndex; $i < $length; $i++) {
            $ch = $content[$i];

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if (($inSingle || $inDouble) && $ch === '\\') {
                $escaped = true;
                continue;
            }

            if (!$inDouble && $ch === "'") {
                $inSingle = !$inSingle;
                continue;
            }
            if (!$inSingle && $ch === '"') {
                $inDouble = !$inDouble;
                continue;
            }
            if ($inSingle || $inDouble) {
                continue;
            }

            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
                if ($depth === 0) {
                    $inner = substr($content, $openIndex + 1, $i - $openIndex - 1);
                    return [$inner, $i];
                }
            }
        }

        return null;
    }

    /**
     * Handle @foreach with optional @empty block.
     * Must run BEFORE plain @foreach conversion.
     */
    protected static function compileSmartForeach($content)
    {
        $offset = 0;
        $length = strlen($content);
        $output = '';
        $needle = '@foreach';

        while (($pos = strpos($content, $needle, $offset)) !== false) {
            $afterName = $pos + strlen($needle);
            if ($afterName < $length) {
                $next = $content[$afterName];
                if (ctype_alnum($next) || $next === '_') {
                    $output .= substr($content, $offset, $afterName - $offset);
                    $offset = $afterName;
                    continue;
                }
            }

            $i = $afterName;
            while ($i < $length && ctype_space($content[$i])) {
                $i++;
            }
            if ($i >= $length || $content[$i] !== '(') {
                $output .= substr($content, $offset, $afterName - $offset);
                $offset = $afterName;
                continue;
            }

            $extracted = self::extractBalancedParentheses($content, $i);
            if ($extracted === null) {
                $output .= substr($content, $offset, $afterName - $offset);
                $offset = $afterName;
                continue;
            }

            [$expression, $closeIndex] = $extracted;
            $expression = trim($expression);
            $bodyStart = $closeIndex + 1;

            // Find matching @endforeach at depth 0 (nested @foreach supported)
            $search = $bodyStart;
            $depth = 1;
            $endPos = null;
            while ($search < $length) {
                $nextForeach = strpos($content, '@foreach', $search);
                $nextEnd = strpos($content, '@endforeach', $search);
                if ($nextEnd === false) {
                    break;
                }
                if ($nextForeach !== false && $nextForeach < $nextEnd) {
                    // ensure not part of a longer token
                    $nfAfter = $nextForeach + 8;
                    if ($nfAfter >= $length || (!ctype_alnum($content[$nfAfter]) && $content[$nfAfter] !== '_')) {
                        $depth++;
                    }
                    $search = $nextForeach + 8;
                    continue;
                }
                $depth--;
                if ($depth === 0) {
                    $endPos = $nextEnd;
                    break;
                }
                $search = $nextEnd + 11;
            }

            if ($endPos === null) {
                $output .= substr($content, $offset, $afterName - $offset);
                $offset = $afterName;
                continue;
            }

            $body = substr($content, $bodyStart, $endPos - $bodyStart);

            if (strpos($body, '@empty') === false) {
                // Leave for plain @foreach converter
                $output .= substr($content, $offset, $endPos + 11 - $offset);
                $offset = $endPos + 11;
                continue;
            }

            $parts = preg_split('/@empty\b/', $body, 2);
            $foreachContent = $parts[0];
            $emptyContent = $parts[1] ?? '';

            $variable = '$items';
            if (preg_match('/^\s*(.+?)\s+as\s+/is', $expression, $varMatch)) {
                $variable = trim($varMatch[1]);
            } elseif (preg_match('/^\s*(\$[A-Za-z_][\w]*)/', $expression, $varMatch)) {
                $variable = $varMatch[1];
            }

            $output .= substr($content, $offset, $pos - $offset);
            $output .= "<?php if (!empty({$variable})): foreach ({$expression}): ?>"
                . $foreachContent
                . "<?php endforeach; else: ?>"
                . $emptyContent
                . "<?php endif; ?>";
            $offset = $endPos + 11;
        }

        $output .= substr($content, $offset);

        return $output;
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
                $default = $parts['default'];
                // Quoted string → HTML/text default (may still contain {{ }} which later steps compile).
                // Bare PHP expression (config(...), $var, ...) → evaluate as PHP.
                $trimmed = trim($default);
                $isQuoted = (strlen($trimmed) >= 2 && (
                    ($trimmed[0] === "'" && str_ends_with($trimmed, "'"))
                    || ($trimmed[0] === '"' && str_ends_with($trimmed, '"'))
                ));
                if ($isQuoted) {
                    $defaultText = substr($trimmed, 1, -1);
                    $output .= "<?php if ({$ns}::hasSection({$name})): echo {$ns}::section({$name}); else: ?>"
                        . $defaultText . "<?php endif; ?>";
                } elseif (str_contains($trimmed, '{{') || str_starts_with($trimmed, '@')) {
                    $output .= "<?php if ({$ns}::hasSection({$name})): echo {$ns}::section({$name}); else: ?>"
                        . $default . "<?php endif; ?>";
                } else {
                    $output .= "<?php echo {$ns}::hasSection({$name}) ? {$ns}::section({$name}) : ({$default}); ?>";
                }
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
            return ['name' => $name, 'default' => $default];
        }
    }
}
