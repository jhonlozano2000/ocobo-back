<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware Combinado de Seguridad
 *
 * OWASP A01-A10:2021, ISO 27001
 *
 * Este middleware agrupa verificaciones de seguridad comunes
 * que deben ejecutarse en todas las peticiones API.
 *
 * NOTA: Los middlewares individuales ya están registrados en el grupo 'api'.
 * Este middleware es para verificaciones adicionales que no requieren
 * headers de respuesta.
 */
class CombinedSecurityMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->verifyMaliciousUrl($request);

        return $next($request);
    }

    /**
     * Verifica que la URL y queryString no contengan caracteres de evasión o secuencias maliciosas.
     */
    private function verifyMaliciousUrl(Request $request): void
    {
        $rawQuery = $request->getQueryString() ?? '';
        $rawPath = $request->path();
        $decodedQuery = rawurldecode($rawQuery);
        $decodedPath = rawurldecode($rawPath);

        // Verificar null bytes (ataques de truncamiento en crudo o codificados)
        if (str_contains($rawQuery, "\0") || str_contains($decodedQuery, "\0") ||
            str_contains($rawPath, "\0") || str_contains($decodedPath, "\0") ||
            str_contains(strtolower($rawQuery), '%00') || str_contains(strtolower($rawPath), '%00')) {
            abort(400, 'URL contiene caracteres no permitidos.');
        }

        // Verificar secuencias de path traversal explícitas (../, ..\, etc.) en crudo o decodificado
        if (preg_match('/(?:\.\.[\/\\\\])/i', $decodedQuery) ||
            preg_match('/(?:\.\.[\/\\\\])/i', $decodedPath) ||
            preg_match('/(?:\.\.[\/\\\\]|%2e%2e[\/\\\\]|%2e%2e%2f|%2e%2e%5c|\.\.%2f|\.\.%5c)/i', $rawQuery) ||
            preg_match('/(?:\.\.[\/\\\\]|%2e%2e[\/\\\\]|%2e%2e%2f|%2e%2e%5c|\.\.%2f|\.\.%5c)/i', $rawPath)) {
            abort(400, 'Secuencia de path traversal no permitida.');
        }
    }
}
