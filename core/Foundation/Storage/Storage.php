<?php

namespace Core\Foundation\Storage;

/**
 * Public-disk storage rooted at public/storage.
 *
 * No symlink required — works on shared hosting that blocks storage:link.
 *
 *   Storage::url('images/logo.png')  => /storage/images/logo.png
 *   Storage::path('images/logo.png') => .../public/storage/images/logo.png
 *   Storage::put('images/logo.png', $bytes)
 */
class Storage
{
    public static function path(string $path = ''): string
    {
        $root = self::root();
        $path = self::normalize($path);

        return $path === '' ? $root : $root . '/' . $path;
    }

    /**
     * Normalize a relative path: "\" becomes "/", empty, "." and ".." segments
     * are dropped (so it can never leave public/storage). A file name that merely
     * contains two dots ("a..b.png") is kept as is.
     */
    protected static function normalize(string $path): string
    {
        $segments = array_filter(
            explode('/', str_replace('\\', '/', $path)),
            fn($segment) => $segment !== '' && $segment !== '.' && $segment !== '..'
        );

        return implode('/', $segments);
    }

    public static function url(string $path = ''): string
    {
        $path = self::normalize($path);

        return $path === '' ? '/storage' : '/storage/' . $path;
    }

    /**
     * Full URL using APP_URL when set.
     */
    public static function fullUrl(string $path = ''): string
    {
        $base = rtrim((string) (\Core\Support\Env::raw('APP_URL', '')), '/');
        $rel = self::url($path);

        return $base === '' ? $rel : $base . $rel;
    }

    /**
     * Write contents to public/storage/$path (creates directories).
     */
    public static function put(string $path, string $contents): bool
    {
        $full = self::path($path);
        $dir = dirname($full);

        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }

        return file_put_contents($full, $contents, LOCK_EX) !== false;
    }

    /**
     * Store an uploaded file (from $_FILES-style tmp path).
     */
    public static function putFile(string $directory, string $tmpPath, ?string $filename = null): ?string
    {
        if (!is_file($tmpPath)) {
            return null;
        }

        $directory = self::normalize($directory);
        $filename = $filename ?: (bin2hex(random_bytes(8)) . '_' . basename($tmpPath));
        $filename = basename($filename);
        $relative = ($directory === '' ? '' : $directory . '/') . $filename;
        $full = self::path($relative);
        $dir = dirname($full);

        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return null;
        }

        if (!@move_uploaded_file($tmpPath, $full) && !@rename($tmpPath, $full)) {
            if (!@copy($tmpPath, $full)) {
                return null;
            }
            @unlink($tmpPath);
        }

        return $relative;
    }

    public static function exists(string $path): bool
    {
        return is_file(self::path($path));
    }

    public static function get(string $path): ?string
    {
        $full = self::path($path);
        if (!is_file($full)) {
            return null;
        }
        $data = file_get_contents($full);
        return $data === false ? null : $data;
    }

    public static function delete(string $path): bool
    {
        $full = self::path($path);
        if (!is_file($full)) {
            return false;
        }
        return @unlink($full);
    }

    /**
     * Root directory: {project}/public/storage
     */
    public static function root(): string
    {
        // core/Foundation/Storage -> 3 levels up = project root
        return dirname(__DIR__, 3) . '/public/storage';
    }
}
