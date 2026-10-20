<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Validation serveur. Règles : required, email, int, numeric, min:x, max:x, date, datetime, time, in:a,b, maxlen:n, bool.
 */
final class Validator
{
    public static function validate(array $data, array $rules, array $labels = []): array
    {
        $errors = [];
        $clean = [];
        foreach ($rules as $field => $ruleString) {
            $rulesList = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $value = $data[$field] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }
            $label = $labels[$field] ?? $field;
            $nullable = in_array('nullable', $rulesList, true);
            $empty = $value === null || $value === '' || $value === [];
            if ($empty) {
                if (in_array('required', $rulesList, true)) {
                    $errors[$field] = "Le champ « $label » est obligatoire.";
                } elseif (in_array('bool', $rulesList, true)) {
                    $clean[$field] = 0;
                } else {
                    $clean[$field] = null;
                }
                continue;
            }
            foreach ($rulesList as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $error = match ($name) {
                    'email' => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : "« $label » doit être une adresse e-mail valide.",
                    'int' => filter_var($value, FILTER_VALIDATE_INT) !== false ? null : "« $label » doit être un nombre entier.",
                    'numeric' => is_numeric(str_replace(',', '.', (string) $value)) ? null : "« $label » doit être un nombre.",
                    'min' => (float) str_replace(',', '.', (string) $value) >= (float) $arg ? null : "« $label » doit être supérieur ou égal à $arg.",
                    'max' => (float) str_replace(',', '.', (string) $value) <= (float) $arg ? null : "« $label » doit être inférieur ou égal à $arg.",
                    'maxlen' => mb_strlen((string) $value) <= (int) $arg ? null : "« $label » ne doit pas dépasser $arg caractères.",
                    'date' => self::isDate((string) $value, 'Y-m-d') ? null : "« $label » doit être une date valide.",
                    'datetime' => self::isDateTime((string) $value) ? null : "« $label » doit être une date et une heure valides.",
                    'time' => preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string) $value) ? null : "« $label » doit être une heure valide.",
                    'in' => in_array((string) $value, explode(',', (string) $arg), true) ? null : "« $label » contient une valeur non autorisée.",
                    default => null,
                };
                if ($error !== null) {
                    $errors[$field] = $error;
                    break;
                }
            }
            if (!isset($errors[$field])) {
                $clean[$field] = match (true) {
                    in_array('int', $rulesList, true) => (int) $value,
                    in_array('numeric', $rulesList, true) => (float) str_replace(',', '.', (string) $value),
                    in_array('bool', $rulesList, true) => in_array($value, [1, '1', 'on', true, 'true'], true) ? 1 : 0,
                    in_array('datetime', $rulesList, true) => str_replace('T', ' ', strlen((string) $value) === 16 ? $value . ':00' : (string) $value),
                    default => $value,
                };
            }
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        return $clean;
    }

    public static function isDate(string $value, string $format = 'Y-m-d'): bool
    {
        $d = \DateTime::createFromFormat('!' . $format, $value);
        return $d !== false && $d->format($format) === $value;
    }

    public static function isDateTime(string $value): bool
    {
        $value = str_replace('T', ' ', $value);
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i'] as $fmt) {
            if (self::isDate($value, $fmt)) {
                return true;
            }
        }
        return false;
    }
}
