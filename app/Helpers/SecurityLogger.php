<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

/**
 * Helper de Logging de Seguridad
 *
 * Proporciona métodos simplificados para logging de seguridad.
 */
class SecurityLogger
{
    /**
     * Log de intento de acceso
     */
    public static function loginAttempt(string $email, bool $success, string $reason = ''): void
    {
        Log::channel('audit')->info('login_attempt', [
            'email' => $email,
            'success' => $success,
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'reason' => $reason,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log de acceso a recurso
     */
    public static function resourceAccess(string $resourceType, int $resourceId, string $action = 'read'): void
    {
        Log::channel('audit')->info('resource_access', [
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'action' => $action,
            'user_id' => Auth::id(),
            'ip' => Request::ip(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log de intento de intrusión
     */
    public static function intrusionAttempt(string $type, string $description, array $context = []): void
    {
        Log::channel('audit')->warning('intrusion_attempt', [
            'type' => $type,
            'description' => $description,
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'context' => $context,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log de cambio de configuración
     */
    public static function configChange(string $configKey, string $oldValue, string $newValue): void
    {
        Log::channel('audit')->info('config_change', [
            'config_key' => $configKey,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'user_id' => Auth::id(),
            'ip' => Request::ip(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log de exportación de datos
     */
    public static function dataExport(string $dataType, int $recordCount): void
    {
        Log::channel('audit')->info('data_export', [
            'data_type' => $dataType,
            'record_count' => $recordCount,
            'user_id' => Auth::id(),
            'ip' => Request::ip(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log de error de seguridad
     */
    public static function securityError(string $errorType, string $message, array $context = []): void
    {
        Log::channel('audit')->error('security_error', [
            'error_type' => $errorType,
            'message' => $message,
            'context' => $context,
            'ip' => Request::ip(),
            'timestamp' => now()->toISOString(),
        ]);
    }
}
