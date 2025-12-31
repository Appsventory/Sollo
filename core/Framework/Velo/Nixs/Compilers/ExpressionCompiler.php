<?php

namespace Core\Framework\Velo\Nixs\Compilers;

/**
 * Handles variable expression compilation and escaping
 */
class ExpressionCompiler
{
    /**
     * Compile template variables: {{ $var }}, {!! $var !!}, etc.
     */
    public static function compile($content)
    {
        // {!! raw output !!}
        $content = preg_replace(
            '/\{!!\s*(.+?)\s*!!\}/',
            '<?= $1 ?>',
            $content
        );

        // {{ escaped output }}
        $content = preg_replace(
            '/\{\{\s*(.+?)\s*\}\}/',
            '<?= htmlspecialchars($1 ?? "", ENT_QUOTES, "UTF-8") ?>',
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
