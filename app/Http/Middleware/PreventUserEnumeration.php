<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de Protección contra Enumeración de Usuarios
 *
 * OWASP A07:2021 - Identification and Authentication Failures
 * Previene que atacantes descubran cuentas válidas através de mensajes de error.
 *
 * Los mensajes de error para login/register deben ser genéricos
 * para no revelar si el usuario existe o no en el sistema.
 */
class PreventUserEnumeration
{
    /**
     * Rutas donde se debe aplicar protección contra enumeración
     */
    private const AUTH_ROUTES = [
        'login',
        'register',
        'password.reset',
        'password.forgot',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Solo modificar respuestas en rutas de autenticación
        if ($this->isAuthRoute($request)) {
            return $this->sanitizeAuthResponse($request, $response);
        }

        return $response;
    }

    /**
     * Verifica si la ruta es de autenticación
     */
    private function isAuthRoute(Request $request): bool
    {
        $path = $request->path();

        foreach (self::AUTH_ROUTES as $route) {
            if (str_contains($path, $route)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitiza la respuesta de autenticación para prevenir enumeración
     */
    private function sanitizeAuthResponse(Request $request, Response $response): Response
    {
        // Solo sanitizar respuestas de error (4xx)
        if ($response->getStatusCode() < 400 || $response->getStatusCode() >= 500) {
            return $response;
        }

        $content = json_decode($response->getContent(), true);

        if (!$content || !is_array($content)) {
            return $response;
        }

        // Mensaje genérico para errores de autenticación
        $genericMessage = $this->getGenericErrorMessage($request, $response->getStatusCode());

        // Reemplazar mensajes específicos con mensajes genéricos
        $content = $this->replaceErrorMessages($content, $genericMessage);

        // Asegurar que no se revela el email en ningún campo de error
        $content = $this->removeEmailFromErrors($content, $request);

        $response->setContent(json_encode($content));

        return $response;
    }

    /**
     * Obtiene un mensaje de error genérico basado en el tipo de request
     */
    private function getGenericErrorMessage(Request $request, int $statusCode): string
    {
        if ($statusCode === 401) {
            return 'Credenciales inválidas.';
        }

        if ($statusCode === 422) {
            if (str_contains($request->path(), 'register')) {
                return 'Los datos proporcionados no son válidos.';
            }
            return 'La validación falló.';
        }

        if ($statusCode === 429) {
            return 'Demasiados intentos. Por favor espere.';
        }

        return 'Ha ocurrido un error. Intente nuevamente.';
    }

    /**
     * Reemplaza mensajes de error con mensajes genéricos
     */
    private function replaceErrorMessages(array $content, string $genericMessage): array
    {
        // Reemplazar en 'message'
        if (isset($content['message'])) {
            $content['message'] = $genericMessage;
        }

        // Reemplazar en 'error' (Laravel often uses this)
        if (isset($content['error'])) {
            unset($content['error']);
        }

        // Para errores de validación, no revelar qué campos causaron el error
        // Reemplazar todo el objeto 'errors' con uno genérico
        if (isset($content['errors']) && is_array($content['errors'])) {
            // Solo mantener la estructura pero con mensaje genérico
            $content['errors'] = ['validation' => [$genericMessage]];
        }

        return $content;
    }

    /**
     * Elimina cualquier mención del email en los errores
     */
    private function removeEmailFromErrors(array $content, Request $request): array
    {
        $email = $request->input('email');

        if (!$email) {
            return $content;
        }

        // Función recursiva para limpiar emails
        array_walk_recursive($content, function (&$value) use ($email) {
            if (is_string($value) && stripos($value, $email) !== false) {
                $value = str_replace($email, '***', $value);
            }
        });

        return $content;
    }
}
