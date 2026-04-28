<?php

namespace App\Http\Middleware;

use App\Services\Seguridad\AuditLogService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de Logging de Auditoría
 *
 * ISO 27001 A.12.4.1 - Registro de eventos
 * AGN Colombia - Trazabilidad documental SGDEA
 *
 * Registra automáticamente todos los accesos a la API.
 */
class AuditLogMiddleware
{
    /**
     * Rutas que no deben ser auditadas
     */
    private const EXCLUDED_PATHS = [
        'up',
        'api/health',
    ];

    /**
     * Métodos que no deben ser auditados
     */
    private const EXCLUDED_METHODS = [
        'OPTIONS',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar si la ruta debe ser excluida
        if ($this->shouldExclude($request)) {
            return $next($request);
        }

        $startTime = microtime(true);

        // Procesar la respuesta
        $response = $next($request);

        // Calcular tiempo de ejecución
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        // Registrar en audit log
        $this->logAudit($request, $response, $duration);

        return $response;
    }

    /**
     * Determina si la petición debe ser excluida del logging
     */
    private function shouldExclude(Request $request): bool
    {
        // Excluir métodos específicos
        if (in_array($request->method(), self::EXCLUDED_METHODS)) {
            return true;
        }

        // Excluir rutas específicas
        foreach (self::EXCLUDED_PATHS as $path) {
            if ($request->is($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Registra el evento de auditoría
     */
    private function logAudit(Request $request, Response $response, float $duration): void
    {
        try {
            $user = $request->user();
            $route = $request->route();

            $auditData = [
                'method' => $request->method(),
                'path' => $request->path(),
                'full_url' => $request->fullUrl(),
                'ip' => $this->getClientIP($request),
                'user_agent' => $request->userAgent(),
                'response_code' => $response->getStatusCode(),
                'duration_ms' => $duration,
                'route_name' => $route ? $route->getName() : null,
            ];

            // Agregar user_id si está autenticado
            if ($user) {
                $auditData['user_id'] = $user->id;
            }

            // Agregar request_id si existe
            $requestId = $request->header('X-Request-ID');
            if ($requestId) {
                $auditData['request_id'] = $requestId;
            }

            // Determinar categoría y evento basado en la respuesta
            $evento = $this->determineEvent($request, $response);
            $categoria = $this->determineCategory($request);
            $nivel = $this->determineLevel($response);

            AuditLogService::log($evento, $categoria, $auditData, $nivel);
        } catch (\Throwable $e) {
            // No fallar la petición si el logging falla
        }
    }

    /**
     * Obtiene la IP del cliente
     */
    private function getClientIP(Request $request): string
    {
        $ipKeys = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($ipKeys as $key) {
            $ip = $request->server($key);
            if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return $request->ip();
    }

    /**
     * Determina el evento basado en la petición y respuesta
     */
    private function determineEvent(Request $request, Response $response): string
    {
        $method = $request->method();
        $path = $request->path();
        $statusCode = $response->getStatusCode();

        // Éxito en autenticación
        if ($statusCode === 200 && str_contains($path, 'login')) {
            return 'auth.login.success';
        }

        // Fallo en autenticación
        if ($statusCode === 401 && str_contains($path, 'login')) {
            return 'auth.login.failure';
        }

        // Logout
        if ($statusCode === 200 && str_contains($path, 'logout')) {
            return 'auth.logout';
        }

        // Rate limit
        if ($statusCode === 429) {
            return 'security.rate_limit_exceeded';
        }

        // Acceso a documento
        if (str_contains($path, 'descargar') || str_contains($path, 'download')) {
            return $method === 'GET' ? 'document.download' : 'document.upload';
        }

        // Radicación
        if (str_contains($path, 'radica')) {
            return 'document.radication';
        }

        // CRUD genérico
        return match ($method) {
            'GET' => 'data.access',
            'POST' => 'data.create',
            'PUT', 'PATCH' => 'data.update',
            'DELETE' => 'data.delete',
            default => 'data.access',
        };
    }

    /**
     * Determina la categoría basada en la ruta
     */
    private function determineCategory(Request $request): string
    {
        $path = $request->path();

        if (str_contains($path, 'auth') || str_contains($path, 'login') || str_contains($path, 'logout')) {
            return AuditLogService::CATEGORIA_AUTENTICACION;
        }

        if (str_contains($path, 'archivo') || str_contains($path, 'documento')) {
            return AuditLogService::CATEGORIA_DOCUMENTO;
        }

        if (str_contains($path, 'user') || str_contains($path, 'role') || str_contains($path, 'permiso')) {
            return AuditLogService::CATEGORIA_AUTORIZACION;
        }

        if (str_contains($path, 'ventanilla') || str_contains($path, 'radica')) {
            return AuditLogService::CATEGORIA_DOCUMENTO;
        }

        return AuditLogService::CATEGORIA_SISTEMA;
    }

    /**
     * Determina el nivel de severidad basado en la respuesta
     */
    private function determineLevel(Response $response): string
    {
        $statusCode = $response->getStatusCode();

        // Códigos de error del servidor
        if ($statusCode >= 500) {
            return AuditLogService::NIVEL_CRITICAL;
        }

        // Códigos de error del cliente
        if ($statusCode >= 400) {
            return AuditLogService::NIVEL_WARNING;
        }

        return AuditLogService::NIVEL_INFO;
    }
}
