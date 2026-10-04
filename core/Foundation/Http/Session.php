<?php

namespace Core\Foundation\Http;

class Session
{
    protected static bool $started = false;
    protected static array $flashData = [];

    /**
     * Start session if not already started
     */
    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        // Cannot start a session after output was sent (CLI / late calls):
        // use an in-memory $_SESSION so get/put still work for this request.
        if (headers_sent()) {
            if (!isset($_SESSION) || !is_array($_SESSION)) {
                $_SESSION = [];
            }
            self::$started = true;
            self::loadFlashData();
            return;
        }

        // Keep sessions inside the project (not the shared system temp dir)
        $savePath = dirname(__DIR__, 3) . '/storage/framework/sessions';
        if ((is_dir($savePath) || @mkdir($savePath, 0755, true)) && is_writable($savePath)) {
            session_save_path($savePath);
        }

        $lifetime = (int) \Core\Support\Env::raw('SESSION_LIFETIME', 120) * 60; // minutes in .env
        if ($lifetime > 0) {
            @ini_set('session.gc_maxlifetime', (string) $lifetime);
            @ini_set('session.cookie_lifetime', (string) $lifetime);
        }

        @ini_set('session.cookie_httponly', '1');
        @ini_set('session.use_only_cookies', '1');
        @ini_set('session.use_strict_mode', '1');
        @ini_set('session.cookie_secure', Request::isSecure() ? '1' : '0');
        @ini_set('session.cookie_samesite', 'Lax');

        session_start();
        self::$started = true;

        self::loadFlashData();
    }

    /**
     * Get session value
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();

        // Check in flash data first
        if (isset(self::$flashData[$key])) {
            return self::$flashData[$key];
        }

        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set session value
     */
    public static function put(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Check if session key exists
     */
    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]) || isset(self::$flashData[$key]);
    }

    /**
     * Remove session key
     */
    public static function forget(string $key): void
    {
        self::start();
        unset($_SESSION[$key], self::$flashData[$key]);
    }

    /**
     * Flash data for next request
     */
    public static function flash(string $key, mixed $value): void
    {
        self::start();

        if (!isset($_SESSION['_flash'])) {
            $_SESSION['_flash'] = [];
        }

        $_SESSION['_flash'][$key] = $value;
    }

    /**
     * Get flash data
     */
    public static function getFlash(string $key, mixed $default = null): mixed
    {
        return self::$flashData[$key] ?? $default;
    }

    /**
     * Load flash data from previous request
     */
    protected static function loadFlashData(): void
    {
        if (isset($_SESSION['_flash'])) {
            self::$flashData = $_SESSION['_flash'];
            unset($_SESSION['_flash']);
        }
    }

    /**
     * Keep flash data for another request
     */
    public static function reflash(array $keys = []): void
    {
        self::start();

        if (empty($keys)) {
            foreach (self::$flashData as $key => $value) {
                self::flash($key, $value);
            }
        } else {
            foreach ($keys as $key) {
                if (isset(self::$flashData[$key])) {
                    self::flash($key, self::$flashData[$key]);
                }
            }
        }
    }

    /**
     * Get all session data
     */
    public static function all(): array
    {
        self::start();
        return array_merge($_SESSION, self::$flashData);
    }

    /**
     * Clear all session data
     */
    public static function flush(): void
    {
        self::start();
        $_SESSION = [];
        self::$flashData = [];
    }

    /**
     * Regenerate session ID
     */
    public static function regenerate(bool $deleteOldSession = true): bool
    {
        self::start();
        return session_regenerate_id($deleteOldSession);
    }

    /**
     * Destroy session
     */
    public static function destroy(): void
    {
        self::start();

        $_SESSION = [];
        self::$flashData = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        self::$started = false;
    }

    /**
     * Get session ID
     */
    public static function getId(): string
    {
        self::start();
        return session_id();
    }

    /**
     * Set session ID
     */
    public static function setId(string $id): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            throw new \Exception('Cannot set session ID when session is already active');
        }

        session_id($id);
    }

    /**
     * Get session name
     */
    public static function getName(): string
    {
        return session_name();
    }

    /**
     * Set session name
     */
    public static function setName(string $name): void
    {
        session_name($name);
    }

    /**
     * Save session data
     */
    public static function save(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    /**
     * Check if session is started
     */
    public static function isStarted(): bool
    {
        return self::$started || session_status() === PHP_SESSION_ACTIVE;
    }
}
