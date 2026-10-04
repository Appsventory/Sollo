<?php

namespace Core\Framework\Velo\Nixs\Support;

/**
 * Cache manager for compiled templates
 */
class TemplateCache
{
    protected static array $cache = [];
    protected static bool $enabled = true;
    protected static ?string $cacheDir = null;

    public static function enable($enabled = true)
    {
        self::$enabled = (bool) $enabled;
    }

    public static function has($key)
    {
        if (!self::$enabled) {
            return false;
        }

        if (isset(self::$cache[$key])) {
            return is_file(self::$cache[$key]);
        }

        return false;
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

        $dir = self::resolveCacheDir();
        foreach (glob($dir . '/nixs_*.php') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        // Clean legacy temp-dir artifacts
        foreach (glob(sys_get_temp_dir() . '/nixs_*.php') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    public static function getCompiledPath($sourcePath)
    {
        $mtime = file_exists($sourcePath) ? filemtime($sourcePath) : 0;
        $cacheKey = md5($sourcePath . $mtime);
        return self::resolveCacheDir() . '/nixs_' . $cacheKey . '.php';
    }

    protected static function resolveCacheDir(): string
    {
        if (self::$cacheDir !== null) {
            return self::$cacheDir;
        }

        $candidates = [
            dirname(__DIR__, 5) . '/storage/framework/views',
            sys_get_temp_dir(),
        ];

        foreach ($candidates as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            if (is_dir($dir) && is_writable($dir)) {
                self::$cacheDir = $dir;
                return self::$cacheDir;
            }
        }

        self::$cacheDir = sys_get_temp_dir();
        return self::$cacheDir;
    }
}
