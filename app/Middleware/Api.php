<?php

namespace App\Middleware;

use Core\Foundation\Http\Cors;
use Core\Foundation\Http\HttpException;
use Core\Foundation\Http\Request;

class Api
{
    /**
     * Handle the API middleware: JSON headers, CORS, request validation, logging.
     * API routes are stateless: no session and no CSRF (use tokens/keys for auth).
     */
    public function handle()
    {
        $this->setJsonHeaders();
        $this->setApiSecurityHeaders();
        $this->validateApiRequest();
        $this->logApiRequest();
    }

    protected function setJsonHeaders(): void
    {
        header('Content-Type: application/json; charset=UTF-8', true);
    }

    protected function setApiSecurityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff', true);

        // Disable caching for API responses
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
        header('Pragma: no-cache', true);
        header('Expires: 0', true);

        header('X-Frame-Options: DENY', true);

        // CORS (API_ALLOWED_ORIGINS in .env) - same logic as the OPTIONS preflight
        Cors::apply();
    }

    /**
     * A request with a body must be JSON (or empty).
     */
    protected function validateApiRequest(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            return;
        }

        $contentType = strtolower(Request::contentType());

        if (!empty($_SERVER['CONTENT_LENGTH']) && !str_contains($contentType, 'application/json')) {
            throw new HttpException(415, 'Content-Type must be application/json');
        }
    }

    /**
     * Log API requests for monitoring and debugging
     */
    protected function logApiRequest(): void
    {
        $logDir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $line = sprintf("[%s] %s %s %s\n", date('Y-m-d H:i:s'), Request::ip(), Request::method(), Request::uri());
        @file_put_contents($logDir . '/api_' . date('Y-m-d') . '.log', $line, FILE_APPEND);
    }
}
