<?php

namespace Core\Foundation\Http;

use Core\Foundation\Validator;
use Core\Foundation\Http\Session;

class Request
{
    protected static ?array $jsonCache = null;
    protected static ?array $allCache = null;
    protected static array $fileCache = [];

    /**
     * Sanitize input data
     */
    protected static function sanitize(mixed $data): mixed
    {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize'], $data);
        }

        return is_string($data) ? htmlspecialchars($data, ENT_QUOTES, 'UTF-8') : $data;
    }

    /**
     * Get request method
     */
    public static function method(): string
    {
        // Get base request method directly from $_SERVER to avoid recursion
        $baseMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Handle method override header
        if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            return strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
        }

        // Handle method spoofing (only if base method is POST)
        if ($baseMethod === 'POST' && self::has('_method')) {
            return strtoupper(self::post('_method'));
        }

        return $baseMethod;
    }

    /**
     * Get request URI
     */
    public static function uri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $pos = strpos($uri, '?');
        return $pos === false ? $uri : substr($uri, 0, $pos);
    }

    /**
     * Get full URL
     */
    public static function url(): string
    {
        $scheme = self::isSecure() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = self::uri();

        return "{$scheme}://{$host}{$uri}";
    }

    /**
     * Get full URL with query parameters
     */
    public static function fullUrl(): string
    {
        $scheme = self::isSecure() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';

        return "{$scheme}://{$host}{$requestUri}";
    }

    /**
     * Check if request is secure (HTTPS)
     */
    public static function isSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            $_SERVER['SERVER_PORT'] == 443 ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }

    /**
     * Get specific input value
     */
    public static function input(string $key, mixed $default = null): mixed
    {
        $data = self::all();
        return $data[$key] ?? $default;
    }

    /**
     * Get POST data
     */
    public static function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return self::sanitize($_POST);
        }

        return isset($_POST[$key]) ? self::sanitize($_POST[$key]) : $default;
    }

    /**
     * Get GET data
     */
    public static function get(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return self::sanitize($_GET);
        }

        return isset($_GET[$key]) ? self::sanitize($_GET[$key]) : $default;
    }

    /**
     * Get all request data
     */
    public static function all(): array
    {
        if (self::$allCache !== null) {
            return self::$allCache;
        }

        $contentType = self::contentType();

        if (str_starts_with($contentType, 'application/json')) {
            self::$allCache = self::json();
        } else {
            self::$allCache = self::sanitize(array_merge($_GET, $_POST));
        }

        return self::$allCache;
    }

    /**
     * Get specific keys only
     */
    public static function only(array $keys): array
    {
        $data = self::all();
        return array_intersect_key($data, array_flip($keys));
    }

    /**
     * Get all except specific keys
     */
    public static function except(array $keys): array
    {
        $data = self::all();
        return array_diff_key($data, array_flip($keys));
    }

    /**
     * Check if input exists
     */
    public static function has(string $key): bool
    {
        $value = self::input($key);
        return $value !== null && $value !== '';
    }

    /**
     * Check if input exists and is not empty
     */
    public static function filled(string $key): bool
    {
        return self::has($key);
    }

    /**
     * Check if any of the given keys exist
     */
    public static function hasAny(array $keys): bool
    {
        foreach ($keys as $key) {
            if (self::has($key)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get uploaded file
     */
    public static function file(string $key): ?array
    {
        if (isset(self::$fileCache[$key])) {
            return self::$fileCache[$key];
        }

        if (!isset($_FILES[$key])) {
            return null;
        }

        $file = $_FILES[$key];

        // Handle multiple files
        if (is_array($file['name'])) {
            $files = [];
            foreach ($file['name'] as $index => $name) {
                $files[] = [
                    'name' => $name,
                    'type' => $file['type'][$index],
                    'tmp_name' => $file['tmp_name'][$index],
                    'error' => $file['error'][$index],
                    'size' => $file['size'][$index],
                ];
            }
            self::$fileCache[$key] = $files;
            return $files;
        }

        self::$fileCache[$key] = $file;
        return $file;
    }

    /**
     * Check if file was uploaded
     */
    public static function hasFile(string $key): bool
    {
        $file = self::file($key);
        return $file !== null && $file['error'] === UPLOAD_ERR_OK;
    }

    /**
     * Get all uploaded files
     */
    public static function allFiles(): array
    {
        $files = [];
        foreach ($_FILES as $key => $file) {
            $files[$key] = self::file($key);
        }
        return $files;
    }

    /**
     * Check request method
     */
    public static function is(string $method): bool
    {
        return strtoupper(self::method()) === strtoupper($method);
    }

    /**
     * Check if request is AJAX
     */
    public static function ajax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Check if request wants JSON response
     */
    public static function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return str_contains($accept, 'application/json') || self::ajax();
    }

    /**
     * Check if request is API request
     */
    public static function isApi(): bool
    {
        return str_starts_with(self::uri(), '/api/') || self::wantsJson();
    }

    /**
     * Get content type
     */
    public static function contentType(): string
    {
        return $_SERVER['CONTENT_TYPE'] ?? '';
    }

    /**
     * Get JSON data
     */
    public static function json(): array
    {
        if (self::$jsonCache !== null) {
            return self::$jsonCache;
        }

        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            self::$jsonCache = [];
        } else {
            self::$jsonCache = is_array($decoded) ? self::sanitize($decoded) : [];
        }

        return self::$jsonCache;
    }

    /**
     * Get raw request body
     */
    public static function raw(): string
    {
        return file_get_contents('php://input');
    }

    /**
     * Get request headers
     */
    public static function headers(): array
    {
        return getallheaders() ?: [];
    }

    /**
     * Get specific header
     */
    public static function header(string $key, ?string $default = null): ?string
    {
        $headers = self::headers();

        // Try different case variations
        $variations = [
            $key,
            strtolower($key),
            strtoupper($key),
            ucfirst(strtolower($key)),
            str_replace('_', '-', $key),
            str_replace('-', '_', $key)
        ];

        foreach ($variations as $variation) {
            if (isset($headers[$variation])) {
                return $headers[$variation];
            }
        }

        return $default;
    }

    /**
     * Get bearer token
     */
    public static function bearerToken(): ?string
    {
        $authorization = self::header('Authorization');

        if ($authorization && str_starts_with($authorization, 'Bearer ')) {
            return substr($authorization, 7);
        }

        return null;
    }

    /**
     * Get user agent
     */
    public static function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Get client IP address
     */
    public static function ip(): string
    {
        // Check for various headers that might contain the real IP
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];

                // Handle comma-separated IPs (X-Forwarded-For can contain multiple IPs)
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }

                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get query string parameters
     */
    public static function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_GET;
        }

        return $_GET[$key] ?? $default;
    }

    /**
     * Get route parameters
     */
    public static function route(?string $key = null, mixed $default = null): mixed
    {
        $routeParams = $_GET['_route_params'] ?? [];

        if ($key === null) {
            return $routeParams;
        }

        return $routeParams[$key] ?? $default;
    }

    /**
     * Validate input data
     */
    public static function validate(array $rules, array $messages = []): array
    {
        $validator = new Validator();
        return $validator->validate(self::all(), $rules, $messages);
    }

    /**
     * Get old input data (for form repopulation after validation errors)
     */
    public static function old(?string $key = null, mixed $default = null): mixed
    {
        $oldData = Session::get('_old_input', []);

        if ($key === null) {
            return $oldData;
        }

        return $oldData[$key] ?? $default;
    }

    /**
     * Flash input data to session (for form repopulation)
     */
    public static function flash(): void
    {
        Session::flash('_old_input', self::all());
    }

    /**
     * Check if request matches given patterns
     */
    public static function isf(string ...$patterns): bool
    {
        $uri = self::uri();

        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $uri)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get preferred language
     */
    public static function getPreferredLanguage(array $available = []): string
    {
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';

        if (empty($acceptLanguage)) {
            return $available[0] ?? 'en';
        }

        preg_match_all('/([a-z]{1,8}(?:-[a-z]{1,8})?)\s*(?:;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $acceptLanguage, $matches);

        $languages = [];
        foreach ($matches[1] as $i => $lang) {
            $quality = isset($matches[2][$i]) ? floatval($matches[2][$i]) : 1.0;
            $languages[strtolower($lang)] = $quality;
        }

        arsort($languages);

        foreach ($languages as $lang => $quality) {
            if (empty($available) || in_array($lang, $available)) {
                return $lang;
            }

            // Check for language without region (e.g., 'en' for 'en-us')
            $shortLang = substr($lang, 0, 2);
            if (in_array($shortLang, $available)) {
                return $shortLang;
            }
        }

        return $available[0] ?? 'en';
    }

    /**
     * Get request fingerprint for caching/security
     */
    public static function fingerprint(): string
    {
        return md5(
            self::method() . '|' .
                self::uri() . '|' .
                serialize(self::all())
        );
    }

    /**
     * Check if request is from bot/crawler
     */
    public static function isBot(): bool
    {
        $userAgent = strtolower(self::userAgent());

        $bots = [
            'googlebot',
            'bingbot',
            'slurp',
            'duckduckbot',
            'baiduspider',
            'yandexbot',
            'facebookexternalhit',
            'twitterbot',
            'whatsapp',
            'telegram'
        ];

        foreach ($bots as $bot) {
            if (str_contains($userAgent, $bot)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get session ID
     */
    public static function sessionId(): string
    {
        return session_id() ?: '';
    }

    /**
     * Get CSRF token
     */
    public static function csrfToken(): string
    {
        return Session::get('_token', '');
    }

    /**
     * Verify CSRF token
     */
    public static function verifyCsrfToken(): bool
    {
        $token = self::input('_token') ?: self::header('X-CSRF-Token');
        return hash_equals(self::csrfToken(), $token ?: '');
    }

    /**
     * Clear cached data (useful for testing)
     */
    public static function clearCache(): void
    {
        self::$jsonCache = null;
        self::$allCache = null;
        self::$fileCache = [];
    }

    /**
     * Create a new request instance with data (useful for testing)
     */
    public static function create(
        string $uri = '/',
        string $method = 'GET',
        array $data = [],
        array $files = []
    ): void {
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['REQUEST_METHOD'] = $method;
        $_GET = $method === 'GET' ? $data : [];
        $_POST = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE']) ? $data : [];
        $_FILES = $files;

        self::clearCache();
    }

    /**
     * Magic method to get input data
     */
    public function __get(string $key): mixed
    {
        return self::input($key);
    }

    /**
     * Magic method to check if input exists
     */
    public function __isset(string $key): bool
    {
        return self::has($key);
    }
}
