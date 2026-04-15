<?php

namespace App\Helpers;

use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\Configuracion\ConfigVarias;
use App\Models\Configuracion\FileClassificationLevel;
use App\Models\VentanillaUnica\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\VentanillaRadicaEnviadosArchivos;
use App\Models\VentanillaUnica\VentanillaRadicaEnviadosMetadata;
use App\Models\VentanillaUnica\VentanillaRadicaEnviadosMetadataHistory;
use App\Models\VentanillaUnica\VentanillaRadicaInterno;
use App\Models\VentanillaUnica\VentanillaRadicaInternoArchivos;
use App\Models\VentanillaUnica\VentanillaRadicaInternoMetadata;
use App\Models\VentanillaUnica\VentanillaRadicaInternoMetadataHistory;
use App\Models\VentanillaUnica\VentanillaRadicaReci;
use App\Models\VentanillaUnica\VentanillaRadicaReciArchivo;
use App\Models\VentanillaUnica\VentanillaRadicaReciMetadata;
use App\Models\VentanillaUnica\VentanillaRadicaReciMetadataHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                'error' => $e->getMessage(),
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

            if (! $radicado) {
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
                'error' => $e->getMessage(),
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
                'cargo' => $resp->userCargo?->cargo?->nom_organico ?? null,
            ] : null;
        })->filter()->values()->toArray();

        $fechaVencimiento = $radicado->fec_venci ? Carbon::parse($radicado->fec_venci)->format('Y-m-d') : null;
        $fechaRetencionFin = self::calcularFechaRetencionFin($nivelClasificacion, $radicado->created_at);
        $fechaDisposicionFinal = self::calcularFechaDisposicionFinal($clasificacion, $radicado->created_at);

        // Para archivos digitales, usar archivo_id = 0 como sentinel
        $archivoIdFinal = ($tipoArchivo === 'digital' && $archivoId === null) ? 0 : $archivoId;

        // Obtener jerarquía de la TRD recursiva
        $jerarquia = $clasificacion ? $clasificacion->getJerarquia() : [];
        $clasificacionRuta = $clasificacion ? implode('/', array_column($jerarquia, 'nom')) : null;
        $serie = count($jerarquia) > 0 ? $jerarquia[0]['nom'] : null;
        $subserie = count($jerarquia) > 1 ? $jerarquia[1]['nom'] : null;
        $tipoDocumento = count($jerarquia) > 2 ? $jerarquia[2]['nom'] : null;

        $metadataData = [
            'archivo_id' => $archivoIdFinal,
            'radicado_id' => $radicado->id,
            'tipo_archivo' => $tipoArchivo,
            'nivel_clasificacion' => $nivelClasificacion,
            'nivel_clasificacion_id' => FileClassificationLevel::getByCode($nivelClasificacion)?->id,

            'titulo_documento' => $radicado->asunto,
            'descripcion' => $radicado->asunto,

            'version_numero' => '1.0',
            'version_descripcion' => '',
            'es_version_actual' => true,

            'dueno_documento_id' => $duenoDocumento?->id,
            'custodio_actual_id' => $duenoDocumento?->id,
            'responsable_clasificacion_id' => $duenoDocumento?->id,

            'fecha_creacion_documento' => $radicado->fec_documento ?? $radicado->created_at,
            'fecha_radicacion' => $radicado->created_at,
            'fecha_modificacion' => $radicado->updated_at,
            'fecha_ultima_consulta' => now(),
            'fecha_vencimiento' => $fechaVencimiento,
            'fecha_disposicion_final' => $fechaDisposicionFinal,
            'fecha_retencion_fin' => $fechaRetencionFin,

            'clasificacion_id' => $clasificacion?->id,
            'clasificacion_ruta' => $clasificacionRuta,
            'clasificacion_serie' => $serie,
            'clasificacion_subserie' => $subserie,
            'clasificacion_tipo_doc' => $tipoDocumento,

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
                'hash' => $hash,
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

            // Campos adicionales
            'firma_digital_hash' => $hash,
            'terminos_retention_id' => null,
            'categoria_informacion' => $tipoDocumento,

            'estado_registro' => 'ACTIVO',
        ];

        $metadata = VentanillaRadicaReciMetadata::create($metadataData);

        $usuario = Auth::user();
        VentanillaRadicaReciMetadataHistory::registrarCreacion(
            $metadata->id,
            $metadataData,
            $usuario?->id,
            $usuario ? trim("{$usuario->nombres} {$usuario->apellidos}") : null
        );

        return $metadata;
    }

    /**
     * Determina el nivel de clasificación según la TRD o por defecto.
     */
    private static function determinarNivelClasificacion($clasificacion): string
    {
        if (! $clasificacion) {
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
        return match ($clasificacion) {
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
        return match ($clasificacion) {
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

        if (! $nivel || $nivel->plazo_retencion_meses <= 0) {
            return null;
        }

        return Carbon::parse($fechaRadicacion)->addMonths($nivel->plazo_retencion_meses)->format('Y-m-d');
    }

    /**
     * Calcula la fecha de disposición final según la TRD (a_g + a_c).
     * a_g = años en archivo de gestión
     * a_c = años en archivo central
     */
    private static function calcularFechaDisposicionFinal($clasificacion, $fechaRadicacion): ?string
    {
        if (! $clasificacion) {
            return null;
        }

        $jerarquia = $clasificacion->getJerarquia();
        $totalAnios = 0;

        foreach ($jerarquia as $elemento) {
            $modelo = self::findTrdElement($elemento['id']);
            if ($modelo) {
                $totalAnios += (int) ($modelo->a_g ?? 0);
                $totalAnios += (int) ($modelo->a_c ?? 0);
            }
        }

        if ($totalAnios <= 0) {
            return null;
        }

        return Carbon::parse($fechaRadicacion)->addYears($totalAnios)->format('Y-m-d');
    }

    /**
     * Obtiene un elemento TRD por ID.
     */
    private static function findTrdElement(int $id): ?ClasificacionDocumentalTRD
    {
        return ClasificacionDocumentalTRD::find($id);
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
                'error' => $e->getMessage(),
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
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public static function crearMetadataArchivoAdjuntoEnviados(VentanillaRadicaEnviadosArchivos $archivo): ?VentanillaRadicaEnviadosMetadata
    {
        try {
            $radicado = $archivo->radicado;
            if (! $radicado) {
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
                'error' => $e->getMessage(),
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
        $radicado->load(['clasificacionDocumental', 'terceroEnviado', 'responsables.userCargo.user', 'usuarioCreaRadicado']);
        $clasificacion = $radicado->clasificacionDocumental;
        $tercero = $radicado->terceroEnviado;
        $responsables = $radicado->responsables;
        $nivelClasificacion = self::determinarNivelClasificacion($clasificacion);
        $duenoDocumento = $radicado->usuarioCreaRadicado;
        $responsablesArray = $responsables->map(function ($resp) {
            $user = $resp->userCargo?->user;

            return $user ? [
                'id' => $user->id,
                'nombre' => trim("{$user->nombres} {$user->apellidos}"),
                'cargo' => $resp->userCargo?->cargo?->nom_organico ?? null,
            ] : null;
        })->filter()->values()->toArray();
        $fechaVencimiento = $radicado->fec_venci ? Carbon::parse($radicado->fec_venci)->format('Y-m-d') : null;
        $fechaRetencionFin = self::calcularFechaRetencionFin($nivelClasificacion, $radicado->created_at);
        $fechaDisposicionFinal = self::calcularFechaDisposicionFinal($clasificacion, $radicado->created_at);

        // Para archivos digitales, usar archivo_id = 0 como sentinel
        $archivoIdFinal = ($tipoArchivo === 'digital' && $archivoId === null) ? 0 : $archivoId;

        // Obtener jerarquía de la TRD recursiva
        $jerarquia = $clasificacion ? $clasificacion->getJerarquia() : [];
        $clasificacionRuta = $clasificacion ? implode('/', array_column($jerarquia, 'nom')) : null;
        $serie = count($jerarquia) > 0 ? $jerarquia[0]['nom'] : null;
        $subserie = count($jerarquia) > 1 ? $jerarquia[1]['nom'] : null;
        $tipoDocumento = count($jerarquia) > 2 ? $jerarquia[2]['nom'] : null;

        $metadataData = [
            'archivo_id' => $archivoIdFinal,
            'radicado_id' => $radicado->id,
            'tipo_archivo' => $tipoArchivo,
            'nivel_clasificacion' => $nivelClasificacion,
            'nivel_clasificacion_id' => FileClassificationLevel::getByCode($nivelClasificacion)?->id,
            'titulo_documento' => $radicado->asunto,
            'descripcion' => $radicado->asunto,
            'version_numero' => '1.0',
            'version_descripcion' => '',
            'es_version_actual' => true,
            'dueno_documento_id' => $duenoDocumento?->id,
            'custodio_actual_id' => $duenoDocumento?->id,
            'responsable_clasificacion_id' => $duenoDocumento?->id,
            'fecha_creacion_documento' => $radicado->fec_documento ?? $radicado->created_at,
            'fecha_radicacion' => $radicado->created_at,
            'fecha_modificacion' => $radicado->updated_at,
            'fecha_ultima_consulta' => now(),
            'fecha_vencimiento' => $fechaVencimiento,
            'fecha_disposicion_final' => $fechaDisposicionFinal,
            'fecha_retencion_fin' => $fechaRetencionFin,
            'clasificacion_id' => $clasificacion?->id,
            'clasificacion_ruta' => $clasificacionRuta,
            'clasificacion_serie' => $serie,
            'clasificacion_subserie' => $subserie,
            'clasificacion_tipo_doc' => $tipoDocumento,
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
            'sede_nombre' => $radicado->usuarioCreaRadicado?->sedes->first()?->nom_sede ?? null,
            'departamento_origen' => $clasificacion?->dependencia ?? null,
            'tipo_archivo' => $tipoArchivo,
            'firma_digital_hash' => $hash,
            'terminos_retention_id' => null,
            'categoria_informacion' => $tipoDocumento,
            'estado_registro' => 'ACTIVO',
        ];
        $metadata = VentanillaRadicaEnviadosMetadata::create($metadataData);

        $usuario = Auth::user();
        VentanillaRadicaEnviadosMetadataHistory::registrarCreacion(
            $metadata->id,
            $metadataData,
            $usuario?->id,
            $usuario ? trim("{$usuario->nombres} {$usuario->apellidos}") : null
        );

        return $metadata;
    }

    public static function crearMetadataArchivoDigitalInterno(VentanillaRadicaInterno $radicado, string $archivoPath, string $hash, int $archivoPeso): ?VentanillaRadicaInternoMetadata
    {
        try {
            $metadata = self::construirMetadataInterno($radicado, null, 'digital', $archivoPath, $hash, $archivoPeso);

            return $metadata;
        } catch (\Exception $e) {
            Log::error('Error al crear metadata para archivo digital interno', [
                'radicado_id' => $radicado->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public static function crearMetadataArchivoAdjuntoInterno(VentanillaRadicaInternoArchivos $archivo): ?VentanillaRadicaInternoMetadata
    {
        try {
            $radicado = $archivo->radicado;
            if (! $radicado) {
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
                'error' => $e->getMessage(),
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
                'cargo' => $resp->userCargo?->cargo?->nom_organico ?? null,
            ] : null;
        })->filter()->values()->toArray();
        $fechaVencimiento = $radicado->fec_venci ? Carbon::parse($radicado->fec_venci)->format('Y-m-d') : null;
        $fechaRetencionFin = self::calcularFechaRetencionFin($nivelClasificacion, $radicado->created_at);
        $fechaDisposicionFinal = self::calcularFechaDisposicionFinal($clasificacion, $radicado->created_at);

        // Para archivos digitales, usar archivo_id = 0 como sentinel
        $archivoIdFinal = ($tipoArchivo === 'digital' && $archivoId === null) ? 0 : $archivoId;

        // Obtener jerarquía de la TRD recursiva
        $jerarquia = $clasificacion ? $clasificacion->getJerarquia() : [];
        $clasificacionRuta = $clasificacion ? implode('/', array_column($jerarquia, 'nom')) : null;
        $serie = count($jerarquia) > 0 ? $jerarquia[0]['nom'] : null;
        $subserie = count($jerarquia) > 1 ? $jerarquia[1]['nom'] : null;
        $tipoDocumento = count($jerarquia) > 2 ? $jerarquia[2]['nom'] : null;

        $metadataData = [
            'archivo_id' => $archivoIdFinal,
            'radicado_id' => $radicado->id,
            'tipo_archivo' => $tipoArchivo,
            'nivel_clasificacion' => $nivelClasificacion,
            'nivel_clasificacion_id' => FileClassificationLevel::getByCode($nivelClasificacion)?->id,
            'titulo_documento' => $radicado->asunto,
            'descripcion' => $radicado->asunto,
            'version_numero' => '1.0',
            'version_descripcion' => '',
            'es_version_actual' => true,
            'dueno_documento_id' => $duenoDocumento?->id,
            'custodio_actual_id' => $duenoDocumento?->id,
            'responsable_clasificacion_id' => $duenoDocumento?->id,
            'fecha_creacion_documento' => $radicado->fec_documento ?? $radicado->created_at,
            'fecha_radicacion' => $radicado->created_at,
            'fecha_modificacion' => $radicado->updated_at,
            'fecha_ultima_consulta' => now(),
            'fecha_vencimiento' => $fechaVencimiento,
            'fecha_disposicion_final' => $fechaDisposicionFinal,
            'fecha_retencion_fin' => $fechaRetencionFin,
            'clasificacion_id' => $clasificacion?->id,
            'clasificacion_ruta' => $clasificacionRuta,
            'clasificacion_serie' => $serie,
            'clasificacion_subserie' => $subserie,
            'clasificacion_tipo_doc' => $tipoDocumento,
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
            'firma_digital_hash' => $hash,
            'terminos_retention_id' => null,
            'categoria_informacion' => $tipoDocumento,
            'estado_registro' => 'ACTIVO',
        ];
        $metadata = VentanillaRadicaInternoMetadata::create($metadataData);

        $usuario = Auth::user();
        VentanillaRadicaInternoMetadataHistory::registrarCreacion(
            $metadata->id,
            $metadataData,
            $usuario?->id,
            $usuario ? trim("{$usuario->nombres} {$usuario->apellidos}") : null
        );

        return $metadata;
    }

    public static function obtenerMetadataConInfo(int $archivoId, string $tipo = 'reci'): ?array
    {
        $metadata = match ($tipo) {
            'enviados' => VentanillaRadicaEnviadosMetadata::where('archivo_id', $archivoId)->with('nivelClasificacion')->first(),
            'interno' => VentanillaRadicaInternoMetadata::where('archivo_id', $archivoId)->with('nivelClasificacion')->first(),
            default => VentanillaRadicaReciMetadata::where('archivo_id', $archivoId)->with('nivelClasificacion')->first(),
        };

        if (! $metadata) {
            return null;
        }

        $usuario = Auth::user();
        $historyClass = match ($tipo) {
            'enviados' => VentanillaRadicaEnviadosMetadataHistory::class,
            'interno' => VentanillaRadicaInternoMetadataHistory::class,
            default => VentanillaRadicaReciMetadataHistory::class,
        };

        $historyClass::registrarConsulta(
            $metadata->id,
            $usuario?->id,
            $usuario ? trim("{$usuario->nombres} {$usuario->apellidos}") : null
        );

        return [
            'metadata' => $metadata,
            'nivel_clasificacion' => $metadata->nivelClasificacion,
            'historial' => $historyClass::where('metadata_id', $metadata->id)
                ->orderBy('fecha_cambio', 'desc')
                ->limit(50)
                ->get(),
        ];
    }

    public static function exportarMetadata(array $filtros = [], string $tipo = 'reci'): StreamedResponse
    {
        $query = match ($tipo) {
            'enviados' => VentanillaRadicaEnviadosMetadata::query(),
            'interno' => VentanillaRadicaInternoMetadata::query(),
            default => VentanillaRadicaReciMetadata::query(),
        };

        if (! empty($filtros['radicado_id'])) {
            $query->where('radicado_id', $filtros['radicado_id']);
        }

        if (! empty($filtros['nivel_clasificacion'])) {
            $query->where('nivel_clasificacion', $filtros['nivel_clasificacion']);
        }

        if (! empty($filtros['fecha_desde'])) {
            $query->where('fecha_creacion_documento', '>=', $filtros['fecha_desde']);
        }

        if (! empty($filtros['fecha_hasta'])) {
            $query->where('fecha_creacion_documento', '<=', $filtros['fecha_hasta']);
        }

        $datos = $query->with('nivelClasificacion')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="metadatos_'.$tipo.'_'.date('Ymd_His').'.csv"',
        ];

        $callback = function () use ($datos) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID', 'Num Radicado', 'Asunto', 'Nivel Clasificación', 'Tipo Archivo',
                'Hash SHA-256', 'Fecha Creación', 'Fecha Vencimiento', 'Fecha Retención Fin',
                'Dueño', 'Custodio', 'Serie', 'Subserie', 'Tipo Doc', 'Estado',
            ]);

            foreach ($datos as $item) {
                fputcsv($handle, [
                    $item->id,
                    $item->num_radicado,
                    $item->asunto,
                    $item->nivel_clasificacion,
                    $item->tipo_archivo,
                    $item->hash_sha256_original,
                    $item->fecha_creacion_documento,
                    $item->fecha_vencimiento,
                    $item->fecha_retencion_fin,
                    $item->dueno_documento_id,
                    $item->custodio_actual_id,
                    $item->clasificacion_serie,
                    $item->clasificacion_subserie,
                    $item->clasificacion_tipo_doc,
                    $item->estado_registro,
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public static function obtenerHistorial(int $metadataId, string $tipo = 'reci'): array
    {
        $historyClass = match ($tipo) {
            'enviados' => VentanillaRadicaEnviadosMetadataHistory::class,
            'interno' => VentanillaRadicaInternoMetadataHistory::class,
            default => VentanillaRadicaReciMetadataHistory::class,
        };

        return $historyClass::where('metadata_id', $metadataId)
            ->orderBy('fecha_cambio', 'desc')
            ->get()
            ->toArray();
    }
}
