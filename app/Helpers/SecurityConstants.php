<?php

namespace App\Helpers;

/**
 * Constantes de Seguridad
 *
 * Valores de configuración de seguridad centralizados.
 */
class SecurityConstants
{
    // Rate Limiting
    public const RATE_LIMIT_API = 60;
    public const RATE_LIMIT_LOGIN = 5;
    public const RATE_LIMIT_REGISTER = 3;
    public const RATE_LIMIT_RADICACION = 30;
    public const RATE_LIMIT_UPLOADS = 10;
    public const RATE_LIMIT_SEARCH = 30;
    public const RATE_LIMIT_FIRMA = 5;
    public const RATE_LIMIT_TERCEROS = 20;
    public const RATE_LIMIT_CONFIG = 30;

    // Session
    public const SESSION_LIFETIME_MINUTES = 480; // 8 hours
    public const SESSION_COOKIE_NAME = 'ocobo_session';

    // Password Policy
    public const PASSWORD_MIN_LENGTH = 8;
    public const PASSWORD_MAX_LENGTH = 128;
    public const PASSWORD_REQUIRE_UPPERCASE = true;
    public const PASSWORD_REQUIRE_LOWERCASE = true;
    public const PASSWORD_REQUIRE_NUMBER = true;
    public const PASSWORD_REQUIRE_SPECIAL = true;

    // File Upload
    public const MAX_UPLOAD_SIZE = 52428800; // 50MB
    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    // API Response
    public const DEFAULT_PER_PAGE = 15;
    public const MAX_PER_PAGE = 100;

    // Headers
    public const SECURITY_HEADERS = [
        'X-XSS-Protection' => '1; mode=block',
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];

    // PII Fields
    public const PII_FIELDS = [
        'num_docu',
        'num_docu_nit',
        'email',
        'telefono',
        'movil',
        'direccion',
    ];

    // Sensitive Response Fields
    public const SENSITIVE_FIELDS = [
        'password',
        'password_hash',
        'password_confirmation',
        'token',
        'api_key',
        'secret',
    ];

    // Audit Retention
    public const AUDIT_RETENTION_DAYS = 365;

    // Cache
    public const CACHE_PREFIX = 'ocobo_';
}