<?php

namespace Lib;

use Database\Database;

class Validator
{
    private array $data;
    private array $rules;
    private array $messages;
    public array $errors = [];

    public function __construct(array $data, array $rules, array $messages = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->messages = $messages;
    }

    public function validate(): array
    {
        foreach ($this->rules as $field => $ruleList) {
            $rules = is_string($ruleList) ? explode('|', $ruleList) : $ruleList;
            $value = str_contains($field, '.') ? $this->dot($field) : ($this->data[$field] ?? null);
            foreach ($rules as $rule) {
                if ($rule === 'nullable' && ($value === null || $value === '')) {
                    break;
                }
                if (is_string($rule) && str_starts_with($rule, 'required_with:')) {
                    $dep = substr($rule, 14);
                    if (!empty($this->data[$dep]) && ($value === null || $value === '')) {
                        $this->add($field, 'required');
                    }
                    continue;
                }
                if ($rule === 'required' && ($value === null || $value === '')) {
                    $this->add($field, 'required');
                    break;
                }
                if ($rule === 'present' && !array_key_exists($field, $this->data)) {
                    $this->add($field, 'required');
                }
                if ($rule === 'string' && $value !== null && !is_string($value)) {
                    $this->add($field, 'string');
                }
                if ($rule === 'boolean' && $value !== null && !is_bool($value) && !in_array($value, [0, 1, '0', '1', true, false], true)) {
                    $this->add($field, 'boolean');
                }
                if ($rule === 'integer' && $value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->add($field, 'integer');
                }
                if (is_string($rule) && str_starts_with($rule, 'max:')) {
                    $max = (int) substr($rule, 4);
                    if (is_string($value) && mb_strlen($value) > $max) {
                        $this->add($field, 'max');
                    }
                    if (is_array($value) && count($value) > $max) {
                        $this->add($field, 'max');
                    }
                }
                if (is_string($rule) && str_starts_with($rule, 'min:')) {
                    $min = (int) substr($rule, 4);
                    if (is_array($value) && count($value) < $min) {
                        $this->add($field, 'min');
                    }
                }
                if ($rule === 'array' && $value !== null && !is_array($value)) {
                    $this->add($field, 'array');
                }
                if ($rule === 'distinct' && is_array($value) && count($value) !== count(array_unique($value))) {
                    $this->add($field, 'distinct');
                }
                if (is_string($rule) && str_starts_with($rule, 'in:')) {
                    $opts = explode(',', substr($rule, 3));
                    if ($value !== null && $value !== '' && !in_array((string) $value, $opts, true)) {
                        $this->add($field, 'in');
                    }
                }
                if (is_string($rule) && str_starts_with($rule, 'regex:')) {
                    $pattern = substr($rule, 6);
                    if ($value !== null && $value !== '' && !preg_match($pattern, (string) $value)) {
                        $this->add($field, 'regex');
                    }
                }
                if (is_string($rule) && str_starts_with($rule, 'same:')) {
                    $other = substr($rule, 5);
                    if ((string) $value !== (string) ($this->data[$other] ?? null)) {
                        $this->add($field, 'same');
                    }
                }
                if (is_string($rule) && str_starts_with($rule, 'date_format:')) {
                    $fmt = substr($rule, 12);
                    if ($value !== null && $value !== '' && !self::matchDate((string) $value, $fmt)) {
                        $this->add($field, 'date_format');
                    }
                }
                if (is_string($rule) && str_starts_with($rule, 'after_or_equal:')) {
                    $other = substr($rule, 15);
                    $ov = str_contains($other, '.')
                        ? $this->dot($other)
                        : ($this->data[$other] ?? null);
                    if ($value && $ov && strtotime((string) $value) < strtotime((string) $ov)) {
                        $this->add($field, 'after_or_equal');
                    }
                }
                if (is_string($rule) && str_starts_with($rule, 'not_in:')) {
                    $forbidden = explode(',', substr($rule, 7));
                    if (in_array((string) $value, $forbidden, true)) {
                        $this->add($field, 'not_in');
                    }
                }
                if (is_string($rule) && str_starts_with($rule, 'exists:')) {
                    $parts = explode(',', substr($rule, 7));
                    $table = $parts[0];
                    $column = $parts[1] ?? 'id';
                    if ($value !== null && $value !== '') {
                        $db = new Database();
                        $row = $db->select("SELECT 1 FROM {$table} WHERE {$column} = ? LIMIT 1", [$value])->fetch();
                        if (!$row) {
                            $this->add($field, 'exists');
                        }
                    }
                }
                if (is_string($rule) && str_starts_with($rule, 'unique:')) {
                    $parts = explode(',', substr($rule, 7));
                    $table = $parts[0];
                    $column = $parts[1] ?? $field;
                    $ignore = $parts[2] ?? null;
                    if ($value !== null && $value !== '') {
                        $db = new Database();
                        $sql = "SELECT 1 FROM {$table} WHERE {$column} = ?";
                        $bind = [$value];
                        if ($ignore !== null) {
                            $sql .= ' AND id != ?';
                            $bind[] = $ignore;
                        }
                        $sql .= ' LIMIT 1';
                        if ($db->select($sql, $bind)->fetch()) {
                            $this->add($field, 'unique');
                        }
                    }
                }
            }
        }
        if ($this->errors) {
            throw new ValidationException($this->errors);
        }
        return $this->onlyValidated();
    }

    private function onlyValidated(): array
    {
        $out = [];
        foreach (array_keys($this->rules) as $field) {
            if (str_contains($field, '.')) {
                continue;
            }
            if (array_key_exists($field, $this->data)) {
                $out[$field] = $this->data[$field];
            }
        }
        foreach ($this->rules as $field => $_) {
            if (str_contains($field, '.')) {
                [$root] = explode('.', $field, 2);
                if (isset($this->data[$root]) && !isset($out[$root])) {
                    $out[$root] = $this->data[$root];
                }
            }
        }
        return $out;
    }

    private function dot(string $path)
    {
        $segments = explode('.', $path);
        $cur = $this->data;
        foreach ($segments as $seg) {
            if (!is_array($cur) || !array_key_exists($seg, $cur)) {
                return null;
            }
            $cur = $cur[$seg];
        }
        return $cur;
    }

    private function add(string $field, string $rule): void
    {
        $key = $field . '.' . $rule;
        $this->errors[$field][] = $this->messages[$key] ?? $this->messages[$field . '.required'] ?? $this->messages[$field] ?? 'مقدار فیلد معتبر نیست.';
    }

    private static function matchDate(string $value, string $fmt): bool
    {
        if ($fmt === 'Y-m-d') {
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
        }
        return false;
    }
}

class ValidationException extends \Exception
{
    public function __construct(public array $errors)
    {
        parent::__construct('validation');
    }
}

function validator(array $data, array $rules, array $messages = []): Validator
{
    return new Validator($data, $rules, $messages);
}
