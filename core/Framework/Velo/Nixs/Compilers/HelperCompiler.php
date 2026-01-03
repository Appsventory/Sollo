<?php

namespace Core\Framework\Velo\Nixs\Compilers;

/**
 * Handles global helper function calls
 */
class HelperCompiler
{
    /**
     * Compile helper function calls
     * Converts session(...) to $session(...)
     * Converts env(...) to $env(...)
     * Handles @env(), @session(), @auth() directives
     */
    public static function compile($content)
    {
        // Handle @env('KEY') or @env(KEY) directive FIRST
        $content = preg_replace_callback(
            '/@env\s*\(\s*[\'"]?([^\'")\s]+)[\'"]?\s*\)/',
            function ($m) {
                $key = $m[1];
                return "<?php echo \$env('{$key}'); ?>";
            },
            $content
        );

        // Handle @session('KEY') or @session(KEY) directive
        $content = preg_replace_callback(
            '/@session\s*\(\s*[\'"]?([^\'")\s]+)[\'"]?\s*\)/',
            function ($m) {
                $key = $m[1];
                return "<?php echo \$session('{$key}'); ?>";
            },
            $content
        );

        // Handle @auth() directive
        $content = preg_replace(
            '/@auth\s*\(\s*\)/',
            "<?php echo \$auth(); ?>",
            $content
        );

        // Convert session() calls to $session() if not part of object access or already in PHP tags
        // Use negative lookbehind to avoid converting $session() or ->session()
        $content = preg_replace(
            '/(?<!\$|->)session\s*\(/',
            '$session(',
            $content
        );

        // Convert env() calls to $env() if not part of object access
        // But NOT if it's already been converted to echo $env()
        $content = preg_replace(
            '/(?<!\$|->|echo\s)env\s*\(/',
            '$env(',
            $content
        );

        // Convert auth() calls
        $content = preg_replace(
            '/(?<!\$|->)auth\s*\(/',
            '$auth(',
            $content
        );

        return $content;
    }
}
