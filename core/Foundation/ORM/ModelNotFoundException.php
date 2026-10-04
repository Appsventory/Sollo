<?php

namespace Core\Foundation\ORM;

use Core\Foundation\Http\HttpException;

/**
 * Thrown by findOrFail()/firstOrFail(). It is an HttpException, so an
 * uncaught one renders the 404 page (or a 404 JSON for API requests).
 */
class ModelNotFoundException extends HttpException
{
    public function __construct(string $model, $id = null)
    {
        parent::__construct(404, "No query results for model [{$model}]" . ($id !== null ? " {$id}" : ''));
    }
}
