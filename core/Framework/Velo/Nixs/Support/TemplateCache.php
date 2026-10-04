<?php

namespace Core\Framework\Velo\Nixs\Support;

/**
 * Compiled-template cache.
 *
 * A compiled file depends ONLY on its own source (layouts, sections and
 * includes are resolved at runtime), so the file name is derived from the
 * source path + modification time. Older versions of the same source are
 * removed when a new one is written.
 */
class TemplateCache
{
    protected static bool $enabled = true;
    protected static ?string $cacheDir = null;

    public static function enable($enabled = true): void
    {
        self::$enabled = (bool) $enabled;
    }

    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    public static function path(string $sourcePath, int $mtime): string
    {
        return self::resolveCacheDir() . '/nixs_' . md5($sourcePath) . '_' . $mtime . '.php';
    }

    /**
     * Return the cached compiled file for this source version, or null.
     */
    public static function find(string $sourcePath, int $mtime): ?string
    {
        if (!self::$enabled) {
            return null;
        }
        $path = self::path($sourcePath, $mtime);
        return is_file($path) ? $path : null;
    }

    /**
     * Store compiled code atomically and drop stale versions of the same source.
     */
    public static function store(string $sourcePath, int $mtime, string $compiled): string
    {
        $path = self::path($sourcePath, $mtime);
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';

        if (file_put_contents($tmp, $compiled, LOCK_EX) === false || !@rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException("Unable to write compiled view to '{$path}'");
        }

        foreach (glob(self::resolveCacheDir() . '/nixs_' . md5($sourcePath) . '_*.php') ?: [] as $old) {
            if ($old !== $path) {
                @unlink($old);
            }
        }

        return $path;
    }

    /**
     * Remove every compiled view. Returns the number of removed files.
     */
    public static function clear(): int
    {
        $count = 0;
        foreach (glob(self::resolveCacheDir() . '/nixs_*.php') ?: [] as $file) {
            if (is_file($file) && @unlink($file)) {
                $count++;
            }
        }
        return $count;
    }

    protected static function resolveCacheDir(): string
    {
        if (self::$cacheDir !== null) {
            return self::$cacheDir;
        }

        $candidates = [
            dirname(__DIR__, 5) . '/storage/framework/views',
            sys_get_temp_dir() . '/sollo_views_' . md5(dirname(__DIR__, 5)),
        ];

        foreach ($candidates as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            if (is_dir($dir) && is_writable($dir)) {
                return self::$cacheDir = $dir;
            }
        }

        throw new \RuntimeException('No writable directory for compiled views (storage/framework/views).');
    }
}
