<?php

namespace Core\Foundation\Http;

use Core\Support\Env;

/**
 * Single CORS implementation shared by the API middleware and OPTIONS preflight.
 *
 * API_ALLOWED_ORIGINS in .env: "*" (default) or a comma separated list of origins.
 * With a list, the Origin header is echoed back only when it is on the list.
 */
class Cors
{
    public const METHODS = 'GET, POST, PUT, PATCH, DELETE, OPTIONS';
    public const HEADERS = 'Content-Type, Authorization, X-Requested-With, X-CSRF-Token, Accept';

    public static function apply(): void
    {
        $allowed = trim((string) Env::raw('API_ALLOWED_ORIGINS', '*'));
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ($allowed === '*' || $allowed === '') {
            header('Access-Control-Allow-Origin: *', true);
        } else {
            $list = array_map('trim', explode(',', $allowed));
            header('Vary: Origin', false);
            if ($origin !== '' && in_array($origin, $list, true)) {
                header('Access-Control-Allow-Origin: ' . $origin, true);
            }
        }

        header('Access-Control-Allow-Methods: ' . self::METHODS, true);
        header('Access-Control-Allow-Headers: ' . self::HEADERS, true);
        header('Access-Control-Max-Age: 3600', true);
    }

    /**
     * Answer an OPTIONS preflight. Only API URLs get CORS headers.
     */
    public static function preflight(): void
    {
        $uri = Request::uri();

        if ($uri === '/api' || str_starts_with($uri, '/api/')) {
            self::apply();
        }

        http_response_code(204);
    }
}
