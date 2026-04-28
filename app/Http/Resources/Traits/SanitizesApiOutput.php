<?php

namespace App\Http\Resources\Traits;

/**
 * Trait para sanitización automática de recursos API
 *
 * OWASP A03:2021 - XSS Prevention
 *
 * Aplica sanitización automática a todos los campos de texto
 * antes de transformar el recurso a JSON.
 */
trait SanitizesApiOutput
{
    /**
     * Campos que contienen HTML y deben permitir etiquetas
     */
    protected array $allowHtml = [];

    /**
     * Campos sensibles que nunca deben mostrarse
     */
    protected array $sensitiveFields = [
        'password',
        'password_confirmation',
        'token',
        'api_key',
        'secret',
    ];

    /**
     * Campos que deben ser enmascarados parcialmente
     */
    protected array $maskedFields = [
        'num_docu' => 'last_4',
        'email' => 'mask',
        'telefono' => 'last_4',
        'movil' => 'last_4',
    ];

    /**
     * Sanitiza el array antes de retornarlo
     *
     * @param array $data
     * @return array
     */
    protected function sanitizeOutput(array $data): array
    {
        // Eliminar campos sensibles
        $data = $this->removeSensitiveFields($data);

        // Enmascarar campos parcialmente
        $data = $this->maskSensitiveData($data);

        // Sanitizar todos los strings contra XSS
        $data = $this->sanitizeStrings($data);

        return $data;
    }

    /**
     * Elimina campos sensibles del array
     *
     * @param array $data
     * @return array
     */
    private function removeSensitiveFields(array $data): array
    {
        foreach ($this->sensitiveFields as $field) {
            unset($data[$field]);
        }

        return $data;
    }

    /**
     * Enmaschera datos sensibles parcialmente
     *
     * @param array $data
     * @return array
     */
    private function maskSensitiveData(array $data): array
    {
        foreach ($this->maskedFields as $field => $method) {
            if (!isset($data[$field])) {
                continue;
            }

            $data[$field] = match ($method) {
                'last_4' => $this->maskLastChars($data[$field], 4),
                'mask' => $this->maskEmail($data[$field]),
                default => '***',
            };
        }

        return $data;
    }

    /**
     * Enmaschera los últimos N caracteres
     */
    private function maskLastChars(string $value, int $visible = 4): string
    {
        if (strlen($value) <= $visible) {
            return str_repeat('*', strlen($value));
        }

        $masked = strlen($value) - $visible;
        return str_repeat('*', $masked) . substr($value, -$visible);
    }

    /**
     * Enmaschera email
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***@***';
        }

        $local = $parts[0];
        $domain = $parts[1];

        if (strlen($local) > 2) {
            $local = substr($local, 0, 2) . '***';
        } else {
            $local = '**' . $local;
        }

        return "{$local}@{$domain}";
    }

    /**
     * Sanitiza todos los strings contra XSS
     *
     * @param array $data
     * @return array
     */
    private function sanitizeStrings(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // Algunos campos permiten HTML
                if (in_array($key, $this->allowHtml)) {
                    $data[$key] = $this->sanitizeHtml($value);
                } else {
                    $data[$key] = $this->sanitizeString($value);
                }
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitizeStrings($value);
            }
        }

        return $data;
    }

    /**
     * Sanitiza un string simple
     */
    private function sanitizeString(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8', true);
    }

    /**
     * Sanitiza HTML permitiendo etiquetas básicas
     */
    private function sanitizeHtml(string $html): string
    {
        // Strip_tags con allowable_tags
        $allowed = '<p><br><strong><b><em><i><u><a><ul><ol><li><h1><h2><h3><h4><h5><h6><span><div>';
        $clean = strip_tags($html, $allowed);

        // Eliminar atributos on* (eventos JS)
        $clean = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $clean);

        return $clean;
    }
}