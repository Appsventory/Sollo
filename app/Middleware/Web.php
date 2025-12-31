<?php

namespace App\Middleware;

use Core\Foundation\Http\Session;
use Core\Foundation\Http\Request;
use Core\Support\Env;

class Web
{
    /**
     * Handle the web middleware.
     * Manages sessions, CSRF tokens, and web-specific security.
     */
    public function handle()
    {
        // 1. Ensure session is started
        Session::start();

        // 2. Initialize CSRF token for forms
        $this->initializeCsrfToken();

        // 3. Set global response headers for web
        $this->setSecurityHeaders();

        // 4. Log web requests (optional, development only)
        $this->logRequest();
    }

    /**
     * Initialize CSRF token for this request
     */
    protected function initializeCsrfToken(): void
    {
        // CSRF token already generated in Session::start()
        // Make it available to views
        if (!defined('CSRF_TOKEN')) {
            define('CSRF_TOKEN', Session::get('_token'));
        }
    }

    /**
     * Set security headers for web responses
     */
    protected function setSecurityHeaders(): void
    {
        // X-Frame-Options: Prevent clickjacking
        header('X-Frame-Options: SAMEORIGIN', true);

        // X-Content-Type-Options: Prevent MIME sniffing
        header('X-Content-Type-Options: nosniff', true);

        // X-XSS-Protection: Enable XSS protection
        header('X-XSS-Protection: 1; mode=block', true);

        // Referrer-Policy: Control referrer information
        header('Referrer-Policy: strict-origin-when-cross-origin', true);

        // Content-Security-Policy: Control which resources can be loaded
        if (Env::env('APP_ENV') === 'production') {
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' cdn.tailwindcss.com cdnjs.cloudflare.com fonts.googleapis.com; style-src 'self' 'unsafe-inline' cdnjs.cloudflare.com fonts.googleapis.com; font-src 'self' fonts.gstatic.com cdnjs.cloudflare.com; img-src 'self' data: https:;", true);
        }
    }

    /**
     * Log web requests (development only)
     */
    protected function logRequest(): void
    {
        if (Env::env('APP_ENV') !== 'production') {
            $method = Request::method();
            $uri = Request::uri();
            $timestamp = date('Y-m-d H:i:s');

            // Log to file (optional)
            $logDir = dirname(__DIR__, 2) . '/storage/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }

            $logFile = $logDir . '/web_' . date('Y-m-d') . '.log';
            $logMessage = "[$timestamp] $method $uri\n";
            @file_put_contents($logFile, $logMessage, FILE_APPEND);
        }
    }
}
