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
                return '<?= htmlspecialchars(' . $expr . ' ?? "", ENT_QUOTES, "UTF-8") ?>';
            },
            $content
        );

        return $content;
    }

    /**
     * Escape value for HTML output
     */
    public static function escape($value)
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    /**
     * Get escaped variable
     */
    public static function get($variable, $default = '')
    {
        return self::escape($variable ?? $default);
    }
}
