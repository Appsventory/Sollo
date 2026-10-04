<?php

namespace Core\Foundation\Http;

/**
 * CSRF protection. One implementation, one session key (_token).
 *
 * The Web middleware verifies the token on every POST/PUT/PATCH/DELETE;
 * forms send it with @csrf and AJAX with the X-CSRF-Token header.
 */
class CsrfToken
{
    const TOKEN_KEY = '_token';

    /**
     * Create a fresh token (e.g. after login). Normally use get().
     */
    public static function generate(): string
    {
        Session::put(self::TOKEN_KEY, $token = bin2hex(random_bytes(32)));
        return $token;
    }

    /**
     * Current token, created on first use.
     */
    public static function get(): string
    {
        $token = Session::get(self::TOKEN_KEY);

        return is_string($token) && $token !== '' ? $token : self::generate();
    }

    /**
     * Compare a submitted token with the session token.
     */
    public static function verify(?string $token): bool
    {
        $sessionToken = Session::get(self::TOKEN_KEY);

        return is_string($sessionToken) && $sessionToken !== ''
            && is_string($token) && hash_equals($sessionToken, $token);
    }

    /**
     * Token submitted with this request (form field or header).
     */
    public static function submitted(): ?string
    {
        $fromForm = $_POST[self::TOKEN_KEY] ?? null;

        if (!is_string($fromForm) && str_starts_with(strtolower(Request::contentType()), 'application/x-www-form-urlencoded')) {
            parse_str(Request::raw(), $body);
            $fromForm = $body[self::TOKEN_KEY] ?? null;
        }

        if (is_string($fromForm) && $fromForm !== '') {
            return $fromForm;
        }

        return $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_XSRF_TOKEN'] ?? null;
    }

    /**
     * Abort with 419 unless the request carries a valid token.
     */
    public static function validate(): void
    {
        if (!self::verify(self::submitted())) {
            throw new HttpException(419);
        }
    }

    /**
     * Hidden input for forms (what @csrf outputs).
     */
    public static function input(): string
    {
        return '<input type="hidden" name="' . self::TOKEN_KEY . '" value="' . htmlspecialchars(self::get(), ENT_QUOTES, 'UTF-8') . '">';
    }
}
