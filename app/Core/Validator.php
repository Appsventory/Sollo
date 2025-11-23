<?php

namespace App\Core;

class Validator
{
    protected array $data = [];
    protected array $rules = [];
    protected array $messages = [];
    protected array $errors = [];

    /**
     * Default error messages
     */
    protected array $defaultMessages = [
        'required' => 'The :field field is required.',
        'email' => 'The :field must be a valid email address.',
        'min' => 'The :field must be at least :min characters.',
        'max' => 'The :field must not exceed :max characters.',
        'numeric' => 'The :field must be a number.',
        'integer' => 'The :field must be an integer.',
        'string' => 'The :field must be a string.',
        'boolean' => 'The :field must be true or false.',
        'url' => 'The :field must be a valid URL.',
        'unique' => 'The :field has already been taken.',
        'exists' => 'The selected :field is invalid.',
        'confirmed' => 'The :field confirmation does not match.',
        'in' => 'The selected :field is invalid.',
        'not_in' => 'The selected :field is invalid.',
        'regex' => 'The :field format is invalid.',
        'alpha' => 'The :field may only contain letters.',
        'alpha_num' => 'The :field may only contain letters and numbers.',
        'alpha_dash' => 'The :field may only contain letters, numbers, dashes and underscores.',
        'date' => 'The :field is not a valid date.',
        'date_format' => 'The :field does not match the format :format.',
        'before' => 'The :field must be a date before :date.',
        'after' => 'The :field must be a date after :date.',
        'file' => 'The :field must be a file.',
        'image' => 'The :field must be an image.',
        'mimes' => 'The :field must be a file of type: :values.',
        'size' => 'The :field must be :size kilobytes.',
        'between' => 'The :field must be between :min and :max characters.',
        'json' => 'The :field must be a valid JSON string.'
    ];

    /**
     * Validate data against rules
     */
    public function validate(array $data, array $rules, array $messages = []): array
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->messages = array_merge($this->defaultMessages, $messages);
        $this->errors = [];

        foreach ($this->rules as $field => $fieldRules) {
            $this->validateField($field, $fieldRules);
        }

        if (!empty($this->errors)) {
            throw new ValidationException($this->errors);
        }

        return array_intersect_key($this->data, $this->rules);
    }

    /**
     * Validate a single field
     */
    protected function validateField(string $field, string|array $rules): void
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        $value = $this->getValue($field);
        $isRequired = in_array('required', $rules);

        foreach ($rules as $rule) {
            $this->validateRule($field, $value, $rule, $isRequired);
        }
    }

    /**
     * Validate a single rule
     */
    protected function validateRule(string $field, mixed $value, string $rule, bool $isRequired): void
    {
        // Parse rule parameters
        [$ruleName, $parameters] = $this->parseRule($rule);

        // Skip validation if value is empty and field is not required
        if (!$isRequired && $this->isEmpty($value) && $ruleName !== 'required') {
            return;
        }

        $method = 'validate' . ucfirst($ruleName);

        if (method_exists($this, $method)) {
            $passes = $this->$method($field, $value, $parameters);
        } else {
            throw new \Exception("Validation rule '{$ruleName}' does not exist.");
        }

        if (!$passes) {
            $this->addError($field, $ruleName, $parameters);
        }
    }

    /**
     * Parse rule and extract parameters
     */
    protected function parseRule(string $rule): array
    {
        if (!str_contains($rule, ':')) {
            return [$rule, []];
        }

        [$ruleName, $parameterString] = explode(':', $rule, 2);
        $parameters = explode(',', $parameterString);

        return [$ruleName, $parameters];
    }

    /**
     * Get field value
     */
    protected function getValue(string $field): mixed
    {
        return $this->data[$field] ?? null;
    }

    /**
     * Check if value is empty
     */
    protected function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * Add validation error
     */
    protected function addError(string $field, string $rule, array $parameters = []): void
    {
        $message = $this->getMessage($field, $rule);
        $message = $this->replaceParameters($message, $field, $parameters);

        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }

        $this->errors[$field][] = $message;
    }

    /**
     * Get error message for rule
     */
    protected function getMessage(string $field, string $rule): string
    {
        $key = "{$field}.{$rule}";
        
        if (isset($this->messages[$key])) {
            return $this->messages[$key];
        }

        return $this->messages[$rule] ?? "The {$field} field is invalid.";
    }

    /**
     * Replace placeholders in error message
     */
    protected function replaceParameters(string $message, string $field, array $parameters): string
    {
        $message = str_replace(':field', $field, $message);

        foreach ($parameters as $index => $parameter) {
            $message = str_replace(":{$index}", $parameter, $message);
        }

        // Replace common parameter names
        if (isset($parameters[0])) {
            $replacements = [
                ':min' => $parameters[0],
                ':max' => $parameters[0],
                ':size' => $parameters[0],
                ':format' => $parameters[0],
                ':date' => $parameters[0],
                ':values' => implode(', ', $parameters)
            ];

            foreach ($replacements as $placeholder => $replacement) {
                $message = str_replace($placeholder, $replacement, $message);
            }
        }

        return $message;
    }

    // Validation Rules

    protected function validateRequired(string $field, mixed $value, array $parameters): bool
    {
        return !$this->isEmpty($value);
    }

    protected function validateEmail(string $field, mixed $value, array $parameters): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    protected function validateMin(string $field, mixed $value, array $parameters): bool
    {
        $min = (int) $parameters[0];
        
        if (is_numeric($value)) {
            return $value >= $min;
        }
        
        return strlen($value) >= $min;
    }

    protected function validateMax(string $field, mixed $value, array $parameters): bool
    {
        $max = (int) $parameters[0];
        
        if (is_numeric($value)) {
            return $value <= $max;
        }
        
        return strlen($value) <= $max;
    }

    protected function validateBetween(string $field, mixed $value, array $parameters): bool
    {
        $min = (int) $parameters[0];
        $max = (int) $parameters[1];
        
        if (is_numeric($value)) {
            return $value >= $min && $value <= $max;
        }
        
        $length = strlen($value);
        return $length >= $min && $length <= $max;
    }

    protected function validateNumeric(string $field, mixed $value, array $parameters): bool
    {
        return is_numeric($value);
    }

    protected function validateInteger(string $field, mixed $value, array $parameters): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    protected function validateString(string $field, mixed $value, array $parameters): bool
    {
        return is_string($value);
    }

    protected function validateBoolean(string $field, mixed $value, array $parameters): bool
    {
        return in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true);
    }

    protected function validateUrl(string $field, mixed $value, array $parameters): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    protected function validateConfirmed(string $field, mixed $value, array $parameters): bool
    {
        $confirmField = $field . '_confirmation';
        return isset($this->data[$confirmField]) && $value === $this->data[$confirmField];
    }

    protected function validateIn(string $field, mixed $value, array $parameters): bool
    {
        return in_array($value, $parameters);
    }

    protected function validateNotIn(string $field, mixed $value, array $parameters): bool
    {
        return !in_array($value, $parameters);
    }

    protected function validateRegex(string $field, mixed $value, array $parameters): bool
    {
        return preg_match($parameters[0], $value) > 0;
    }

    protected function validateAlpha(string $field, mixed $value, array $parameters): bool
    {
        return preg_match('/^[a-zA-Z]+$/', $value) > 0;
    }

    protected function validateAlphaNum(string $field, mixed $value, array $parameters): bool
    {
        return preg_match('/^[a-zA-Z0-9]+$/', $value) > 0;
    }

    protected function validateAlphaDash(string $field, mixed $value, array $parameters): bool
    {
        return preg_match('/^[a-zA-Z0-9_-]+$/', $value) > 0;
    }

    protected function validateDate(string $field, mixed $value, array $parameters): bool
    {
        return strtotime($value) !== false;
    }

    protected function validateDateFormat(string $field, mixed $value, array $parameters): bool
    {
        $format = $parameters[0];
        $date = \DateTime::createFromFormat($format, $value);
        return $date && $date->format($format) === $value;
    }

    protected function validateBefore(string $field, mixed $value, array $parameters): bool
    {
        $beforeDate = strtotime($parameters[0]);
        $valueDate = strtotime($value);
        
        return $valueDate !== false && $beforeDate !== false && $valueDate < $beforeDate;
    }

    protected function validateAfter(string $field, mixed $value, array $parameters): bool
    {
        $afterDate = strtotime($parameters[0]);
        $valueDate = strtotime($value);
        
        return $valueDate !== false && $afterDate !== false && $valueDate > $afterDate;
    }

    protected function validateJson(string $field, mixed $value, array $parameters): bool
    {
        if (!is_string($value)) {
            return false;
        }
        
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    protected function validateFile(string $field, mixed $value, array $parameters): bool
    {
        $file = Request::file($field);
        return $file !== null && $file['error'] === UPLOAD_ERR_OK;
    }

    protected function validateImage(string $field, mixed $value, array $parameters): bool
    {
        if (!$this->validateFile($field, $value, $parameters)) {
            return false;
        }
        
        $file = Request::file($field);
        $imageInfo = getimagesize($file['tmp_name']);
        
        return $imageInfo !== false;
    }

    protected function validateMimes(string $field, mixed $value, array $parameters): bool
    {
        if (!$this->validateFile($field, $value, $parameters)) {
            return false;
        }
        
        $file = Request::file($field);
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        return in_array($extension, $parameters);
    }

    protected function validateSize(string $field, mixed $value, array $parameters): bool
    {
        if (!$this->validateFile($field, $value, $parameters)) {
            return false;
        }
        
        $file = Request::file($field);
        $sizeInKb = $file['size'] / 1024;
        $expectedSize = (int) $parameters[0];
        
        return $sizeInKb == $expectedSize;
    }

    protected function validateUnique(string $field, mixed $value, array $parameters): bool
    {
        if (empty($parameters[0])) {
            return false;
        }
        
        $table = $parameters[0];
        $column = $parameters[1] ?? $field;
        $except = $parameters[2] ?? null;
        $exceptColumn = $parameters[3] ?? 'id';
        
        $db = Database::getInstance();
        
        $sql = "SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ?";
        $params = [$value];
        
        if ($except !== null) {
            $sql .= " AND {$exceptColumn} != ?";
            $params[] = $except;
        }
        
        $result = $db->select($sql, $params);
        
        return $result[0]['count'] == 0;
    }

    protected function validateExists(string $field, mixed $value, array $parameters): bool
    {
        if (empty($parameters[0])) {
            return false;
        }
        
        $table = $parameters[0];
        $column = $parameters[1] ?? $field;
        
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ?";
        $result = $db->select($sql, [$value]);
        
        return $result[0]['count'] > 0;
    }

    /**
     * Get all validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if validation has errors
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get errors for specific field
     */
    public function getErrorsFor(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Get first error for field
     */
    public function getFirstError(string $field): ?string
    {
        $errors = $this->getErrorsFor($field);
        return $errors[0] ?? null;
    }

    /**
     * Static validation method
     */
    public static function make(array $data, array $rules, array $messages = []): self
    {
        $validator = new self();
        $validator->validate($data, $rules, $messages);
        return $validator;
    }
}

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