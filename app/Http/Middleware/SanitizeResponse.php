<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de Sanitización de Respuestas
 *
 * OWASP A01:2021 - Broken Access Control
 * OWASP A03:2021 - Injection
 *
 * Sanitiza las respuestas antes de enviarlas al cliente.
 */
class SanitizeResponse
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Solo sanitizar respuestas JSON
        if ($this->isJsonResponse($response)) {
            return $this->sanitizeJsonResponse($response, $request);
        }

        return $response;
    }

    /**
     * Verifica si la respuesta es JSON
     */
    private function isJsonResponse(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');

        return str_contains($contentType, 'application/json');
    }

    /**
     * Sanitiza la respuesta JSON
     */
    private function sanitizeJsonResponse(Response $response, Request $request): Response
    {
        $content = $response->getContent();

        if (empty($content)) {
            return $response;
        }

        $data = json_decode($content, true);

        if (!is_array($data)) {
            return $response;
        }

        // Eliminar campos sensibles que puedan estar en la respuesta
        $data = $this->removeSensitiveFields($data);

        // Agregar headers de seguridad adicionales
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Solo agregar Cache-Control si es contenido sensible
        if ($this->isSensitiveEndpoint($request)) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        // Actualizar contenido
        $response->setContent(json_encode($data));

        return $response;
    }

    /**
     * Elimina campos sensibles de la respuesta
     */
    private function removeSensitiveFields(array $data): array
    {
        $sensitiveFields = [
            'password',
            'password_hash',
            'password_confirmation',
            'token',
            'api_key',
            'secret',
            'credentials',
            'private_key',
        ];

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                unset($data[$field]);
            }
        }

        // Procesar recursivamente para arrays anidados
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->removeSensitiveFields($value);
            }
        }

        return $data;
    }

    /**
     * Verifica si el endpoint es sensible y no debe ser cacheado
     */
    private function isSensitiveEndpoint(Request $request): bool
    {
        $sensitivePatterns = [
            '/api/user',
            '/api/profile',
            '/api/auth',
            '/api/login',
            '/api/me',
        ];

        $path = $request->path();

        foreach ($sensitivePatterns as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}