<?php

namespace Core\Support;

/**
 * Environment access.
 *
 * - Real environment variables (Docker, Apache SetEnv, systemd, CI) always win
 *   over values in the .env file.
 * - env() converts the literals true / false / null / empty to PHP values;
 *   raw() returns the string exactly as written.
 */
class Env
{
    /** @var array<string,true> names that were set from the .env file */
    private static array $fromFile = [];

    /**
     * Raw string value (no type conversion), or $default when not set.
     */
    public static function raw(string $key, $default = null)
    {
        // getenv() is the live source (load() also putenv()s every .env value);
        // $_ENV / $_SERVER are fallbacks for setups where putenv() is unavailable.
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        if (isset($_SERVER[$key]) && is_string($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        return $default;
    }

    /**
     * Value with literal conversion: "true" => true, "false" => false,
     * "null" => null, "empty" => ''.
     */
    public static function env($key, $default = null)
    {
        $value = self::raw((string) $key);

        if ($value === null) {
            return $default;
        }

        switch (strtolower(trim((string) $value))) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'null':
            case '(null)':
                return null;
            case 'empty':
            case '(empty)':
                return '';
        }

        return $value;
    }

    /**
     * Boolean flag. Unset variables return $default.
     */
    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::raw($key);
        if ($value === null) {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed ?? $default;
    }

    public static function load(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#') {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = ltrim(substr($line, 7));
            }

            if (strpos($line, '=') === false) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);

            if ($name === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $name)) {
                continue;
            }

            $value = self::parseValue($value);

            // A real environment variable wins over the .env file.
            if (!isset(self::$fromFile[$name])) {
                $existing = getenv($name);
                if ($existing !== false) {
                    $_ENV[$name] = $existing;
                    continue;
                }
            }

            self::$fromFile[$name] = true;
            $_ENV[$name] = $value;
            putenv("{$name}={$value}");
        }
    }

    private static function parseValue(string $raw): string
    {
        $raw = trim($raw);

        if ($raw === '') {
            return '';
        }

        $quote = $raw[0];
        if ($quote === '"' || $quote === "'") {
            $end = strpos($raw, $quote, 1);
            if ($end !== false) {
                $inner = substr($raw, 1, $end - 1);
                return $quote === '"' ? str_replace(['\\n', '\\"'], ["\n", '"'], $inner) : $inner;
            }
            return trim($raw, "\"'");
        }

        // Unquoted: strip an inline comment ("value # comment")
        $hash = preg_match('/\s#/', $raw, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] : false;
        if ($hash !== false) {
            $raw = substr($raw, 0, $hash);
        }

        return rtrim($raw);
    }
}
