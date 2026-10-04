<?php

namespace Core\Foundation\Http;

/**
 * An exception that maps to an HTTP status code (404, 403, 419, ...).
 * Throw it directly or use the abort() helper:  abort(404);
 */
class HttpException extends \RuntimeException
{
    protected int $statusCode;
    protected array $headers;

    public function __construct(int $statusCode, string $message = '', array $headers = [], ?\Throwable $previous = null)
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;

        parent::__construct($message !== '' ? $message : self::defaultMessage($statusCode), $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public static function defaultMessage(int $status): string
    {
        return [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            415 => 'Unsupported Media Type',
            419 => 'Page Expired (invalid CSRF token)',
            422 => 'Unprocessable Content',
            429 => 'Too Many Requests',
            500 => 'Server Error',
            503 => 'Service Unavailable',
        ][$status] ?? 'HTTP Error';
    }
}
