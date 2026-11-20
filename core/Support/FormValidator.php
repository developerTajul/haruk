<?php

declare(strict_types=1);

namespace DevlinkStudios\StarterPlugin\Support;

/**
 * Reusable request validation and error bag manager for admin forms, AJAX, and REST endpoints.
 */
class FormValidator
{
    /**
     * @var array<string, list<string>>
     */
    private array $errors = [];

    /**
     * Validate data against specified rules.
     *
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $rules
     * @return bool
     */
    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];

        foreach ($rules as $field => $fieldRules) {
            $ruleList = is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules);
            $value = $data[$field] ?? null;

            foreach ($ruleList as $rule) {
                $this->validateRule($field, $value, trim($rule));
            }
        }

        return empty($this->errors);
    }

    /**
     * Get all validation errors.
     *
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Check if a field has any errors.
     *
     * @param string $field
     * @return bool
     */
    public function hasError(string $field): bool
    {
        return !empty($this->errors[$field]);
    }

    /**
     * Get the first error message for a given field.
     *
     * @param string $field
     * @return string|null
     */
    public function firstError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Validate a single rule against a field value.
     *
     * @param string $field
     * @param mixed $value
     * @param string $rule
     * @return void
     */
    private function validateRule(string $field, mixed $value, string $rule): void
    {
        if ($rule === 'required') {
            if ($this->isEmpty($value)) {
                $this->addError($field, sprintf('The %s field is required.', $field));
            }
            return;
        }

        // If field is optional and empty, skip further rule validations
        if ($this->isEmpty($value)) {
            return;
        }

        if ($rule === 'email') {
            if (!is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                $this->addError($field, sprintf('The %s field must be a valid email address.', $field));
            }
            return;
        }

        if ($rule === 'numeric') {
            if (!is_numeric($value)) {
                $this->addError($field, sprintf('The %s field must be numeric.', $field));
            }
            return;
        }

        if (str_starts_with($rule, 'max:')) {
            $max = (int) substr($rule, 4);
            if (is_string($value) && mb_strlen($value) > $max) {
                $this->addError($field, sprintf('The %s field must not exceed %d characters.', $field, $max));
            } elseif (is_numeric($value) && (float) $value > $max) {
                $this->addError($field, sprintf('The %s field must not be greater than %d.', $field, $max));
            } elseif (is_array($value) && count($value) > $max) {
                $this->addError($field, sprintf('The %s field must not contain more than %d items.', $field, $max));
            }
            return;
        }
    }

    /**
     * Determine whether a value counts as empty for validation purposes.
     *
     * Whitespace-only strings are treated as empty so that `required` cannot be
     * satisfied with padding characters.
     *
     * @param mixed $value Value under validation.
     * @return bool
     */
    private function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return $value === [];
        }

        return false;
    }

    /**
     * Add an error message for a field.
     *
     * @param string $field
     * @param string $message
     * @return void
     */
    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }
}
