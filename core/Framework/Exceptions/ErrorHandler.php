<?php

namespace Core\Framework\Exceptions;

use Core\Foundation\Http\Request;
use App\Core\Session;
use Core\Framework\Exceptions\HttpErrorRenderer;

class ErrorHandler
{
    protected static $registered = false;

    /**
     * Register error and exception handlers
     */
    public static function register()
    {
        if (self::$registered) {
            return;
        }

        // Set error handler
        set_error_handler([self::class, 'handleError']);

        // Set exception handler
        set_exception_handler([self::class, 'handleException']);

        // Set shutdown handler for fatal errors
        register_shutdown_function([self::class, 'handleShutdown']);

        self::$registered = true;
    }

    /**
     * Handle errors
     */
    public static function handleError($level, $message, $file = '', $line = 0)
    {
        if (error_reporting() & $level) {
            throw new \ErrorException($message, 0, $level, $file, $line);
        }
    }

    /**
     * Handle exceptions
     */
    public static function handleException(\Throwable $e)
    {
        try {
            self::logException($e);
            self::renderException($e);
        } catch (\Throwable $renderException) {
            // Fallback if rendering fails
            HttpErrorRenderer::error500();
        }
    }

    /**
     * Handle fatal errors
     */
    public static function handleShutdown()
    {
        $error = error_get_last();

        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $exception = new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );

            self::handleException($exception);
        }
    }

    /**
     * Render exception to user
     */
    protected static function renderException(\Throwable $e)
    {
        $isDebug = self::isDebugMode();

        // Check if request wants JSON
        if (Request::wantsJson() || Request::isApi()) {
            self::renderJsonException($e, $isDebug);
            return;
        }

        // Render HTML
        if ($isDebug) {
            self::renderDetailedException($e);
        } else {
            self::renderProductionException($e);
        }
    }

    /**
     * Render detailed exception (development)
     */
    protected static function renderDetailedException(\Throwable $e)
    {
        http_response_code(500);

        $renderer = new ErrorRenderer($e);
        $data = $renderer->render();

        // Check if exception view exists
        $viewPath = dirname(__DIR__) . '/resources/Views/errors/exception.nixs.php';
        $appDebug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $errorDisplay = $_ENV['ERROR_DISPLAY'] ?? 'inline';

        // Flag to check if already handled
        $handled = false;

        if ($appDebug) {
            if ($errorDisplay === 'pro') {
                HttpErrorRenderer::NixsHighlighting($data);
                $handled = true;
            } elseif ($errorDisplay === 'inline') {
                HttpErrorRenderer::InlineException($data);
                $handled = true;
            } elseif ($errorDisplay === 'custom' && is_file($viewPath)) {
                if (is_array($data)) {
                    extract($data, EXTR_SKIP);
                }
                require $viewPath;
                $handled = true;
            }
        }

        // Fallback to production rendering if not handled
        if (!$handled) {
            self::renderProductionException($e);
        }

        exit;
    }

    /**
     * Render production exception
     */
    protected static function renderProductionException(\Throwable $e)
    {
        $statusCode = 500;
        if (is_object($e) && is_callable([$e, 'getStatusCode'])) {
            $code = call_user_func([$e, 'getStatusCode']);
            $statusCode = is_int($code) ? $code : (int) $code;
        }
        http_response_code($statusCode);

        $viewFile = dirname(__DIR__) . "/resources/Views/errors/{$statusCode}.nixs.php";

        if (!file_exists($viewFile)) {
            $viewFile = dirname(__DIR__) . '/resources/Views/errors/500.nixs.php';
        }

        if (file_exists($viewFile)) {
            $errorId = self::generateErrorId();
            require $viewFile;
        } else {
            HttpErrorRenderer::error500();
        }

        exit;
    }

    /**
     * Render JSON exception
     */
    protected static function renderJsonException(\Throwable $e, $isDebug)
    {
        $statusCode = 500;
        if (is_object($e) && is_callable([$e, 'getStatusCode'])) {
            $code = call_user_func([$e, 'getStatusCode']);
            $statusCode = is_int($code) ? $code : (int) $code;
        }
        http_response_code($statusCode);

        header('Content-Type: application/json');

        $response = [
            'error' => true,
            'message' => $isDebug ? $e->getMessage() : 'An error occurred',
            'status' => $statusCode
        ];

        if ($isDebug) {
            $response['exception'] = get_class($e);
            $response['file'] = $e->getFile();
            $response['line'] = $e->getLine();
            $response['trace'] = $e->getTrace();
        }

        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Log exception
     */
    protected static function logException(\Throwable $e)
    {
        $logDir = dirname(__DIR__, 3) . '/storage/logs';

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/' . date('Y-m-d') . '.log';

        $logMessage = sprintf(
            "[%s] %s: %s in %s:%d\nStack trace:\n%s\n\n",
            date('Y-m-d H:i:s'),
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );

        @file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

    /**
     * Check if debug mode
     */
    protected static function isDebugMode()
    {
        return ($_ENV['APP_DEBUG'] ?? 'false') === 'true' ||
            ($_ENV['APP_ENV'] ?? 'production') === 'local' ||
            ($_ENV['APP_ENV'] ?? 'production') === 'development';
    }

    /**
     * Generate unique error ID
     */
    protected static function generateErrorId()
    {
        return strtoupper(substr(md5(uniqid()), 0, 8));
    }
}
