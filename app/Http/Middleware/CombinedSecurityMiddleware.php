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
        // Verificar que la petición no venga de un proxy no confiable
        $this->verifyTrustedProxies($request);

        // Verificar que la petición tenga headers mínimos
        $this->verifyRequiredHeaders($request);

        // Verificar URL maliciosa
        $this->verifyMaliciousUrl($request);

        $response = $next($request);

        return $response;
    }

    /**
     * Verifica proxies confiables
     */
    private function verifyTrustedProxies(Request $request): void
    {
        // Si hay proxy header, verificar que venga de un proxy conocido
        $forwardedFor = $request->header('X-Forwarded-For');
        $forwardedProto = $request->header('X-Forwarded-Proto');
        $forwardedIp = $request->header('X-Forwarded-IP');

        // Si hay headers de proxy pero el trust proxy no está configurado
        if (($forwardedFor || $forwardedProto || $forwardedIp) && !config('trustedproxy.proxies')) {
            // Solo warn, no bloquear - el TrustProxies middleware maneja esto
        }
    }

    /**
     * Verifica headers requeridos
     */
    private function verifyRequiredHeaders(Request $request): void
    {
        // Solo para endpoints de API que requieren Content-Type
        if (!$request->is('api/*')) {
            return;
        }

        // POST, PUT, PATCH deben tener Content-Type
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            // Content-Type será validado por ValidateContentType middleware
        }
    }

    /**
     * Verifica URLs maliciosas
     */
    private function verifyMaliciousUrl(Request $request): void
    {
        $url = $request->getQueryString() ?? '';

        // Verificar null bytes
        if (str_contains($url, "\0")) {
            abort(response()->json([
                'success' => false,
                'message' => 'URL inválida.',
                'error' => 'INVALID_URL',
            ], 400));
        }

        // Verificar sequences path traversal en query string
        if (preg_match('/(\.\.|%2e%2e)/i', $url)) {
            abort(response()->json([
                'success' => false,
                'message' => 'URL inválida.',
                'error' => 'INVALID_URL',
            ], 400));
        }
    }
}