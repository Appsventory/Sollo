<?php

namespace Core\Framework\Velo\Nixs\Support;

/**
 * Cache manager for compiled templates
 */
class TemplateCache
{
    protected static $cache = [];
    protected static $enabled = true;

    public static function enable($enabled = true)
    {
        self::$enabled = $enabled;
    }

    public static function has($key)
    {
        return isset(self::$cache[$key]);
    }

    public static function get($key)
    {
        return self::$cache[$key] ?? null;
    }

    public static function set($key, $value)
    {
        if (self::$enabled) {
            self::$cache[$key] = $value;
        }
    }

    public static function clear()
    {
        self::$cache = [];

        // Also clear temp files
        $tempDir = sys_get_temp_dir();
        $files = glob($tempDir . '/nixs_*.php');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    public static function getCompiledPath($sourcePath)
    {
        $cacheKey = md5($sourcePath . filemtime($sourcePath));
        return sys_get_temp_dir() . '/nixs_' . $cacheKey . '.php';
    }
}
