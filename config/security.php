<?php

/**
 * Configuración de Seguridad
 *
 * OWASP A01-A10:2021
 * ISO 27001
 *
 * Este archivo centraliza las configuraciones de seguridad de la aplicación.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Configuración de Request Size
    |--------------------------------------------------------------------------
    |
    | Tamaños máximos permitidos para diferentes tipos de requests.
    |
    */
    'max_request_size' => env('SECURITY_MAX_REQUEST_SIZE', 10485760), // 10MB

    'max_upload_size' => env('SECURITY_MAX_UPLOAD_SIZE', 52428800), // 50MB

    /*
    |--------------------------------------------------------------------------
    | Validación de URLs (SSRF Protection)
    |--------------------------------------------------------------------------
    |
    | Habilitar validación de URLs antes de realizar solicitudes HTTP.
    |
    */
    'validate_urls' => env('SECURITY_VALIDATE_URLS', true),

    'allowed_external_hosts' => explode(',', env('SECURITY_ALLOWED_HOSTS', '')),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configuración global de rate limiting.
    |
    */
    'rate_limit_enabled' => env('SECURITY_RATE_LIMIT', true),

    'rate_limit_cache' => env('RATE_LIMIT_CACHE', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Encryption
    |--------------------------------------------------------------------------
    |
    | Configuración de encriptación de datos sensibles.
    |
    */
    'encrypt_pii' => env('SECURITY_ENCRYPT_PII', true),

    'pii_fields' => [
        'num_docu',
        'num_docu_nit',
        'email',
        'telefono',
        'movil',
        'direccion',
        'password',
    ],

    /*
    |--------------------------------------------------------------------------
    | Auditoría
    |--------------------------------------------------------------------------
    |
    | Configuración de logging de auditoría.
    |
    */
    'audit_enabled' => env('SECURITY_AUDIT', true),

    'audit_retention_days' => env('SECURITY_AUDIT_RETENTION', 365),

    'audit_sensitive_fields' => [
        'password',
        'password_confirmation',
        'token',
        'secret',
        'api_key',
        'num_docu',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sesión
    |--------------------------------------------------------------------------
    |
    | Configuración de seguridad de sesión.
    |
    */
    'session_lifetime' => env('SECURITY_SESSION_LIFETIME', 480), // 8 horas

    'session_encrypt' => env('SECURITY_SESSION_ENCRYPT', true),

    'session_regenerate_on_login' => true,

    /*
    |--------------------------------------------------------------------------
    | CORS
    |--------------------------------------------------------------------------
    |
    | Configuración de CORS.
    |
    */
    'cors_enabled' => true,

    'cors_max_age' => 86400, // 24 horas

    /*
    |--------------------------------------------------------------------------
    | Headers de Seguridad
    |--------------------------------------------------------------------------
    |
    | Headers aplicados automáticamente.
    |
    */
    'security_headers' => [
        'X-XSS-Protection' => '1; mode=block',
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Policy
    |--------------------------------------------------------------------------
    |
    | Requisitos mínimos de contraseña.
    |
    */
    'password_min_length' => 8,

    'password_require_uppercase' => true,

    'password_require_lowercase' => true,

    'password_require_number' => true,

    'password_require_special' => true,

    'password_max_length' => 128,

    'password_common_list' => [
        'password',
        'password123',
        '123456',
        '12345678',
        'qwerty',
    ],
];