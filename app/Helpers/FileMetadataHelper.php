<?php

namespace App\Helpers;

use App\Models\Configuracion\ConfigVarias;
use App\Models\Configuracion\FileClassificationLevel;
use App\Models\VentanillaUnica\VentanillaRadicaReci;
use App\Models\VentanillaUnica\VentanillaRadicaReciArchivo;
use App\Models\VentanillaUnica\VentanillaRadicaReciMetadata;
use App\Models\VentanillaUnica\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\VentanillaRadicaEnviadosArchivos;
use App\Models\VentanillaUnica\VentanillaRadicaEnviadosMetadata;
use App\Models\VentanillaUnica\VentanillaRadicaInterno;
use App\Models\VentanillaUnica\VentanillaRadicaInternoArchivos;
use App\Models\VentanillaUnica\VentanillaRadicaInternoMetadata;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileMetadataHelper
{
    /**
     * Crea metadatos ISO 27001 para el archivo digital de un radicado recibido.
     */
    public static function crearMetadataArchivoDigital(VentanillaRadicaReci $radicado, string $archivoPath, string $hash, int $archivoPeso): ?VentanillaRadicaReciMetadata
    {
        try {
            $metadata = self::construirMetadata($radicado, null, 'digital', $archivoPath, $hash, $archivoPeso);
            
            return $metadata;
        } catch (\Exception $e) {
            Log::error('Error al crear metadata para archivo digital', [
                'radicado_id' => $radicado->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Crea metadatos ISO 27001 para un archivo adjunto de un radicado recibido.
     */
    public static function crearMetadataArchivoAdjunto(VentanillaRadicaReciArchivo $archivo): ?VentanillaRadicaReciMetadata
    {
        try {
            $radicado = $archivo->radicado;
            
            if (!$radicado) {
                Log::warning('No se encontró radicado para archivo adjunto', ['archivo_id' => $archivo->id]);
                return null;
            }

            $metadata = self::construirMetadata(
                $radicado,
                $archivo->id,
                'adjunto',
                $archivo->archivo,
                $archivo->hash_sha256,
                $archivo->archivo_peso
            );
            
            return $metadata;
        } catch (\Exception $e) {
            Log::error('Error al crear metadata para archivo adjunto', [
                'archivo_id' => $archivo->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Construye y guarda los metadatos completos.
     */
    private static function construirMetadata(
        VentanillaRadicaReci $radicado,
        ?int $archivoId,
        string $tipoArchivo,
        string $archivoPath,
        string $hash,
        int $archivoPeso
    ): ?VentanillaRadicaReciMetadata {
        $radicado->load(['clasificacionDocumental', 'tercero', 'responsables.userCargo.user', 'usuarioCreaRadicado']);
        
        $clasificacion = $radicado->clasificacionDocumental;
        $tercero = $radicado->tercero;
        $responsables = $radicado->responsables;
        
        $nivelClasificacion = self::determinarNivelClasificacion($clasificacion);
        
        $duenoDocumento = $radicado->usuarioCreaRadicado;
        
        $responsablesArray = $responsables->map(function ($resp) {
            $user = $resp->userCargo?->user;
            return $user ? [
                'id' => $user->id,
                'nombre' => trim("{$user->nombres} {$user->apellidos}"),
                'cargo' => $resp->userCargo?->cargo?->nom_organico ?? null
            ] : null;
        })->filter()->values()->toArray();
        
        $fechaVencimiento = $radicado->fec_venci ? \Carbon\Carbon::parse($radicado->fec_venci)->format('Y-m-d') : null;
        $fechaRetencionFin = self::calcularFechaRetencionFin($nivelClasificacion, $radicado->created_at);
        
        $metadataData = [
            'archivo_id' => $archivoId,
            'radicado_id' => $radicado->id,
            'nivel_clasificacion' => $nivelClasificacion,
            'nivel_clasificacion_id' => FileClassificationLevel::getByCode($nivelClasificacion)?->id,
            
            'titulo_documento' => $radicado->asunto,
            'descripcion' => $radicado->observaciones ?? null,
            
            'version_numero' => '1.0',
            'es_version_actual' => true,
            
            'dueno_documento_id' => $duenoDocumento?->id,
            'custodio_actual_id' => $duenoDocumento?->id,
            
            'fecha_creacion_documento' => $radicado->fec_documento ?? $radicado->created_at,
            'fecha_radicacion' => $radicado->created_at,
            'fecha_modificacion' => $radicado->updated_at,
            'fecha_ultima_consulta' => now(),
            'fecha_vencimiento' => $fechaVencimiento,
            'fecha_retencion_fin' => $fechaRetencionFin,
            
            'clasificacion_id' => $clasificacion?->id,
            'clasificacion_ruta' => $clasificacion ? "{$clasificacion->serie}/{$clasificacion->sub_serie}/{$clasificacion->tipo_doc}" : null,
            'clasificacion_serie' => $clasificacion?->serie ?? null,
            'clasificacion_subserie' => $clasificacion?->sub_serie ?? null,
            'clasificacion_tipo_doc' => $clasificacion?->tipo_doc ?? null,
            
            'hash_sha256_original' => $hash,
            'hash_sha256_archivo' => $hash,
            'algoritmo_hash' => 'SHA-256',
            
            'control_acceso_nivel' => self::getNivelAcceso($nivelClasificacion),
            'roles_autorizados' => self::getRolesAutorizados($nivelClasificacion),
            'usuarios_con_acceso' => array_column($responsablesArray, 'id'),
            'requiere_autenticacion_adicional' => in_array($nivelClasificacion, ['CONFIDENCIAL', 'RESERVADO', 'SECRETO']),
            
            'es_registro_vital' => in_array($nivelClasificacion, ['RESERVADO', 'SECRETO']),
            'copias_backups' => json_encode([[
                'fecha' => now()->toIso8601String(),
                'ubicacion' => 'Servidor Principal',
                'hash' => $hash
            ]]),
            
            'num_radicado' => $radicado->num_radicado,
            'asunto' => $radicado->asunto,
            'tercero_nombre' => $tercero?->nom_razo_soci,
            'tercero_identificacion' => $tercero?->num_identificacion,
            'empresa_nombre' => ConfigVarias::getValor('razon_social_empresa'),
            'sede_nombre' => $radicado->usuarioCreaRadicado?->sedes->first()?->nom_sede ?? null,
            'departamento_origen' => $clasificacion?->dependencia ?? null,
            'medio_recepcion' => $radicado->medioRecepcion?->nombre ?? null,
            'tipo_archivo' => $tipoArchivo,
            
            'estado_registro' => 'ACTIVO',
        ];
        
        return VentanillaRadicaReciMetadata::create($metadataData);
    }

    /**
     * Determina el nivel de clasificación según la TRD o por defecto.
     */
    private static function determinarNivelClasificacion($clasificacion): string
    {
        if (!$clasificacion) {
            return 'PUBLICO';
        }
        
        if ($clasificacion->clasifica_nivel && in_array($clasificacion->clasifica_nivel, ['PUBLICO', 'INTERNO', 'CONFIDENCIAL', 'RESERVADO', 'SECRETO'])) {
            return $clasificacion->clasifica_nivel;
        }
        
        return 'PUBLICO';
    }

    /**
     * Calcula el nivel de acceso según la clasificación.
     */
    private static function getNivelAcceso(string $clasificacion): int
    {
        return match($clasificacion) {
            'PUBLICO' => 1,
            'INTERNO' => 2,
            'CONFIDENCIAL' => 3,
            'RESERVADO' => 4,
            'SECRETO' => 5,
            default => 1
        };
    }

    /**
     * Obtiene los roles autorizados según la clasificación.
     */
    private static function getRolesAutorizados(string $clasificacion): array
    {
        return match($clasificacion) {
            'PUBLICO', 'INTERNO' => ['Funcionario', 'Jefe de Archivo', 'Administrador'],
            'CONFIDENCIAL' => ['Jefe de Archivo', 'Administrador'],
            'RESERVADO', 'SECRETO' => ['Administrador'],
            default => ['Funcionario', 'Jefe de Archivo', 'Administrador']
        };
    }

    /**
     * Calcula la fecha fin de retención según el nivel de clasificación.
     */
    private static function calcularFechaRetencionFin(string $clasificacion, $fechaRadicacion): ?string
    {
        $nivel = FileClassificationLevel::getByCode($clasificacion);
        
        if (!$nivel || $nivel->plazo_retencion_meses <= 0) {
            return null;
        }
        
        return \Carbon\Carbon::parse($fechaRadicacion)->addMonths($nivel->plazo_retencion_meses)->format('Y-m-d');
    }

    /**
     * Actualiza la fecha de última consulta.
     */
    public static function actualizarUltimaConsulta(int $metadataId): bool
    {
        try {
            $metadata = VentanillaRadicaReciMetadata::find($metadataId);
            if ($metadata) {
                $metadata->update(['fecha_ultima_consulta' => now()]);
            }
            return true;
        } catch (\Exception $e) {
            Log::warning('Error al actualizar última consulta', [
                'metadata_id' => $metadataId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtiene los metadatos de un archivo.
     */
    public static function obtenerMetadata(int $archivoId): ?VentanillaRadicaReciMetadata
    {
        return VentanillaRadicaReciMetadata::where('archivo_id', $archivoId)->first();
    }

    public static function crearMetadataArchivoDigitalEnviados(VentanillaRadicaEnviados $radicado, string $archivoPath, string $hash, int $archivoPeso): ?VentanillaRadicaEnviadosMetadata
    {
        try {
            $metadata = self::construirMetadataEnviados($radicado, null, 'digital', $archivoPath, $hash, $archivoPeso);
            return $metadata;
        } catch (\Exception $e) {
            Log::error('Error al crear metadata para archivo digital enviados', [
                'radicado_id' => $radicado->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public static function crearMetadataArchivoAdjuntoEnviados(VentanillaRadicaEnviadosArchivos $archivo): ?VentanillaRadicaEnviadosMetadata
    {
        try {
            $radicado = $archivo->radicado;
            if (!$radicado) {
                Log::warning('No se encontró radicado para archivo adjunto enviados', ['archivo_id' => $archivo->id]);
                return null;
            }
            $metadata = self::construirMetadataEnviados(
                $radicado,
                $archivo->id,
                'adjunto',
                $archivo->archivo,
                $archivo->hash_sha256 ?? $hash ?? '',
                $archivo->archivo_peso ?? 0
            );
            return $metadata;
        } catch (\Exception $e) {
            Log::error('Error al crear metadata para archivo adjunto enviados', [
                'archivo_id' => $archivo->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    private static function construirMetadataEnviados(
        VentanillaRadicaEnviados $radicado,
        ?int $archivoId,
        string $tipoArchivo,
        string $archivoPath,
        string $hash,
        int $archivoPeso
    ): ?VentanillaRadicaEnviadosMetadata {
        $radicado->load(['clasificacionDocumental', 'terceroEnviado', 'responsables.userCargo.user', 'usuarioCrea']);
        $clasificacion = $radicado->clasificacionDocumental;
        $tercero = $radicado->terceroEnviado;
        $responsables = $radicado->responsables;
        $nivelClasificacion = self::determinarNivelClasificacion($clasificacion);
        $duenoDocumento = $radicado->usuarioCrea;
        $responsablesArray = $responsables->map(function ($resp) {
            $user = $resp->userCargo?->user;
            return $user ? [
                'id' => $user->id,
                'nombre' => trim("{$user->nombres} {$user->apellidos}"),
                'cargo' => $resp->userCargo?->cargo?->nom_organico ?? null
            ] : null;
        })->filter()->values()->toArray();
        $fechaVencimiento = $radicado->fec_venci ? \Carbon\Carbon::parse($radicado->fec_venci)->format('Y-m-d') : null;
        $fechaRetencionFin = self::calcularFechaRetencionFin($nivelClasificacion, $radicado->created_at);
        $metadataData = [
            'archivo_id' => $archivoId,
            'radicado_id' => $radicado->id,
            'nivel_clasificacion' => $nivelClasificacion,
            'nivel_clasificacion_id' => FileClassificationLevel::getByCode($nivelClasificacion)?->id,
            'titulo_documento' => $radicado->asunto,
            'descripcion' => $radicado->observaciones ?? null,
            'version_numero' => '1.0',
            'es_version_actual' => true,
            'dueno_documento_id' => $duenoDocumento?->id,
            'custodio_actual_id' => $duenoDocumento?->id,
            'fecha_creacion_documento' => $radicado->fec_documento ?? $radicado->created_at,
            'fecha_radicacion' => $radicado->created_at,
            'fecha_modificacion' => $radicado->updated_at,
            'fecha_ultima_consulta' => now(),
            'fecha_vencimiento' => $fechaVencimiento,
            'fecha_retencion_fin' => $fechaRetencionFin,
            'clasificacion_id' => $clasificacion?->id,
            'clasificacion_ruta' => $clasificacion ? "{$clasificacion->serie}/{$clasificacion->sub_serie}/{$clasificacion->tipo_doc}" : null,
            'clasificacion_serie' => $clasificacion?->serie ?? null,
            'clasificacion_subserie' => $clasificacion?->sub_serie ?? null,
            'clasificacion_tipo_doc' => $clasificacion?->tipo_doc ?? null,
            'hash_sha256_original' => $hash,
            'hash_sha256_archivo' => $hash,
            'algoritmo_hash' => 'SHA-256',
            'control_acceso_nivel' => self::getNivelAcceso($nivelClasificacion),
            'roles_autorizados' => self::getRolesAutorizados($nivelClasificacion),
            'usuarios_con_acceso' => array_column($responsablesArray, 'id'),
            'requiere_autenticacion_adicional' => in_array($nivelClasificacion, ['CONFIDENCIAL', 'RESERVADO', 'SECRETO']),
            'es_registro_vital' => in_array($nivelClasificacion, ['RESERVADO', 'SECRETO']),
            'copias_backups' => json_encode([['fecha' => now()->toIso8601String(), 'ubicacion' => 'Servidor Principal', 'hash' => $hash]]),
            'num_radicado' => $radicado->num_radicado,
            'asunto' => $radicado->asunto,
            'tercero_nombre' => $tercero?->nom_razo_soci ?? $tercero?->nombre_completo ?? null,
            'tercero_identificacion' => $tercero?->num_identificacion ?? null,
            'empresa_nombre' => ConfigVarias::getValor('razon_social_empresa'),
            'sede_nombre' => $radicado->usuarioCrea?->sedes->first()?->nom_sede ?? null,
            'departamento_origen' => $clasificacion?->dependencia ?? null,
            'tipo_archivo' => $tipoArchivo,
            'estado_registro' => 'ACTIVO',
        ];
        return VentanillaRadicaEnviadosMetadata::create($metadataData);
    }

    public static function crearMetadataArchivoDigitalInterno(VentanillaRadicaInterno $radicado, string $archivoPath, string $hash, int $archivoPeso): ?VentanillaRadicaInternoMetadata
    {
        try {
            $metadata = self::construirMetadataInterno($radicado, null, 'digital', $archivoPath, $hash, $archivoPeso);
            return $metadata;
        } catch (\Exception $e) {
            Log::error('Error al crear metadata para archivo digital interno', [
                'radicado_id' => $radicado->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public static function crearMetadataArchivoAdjuntoInterno(VentanillaRadicaInternoArchivos $archivo): ?VentanillaRadicaInternoMetadata
    {
        try {
            $radicado = $archivo->radicado;
            if (!$radicado) {
                Log::warning('No se encontró radicado para archivo adjunto interno', ['archivo_id' => $archivo->id]);
                return null;
            }
            $metadata = self::construirMetadataInterno(
                $radicado,
                $archivo->id,
                'adjunto',
                $archivo->ruta_archivo,
                $archivo->hash_sha256 ?? $hash ?? '',
                $archivo->tamano_archivo ?? 0
            );
            return $metadata;
        } catch (\Exception $e) {
            Log::error('Error al crear metadata para archivo adjunto interno', [
                'archivo_id' => $archivo->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    private static function construirMetadataInterno(
        VentanillaRadicaInterno $radicado,
        ?int $archivoId,
        string $tipoArchivo,
        string $archivoPath,
        string $hash,
        int $archivoPeso
    ): ?VentanillaRadicaInternoMetadata {
        $radicado->load(['clasificacionDocumental', 'tercero', 'responsables.userCargo.user', 'usuarioCrea']);
        $clasificacion = $radicado->clasificacionDocumental;
        $tercero = $radicado->tercero;
        $responsables = $radicado->responsables;
        $nivelClasificacion = self::determinarNivelClasificacion($clasificacion);
        $duenoDocumento = $radicado->usuarioCrea;
        $responsablesArray = $responsables->map(function ($resp) {
            $user = $resp->userCargo?->user;
            return $user ? [
                'id' => $user->id,
                'nombre' => trim("{$user->nombres} {$user->apellidos}"),
                'cargo' => $resp->userCargo?->cargo?->nom_organico ?? null
            ] : null;
        })->filter()->values()->toArray();
        $fechaVencimiento = $radicado->fec_venci ? \Carbon\Carbon::parse($radicado->fec_venci)->format('Y-m-d') : null;
        $fechaRetencionFin = self::calcularFechaRetencionFin($nivelClasificacion, $radicado->created_at);
        $metadataData = [
            'archivo_id' => $archivoId,
            'radicado_id' => $radicado->id,
            'nivel_clasificacion' => $nivelClasificacion,
            'nivel_clasificacion_id' => FileClassificationLevel::getByCode($nivelClasificacion)?->id,
            'titulo_documento' => $radicado->asunto,
            'descripcion' => $radicado->observaciones ?? null,
            'version_numero' => '1.0',
            'es_version_actual' => true,
            'dueno_documento_id' => $duenoDocumento?->id,
            'custodio_actual_id' => $duenoDocumento?->id,
            'fecha_creacion_documento' => $radicado->fec_documento ?? $radicado->created_at,
            'fecha_radicacion' => $radicado->created_at,
            'fecha_modificacion' => $radicado->updated_at,
            'fecha_ultima_consulta' => now(),
            'fecha_vencimiento' => $fechaVencimiento,
            'fecha_retencion_fin' => $fechaRetencionFin,
            'clasificacion_id' => $clasificacion?->id,
            'clasificacion_ruta' => $clasificacion ? "{$clasificacion->serie}/{$clasificacion->sub_serie}/{$clasificacion->tipo_doc}" : null,
            'clasificacion_serie' => $clasificacion?->serie ?? null,
            'clasificacion_subserie' => $clasificacion?->sub_serie ?? null,
            'clasificacion_tipo_doc' => $clasificacion?->tipo_doc ?? null,
            'hash_sha256_original' => $hash,
            'hash_sha256_archivo' => $hash,
            'algoritmo_hash' => 'SHA-256',
            'control_acceso_nivel' => self::getNivelAcceso($nivelClasificacion),
            'roles_autorizados' => self::getRolesAutorizados($nivelClasificacion),
            'usuarios_con_acceso' => array_column($responsablesArray, 'id'),
            'requiere_autenticacion_adicional' => in_array($nivelClasificacion, ['CONFIDENCIAL', 'RESERVADO', 'SECRETO']),
            'es_registro_vital' => in_array($nivelClasificacion, ['RESERVADO', 'SECRETO']),
            'copias_backups' => json_encode([['fecha' => now()->toIso8601String(), 'ubicacion' => 'Servidor Principal', 'hash' => $hash]]),
            'num_radicado' => $radicado->num_radicado,
            'asunto' => $radicado->asunto,
            'tercero_nombre' => $tercero?->nom_razo_soci ?? null,
            'tercero_identificacion' => $tercero?->num_identificacion ?? null,
            'empresa_nombre' => ConfigVarias::getValor('razon_social_empresa'),
            'sede_nombre' => $radicado->usuarioCrea?->sedes->first()?->nom_sede ?? null,
            'departamento_origen' => $clasificacion?->dependencia ?? null,
            'tipo_archivo' => $tipoArchivo,
            'estado_registro' => 'ACTIVO',
        ];
        return VentanillaRadicaInternoMetadata::create($metadataData);
    }
}
