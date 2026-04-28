<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de Validación de Content-Type
 *
 * OWASP A05:2021 - Security Misconfiguration
 * Previene ataques por tipo de contenido incorrecto.
 */
class ValidateContentType
{
    /**
     * Tipos de contenido permitidos para la API
     */
    private const ALLOWED_CONTENT_TYPES = [
        'application/json',
        'application/json; charset=utf-8',
        'multipart/form-data',
        'application/x-www-form-urlencoded',
    ];

    /**
     * Tipos de contenido permitidos para respuestas
     */
    private const ALLOWED_ACCEPT_TYPES = [
        'application/json',
        '*/*',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Validar Content-Type solo para métodos que envían datos
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $this->validateRequestContentType($request);
        }

        // Validar Accept header
        $this->validateAcceptHeader($request);

        $response = $next($request);

        // Asegurar que la respuesta tiene el Content-Type correcto
        return $this->ensureResponseContentType($response);
    }

    /**
     * Valida el Content-Type del request
     */
    private function validateRequestContentType(Request $request): void
    {
        $contentType = $request->header('Content-Type');

        if (empty($contentType)) {
            // Algunos clientes no envían Content-Type, permitir si es GET
            if ($request->method() === 'GET') {
                return;
            }

            abort(response()->json([
                'success' => false,
                'message' => 'Content-Type es requerido.',
                'error' => 'MISSING_CONTENT_TYPE'
            ], 415));
        }

        // Extraer el tipo base sin charset u otros parámetros
        $baseContentType = explode(';', $contentType)[0];

        if (!in_array(trim($baseContentType), self::ALLOWED_CONTENT_TYPES)) {
            abort(response()->json([
                'success' => false,
                'message' => 'Content-Type no soportado. Use application/json.',
                'error' => 'UNSUPPORTED_CONTENT_TYPE'
            ], 415));
        }

        // Validar que para multipart/form-data solo se use para uploads
        if (str_contains($baseContentType, 'multipart/form-data') && !$this->hasFileUpload($request)) {
            abort(response()->json([
                'success' => false,
                'message' => 'Content-Type multipart/form-data solo es válido para subida de archivos.',
                'error' => 'INVALID_MULTIPART_USAGE'
            ], 415));
        }
    }

    /**
     * Valida el Accept header
     */
    private function validateAcceptHeader(Request $request): void
    {
        $accept = $request->header('Accept');

        if (empty($accept)) {
            // Permitir si no se especifica (asumir JSON)
            return;
        }

        // Extraer el tipo base
        $baseAccept = explode(';', $accept)[0];

        if (!in_array(trim($baseAccept), self::ALLOWED_ACCEPT_TYPES)) {
            abort(response()->json([
                'success' => false,
                'message' => 'Accept no soportado. Use application/json.',
                'error' => 'UNSUPPORTED_ACCEPT_TYPE'
            ], 406));
        }
    }

    /**
     * Asegura que la respuesta tenga el Content-Type correcto
     */
    private function ensureResponseContentType(Response $response): Response
    {
        // No modificar respuestas que ya tienen Content-Type establecido
        if ($response->headers->has('Content-Type')) {
            return $response;
        }

        // Para respuestas JSON, establecer el Content-Type
        if ($this->isJsonResponse($response)) {
            $response->headers->set('Content-Type', 'application/json; charset=utf-8');
        }

        return $response;
    }

    /**
     * Determina si la respuesta es JSON
     */
    private function isJsonResponse(Response $response): bool
    {
        $content = $response->getContent();

        if (empty($content)) {
            return false;
        }

        // Intentar decodificar como JSON
        json_decode($content);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Verifica si el request tiene archivos
     */
    private function hasFileUpload(Request $request): bool
    {
        return $request->hasFile('file') ||
               $request->hasFile('files') ||
               $request->hasFile('archivo') ||
               $request->hasFile('documento') ||
               $request->hasFile('anexos');
    }
}
