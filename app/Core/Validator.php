<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    private array $errors = [];

    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];

        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;
            $ruleList = is_array($ruleString) ? $ruleString : explode('|', $ruleString);

            foreach ($ruleList as $rule) {
                $params = [];
                if (str_contains($rule, ':')) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }

                $this->applyRule($field, $value, $rule, $params, $data);
            }
        }

        return $this->errors === [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    private function applyRule(string $field, mixed $value, string $rule, array $params, array $data): void
    {
        $label = str_replace('_', ' ', $field);

        switch ($rule) {
            case 'required':
                if ($value === null || $value === '') {
                    $this->errors[$field][] = ucfirst($label) . ' wajib diisi.';
                }
                break;
            case 'email':
                if ($value !== null && $value !== '' && !filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$field][] = 'Format email tidak valid.';
                }
                break;
            case 'min':
                $min = (int) ($params[0] ?? 0);
                if (is_string($value) && strlen($value) < $min) {
                    $this->errors[$field][] = ucfirst($label) . " minimal {$min} karakter.";
                }
                break;
            case 'max':
                $max = (int) ($params[0] ?? 0);
                if (is_string($value) && strlen($value) > $max) {
                    $this->errors[$field][] = ucfirst($label) . " maksimal {$max} karakter.";
                }
                break;
            case 'in':
                if ($value !== null && $value !== '' && !in_array($value, $params, true)) {
                    $this->errors[$field][] = ucfirst($label) . ' tidak valid.';
                }
                break;
            case 'regex':
                $pattern = $params[0] ?? '';
                if ($value !== null && $value !== '' && $pattern !== '' && !preg_match($pattern, (string) $value)) {
                    $this->errors[$field][] = ucfirst($label) . ' format tidak valid.';
                }
                break;
            case 'confirmed':
                $confirm = $data[$field . '_confirmation'] ?? null;
                if ($value !== $confirm) {
                    $this->errors[$field][] = 'Konfirmasi tidak cocok.';
                }
                break;
        }
    }
}
