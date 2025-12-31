<?php

namespace App\Middleware;

use Core\Foundation\Http\Request;
use Core\Support\Env;

class Api
{
    /**
     * Handle the API middleware.
     * Sets up JSON responses, rate limiting, and API-specific security.
     */
    public function handle()
    {
        // 1. Set JSON response headers
        $this->setJsonHeaders();

        // 2. Set API-specific security headers
        $this->setApiSecurityHeaders();

        // 3. Validate API requests
        $this->validateApiRequest();

        // 4. Log API requests
        $this->logApiRequest();
    }

    /**
     * Set JSON content-type and encoding headers
     */
    protected function setJsonHeaders(): void
    {
        header('Content-Type: application/json; charset=UTF-8', true);
        header('Accept: application/json', true);
    }

    /**
     * Set API-specific security headers
     */
    protected function setApiSecurityHeaders(): void
    {
        // Prevent MIME sniffing
        header('X-Content-Type-Options: nosniff', true);

        // Disable caching for API responses
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
        header('Pragma: no-cache', true);
        header('Expires: 0', true);

        // Prevent clickjacking
        header('X-Frame-Options: DENY', true);

        // CORS Headers (modify as needed)
        $allowedOrigins = Env::env('API_ALLOWED_ORIGINS', '*');
        header("Access-Control-Allow-Origin: {$allowedOrigins}", true);
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS', true);
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept', true);
        header('Access-Control-Max-Age: 3600', true);
    }

    /**
     * Validate API request format
     */
    protected function validateApiRequest(): void
    {
        $method = Request::method();

        // Validate Content-Type for POST/PUT/PATCH requests
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

            if (!empty($_SERVER['CONTENT_LENGTH']) && !self::strContains($contentType, 'application/json')) {
                http_response_code(415);
                echo json_encode([
                    'error' => 'Unsupported Media Type',
                    'message' => 'Content-Type must be application/json'
                ]);
                exit;
            }
        }
    }

    /**
     * Log API requests for monitoring and debugging
     */
    protected function logApiRequest(): void
    {
        $method = Request::method();
        $uri = Request::uri();
        $timestamp = date('Y-m-d H:i:s');
        $ip = $this->getClientIp();

        // Always log API requests
        $logDir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/api_' . date('Y-m-d') . '.log';
        $logMessage = "[$timestamp] $ip $method $uri\n";
        @file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
    /**
     * Get client IP address
     */
    protected function getClientIp(): string
    {
        // Check for shared internet
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }
        // Check for IP passed from proxy
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }
        // Remote address
        else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }

        return trim($ip);
    }

    /**
     * String contains helper (PHP 7.x compatibility)
     */
    protected static function strContains(string $haystack, string $needle): bool
    {
        if (function_exists('str_contains')) {
            return str_contains($haystack, $needle);
        }
        return strpos($haystack, $needle) !== false;
    }
}
