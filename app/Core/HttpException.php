<?php
namespace App\Core;

class HttpException extends \Exception
{
    protected int $statusCode;

    public function __construct(int $statusCode, string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        $this->statusCode = $statusCode;
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}