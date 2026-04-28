<?php

namespace App\Http\Requests;

use App\Services\Seguridad\AuditLogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Routing\Route;

/**
 * Base FormRequest con sanitización automática
 *
 * OWASP A03:2021 - Injection
 * Sanitiza todos los inputs automáticamente
 *
 * Para usar, extender esta clase en lugar de FormRequest.
 */
abstract class SanitizedFormRequest extends FormRequest
{
    /**
     * Determina si la validación debe detenerse en el primer error.
     *
     * @var bool
     */
    protected $stopOnFirstFailure = true;

    /**
     * Configuración de sanitización por campo
     * Formato: ['nombre_campo' => 'filter']
     *
     * Filtros disponibles:
     * - 'trim' : Elimina espacios al inicio y final
     * - 'upper' : Convierte a mayúsculas
     * - 'lower' : Convierte a minúsculas
     * - 'capitalize' : Primera letra en mayúscula
     * - 'alphanumeric' : Solo letras y números
     * - 'numeric' : Solo números
     * - 'email' : Formatea como email válido
     * - 'url' : Formatea como URL válida
     * - 'escape' : Escapa caracteres especiales HTML
     *
     * @var array
     */
    protected array $sanitizationRules = [];

    /**
     * Caracteres peligrosos para SQL Injection
     *
     * @var array
     */
    private const SQL_INJECTION_PATTERNS = [
        '/(\%27|\')(\s)*(\%20|\+)*(OR|AND)(\s)*(\%27|\')/i',
        '/(\%27|\')(\s)*(\%20|\+)*(UNION|SELECT|INSERT|UPDATE|DELETE|DROP)/i',
        '/(\%22|\")(\s)*(OR|AND)(\s)*(\%22|\")/i',
        '/(EXEC|EXECUTE|xp_|sp_)/i',
        '/(\-\-|\#|\/\*)/',
    ];

    /**
     * Caracteres peligrosos para XSS
     *
     * @var array
     */
    private const XSS_PATTERNS = [
        '/<script\b[^>]*>(.*?)<\/script>/is',
        '/<iframe\b[^>]*>(.*?)<\/iframe>/is',
        '/javascript:/i',
        '/on\w+\s*=/i',
    ];

    /**
     * Sanitiza los datos de entrada antes de la validación
     */
    public function validationData(): array
    {
        $datos = $this->all();

        $datosSanitizados = $this->sanitizarDatos($datos);

        // Reemplazar los datos en el request
        $this->replace($datosSanitizados);

        return $datosSanitizados;
    }

    /**
     * Aplica sanitización recursiva a los datos
     */
    private function sanitizarDatos(array $datos): array
    {
        foreach ($datos as $clave => $valor) {
            // Omitir archivos
            if ($valor instanceof \Illuminate\Http\UploadedFile) {
                continue;
            }

            // Omitir arrays de archivos
            if (is_array($valor)) {
                $datos[$clave] = $this->sanitizarDatos($valor);
                continue;
            }

            // Omitir null
            if ($valor === null) {
                continue;
            }

            // Aplicar filtros específicos por campo
            if (isset($this->sanitizationRules[$clave])) {
                $datos[$clave] = $this->aplicarFiltro($valor, $this->sanitizationRules[$clave]);
            } else {
                // Sanitización por defecto: trim y escape básico
                $datos[$clave] = $this->sanitizarValor($valor);
            }
        }

        return $datos;
    }

    /**
     * Aplica un filtro específico a un valor
     */
    private function aplicarFiltro($valor, string $filtro)
    {
        if (!is_string($valor)) {
            return $valor;
        }

        return match ($filtro) {
            'trim' => trim($valor),
            'upper' => mb_strtoupper(trim($valor)),
            'lower' => mb_strtolower(trim($valor)),
            'capitalize' => ucfirst(mb_strtolower(trim($valor))),
            'alphanumeric' => preg_replace('/[^a-zA-Z0-9]/', '', $valor),
            'numeric' => preg_replace('/[^0-9]/', '', $valor),
            'email' => filter_var(trim($valor), FILTER_SANITIZE_EMAIL),
            'url' => filter_var(trim($valor), FILTER_SANITIZE_URL),
            'escape' => htmlspecialchars(trim($valor), ENT_QUOTES, 'UTF-8'),
            'clean' => $this->sanitizarValor($valor),
            default => $this->sanitizarValor($valor),
        };
    }

    /**
     * Sanitización por defecto para strings
     */
    private function sanitizarValor($valor): string
    {
        if (!is_string($valor)) {
            return $valor;
        }

        $valor = trim($valor);

        // Verificar SQL Injection patterns
        foreach (self::SQL_INJECTION_PATTERNS as $patron) {
            if (preg_match($patron, $valor)) {
                AuditLogService::logIntentoIntrusion(
                    'sql_injection',
                    'Posible SQL Injection detectado en input',
                    ['valor' => substr($valor, 0, 100)]
                );
                // Reemplazar con texto seguro
                $valor = preg_replace($patron, '[FILTERED]', $valor);
            }
        }

        // Verificar XSS patterns
        foreach (self::XSS_PATTERNS as $patron) {
            if (preg_match($patron, $valor)) {
                AuditLogService::logIntentoIntrusion(
                    'xss',
                    'Posible XSS detectado en input',
                    ['valor' => substr($valor, 0, 100)]
                );
                // Eliminar tags y atributos peligrosos
                $valor = preg_replace($patron, '', $valor);
            }
        }

        return $valor;
    }

    /**
     * Maneja una validación fallida
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
                'error' => 'VALIDATION_ERROR',
            ], 422)
        );
    }

    /**
     * Prepara los datos para la validación
     */
    protected function prepareForValidation(): void
    {
        // Hook para personalización en clases hijos
    }
}
