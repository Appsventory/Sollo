<?php

namespace Core\Foundation;

use Core\Foundation\Database\Database;
use Core\Foundation\Http\Request;

class Validator
{
    protected array $data = [];
    protected array $rules = [];
    protected array $messages = [];
    protected array $errors = [];
    /** Rules of the field being validated (needed to know if min/max/between mean numbers or length). */
    protected array $currentRules = [];

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
        'json' => 'The :field must be a valid JSON string.',
        'max_numeric' => 'The :field must not be greater than :max.',
        'min_numeric' => 'The :field must be at least :min.',
        'between_numeric' => 'The :field must be between :min and :max.',
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
     * Validate a single field. Rules are a "a|b:1" string or an array
     * (use the array form when a rule parameter contains "|", e.g. regex).
     */
    protected function validateField(string $field, string|array $rules): void
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        $this->currentRules = array_map(
            fn($r) => is_string($r) ? strtolower(explode(':', $r, 2)[0]) : '',
            $rules
        );

        $value = $this->getValue($field);
        $isRequired = in_array('required', $this->currentRules, true);

        foreach ($rules as $rule) {
            $this->validateRule($field, $value, (string) $rule, $isRequired);
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
     * Parse "name:p1,p2" into [name, [p1, p2]]. The regex rule keeps its whole
     * parameter (a pattern may contain commas).
     */
    protected function parseRule(string $rule): array
    {
        if (!str_contains($rule, ':')) {
            return [$rule, []];
        }

        [$ruleName, $parameterString] = explode(':', $rule, 2);

        if ($ruleName === 'regex' || $ruleName === 'not_regex') {
            return [$ruleName, [$parameterString]];
        }

        return [$ruleName, explode(',', $parameterString)];
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

    protected function addError(string $field, string $rule, array $parameters = []): void
    {
        $message = $this->getMessage($field, $rule);
        $message = $this->replaceParameters($message, $field, $rule, $parameters);

        $this->errors[$field][] = $message;
    }

    protected function getMessage(string $field, string $rule): string
    {
        $key = "{$field}.{$rule}";

        if (isset($this->messages[$key])) {
            return $this->messages[$key];
        }

        $userDefined = $this->messages[$rule] ?? null;
        $isDefault = $userDefined === ($this->defaultMessages[$rule] ?? null);

        // min/max/between measure numbers when the field also has numeric/integer
        if ($isDefault && in_array($rule, ['min', 'max', 'between'], true) && $this->isNumericContext()) {
            return $this->messages["{$rule}_numeric"];
        }

        return $userDefined ?? "The {$field} field is invalid.";
    }

    protected function replaceParameters(string $message, string $field, string $rule, array $parameters): string
    {
        $map = [':field' => $field];

        foreach ($parameters as $index => $parameter) {
            $map[":{$index}"] = $parameter;
        }

        switch ($rule) {
            case 'min':
                $map[':min'] = $parameters[0] ?? '';
                break;
            case 'max':
                $map[':max'] = $parameters[0] ?? '';
                break;
            case 'between':
                $map[':min'] = $parameters[0] ?? '';
                $map[':max'] = $parameters[1] ?? '';
                break;
            case 'size':
                $map[':size'] = $parameters[0] ?? '';
                break;
            case 'date_format':
                $map[':format'] = $parameters[0] ?? '';
                break;
            case 'before':
            case 'after':
                $map[':date'] = $parameters[0] ?? '';
                break;
            case 'mimes':
                $map[':values'] = implode(', ', $parameters);
                break;
        }

        return strtr($message, $map);
    }

    /**
     * True when the current field is validated as a number (has numeric/integer).
     */
    protected function isNumericContext(): bool
    {
        return (bool) array_intersect($this->currentRules, ['numeric', 'integer']);
    }

    /**
     * Size of a value for min/max/between: the number itself for numeric
     * fields, the item count for arrays, otherwise the UTF-8 character count.
     */
    protected function sizeOf(mixed $value): int|float
    {
        // Uploaded file ($_FILES entry): size in kilobytes
        if (is_array($value) && isset($value['tmp_name'], $value['size'])) {
            return $value['size'] / 1024;
        }

        if (is_array($value)) {
            return count($value);
        }

        if ($this->isNumericContext() && is_numeric($value)) {
            return $value + 0;
        }

        $string = (string) $value;
        return function_exists('mb_strlen') ? mb_strlen($string, 'UTF-8') : strlen($string);
    }

    /**
     * Table/column names come from rule strings: allow identifiers only.
     */
    protected function identifier(string $name): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $name)) {
            throw new \InvalidArgumentException("Invalid table/column name '{$name}' in validation rule.");
        }

        return $name;
    }

    // Validation Rules

    protected function validateRequired(string $field, mixed $value, array $parameters): bool
    {
        return !$this->isEmpty($value);
    }

    protected function validateEmail(string $field, mixed $value, array $parameters): bool
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    protected function validateMin(string $field, mixed $value, array $parameters): bool
    {
        return $this->sizeOf($value) >= (float) $parameters[0];
    }

    protected function validateMax(string $field, mixed $value, array $parameters): bool
    {
        return $this->sizeOf($value) <= (float) $parameters[0];
    }

    protected function validateBetween(string $field, mixed $value, array $parameters): bool
    {
        $size = $this->sizeOf($value);
        return $size >= (float) $parameters[0] && $size <= (float) $parameters[1];
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
        return is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    protected function validateConfirmed(string $field, mixed $value, array $parameters): bool
    {
        $confirmField = $field . '_confirmation';
        return isset($this->data[$confirmField]) && $value === $this->data[$confirmField];
    }

    protected function validateIn(string $field, mixed $value, array $parameters): bool
    {
        return is_scalar($value) && in_array((string) $value, $parameters, true);
    }

    protected function validateNotIn(string $field, mixed $value, array $parameters): bool
    {
        return !is_scalar($value) || !in_array((string) $value, $parameters, true);
    }

    protected function validateRegex(string $field, mixed $value, array $parameters): bool
    {
        return is_scalar($value) && preg_match($parameters[0], (string) $value) === 1;
    }

    protected function validateAlpha(string $field, mixed $value, array $parameters): bool
    {
        return is_scalar($value) && preg_match('/^[a-zA-Z]+$/', (string) $value) === 1;
    }

    protected function validateAlphaNum(string $field, mixed $value, array $parameters): bool
    {
        return is_scalar($value) && preg_match('/^[a-zA-Z0-9]+$/', (string) $value) === 1;
    }

    protected function validateAlphaDash(string $field, mixed $value, array $parameters): bool
    {
        return is_scalar($value) && preg_match('/^[a-zA-Z0-9_-]+$/', (string) $value) === 1;
    }

    protected function validateDate(string $field, mixed $value, array $parameters): bool
    {
        return is_string($value) && strtotime($value) !== false;
    }

    protected function validateDateFormat(string $field, mixed $value, array $parameters): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $date = \DateTime::createFromFormat($parameters[0], $value);
        return $date && $date->format($parameters[0]) === $value;
    }

    protected function validateBefore(string $field, mixed $value, array $parameters): bool
    {
        $before = strtotime($parameters[0]);
        $valueDate = is_string($value) ? strtotime($value) : false;

        return $valueDate !== false && $before !== false && $valueDate < $before;
    }

    protected function validateAfter(string $field, mixed $value, array $parameters): bool
    {
        $after = strtotime($parameters[0]);
        $valueDate = is_string($value) ? strtotime($value) : false;

        return $valueDate !== false && $after !== false && $valueDate > $after;
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

        $table = $this->identifier($parameters[0]);
        $column = $this->identifier($parameters[1] ?? $field);
        $except = $parameters[2] ?? null;
        $exceptColumn = $this->identifier($parameters[3] ?? 'id');

        $sql = "SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ?";
        $params = [$value];

        if ($except !== null && $except !== '') {
            $sql .= " AND {$exceptColumn} != ?";
            $params[] = $except;
        }

        $result = Database::getInstance()->select($sql, $params);

        return (int) $result[0]['count'] === 0;
    }

    protected function validateExists(string $field, mixed $value, array $parameters): bool
    {
        if (empty($parameters[0])) {
            return false;
        }

        $table = $this->identifier($parameters[0]);
        $column = $this->identifier($parameters[1] ?? $field);

        $result = Database::getInstance()->select("SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ?", [$value]);

        return (int) $result[0]['count'] > 0;
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
