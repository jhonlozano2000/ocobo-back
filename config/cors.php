<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración de CORS para OCOBO.
    |
    | OWASP A05:2021 - Security Misconfiguration
    | Los orígenes permitidos se configuran vía variables de entorno.
    |
    | Para producción: usar dominios específicos, NO IPs.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'firma-electronica/*', 'sanctum/csrf-cookie', 'login', 'logout', 'test-session'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    /*
    |--------------------------------------------------------------------------
    | Orígenes Permitidos
    |--------------------------------------------------------------------------
    |
    | IMPORTANTE: En producción usar SOLO dominios verificados.
    | NO usar IPs hardcoded en producción.
    |
    | Formato entorno: comma-separated domains
    | CORS_ALLOWED_ORIGINS=http://frontend.test,https://ocobo.com
    |
    */
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://localhost:5173')),

    /*
    |--------------------------------------------------------------------------
    | Patrones de Orígenes (para wildcard subdomains)
    |--------------------------------------------------------------------------
    |
    | Ejemplo: *.example.com permite sub.example.com, app.example.com
    |
    */
    'allowed_origins_patterns' => explode(',', env('CORS_ALLOWED_ORIGINS_PATTERNS', '')),

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-CSRF-TOKEN',
        'X-XSRF-TOKEN',
        'Cookie',
        'Origin',
    ],

    'exposed_headers' => [
        'X-Request-ID',
    ],

    /*
    |--------------------------------------------------------------------------
    | Max Age
    |--------------------------------------------------------------------------
    |
    | Tiempo en segundos que el navegador cachea la respuesta preflight OPTIONS.
    | 86400 = 24 horas
    |
    */
    'max_age' => env('CORS_MAX_AGE', 86400),

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    |
    | Requiere que Access-Control-Allow-Credentials sea true.
    | Solo usar con orígenes específicos (no con *).
    |
    */
    'supports_credentials' => true,

];
