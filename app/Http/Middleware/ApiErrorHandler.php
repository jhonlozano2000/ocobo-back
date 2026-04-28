<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\ValidationException;

/**
 * Middleware de Validación de Errores
 *
 * OWASP A05: Security Misconfiguration
 *
 * Normaliza respuestas de error para API.
 */
class ApiErrorHandler
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        return $response;
    }

    /**
     * Formateaerror de validación para JSON consistente
     */
    public static function formatValidationError(ValidationException $e): array
    {
        return [
            'success' => false,
            'message' => 'Error de validación',
            'errors' => $e->errors(),
            'error' => 'VALIDATION_ERROR',
        ];
    }

    /**
     * Formateaerror genérico para producción
     */
    public static function formatGenericError(string $message = 'Error interno'): array
    {
        return [
            'success' => false,
            'message' => $message,
            'error' => 'INTERNAL_ERROR',
        ];
    }

    /**
     * Formateaerror de autenticación
     */
    public static function formatAuthError(string $message = 'No autenticado'): array
    {
        return [
            'success' => false,
            'message' => $message,
            'error' => 'AUTH_ERROR',
        ];
    }

    /**
     * Formateaerror de autorización
     */
    public static function formatAuthorizationError(string $message = 'No autorizado'): array
    {
        return [
            'success' => false,
            'message' => $message,
            'error' => 'AUTHORIZATION_ERROR',
        ];
    }

    /**
     * Formateaerror de no encontrado
     */
    public static function formatNotFoundError(string $message = 'Recurso no encontrado'): array
    {
        return [
            'success' => false,
            'message' => $message,
            'error' => 'NOT_FOUND',
        ];
    }
}