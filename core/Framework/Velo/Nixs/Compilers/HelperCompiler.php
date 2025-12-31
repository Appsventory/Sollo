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
     */
    public static function compile($content)
    {
        // Convert session() calls to $session() if not part of object access
        // Use negative lookbehind to avoid converting $session() or ->session()
        $content = preg_replace(
            '/(?<!\$|->)session\s*\(/',
            '$session(',
            $content
        );

        // Convert env() calls
        $content = preg_replace(
            '/(?<!\$|->)env\s*\(/',
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
