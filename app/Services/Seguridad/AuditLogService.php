<?php

namespace App\Services\Seguridad;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Servicio de Logging de Auditoría
 *
 * Cumple con ISO 27001 A.12.4.1 - Registro de eventos
 * y AGN Colombia - Trazabilidad documental
 *
 * Todos los eventos de seguridad deben ser registrados para:
 * - Auditoría legal
 * - Detección de intrusiones
 * - Cumplimiento normativo
 * - Investigación de incidentes
 */
class AuditLogService
{
    /**
     * Categorías de eventos de auditoría
     */
    public const CATEGORIA_AUTENTICACION = 'autenticacion';
    public const CATEGORIA_AUTORIZACION = 'autorizacion';
    public const CATEGORIA_DATO = 'dato';
    public const CATEGORIA_DOCUMENTO = 'documento';
    public const CATEGORIA_CONFIGURACION = 'configuracion';
    public const CATEGORIA_SEGURIDAD = 'seguridad';
    public const CATEGORIA_SISTEMA = 'sistema';

    /**
     * Niveles de severidad
     */
    public const NIVEL_INFO = 'info';
    public const NIVEL_WARNING = 'warning';
    public const NIVEL_CRITICAL = 'critical';

    /**
     * Eventos específicos de auditoría
     */
    public const EVENTO_LOGIN_EXITO = 'auth.login.success';
    public const EVENTO_LOGIN_FALLO = 'auth.login.failure';
    public const EVENTO_LOGOUT = 'auth.logout';
    public const EVENTO_PERMISO_DENEGADO = 'auth.permission.denied';
    public const EVENTO_ACCESO_DATOS = 'data.access';
    public const EVENTO_CREACION_DATOS = 'data.create';
    public const EVENTO_MODIFICACION_DATOS = 'data.update';
    public const EVENTO_ELIMINACION_DATOS = 'data.delete';
    public const EVENTO_SUBIDA_ARCHIVO = 'document.upload';
    public const EVENTO_DESCARGA_ARCHIVO = 'document.download';
    public const EVENTO_ELIMINACION_ARCHIVO = 'document.delete';
    public const EVENTO_CAMBIO_PASSWORD = 'auth.password.change';
    public const EVENTO_CAMBIO_ROL = 'auth.role.change';
    public const EVENTO_FIRMA_DOCUMENTO = 'document.sign';
    public const EVENTO_RADICACION = 'document.radication';
    public const EVENTO_EXPORTE_DATOS = 'data.export';
    public const EVENTO_INTENTO_INTRUSION = 'security.intrusion_attempt';
    public const EVENTO_RATE_LIMIT_EXCEDIDO = 'security.rate_limit_exceeded';
    public const EVENTO_CONFIG_CAMBIO = 'config.change';

    /**
     * Registra un evento de auditoría
     *
     * @param string $evento Evento específico
     * @param string $categoria Categoría del evento
     * @param array $datos Datos adicionales del evento
     * @param string $nivel Nivel de severidad
     * @return void
     */
    public static function log(
        string $evento,
        string $categoria = self::CATEGORIA_SISTEMA,
        array $datos = [],
        string $nivel = self::NIVEL_INFO
    ): void {
        $userId = Auth::id();
        $ip = self::getClientIP();
        $userAgent = Request::userAgent();
        $requestId = self::getRequestId();

        $auditEntry = [
            'timestamp' => now()->toISOString(),
            'evento' => $evento,
            'categoria' => $categoria,
            'nivel' => $nivel,
            'usuario' => [
                'id' => $userId,
                'ip' => $ip,
                'user_agent' => $userAgent,
            ],
            'request_id' => $requestId,
            'datos' => self::sanitizarDatos($datos),
            'modulo' => self::detectarModulo(),
        ];

        // Usar el canal de auditoría dedicado
        Log::channel('audit')->info(json_encode($auditEntry));

        // Para eventos críticos, también registrar en el log principal
        if ($nivel === self::NIVEL_CRITICAL) {
            Log::critical("AUDITORIA: {$evento}", $auditEntry);
        }
    }

    /**
     * Registra un evento de autenticación
     */
    public static function logAutenticacion(
        string $evento,
        bool $exito,
        array $datosAdicionales = []
    ): void {
        $nivel = $exito ? self::NIVEL_INFO : self::NIVEL_WARNING;

        if (!$exito) {
            $datosAdicionales['ip'] = self::getClientIP();
        }

        self::log(
            $evento,
            self::CATEGORIA_AUTENTICACION,
            array_merge(['exito' => $exito], $datosAdicionales),
            $nivel
        );
    }

    /**
     * Registra un evento de autorización (permisos denegados)
     */
    public static function logPermisoDenegado(
        string $permiso,
        string $recurso = null
    ): void {
        self::log(
            self::EVENTO_PERMISO_DENEGADO,
            self::CATEGORIA_AUTORIZACION,
            [
                'permiso' => $permiso,
                'recurso' => $recurso,
                'ip' => self::getClientIP(),
            ],
            self::NIVEL_WARNING
        );
    }

    /**
     * Registra acceso a datos sensibles
     */
    public static function logAccesoDatos(
        string $modelo,
        int $registroId,
        string $accion = 'read'
    ): void {
        self::log(
            self::EVENTO_ACCESO_DATOS,
            self::CATEGORIA_DATO,
            [
                'modelo' => $modelo,
                'registro_id' => $registroId,
                'accion' => $accion,
            ]
        );
    }

    /**
     * Registra creación de radicado (AGN Colombia)
     */
    public static function logRadicacion(
        int $radicadoId,
        string $numeroRadicado,
        array $metadatos = []
    ): void {
        self::log(
            self::EVENTO_RADICACION,
            self::CATEGORIA_DOCUMENTO,
            [
                'radicado_id' => $radicadoId,
                'numero_radicado' => $numeroRadicado,
                'metadatos' => $metadatos,
            ]
        );
    }

    /**
     * Registra subida de archivo
     */
    public static function logSubidaArchivo(
        string $disk,
        string $ruta,
        int $size,
        string $mimeType
    ): void {
        self::log(
            self::EVENTO_SUBIDA_ARCHIVO,
            self::CATEGORIA_DOCUMENTO,
            [
                'disk' => $disk,
                'ruta' => $ruta,
                'size' => $size,
                'mime_type' => $mimeType,
            ]
        );
    }

    /**
     * Registra intento de intrusión
     */
    public static function logIntentoIntrusion(
        string $tipo,
        string $descripcion,
        array $datos = []
    ): void {
        self::log(
            self::EVENTO_INTENTO_INTRUSION,
            self::CATEGORIA_SEGURIDAD,
            array_merge([
                'tipo' => $tipo,
                'descripcion' => $descripcion,
                'ip' => self::getClientIP(),
            ], $datos),
            self::NIVEL_CRITICAL
        );
    }

    /**
     * Registra rate limit excedido
     */
    public static function logRateLimitExcedido(
        string $endpoint,
        string $limitador
    ): void {
        self::log(
            self::EVENTO_RATE_LIMIT_EXCEDIDO,
            self::CATEGORIA_SEGURIDAD,
            [
                'endpoint' => $endpoint,
                'limitador' => $limitador,
                'ip' => self::getClientIP(),
            ],
            self::NIVEL_WARNING
        );
    }

    /**
     * Obtiene la IP del cliente
     */
    private static function getClientIP(): ?string
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];

        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Verificar si es una IP válida
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return Request::ip();
    }

    /**
     * Obtiene o genera un ID de request
     */
    private static function getRequestId(): string
    {
        static $requestId = null;

        if ($requestId === null) {
            $requestId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        }

        return $requestId;
    }

    /**
     * Detecta el módulo actual desde la ruta
     */
    private static function detectarModulo(): ?string
    {
        $route = request()->route();

        if ($route) {
            $uri = $route->uri();
            $parts = explode('/', $uri);
            return $parts[0] ?? null;
        }

        return null;
    }

    /**
     * Sanitiza datos sensibles antes de guardar en logs
     */
    private static function sanitizarDatos(array $datos): array
    {
        $camposSensibles = [
            'password',
            'password_confirmation',
            'current_password',
            'token',
            'secret',
            'api_key',
            'Authorization',
            'Bearer',
            'num_docu',
            'num_docu_nit',
            'email',
            'direccion',
            'telefono',
        ];

        foreach ($datos as $key => $value) {
            $keyLower = strtolower($key);

            // Verificar si el campo es sensible
            foreach ($camposSensibles as $campoSensible) {
                if (str_contains($keyLower, strtolower($campoSensible))) {
                    $datos[$key] = '[REDACTED]';
                    break;
                }
            }

            // Para arrays, sanitizar recursivamente
            if (is_array($datos[$key])) {
                $datos[$key] = self::sanitizarDatos($datos[$key]);
            }
        }

        return $datos;
    }
}
