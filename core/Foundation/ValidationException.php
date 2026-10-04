<?php

namespace Core\Foundation;

class ValidationException extends \Exception
{
    protected array $errors;

    public function __construct(array $errors)
    {
        $this->errors = $errors;

        $message = 'Validation failed';
        if (!empty($errors)) {
            $firstField = array_key_first($errors);
            $firstError = $errors[$firstField][0] ?? 'Unknown error';
            $message = $firstError;
        }

        parent::__construct($message);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getErrorsFor(string $field): array
    {
        return $this->errors[$field] ?? [];
    }
}
