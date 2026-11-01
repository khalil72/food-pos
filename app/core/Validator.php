<?php
declare(strict_types=1);

/**
 * Minimal rule-string validator: "required|numeric|min:0|max:100|email|in:a,b|maxlen:100|int|date"
 */
final class Validator
{
    private array $errors = [];

    public function __construct(private array $data)
    {
    }

    public static function make(array $data, array $rules): self
    {
        $v = new self($data);
        foreach ($rules as $field => [$label, $ruleString]) {
            $v->check($field, $label, $ruleString);
        }
        return $v;
    }

    public function check(string $field, string $label, string $ruleString): self
    {
        $value = $this->data[$field] ?? null;
        $value = is_string($value) ? trim($value) : $value;
        $empty = $value === null || $value === '' || $value === [];

        foreach (explode('|', $ruleString) as $rule) {
            [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
            if ($name === 'required') {
                if ($empty) {
                    return $this->fail($field, "$label is required.");
                }
                continue;
            }
            if ($empty) {
                continue; // optional field left blank
            }
            $error = match ($name) {
                'numeric' => is_numeric($value) ? null : "$label must be a number.",
                'int'     => filter_var($value, FILTER_VALIDATE_INT) !== false ? null : "$label must be a whole number.",
                'min'     => is_numeric($value) && (float)$value >= (float)$arg ? null : "$label must be at least $arg.",
                'max'     => is_numeric($value) && (float)$value <= (float)$arg ? null : "$label may not be greater than $arg.",
                'maxlen'  => mb_strlen((string)$value) <= (int)$arg ? null : "$label may not exceed $arg characters.",
                'minlen'  => mb_strlen((string)$value) >= (int)$arg ? null : "$label must be at least $arg characters.",
                'email'   => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : "$label must be a valid email address.",
                'in'      => in_array((string)$value, explode(',', (string)$arg), true) ? null : "$label has an invalid value.",
                'date'    => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$value) && strtotime((string)$value) ? null : "$label must be a valid date.",
                'color'   => preg_match('/^#[0-9a-fA-F]{6}$/', (string)$value) ? null : "$label must be a hex colour.",
                'alnum'   => preg_match('/^[A-Za-z0-9_.-]+$/', (string)$value) ? null : "$label may only contain letters, numbers, dots, dashes and underscores.",
                default   => null,
            };
            if ($error) {
                return $this->fail($field, $error);
            }
        }
        return $this;
    }

    public function addError(string $field, string $message): self
    {
        return $this->fail($field, $message);
    }

    private function fail(string $field, string $message): self
    {
        $this->errors[$field] ??= $message;
        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function first(): string
    {
        return (string)(array_values($this->errors)[0] ?? '');
    }
}
