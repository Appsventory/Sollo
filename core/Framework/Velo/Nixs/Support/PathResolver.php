<?php

namespace Core\Framework\Velo\Nixs\Support;

/**
 * Template path resolver
 */
class PathResolver
{
    protected static $basePath = null;

    public static function setBasePath($path)
    {
        self::$basePath = rtrim($path, '/\\');
    }

    public static function resolve($template)
    {
        if (!self::$basePath) {
            // core/Framework/Velo/Nixs/Support -> project root (5 levels up)
            self::$basePath = dirname(__DIR__, 5);

            // Fallback: walk up until resources/Views exists
            if (!is_dir(self::$basePath . '/resources/Views')) {
                $dir = __DIR__;
                for ($i = 0; $i < 8; $i++) {
                    $dir = dirname($dir);
                    if (is_dir($dir . '/resources/Views')) {
                        self::$basePath = $dir;
                        break;
                    }
                }
            }
        }

        // Absolute path: allowed only inside the project (a user-controlled
        // view name must never read arbitrary files such as /etc/passwd).
        if (str_starts_with($template, '/') || preg_match('#^[A-Za-z]:[/\\\\]#', $template)) {
            $real = realpath($template);
            $root = realpath(self::$basePath);

            if ($real === false || $root === false || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
                throw new \InvalidArgumentException("View path is outside the project: {$template}");
            }

            return $template;
        }

        // Already prefixed with resources/
        if (str_starts_with($template, 'resources/')) {
            return self::$basePath . '/' . $template;
        }

        // Dot notation -> path
        $path = str_replace('.', '/', $template);

        return self::$basePath . '/resources/Views/' . $path . '.nixs.php';
    }
}
