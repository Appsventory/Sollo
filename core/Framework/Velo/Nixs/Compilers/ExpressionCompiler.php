<?php

namespace Core\Framework\Velo\Nixs\Compilers;

/**
 * Handles variable expression compilation and escaping
 */
class ExpressionCompiler
{
    /**
     * Compile template variables: {{ $var }}, {!! $var !!}, etc.
     * Comments {{-- --}} are left untouched (handled earlier in the pipeline).
     */
    public static function compile($content)
    {
        // {!! raw output !!}
        $content = preg_replace(
            '/\{!!\s*(.+?)\s*!!\}/s',
            '<?= $1 ?>',
            $content
        );

        // {{ escaped output }} — skip Blade-style comments {{-- ... --}}
        $content = preg_replace_callback(
            '/\{\{(?![-_])\s*(.+?)\s*\}\}/s',
            function ($m) {
                $expr = trim($m[1]);
                // Safety: reject comment-like leftovers
                if (str_starts_with($expr, '--') || str_starts_with($expr, '-')) {
                    return $m[0];
                }
                // Use helper so arrays/objects never TypeError on htmlspecialchars (PHP 8+)
                return '<?= \\Core\\Framework\\Velo\\Nixs\\Compilers\\ExpressionCompiler::escape(' . $expr . ') ?>';
            },
            $content
        );

        return $content;
    }

    /**
     * Escape value for HTML output (null / scalar / object safe)
     */
    public static function escape($value)
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '';
        }
        if (is_array($value)) {
            return htmlspecialchars(json_encode($value, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
        }
        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            }
            return htmlspecialchars(json_encode($value, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
        }

        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Get escaped variable
     */
    public static function get($variable, $default = '')
    {
        return self::escape($variable ?? $default);
    }
}
