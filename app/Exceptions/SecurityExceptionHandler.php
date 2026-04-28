<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Handler de Excepciones Personalizado
 *
 * OWASP A05: Security Misconfiguration
 *
 * Manejo centralizado de excepciones con respuestas consistentes.
 */
class SecurityExceptionHandler extends ExceptionHandler
{
    /**
     * Report or log an exception.
     */
    public function report(Throwable $e): void
    {
        parent::report($e);

        // Log adicional para errores de seguridad
        if ($this->isSecurityException($e)) {
            report($e);
        }
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->handleApiException($e);
        }

        return parent::render($request, $e);
    }

    /**
     * Determine if the exception is security-related.
     */
    protected function isSecurityException(Throwable $e): bool
    {
        $securityExceptions = [
            '\Illuminate\Auth\AuthenticationException',
            '\Illuminate\Auth\Access\AuthorizationException',
            '\Symfony\Component\HttpKernel\Exception\HttpException',
        ];

        foreach ($securityExceptions as $class) {
            if ($e instanceof $class) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle API exceptions with consistent JSON responses.
     */
    protected function handleApiException(Throwable $e)
    {
        if ($e instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
                'error' => 'VALIDATION_ERROR',
            ], 422);
        }

        if ($e instanceof AuthenticationException) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado',
                'error' => 'AUTH_ERROR',
            ], 401);
        }

        // Generic error message for production
        $message = config('app.debug') ? $e->getMessage() : 'Error interno del servidor';

        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => 'SERVER_ERROR',
        ], 500);
    }
}