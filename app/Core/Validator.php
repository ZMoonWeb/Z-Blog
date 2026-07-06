<?php

declare(strict_types=1);

namespace App\Core;

class Validator
{
    private array $errors = [];

    public function required(string $field, mixed $value, string $message): self
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === null || $value === '') {
            $this->errors[$field] = $message;
        }

        return $this;
    }

    public function max(string $field, mixed $value, int $max, string $message): self
    {
        if (is_string($value) && mb_strlen($value) > $max) {
            $this->errors[$field] = $message;
        }

        return $this;
    }

    public function in(string $field, mixed $value, array $allowed, string $message): self
    {
        if (!in_array($value, $allowed, true)) {
            $this->errors[$field] = $message;
        }

        return $this;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): string
    {
        return reset($this->errors) ?: '';
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }
}
