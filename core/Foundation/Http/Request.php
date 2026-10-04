<?php

namespace Core\Foundation\Http;

use Core\Foundation\Routing\Router;
use Core\Foundation\Validator;

class Request
{
    protected static ?array $jsonCache = null;
    protected static ?array $allCache = null;
    protected static array $fileCache = [];
    protected static ?array $formBodyCache = null;
    protected static ?string $rawCache = null;

    /** Fields used by the framework itself; never part of all()/only()/except(). */
    protected const RESERVED_FIELDS = ['_token', '_method'];

    /**
     * Get request method.
     * Only a real POST can be overridden (HTML forms), via the _method field
     * or the X-HTTP-Method-Override header, and only to PUT/PATCH/DELETE.
     */
    public static function method(): string
    {
        $baseMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($baseMethod !== 'POST') {
            return $baseMethod;
        }

        $override = $_POST['_method'] ?? ($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? null);

        if (is_string($override)) {
            $override = strtoupper(trim($override));
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
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
     * Check if request is secure (HTTPS). X-Forwarded-Proto is honoured only
     * when the request comes from a trusted proxy (TRUSTED_PROXIES in .env).
     */
    public static function isSecure(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
            return true;
        }

        return self::isTrustedProxy()
            && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    /**
     * Whether the direct peer (REMOTE_ADDR) is listed in TRUSTED_PROXIES
     * (comma separated IPs, or "*"). Empty by default: forwarded headers are ignored.
     */
    public static function isTrustedProxy(): bool
    {
        $trusted = trim((string) \Core\Support\Env::raw('TRUSTED_PROXIES', ''));
        if ($trusted === '') {
            return false;
        }

        if ($trusted === '*') {
            return true;
        }

        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        return in_array($remote, array_map('trim', explode(',', $trusted)), true);
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
     * Get POST data (raw values, never HTML-escaped: escape on output with {{ }}).
     */
    public static function post(?string $key = null, mixed $default = null): mixed
    {
        $data = self::body();

        if ($key === null) {
            return $data;
        }

        return $data[$key] ?? $default;
    }

    /**
     * Get GET (query string) data
     */
    public static function get(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_GET;
        }

        return $_GET[$key] ?? $default;
    }

    /**
     * Request body fields: JSON body, form body, or $_POST.
     * PUT/PATCH/DELETE with a urlencoded body are parsed here because PHP only fills $_POST for POST.
     */
    protected static function body(): array
    {
        if (str_starts_with(strtolower(self::contentType()), 'application/json')) {
            return self::json();
        }

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if (in_array($method, ['PUT', 'PATCH', 'DELETE'], true)
            && str_starts_with(strtolower(self::contentType()), 'application/x-www-form-urlencoded')
        ) {
            if (self::$formBodyCache === null) {
                parse_str(self::raw(), $parsed);
                self::$formBodyCache = $parsed;
            }
            return self::$formBodyCache;
        }

        return $_POST;
    }

    /**
     * Get all request data (query string + body), without framework fields (_token, _method).
     */
    public static function all(): array
    {
        if (self::$allCache !== null) {
            return self::$allCache;
        }

        $data = array_merge($_GET, self::body());

        return self::$allCache = array_diff_key($data, array_flip(self::RESERVED_FIELDS));
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

    public static function isApi(): bool
    {
        $uri = self::uri();
        return $uri === '/api' || str_starts_with($uri, '/api/') || self::wantsJson();
    }

    /**
     * Get content type
     */
    public static function contentType(): string
    {
        return $_SERVER['CONTENT_TYPE'] ?? '';
    }

    /**
     * Get JSON data. A non-empty body that is not valid JSON is a 400 error.
     */
    public static function json(): array
    {
        if (self::$jsonCache !== null) {
            return self::$jsonCache;
        }

        $raw = trim(self::raw());

        if ($raw === '') {
            return self::$jsonCache = [];
        }

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new HttpException(400, 'Malformed JSON body: ' . json_last_error_msg());
        }

        return self::$jsonCache = is_array($decoded) ? $decoded : [];
    }

    public static function raw(): string
    {
        return self::$rawCache ??= (string) file_get_contents('php://input');
    }

    public static function headers(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true) && $value !== '') {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))));
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    public static function header(string $key, ?string $default = null): ?string
    {
        $wanted = strtolower(str_replace('_', '-', $key));

        foreach (self::headers() as $name => $value) {
            if (strtolower($name) === $wanted) {
                return (string) $value;
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
     * Get client IP address. Forwarded headers are used only when the direct
     * peer is a trusted proxy (TRUSTED_PROXIES), otherwise they could be spoofed.
     */
    public static function ip(): string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        if (!self::isTrustedProxy()) {
            return $remote;
        }

        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'] as $header) {
            if (!empty($_SERVER[$header])) {
                $candidate = trim(explode(',', (string) $_SERVER[$header])[0]);
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }
        }

        return $remote;
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
     * Get route parameters of the matched route
     */
    public static function route(?string $key = null, mixed $default = null): mixed
    {
        $routeParams = Router::currentParameters();

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
     * URL of the previous page when it is on this same host, "/" otherwise
     * (never redirect to an arbitrary external Referer).
     */
    public static function previousUrl(): string
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';

        if ($referer === '') {
            return '/';
        }

        $parts = parse_url($referer);
        $host = $parts['host'] ?? null;
        $currentHost = preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));

        if ($host === null || strcasecmp($host, $currentHost) !== 0) {
            return '/';
        }

        return ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }

    /**
     * Get old input data (flashed by a failed validation, for form repopulation)
     */
    public static function old(?string $key = null, mixed $default = null): mixed
    {
        $oldData = Session::get('_old_input', []);

        if (!is_array($oldData)) {
            $oldData = [];
        }

        if ($key === null) {
            return $oldData;
        }

        return $oldData[$key] ?? $default;
    }

    /**
     * Flash input to the next request (password fields are never flashed)
     */
    public static function flash(): void
    {
        $data = array_filter(
            self::all(),
            fn($key) => !preg_match('/password|passwd|secret|token|_confirmation$/i', (string) $key),
            ARRAY_FILTER_USE_KEY
        );

        Session::flash('_old_input', $data);
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

    public static function clearCache(): void
    {
        self::$jsonCache = null;
        self::$allCache = null;
        self::$formBodyCache = null;
        self::$rawCache = null;
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
