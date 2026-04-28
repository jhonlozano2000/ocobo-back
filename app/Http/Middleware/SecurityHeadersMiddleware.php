<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de Headers de Seguridad
 *
 * OWASP A05:2021 - Security Misconfiguration
 * ISO 27001 A.10.1 - Cryptographic Controls
 *
 * Agrega headers de seguridad a todas las respuestas HTTP.
 */
class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Solo agregar headers a respuestas que no sean para downloads
        if ($this->shouldApplyHeaders($response)) {
            $this->applySecurityHeaders($response, $request);
        }

        return $response;
    }

    /**
     * Determina si se deben aplicar los headers de seguridad.
     */
    private function shouldApplyHeaders(Response $response): bool
    {
        // No aplicar a respuestas de archivos binarios grandes
        $contentType = $response->headers->get('Content-Type', '');

        if (str_contains($contentType, 'application/octet-stream')) {
            return false;
        }

        // No aplicar a respuestas streaming
        if ($response instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
            return false;
        }

        return true;
    }

    /**
     * Aplica los headers de seguridad a la respuesta.
     */
    private function applySecurityHeaders(Response $response, Request $request): void
    {
        $isProduction = app()->environment('production');

        // X-XSS-Protection
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // X-Content-Type-Options
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // X-Frame-Options
        $response->headers->set('X-Frame-Options', 'DENY');

        // Referrer Policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions Policy
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // Content-Security-Policy (solo en producción para evitar problemas en desarrollo)
        if ($isProduction) {
            $csp = $this->buildCSP();
            $response->headers->set('Content-Security-Policy', $csp);
        }

        // HSTS (solo en producción)
        if ($isProduction) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // Cache-Control para contenido sensible
        if ($this->isSensitiveContent($request)) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }
    }

    /**
     * Construye la Content Security Policy.
     */
    private function buildCSP(): string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'strict-dynamic'",
            "style-src 'self' 'nonce-{NONCE}'",
            "img-src 'self' data: blob: https://",
            "font-src 'self' data:",
            "connect-src 'self' http://localhost:* http://*.test",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
            "require-trusted-types-for 'script'",
        ];

        return implode('; ', $directives);
    }

    /**
     * Determina si el contenido es sensible y no debe ser cacheado.
     */
    private function isSensitiveContent(Request $request): bool
    {
        $sensitivePatterns = [
            '/api/user',
            '/api/profile',
            '/api/settings',
            '/api/permissions',
            '/api/roles',
            '/api/users/',
            '/api/ventanilla/',
            '/api/archivo/',
        ];

        $path = $request->path();

        foreach ($sensitivePatterns as $pattern) {
            if (str_starts_with($path, ltrim($pattern, '/'))) {
                return true;
            }
        }

        return false;
    }
}
