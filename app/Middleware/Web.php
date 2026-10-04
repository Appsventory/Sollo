<?php

namespace App\Middleware;

use Core\Foundation\Http\CsrfToken;
use Core\Foundation\Http\Request;
use Core\Foundation\Http\Session;
use Core\Support\Env;

class Web
{
    /**
     * URI patterns (fnmatch) that skip CSRF verification, e.g. incoming
     * webhooks that cannot carry a token:  ['/webhooks/*']
     */
    protected array $except = [];

    /**
     * Handle the web middleware: session, CSRF verification, security headers.
     */
    public function handle()
    {
        Session::start();

        $this->setSecurityHeaders();
        $this->verifyCsrfToken();
        $this->logRequest();
    }

    /**
     * Every state-changing request (POST/PUT/PATCH/DELETE) must carry a valid
     * CSRF token (@csrf in forms, X-CSRF-Token header for AJAX). Failure => 419.
     */
    protected function verifyCsrfToken(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        foreach ($this->except as $pattern) {
            if (Request::isf($pattern)) {
                return;
            }
        }

        CsrfToken::validate();
    }

    /**
     * Set security headers for web responses
     */
    protected function setSecurityHeaders(): void
    {
        header('X-Frame-Options: SAMEORIGIN', true);
        header('X-Content-Type-Options: nosniff', true);
        // Legacy XSS auditors are harmful; "0" is the current recommendation.
        header('X-XSS-Protection: 0', true);
        header('Referrer-Policy: strict-origin-when-cross-origin', true);

        if (Env::env('APP_ENV') === 'production') {
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' cdn.tailwindcss.com cdnjs.cloudflare.com fonts.googleapis.com; style-src 'self' 'unsafe-inline' cdnjs.cloudflare.com fonts.googleapis.com; font-src 'self' fonts.gstatic.com cdnjs.cloudflare.com; img-src 'self' data: https:;", true);
        }
    }

    /**
     * Log web requests (development only)
     */
    protected function logRequest(): void
    {
        if (Env::env('APP_ENV') === 'production') {
            return;
        }

        $logDir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $line = sprintf("[%s] %s %s\n", date('Y-m-d H:i:s'), Request::method(), Request::uri());
        @file_put_contents($logDir . '/web_' . date('Y-m-d') . '.log', $line, FILE_APPEND);
    }
}
