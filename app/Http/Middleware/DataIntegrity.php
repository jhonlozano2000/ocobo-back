<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de Integridad de Datos
 *
 * OWASP A04:2021 - Insecure Design
 * Previene manipulación de datos en requests.
 *
 * Verifica que los datos críticos no hayan sido modificados
 * usando un hash HMAC generado en el cliente.
 */
class DataIntegrity
{
    /**
     * Header que contiene el hash de integridad
     */
    private const INTEGRITY_HEADER = 'X-Data-Integrity';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Solo validar en rutas que lo requieran
        if ($this->requiresIntegrity($request)) {
            $this->verifyIntegrity($request);
        }

        $response = $next($request);

        // Agregar header de integridad a la respuesta si es necesario
        if ($this->shouldAddIntegrity($request)) {
            $this->addIntegrityHeader($response, $request);
        }

        return $response;
    }

    /**
     * Determina si el request requiere verificación de integridad
     */
    private function requiresIntegrity(Request $request): bool
    {
        $paths = [
            '/api/ventanilla/',
            '/api/archivo/',
            '/api/firma/',
        ];

        foreach ($paths as $path) {
            if ($request->is($path . '*')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica la integridad de los datos del request
     */
    private function verifyIntegrity(Request $request): void
    {
        $integrityHeader = $request->header(self::INTEGRITY_HEADER);

        if (!$integrityHeader) {
            return;
        }

        // reconstruir el hash desde los datos del request
        $data = $this->getSignableData($request);
        $calculatedHash = $this->calculateHash($data);

        if (!hash_equals($integrityHeader, $calculatedHash)) {
            abort(response()->json([
                'success' => false,
                'message' => 'La integridad de los datos no puede ser verificada.',
                'error' => 'DATA_INTEGRITY_FAILED',
            ], 422));
        }
    }

    /**
     * Obtiene los datos firmables del request
     */
    private function getSignableData(Request $request): string
    {
        $data = $request->all();

        // Ordenar las claves para consistencia
        ksort($data);

        // Excluir campos no relevantes
        unset($data['_token']);
        unset($data['_signature']);

        return json_encode($data);
    }

    /**
     * Calcula el hash HMAC de los datos
     */
    private function calculateHash(string $data): string
    {
        $key = config('app.key', '');
        
        return hash_hmac('sha256', $data, $key);
    }

    /**
     * Determina si se debe agregar header de integridad a la respuesta
     */
    private function shouldAddIntegrity(Request $request): bool
    {
        return $request->header('X-Request-Integrity') === 'true';
    }

    /**
     * Agrega header de integridad a la respuesta
     */
    private function addIntegrityHeader(Response $response, Request $request): void
    {
        $data = $response->getContent();
        $hash = $this->calculateHash($data);

        $response->headers->set('X-Response-Integrity', $hash);
    }

    /**
     * Genera un token de integridad para el request
     *
     * @param array $data
     * @return string
     */
    public static function generateToken(array $data): string
    {
        ksort($data);
        $json = json_encode($data);
        
        return hash_hmac('sha256', $json, config('app.key', ''));
    }

    /**
     * Verifica un token de integridad
     *
     * @param array $data
     * @param string $token
     * @return bool
     */
    public static function verifyToken(array $data, string $token): bool
    {
        $calculated = self::generateToken($data);
        
        return hash_equals($calculated, $token);
    }
}