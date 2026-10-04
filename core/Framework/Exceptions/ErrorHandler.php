<?php

namespace Core\Framework\Exceptions;

use Core\Foundation\Http\HttpException;
use Core\Foundation\Http\Request;
use Core\Foundation\Http\Session;
use Core\Foundation\ValidationException;
use Core\Support\Env;

class ErrorHandler
{
    protected static $registered = false;
    public static int $baseObLevel = 0;

    /** Set to false in tests so rendering an error does not terminate the process. */
    public static bool $exitAfterRender = true;

    /**
     * Register error and exception handlers
     */
    public static function register()
    {
        if (self::$registered) {
            return;
        }

        self::$baseObLevel = ob_get_level();

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);

        self::$registered = true;
    }

    /**
     * Convert PHP warnings/notices to exceptions. Deprecations are only logged:
     * they must not take a page down (e.g. after a PHP upgrade).
     */
    public static function handleError($level, $message, $file = '', $line = 0)
    {
        if (!(error_reporting() & $level)) {
            return false;
        }

        if ($level === E_DEPRECATED || $level === E_USER_DEPRECATED) {
            self::writeLog(sprintf("[%s] Deprecated: %s in %s:%d\n", date('Y-m-d H:i:s'), $message, $file, $line));
            return true;
        }

        throw new \ErrorException($message, 0, $level, $file, $line);
    }

    /**
     * Handle exceptions
     */
    public static function handleException(\Throwable $e)
    {
        try {
            if (self::shouldLog($e)) {
                self::logException($e);
            }
            self::renderException($e);
        } catch (\Throwable $renderException) {
            if (!headers_sent()) {
                http_response_code(500);
            }
            echo '<h1>500 Server Error</h1>';
            self::terminate();
        }
    }

    /**
     * Handle fatal errors
     */
    public static function handleShutdown()
    {
        $error = error_get_last();

        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            self::handleException(new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            ));
        }
    }

    protected static function terminate(): void
    {
        if (self::$exitAfterRender) {
            exit;
        }
    }

    protected static function shouldLog(\Throwable $e): bool
    {
        // Expected client errors (404, 419, 422 ...) are not application failures.
        if ($e instanceof ValidationException) {
            return false;
        }

        return !($e instanceof HttpException && $e->getStatusCode() < 500);
    }

    protected static function statusFor(\Throwable $e): int
    {
        if ($e instanceof ValidationException) {
            return 422;
        }

        if ($e instanceof HttpException) {
            return $e->getStatusCode();
        }

        return 500;
    }

    /**
     * Render exception to user
     */
    protected static function renderException(\Throwable $e)
    {
        // Drop partially rendered output (but keep buffers that existed before the app started)
        while (ob_get_level() > self::$baseObLevel) {
            ob_end_clean();
        }

        $status = self::statusFor($e);
        $isDebug = self::isDebugMode();

        if (!headers_sent()) {
            if ($e instanceof HttpException) {
                foreach ($e->getHeaders() as $name => $value) {
                    header("{$name}: {$value}");
                }
            }
        }

        // Validation failure on a normal form post: redirect back with errors and old input
        if ($e instanceof ValidationException && !(Request::wantsJson() || Request::isApi())) {
            Session::flash('errors', $e->getErrors());
            Request::flash();

            if (!headers_sent()) {
                http_response_code(302);
                header('Location: ' . Request::previousUrl());
            }
            self::terminate();
            return;
        }

        if (Request::wantsJson() || Request::isApi()) {
            self::renderJsonException($e, $status, $isDebug);
            return;
        }

        if (!headers_sent()) {
            http_response_code($status);
        }

        if ($status >= 500 && $isDebug) {
            self::renderDetailedException($e);
        } else {
            self::renderStatusPage($status);
        }

        self::terminate();
    }

    /**
     * Render detailed exception (development only)
     */
    protected static function renderDetailedException(\Throwable $e)
    {
        $data = (new ErrorRenderer($e))->render();
        $mode = Env::raw('ERROR_DISPLAY', 'inline');
        $viewPath = dirname(__DIR__, 3) . '/resources/Views/errors/exception.nixs.php';

        if ($mode === 'pro') {
            HttpErrorRenderer::NixsHighlighting($data);
        } elseif ($mode === 'custom' && is_file($viewPath)) {
            extract($data, EXTR_SKIP);
            require $viewPath;
        } else {
            HttpErrorRenderer::InlineException($data);
        }
    }

    /**
     * Friendly error page: resources/Views/errors/{status}.nixs.php,
     * falling back to 500.nixs.php and finally to the built-in page.
     */
    protected static function renderStatusPage(int $status): void
    {
        $dir = dirname(__DIR__, 3) . '/resources/Views/errors';
        $viewFile = "{$dir}/{$status}.nixs.php";

        if (!is_file($viewFile)) {
            $viewFile = $status >= 500 ? "{$dir}/500.nixs.php" : '';
        }

        if ($viewFile !== '' && is_file($viewFile)) {
            $errorId = self::generateErrorId();
            require $viewFile;
            return;
        }

        if ($status === 404) {
            HttpErrorRenderer::error404();
        } elseif ($status >= 500) {
            HttpErrorRenderer::error500();
        } else {
            HttpErrorRenderer::errorPage($status, HttpException::defaultMessage($status));
        }
    }

    /**
     * Render JSON exception
     */
    protected static function renderJsonException(\Throwable $e, int $status, bool $isDebug)
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }

        $clientSafe = $status < 500 || $isDebug;

        $response = [
            'error' => true,
            'message' => $clientSafe ? $e->getMessage() : 'Server Error',
            'status' => $status,
        ];

        if ($e instanceof ValidationException) {
            $response['message'] = 'The given data was invalid.';
            $response['errors'] = $e->getErrors();
        }

        if ($isDebug && $status >= 500) {
            $response['exception'] = get_class($e);
            $response['file'] = $e->getFile();
            $response['line'] = $e->getLine();
            $response['trace'] = array_map(
                fn($f) => ($f['file'] ?? '[internal]') . ':' . ($f['line'] ?? '?') . ' ' . ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? ''),
                $e->getTrace()
            );
        }

        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        self::terminate();
    }

    /**
     * Log exception
     */
    protected static function logException(\Throwable $e)
    {
        self::writeLog(sprintf(
            "[%s] %s: %s in %s:%d\nStack trace:\n%s\n\n",
            date('Y-m-d H:i:s'),
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));
    }

    protected static function writeLog(string $message): void
    {
        $logDir = dirname(__DIR__, 3) . '/storage/logs';

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        @file_put_contents($logDir . '/' . date('Y-m-d') . '.log', $message, FILE_APPEND);
    }

    /**
     * Debug mode: APP_DEBUG wins when set; when it is not set,
     * APP_ENV=local|development turns debug on.
     */
    public static function isDebugMode(): bool
    {
        $flag = Env::raw('APP_DEBUG');

        if ($flag !== null && $flag !== '') {
            return Env::bool('APP_DEBUG');
        }

        return in_array(Env::raw('APP_ENV', 'production'), ['local', 'development'], true);
    }

    /**
     * Generate unique error ID
     */
    protected static function generateErrorId()
    {
        return strtoupper(bin2hex(random_bytes(4)));
    }
}
