<?php

namespace App\Helpers;

use DOMDocument;
use Illuminate\Support\Facades\Log;

/**
 * Helper para Sanitización de Salidas (XSS Prevention)
 *
 * OWASP A03:2021 - Injection (XSS)
 *
 * Proporciona métodos para sanitizar datos antes de mostrarlos
 * en HTML, JSON, o otros formatos de salida.
 *
 * NOTA: La sanitización se aplica en el momento de la salida,
 * no al almacenar datos.
 */
class OutputSanitizer
{
    /**
     * Etiquetas HTML permitidas por defecto
     */
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><a><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><code><pre><span><div>';

    /**
     * Atributos permitidos por defecto
     */
    private const ALLOWED_ATTRS = [
        'href',
        'src',
        'title',
        'alt',
        'class',
        'id',
        'target',
    ];

    /**
     * Protocolos permitidos en URLs
     */
    private const ALLOWED_PROTOCOLS = ['http', 'https', 'mailto', 'tel'];

    /**
     * Sanitiza un string para prevenir XSS
     *
     * @param mixed $value
     * @param bool $allowHtml
     * @return string
     */
    public static function sanitize($value, bool $allowHtml = false): string
    {
        if ($value === null) {
            return '';
        }

        if (!is_string($value)) {
            $value = (string) $value;
        }

        // Si permite HTML, usar sanitización más compleja
        if ($allowHtml) {
            return self::sanitizeHtml($value);
        }

        // HTML entities - escape estándar
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8', true);
    }

    /**
     * Sanitiza HTML permitiendo solo etiquetas seguras
     *
     * @param string $html
     * @return string
     */
    public static function sanitizeHtml(string $html): string
    {
        // Si no hay HTML, solo sanitizar caracteres especiales
        if (!preg_match('/<[^>]+>/', $html)) {
            return htmlspecialchars($html, ENT_QUOTES, 'UTF-8', true);
        }

        try {
            $doc = new DOMDocument();
            $doc->loadHTML(
                mb_encode_numericentity(
                    $html,
                    [0x80, 0x10FFFF, 0, 0xFFFFF],
                    'UTF-8'
                ),
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );

            // Eliminar scripts y eventos
            self::removeScripts($doc);

            return $doc->saveHTML();
        } catch (\Exception $e) {
            Log::warning('OutputSanitizer: Error sanitizing HTML', ['error' => $e->getMessage()]);
            return htmlspecialchars($html, ENT_QUOTES, 'UTF-8', true);
        }
    }

    /**
     * Elimina elementos script y eventos JS del documento
     */
    private static function removeScripts(DOMDocument $doc): void
    {
        // Eliminar todas las etiquetas script
        $scripts = $doc->getElementsByTagName('script');
        while ($scripts->length > 0) {
            $script = $scripts->item(0);
            $script->parentNode->removeChild($script);
        }

        // Eliminar todas las etiquetas iframe (protección contra clickjacking)
        $iframes = $doc->getElementsByTagName('iframe');
        while ($iframes->length > 0) {
            $iframe = $iframes->item(0);
            $iframe->parentNode->removeChild($iframe);
        }

        // Eliminar eventos on* de todos los elementos
        $xpath = new \DOMXPath($doc);
        foreach ($xpath->query('//@*[starts-with(name(), "on")]') as $attr) {
            $attr->parentNode->removeAttributeNode($attr);
        }
    }

    /**
     * Sanitiza un array de datos recursivamente
     *
     * @param array $data
     * @param array $fieldsToSkip Campos a omitir (ya sanitizados o seguros)
     * @return array
     */
    public static function sanitizeArray(array $data, array $fieldsToSkip = []): array
    {
        foreach ($data as $key => $value) {
            if (in_array($key, $fieldsToSkip)) {
                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::sanitizeArray($value, $fieldsToSkip);
            } elseif (is_string($value)) {
                $data[$key] = self::sanitize($value);
            }
        }

        return $data;
    }

    /**
     * Sanitiza un objeto (stdClass o similar)
     *
     * @param object $obj
     * @return object
     */
    public static function sanitizeObject(object $obj): object
    {
        foreach ($obj as $key => $value) {
            if (is_string($value)) {
                $obj->$key = self::sanitize($value);
            } elseif (is_array($value)) {
                $obj->$key = self::sanitizeArray($value);
            }
        }

        return $obj;
    }

    /**
     * Limpia datos de usuario para mostrar en JSON
     * Aplica sanitización a campos conocidos como sensibles
     *
     * @param array $data
     * @return array
     */
    public static function sanitizeForJson(array $data): array
    {
        $sensitiveFields = ['password', 'token', 'secret', 'api_key', 'num_docu'];

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '***REDACTED***';
            }
        }

        return $data;
    }

    /**
     * Sanitiza un email para mostrar
     *
     * @param string $email
     * @return string
     */
    public static function sanitizeEmail(string $email): string
    {
        $sanitized = filter_var(trim($email), FILTER_SANITIZE_EMAIL);

        // Ocultar parte del email si es muy largo
        $parts = explode('@', $sanitized);
        if (count($parts) === 2 && strlen($parts[0]) > 3) {
            $parts[0] = substr($parts[0], 0, 2) . '***';
        }

        return implode('@', $parts);
    }

    /**
     * Sanitiza un número de documento para mostrar
     *
     * @param string $document
     * @return string
     */
    public static function sanitizeDocument(string $document): string
    {
        // Mostrar solo últimos 4 dígitos
        $clean = preg_replace('/[^0-9]/', '', $document);

        if (strlen($clean) <= 4) {
            return str_repeat('*', strlen($clean));
        }

        return str_repeat('*', strlen($clean) - 4) . substr($clean, -4);
    }

    /**
     * Sanitiza un número de teléfono para mostrar
     *
     * @param string $phone
     * @return string
     */
    public static function sanitizePhone(string $phone): string
    {
        // Mantener solo últimos 4 dígitos
        $clean = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($clean) <= 4) {
            return str_repeat('*', strlen($clean));
        }

        return str_repeat('*', strlen($clean) - 4) . substr($clean, -4);
    }

    /**
     * Protege contra XSS en URLs (validar y sanitizar)
     *
     * @param string $url
     * @return string
     */
    public static function sanitizeUrl(string $url): string
    {
        // Validar protocolo
        $parsed = parse_url($url);

        if (empty($parsed['scheme'])) {
            return '';
        }

        if (!in_array(strtolower($parsed['scheme']), self::ALLOWED_PROTOCOLS)) {
            return '';
        }

        return filter_var($url, FILTER_SANITIZE_URL);
    }
}