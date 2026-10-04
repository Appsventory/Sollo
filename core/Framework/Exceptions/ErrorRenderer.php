<?php

namespace Core\Framework\Exceptions;

use Core\Foundation\Http\Request;

class ErrorRenderer
{
    protected $exception;

    public function __construct(\Throwable $exception)
    {
        $this->exception = $exception;
    }

    /**
     * Render exception data
     */
    public function render()
    {
        return [
            'exception' => $this->exception,
            'exceptionClass' => get_class($this->exception),
            'message' => $this->exception->getMessage(),
            'file' => $this->exception->getFile(),
            'line' => $this->exception->getLine(),
            'codePreview' => $this->getCodePreview(),
            'stackTrace' => $this->getStackTrace(),
            'requestData' => $this->getRequestData(),
            'serverData' => $this->getServerData(),
            'sessionData' => $this->getSessionData(),
            'environmentData' => $this->getEnvironmentData(),
        ];
    }

    /**
     * Get code preview around error line
     */
    protected function getCodePreview()
    {
        $file = $this->exception->getFile();
        $line = $this->exception->getLine();

        if (!file_exists($file)) {
            return ['lines' => [], 'errorLine' => $line];
        }

        $lines = file($file);
        $start = max(0, $line - 11); // 10 lines before
        $end = min(count($lines), $line + 10); // 10 lines after

        $preview = [];
        for ($i = $start; $i < $end; $i++) {
            $preview[$i + 1] = $lines[$i];
        }

        return [
            'lines' => $preview,
            'errorLine' => $line,
            'file' => $file
        ];
    }

    /**
     * Get formatted stack trace
     */
    protected function getStackTrace()
    {
        $trace = $this->exception->getTrace();
        $formatted = [];

        foreach ($trace as $index => $frame) {
            $formatted[] = [
                'index' => $index,
                'file' => $frame['file'] ?? '[internal function]',
                'line' => $frame['line'] ?? null,
                'function' => ($frame['class'] ?? '') . ($frame['type'] ?? '') . $frame['function'],
                'args' => $this->formatArgs($frame['args'] ?? []),
                'isVendor' => isset($frame['file']) && str_contains($frame['file'], '/vendor/')
            ];
        }

        return $formatted;
    }

    /**
     * Format function arguments
     */
    protected function formatArgs($args)
    {
        return array_map(function ($arg) {
            if (is_object($arg)) {
                return get_class($arg);
            } elseif (is_array($arg)) {
                return 'Array(' . count($arg) . ')';
            } elseif (is_string($arg)) {
                return '"' . (strlen($arg) > 50 ? substr($arg, 0, 50) . '...' : $arg) . '"';
            } elseif (is_bool($arg)) {
                return $arg ? 'true' : 'false';
            } elseif (is_null($arg)) {
                return 'null';
            }
            return (string) $arg;
        }, $args);
    }

    /**
     * Get request data
     */
    protected function getRequestData()
    {
        return [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'url' => $_SERVER['REQUEST_URI'] ?? '/',
            'ip' => Request::ip(),
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'get' => $_GET,
            'post' => $this->sanitizePostData($_POST),
            'headers' => $this->getHeaders(),
            'cookies' => $_COOKIE
        ];
    }

    /**
     * Sanitize POST data (hide passwords)
     */
    protected function sanitizePostData($data)
    {
        $sensitiveKeys = ['password', 'password_confirmation', 'token', 'secret', 'api_key'];

        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                $data[$key] = '••••••••';
            }
        }

        return $data;
    }

    /**
     * Get HTTP headers
     */
    protected function getHeaders()
    {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $header = str_replace('_', '-', substr($key, 5));
                $headers[$header] = $value;
            }
        }

        return $headers;
    }

    /**
     * Get server data
     */
    protected function getServerData()
    {
        return [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
            'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? '',
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'localhost',
            'server_addr' => $_SERVER['SERVER_ADDR'] ?? '127.0.0.1'
        ];
    }

    /**
     * Get session data
     */
    protected function getSessionData()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return $_SESSION;
        }

        return [];
    }

    /**
     * Get environment data
     */
    protected function getEnvironmentData()
    {
        return [
            'app_env' => \Core\Support\Env::raw('APP_ENV', 'production'),
            'app_debug' => \Core\Support\Env::raw('APP_DEBUG', 'false'),
            'app_url' => \Core\Support\Env::raw('APP_URL', ''),
            'db_connection' => \Core\Support\Env::raw('DB_CONNECTION', 'mysql')
        ];
    }
}
