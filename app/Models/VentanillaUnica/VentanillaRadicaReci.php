<?php

namespace App\Models\VentanillaUnica;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Helpers\ArchivoHelper;

class VentanillaRadicaReci extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_reci';

    protected $fillable = [
        'num_radicado',
        'clasifica_documen_id',
        'usuario_crea',
        'tercero_id',
        'medio_recep_id',
        'config_server_id',
        'fec_venci',
        'fec_docu',
        'num_folios',
        'num_anexos',
        'descrip_anexos',
        'asunto',
        'radicado_respuesta',
        'archivo_digital',
        'cod_verifica',
        'uploaded_by',
        'impri_rotulo',
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleted(function ($radicado) {
            // Usar ArchivoHelper para eliminar el archivo si existe
            if ($radicado->archivo_digital) {
                ArchivoHelper::eliminarArchivo($radicado->archivo_digital, 'radicados_recibidos');
            }
        });
    }

    public function clasificacionDocumental()
    {
        return $this->belongsTo(\App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD::class, 'clasifica_documen_id');
    }

    public function usuarioCreaRadicado()
    {
        return $this->belongsTo(\App\Models\User::class, 'usuario_crea');
    }

    public function tercero()
    {
        return $this->belongsTo(\App\Models\Gestion\GestionTercero::class, 'tercero_id');
    }

    public function medioRecepcion()
    {
        return $this->belongsTo(\App\Models\Configuracion\ConfigListaDetalle::class, 'medio_recep_id');
    }

    public function servidorArchivos()
    {
        return $this->belongsTo(\App\Models\Configuracion\ConfigServerArchivo::class, 'config_server_id');
    }

    public function usuarioSubio()
    {
        return $this->belongsTo(\App\Models\User::class, 'uploaded_by');
    }

    /**
     * Obtiene los responsables asignados a esta radicación.
     */
    public function responsables()
    {
        return $this->hasMany(VentanillaRadicaReciResponsa::class, 'radica_reci_id');
    }

    /**
     * Obtiene los usuarios responsables a través de la tabla pivot con users_cargos.
     */
    public function usuariosResponsables()
    {
        return $this->belongsToMany(\App\Models\ControlAcceso\UserCargo::class, 'ventanilla_radica_reci_responsa', 'radica_reci_id', 'users_cargos_id')
            ->withPivot('custodio', 'fechor_visto')
            ->withTimestamps();
    }

    /**
     * Obtiene los archivos adicionales asociados al radicado.
     */
    public function archivos()
    {
        return $this->hasMany(VentanillaRadicaReciArchivo::class, 'radicado_id');
    }

    /**
     * Obtiene información completa de documentos relacionados (archivo principal y adicionales).
     * Optimizado para usar relaciones ya cargadas con eager loading.
     *
     * @return array
     */
    public function getDocumentosRelacionados(): array
    {
        // Cachear usuario que subió archivo (se usa dos veces)
        $usuarioSubio = $this->getInfoUsuarioSubio();

        // Archivo principal
        $archivoPrincipal = null;
        if ($this->archivo_digital) {
            $archivoPrincipal = $this->getInfoArchivo('archivo_digital', 'radicados_recibidos');
            if ($archivoPrincipal && $usuarioSubio) {
                $archivoPrincipal['subido_por'] = $usuarioSubio['nombre_completo'];
            }
        }

        // Archivos adicionales (usar relación ya cargada si existe, sino cargar)
        $archivosRelacion = $this->relationLoaded('archivos') ? $this->archivos : $this->archivos()->get();

        // Pre-inicializar collection (optimización de memoria)
        $archivosAdicionales = collect();

        foreach ($archivosRelacion as $archivo) {
            $info = $archivo->getInfoArchivo('archivo', 'radicados_recibidos');
            if ($info) {
                $info['fecha_subida'] = $archivo->created_at;
                $archivosAdicionales->push($info);
            }
        }

        // Calcular totales optimizado (evitar múltiples llamadas a count)
        $countArchivosAdicionales = $archivosAdicionales->count();
        $totalArchivos = ($archivoPrincipal ? 1 : 0) + $countArchivosAdicionales;
        $tieneArchivosAdicionales = $countArchivosAdicionales > 0;

        return [
            'archivo_principal' => $archivoPrincipal,
            'archivos_adicionales' => $archivosAdicionales,
            'total_archivos' => $totalArchivos,
            'tiene_archivo_principal' => $archivoPrincipal !== null,
            'tiene_archivos_adicionales' => $tieneArchivosAdicionales,
            'fecha_creacion' => $this->created_at,
            'fecha_actualizacion' => $this->updated_at,
            'usuario_subio_archivo' => $usuarioSubio,
        ];
    }

    /**
     * Obtiene información completa de responsables relacionados.
     * Optimizado para usar relaciones ya cargadas con eager loading.
     * Opcionalmente usa totales de la vista si están disponibles (para evitar recálculo).
     *
     * @param int|null $totalResponsablesDesdeVista Total desde la vista SQL (opcional, evita recálculo)
     * @param int|null $totalCustodiosDesdeVista Total desde la vista SQL (opcional, evita recálculo)
     * @return array
     */
    public function getResponsablesInfo(?int $totalResponsablesDesdeVista = null, ?int $totalCustodiosDesdeVista = null): array
    {
        // Usar relación ya cargada si existe, sino cargar
        $responsablesRelacion = $this->relationLoaded('responsables') ? $this->responsables : $this->responsables()->with(['userCargo.user', 'userCargo.cargo'])->get();

        // Pre-inicializar collection (optimización de memoria)
        $responsablesInfo = collect();
        $totalCustodios = 0;

        foreach ($responsablesRelacion as $responsable) {
            $info = $responsable->getInfoResponsable();
            if ($info) {
                $responsablesInfo->push($info);
                // Solo contar si no tenemos el valor de la vista
                if ($totalCustodiosDesdeVista === null && !empty($info['custodio']) && $info['custodio']) {
                    $totalCustodios++;
                }
            }
        }

        // Usar totales de la vista si están disponibles (más eficiente)
        // Evitar count() si ya tenemos el valor de la vista
        $countResponsablesInfo = $responsablesInfo->count();
        $totalResponsablesFinal = $totalResponsablesDesdeVista ?? $countResponsablesInfo;
        $totalCustodiosFinal = $totalCustodiosDesdeVista ?? $totalCustodios;

        return [
            'responsables' => $responsablesInfo,
            'total_responsables' => $totalResponsablesFinal,
            'total_custodios' => $totalCustodiosFinal,
        ];
    }

    /**
     * Obtiene toda la información relacionada del radicado (documentos, responsables, usuarios).
     * Opcionalmente acepta totales desde la vista para evitar recálculos.
     *
     * @param int|null $totalResponsablesDesdeVista Total desde la vista SQL (opcional)
     * @param int|null $totalCustodiosDesdeVista Total desde la vista SQL (opcional)
     * @return array
     */
    public function getInformacionCompleta(?int $totalResponsablesDesdeVista = null, ?int $totalCustodiosDesdeVista = null): array
    {
        return [
            'documentos' => $this->getDocumentosRelacionados(),
            'usuario_creo_radicado' => $this->getInfoUsuarioCrea(),
            ...$this->getResponsablesInfo($totalResponsablesDesdeVista, $totalCustodiosDesdeVista),
        ];
    }

    /**
     * Scope para filtrar por estado activo.
     */
    public function scopeActivo($query)
    {
        return $query->where('estado', true);
    }

    /**
     * Scope para filtrar por estado inactivo.
     */
    public function scopeInactivo($query)
    {
        return $query->where('estado', false);
    }

    /**
     * Verifica si la radicación tiene archivos.
     */
    public function tieneArchivoDigital()
    {
        return !empty($this->archivo_digital);
    }

    /**
     * Verifica si la radicación tiene archivos (digital o adicionales).
     */
    public function tieneArchivos()
    {
        return $this->tieneArchivoDigital() || $this->archivos()->exists();
    }

    /**
     * Obtiene los días restantes para el vencimiento.
     */
    public function getDiasParaVencerAttribute()
    {
        if (!$this->fec_venci) {
            return null;
        }
        return now()->diffInDays($this->fec_venci, false);
    }

    /**
     * Verifica si la radicación está vencida.
     */
    public function isVencida()
    {
        if (!$this->fec_venci) {
            return false;
        }
        return now()->isAfter($this->fec_venci);
    }

    /**
     * Obtiene la URL del archivo asociado a la radicación.
     *
     * @return string|null
     */
    public function getUrlArchivoDigital()
    {
        return $this->getArchivoUrl('archivo_digital', 'radicados_recibidos');
    }

    /**
     * Obtiene la URL de cualquier archivo usando ArchivoHelper.
     * @param string $campo Nombre del atributo (ej: 'archivo_digital')
     * @param string $disk Nombre del disco
     * @return string|null
     */
    public function getArchivoUrl(string $campo, string $disk): ?string
    {
        return ArchivoHelper::obtenerUrl($this->{$campo} ?? null, $disk);
    }

    /**
     * Obtiene información del archivo asociado a la radicación.
     *
     * @return array|null
     */
    public function getInfoArchivoDigital()
    {
        if (!$this->archivo_digital) {
            return null;
        }

        return [
            'nombre' => basename($this->archivo_digital),
            'url' => $this->getUrlArchivoDigital(),
            'tamaño' => Storage::disk('radicados_recibidos')->size($this->archivo_digital),
            'tipo' => Storage::disk('radicados_recibidos')->mimeType($this->archivo_digital),
            'extension' => pathinfo($this->archivo_digital, PATHINFO_EXTENSION)
        ];
    }

    /**
     * Obtiene información formateada del usuario que creó el radicado.
     *
     * @return array|null
     */
    public function getInfoUsuarioCrea(): ?array
    {
        if (!$this->usuarioCreaRadicado) {
            return null;
        }
        return $this->usuarioCreaRadicado->getInfoUsuario();
    }

    /**
     * Obtiene información formateada del usuario que subió el archivo.
     *
     * @return array|null
     */
    public function getInfoUsuarioSubio(): ?array
    {
        if (!$this->usuarioSubio) {
            return null;
        }
        return $this->usuarioSubio->getInfoUsuario();
    }

    /**
     * Obtiene información básica de un archivo (sin acceder al filesystem).
     * Los metadatos del archivo (tamaño, tipo) se obtienen solo al descargar/ver detalles.
     *
     * @param string $campo Nombre del atributo del archivo
     * @param string $disk Nombre del disco
     * @param bool $incluirMetadatos Si es true, obtiene metadatos del filesystem (por defecto false)
     * @return array|null
     */
    public function getInfoArchivo(string $campo, string $disk, bool $incluirMetadatos = false): ?array
    {
        $rutaArchivo = $this->{$campo} ?? null;
        if (!$rutaArchivo) {
            return null;
        }

        $info = [
            'nombre' => basename($rutaArchivo),
            'ruta' => $rutaArchivo,
            'url' => $this->getArchivoUrl($campo, $disk),
            'extension' => pathinfo($rutaArchivo, PATHINFO_EXTENSION),
        ];

        // Solo acceder al filesystem si se solicita explícitamente (para descarga/detalles)
        if ($incluirMetadatos) {
            try {
                if (Storage::disk($disk)->exists($rutaArchivo)) {
                    $info['tamaño'] = Storage::disk($disk)->size($rutaArchivo);
                    $info['tipo'] = Storage::disk($disk)->mimeType($rutaArchivo);
                }
            } catch (\Exception $e) {
                // Si hay error al obtener información del archivo, continuar sin esos datos
            }
        }

        return $info;
    }
}
