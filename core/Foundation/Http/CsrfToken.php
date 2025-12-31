<?php

namespace Core\Foundation\Http;

class CsrfToken
{
    const TOKEN_KEY = '_token';

    public static function generate(): string
    {
        Session::start();
        $token = bin2hex(random_bytes(32));
        $_SESSION[self::TOKEN_KEY] = $token;
        return $token;
    }

    public static function get(): ?string
    {
        Session::start();
        return $_SESSION[self::TOKEN_KEY] ?? null;
    }

    public static function validate(): void
    {
        Session::start();
        $token = $_POST[self::TOKEN_KEY] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $sessionToken = $_SESSION[self::TOKEN_KEY] ?? null;

        if (!$sessionToken || !hash_equals($sessionToken, $token)) {
            http_response_code(403);
            exit('Invalid CSRF token.');
        }

        // Don't regenerate - keep token for reuse in this session
    }

    public static function input(): string
    {
        $token = self::get() ?? self::generate();
        return '<input type="hidden" name="' . self::TOKEN_KEY . '" value="' . $token . '">';
    }
}
