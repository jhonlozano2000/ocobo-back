<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * Helper de Validación de Entrada
 *
 * OWASP A03:2021 - Injection Prevention
 *
 * Proporciona métodos de validación-centralizada.
 */
class InputValidator
{
    /**
     * Campos que siempre deben ser sanitizados
     */
    private const ALWAYS_SANITIZE = ['search', 'query', 'q', 'filter', 'name', 'subject', 'title'];

    /**
     * Valida y sanitiza un array de entrada
     *
     * @param array $data
     * @param array $rules
     * @return array ['valid' => bool, 'data' => array, 'errors' => array]
     */
    public static function validate(array $data, array $rules): array
    {
        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return [
                'valid' => false,
                'data' => $data,
                'errors' => $validator->errors()->toArray(),
            ];
        }

        return [
            'valid' => true,
            'data' => $validator->validated(),
            'errors' => [],
        ];
    }

    /**
     * Sanitiza campos de texto comunes
     *
     * @param array $data
     * @return array
     */
    public static function sanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array($key, self::ALWAYS_SANITIZE) && is_string($value)) {
                $data[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
            }
        }

        return $data;
    }

    /**
     * Valida ID numérico
     *
     * @param mixed $id
     * @return bool
     */
    public static function isValidId($id): bool
    {
        if (!is_numeric($id) || $id <= 0) {
            return false;
        }

        return true;
    }

    /**
     * Valida formato de email
     *
     * @param string $email
     * @return bool
     */
    public static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Valida formato de URL
     *
     * @param string $url
     * @return bool
     */
    public static function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Valida y limpia IDs de entrada
     *
     * @param mixed $id
     * @param string $fieldName
     * @return array ['valid' => bool, 'id' => int|null, 'error' => string|null]
     */
    public static function validateId($id, string $fieldName = 'id'): array
    {
        if ($id === null || $id === '') {
            return [
                'valid' => false,
                'id' => null,
                'error' => "El campo {$fieldName} es requerido.",
            ];
        }

        if (!is_numeric($id)) {
            return [
                'valid' => false,
                'id' => null,
                'error' => "El campo {$fieldName} debe ser numérico.",
            ];
        }

        $intId = (int) $id;

        if ($intId <= 0) {
            return [
                'valid' => false,
                'id' => null,
                'error' => "El campo {$fieldName} debe ser mayor a 0.",
            ];
        }

        return [
            'valid' => true,
            'id' => $intId,
            'error' => null,
        ];
    }

    /**
     * Valida longitud de strings
     *
     * @param string $value
     * @param int $min
     * @param int $max
     * @param string $fieldName
     * @return array
     */
    public static function validateLength(string $value, int $min = 1, int $max = 255, string $fieldName = 'campo'): array
    {
        $length = strlen($value);

        if ($length < $min) {
            return [
                'valid' => false,
                'error' => "El campo {$fieldName} debe tener al menos {$min} caracteres.",
            ];
        }

        if ($length > $max) {
            return [
                'valid' => false,
                'error' => "El campo {$fieldName} no debe exceder {$max} caracteres.",
            ];
        }

        return [
            'valid' => true,
            'error' => null,
        ];
    }
}