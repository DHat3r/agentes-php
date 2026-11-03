<?php

declare(strict_types=1);

namespace Agentes\Core\Tools;

/**
 * Validador mínimo de JSON Schema: cubre lo que usan nuestras herramientas
 * (type, required, enum, minimum, maximum, minLength, maxLength).
 *
 * No usamos una librería completa a propósito: así el núcleo queda pequeño y
 * cada regla se puede leer en este archivo.
 */
final class SchemaValidator
{
    /**
     * @return list<string> Errores en español; vacío si los argumentos son válidos.
     */
    public function validate(array $schema, array $arguments): array
    {
        $errors = [];

        foreach ($schema['required'] ?? [] as $field) {
            if (! array_key_exists($field, $arguments)) {
                $errors[] = "Falta el argumento obligatorio '{$field}'.";
            }
        }

        foreach ($schema['properties'] ?? [] as $field => $rules) {
            if (array_key_exists($field, $arguments)) {
                array_push($errors, ...$this->validateValue($field, $arguments[$field], $rules));
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function validateValue(string $field, mixed $value, array $rules): array
    {
        $type = $rules['type'] ?? null;
        if ($type !== null && ! $this->matchesType($value, $type)) {
            return ["'{$field}' debe ser de tipo {$type}."];
        }

        $errors = [];
        if (isset($rules['enum']) && ! in_array($value, $rules['enum'], true)) {
            $errors[] = "'{$field}' debe ser uno de: ".implode(', ', $rules['enum']).'.';
        }
        if (isset($rules['minimum']) && $value < $rules['minimum']) {
            $errors[] = "'{$field}' debe ser mayor o igual a {$rules['minimum']}.";
        }
        if (isset($rules['maximum']) && $value > $rules['maximum']) {
            $errors[] = "'{$field}' debe ser menor o igual a {$rules['maximum']}.";
        }
        if (isset($rules['minLength']) && is_string($value) && mb_strlen(trim($value)) < $rules['minLength']) {
            $errors[] = "'{$field}' debe tener al menos {$rules['minLength']} caracteres.";
        }
        if (isset($rules['maxLength']) && is_string($value) && mb_strlen($value) > $rules['maxLength']) {
            $errors[] = "'{$field}' debe tener como máximo {$rules['maxLength']} caracteres.";
        }

        return $errors;
    }

    private function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'array' => is_array($value) && array_is_list($value),
            'object' => is_array($value),
            default => true,
        };
    }
}
