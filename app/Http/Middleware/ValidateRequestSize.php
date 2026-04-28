<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de Validación de Request Body
 *
 * OWASP A05:2021 - Security Misconfiguration
 * Previene ataques por requests demasiado grandes.
 */
class ValidateRequestSize
{
    /**
     * Tamaño máximo por defecto (10MB)
     */
    private const DEFAULT_MAX_SIZE = 10485760;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $maxSize = $this->getMaxSize($request);

        if ($request->getContentLength() > $maxSize) {
            return response()->json([
                'success' => false,
                'message' => 'El request excede el tamaño máximo permitido.',
                'error' => 'REQUEST_TOO_LARGE',
                'max_size_bytes' => $maxSize,
            ], 413);
        }

        return $next($request);
    }

    /**
     * Obtiene el tamaño máximo permitido para el request
     */
    private function getMaxSize(Request $request): int
    {
        $path = $request->path();

        // Uploads pueden ser más grandes
        if (str_contains($path, 'upload') || str_contains($path, 'archivo') || $request->hasFile('file')) {
            return config('security.max_upload_size', 52428800); // 50MB default
        }

        // API requests estándar
        return config('security.max_request_size', self::DEFAULT_MAX_SIZE);
    }
}