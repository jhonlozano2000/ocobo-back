<?php

namespace App\Models\VentanillaUnica\Internos;

use App\Helpers\ArchivoHelper;
use App\Helpers\CalendarioHelper;
use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\MiBandeja\MiBandejaTemp;
use App\Models\Transversal\FirmaEvento;
use App\Models\User;
use App\Services\MiBandeja\MiBandejaFiltroService;
use App\Services\VentanillaUnica\RadicadoEstadoTrabajoService;
use App\Traits\AbacHierarquico;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class VentanillaRadicaInterno extends Model
{
    use AbacHierarquico, HasFactory;

    protected $table = 'ventanilla_radica_internos';

    // M03-03: visibles para creador, responsables, destinatarios, proyectores, firmantes y la jerarquía del creador
    protected const ABAC_USER_COLUMN = 'usuario_crea';

    protected const ABAC_RESPONSABLES_RELATION = 'responsables';

    protected const ABAC_VER_TODOS_PERMISO = 'Radicar -> Cores. Interna -> Ver Todos';

    public static function accesiblePara(User $user, int $id): bool
    {
        return static::conPermisoJerarquico($user)->whereKey($id)->exists();
    }

    /**
     * Cláusulas ABAC adicionales (AbacHierarquico): destinatarios, proyectores y firmantes.
     */
    protected function abacAccesosAdicionales($q, User $user): void
    {
        foreach (['destinatarios', 'proyectores'] as $relacion) {
            $q->orWhereHas($relacion, fn ($subQ) => $subQ->whereHas('userCargo', fn ($uc) => $uc->where('user_id', $user->id)));
        }
        $q->orWhereHas('firmantes', fn ($subQ) => $subQ->where('users_id', $user->id));
    }

    protected $fillable = [
        'num_radicado',
        'clasifica_documen_id',
        'usuario_crea',
        'fec_docu',
        'fec_venci',
        'num_folios',
        'num_anexos',
        'descrip_anexos',
        'asunto',
        'archivo_digital',
        'hash_sha256',
        'archivo_tipo',
        'archivo_peso',
        'nom_origi',
        'subido_por',
        'es_pdf_a',
        'pdf_a_nivel',
        'ocr',
        'ocr_aplicado',
        'estado_trabajo',
        'usua_soli_anula_id',
        'observa_soli_anula',
        'usua_aprue_anula_id',
        'observa_aprue_anula',
        'estado_firma',
        'fecha_firma',
        'dependencia_origen_id',
    ];

    protected $casts = [
        'fecha_firma' => 'datetime',
    ];

    /**
     * Clasificación documental (recursiva: Serie > SubSerie > TipoDocumento).
     * Para cargar con jerarquía completa usar: load(['clasificacionDocumental' => fn($q) => $q->with(['parent' => fn($q) => $q->with('parent')])])
     */
    public function clasificacionDocumental()
    {
        return $this->belongsTo(ClasificacionDocumentalTRD::class, 'clasifica_documen_id');
    }

    /**
     * Usuario que creó la radicación interna.
     */
    public function usuarioCrea()
    {
        return $this->belongsTo(User::class, 'usuario_crea');
    }

    /**
     * Alias de usuarioCrea usado por el filtrado jerárquico de AbacHierarquico.
     */
    public function usuarioCreaRadicado()
    {
        return $this->belongsTo(User::class, 'usuario_crea');
    }

    /**
     * Dependencia de origen.
     */
    public function dependenciaOrigen()
    {
        return $this->belongsTo(CalidadOrganigrama::class, 'dependencia_origen_id');
    }

    /**
     * Atributo compuesto: prefiere la relación guardada, fallback a inferencia del usuario creador.
     */
    public function getDependenciaOrigenAttribute()
    {
        if ($this->relationLoaded('dependenciaOrigen')) {
            $dep = $this->getRelation('dependenciaOrigen');

            return $dep ? ['id' => $dep->id, 'nombre' => $dep->nom_organico] : null;
        }

        if ($this->dependencia_origen_id) {
            $dep = $this->dependenciaOrigen;

            return $dep ? ['id' => $dep->id, 'nombre' => $dep->nom_organico] : null;
        }

        $cargoActivo = $this->usuarioCrea?->cargoActivo;
        if (! $cargoActivo?->cargo) {
            return null;
        }

        $node = $cargoActivo->cargo;
        $visited = 0;
        while ($node && $node->tipo !== 'Dependencia' && $visited < 20) {
            $parentId = $node->getAttribute('parent');
            $node = $parentId ? $node->parent()->first() : null;
            $visited++;
        }

        return $node ? ['id' => $node->id, 'nombre' => $node->nom_organico] : null;
    }

    public function usuarioSubido()
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    public function usuario_soli_anula()
    {
        return $this->belongsTo(User::class, 'usua_soli_anula_id');
    }

    public function usuario_aprue_anula()
    {
        return $this->belongsTo(User::class, 'usua_aprue_anula_id');
    }

    /**
     * Destinatarios de la radicación interna.
     */
    public function destinatarios()
    {
        return $this->hasMany(VentanillaRadicaInternoDestinatarios::class, 'radica_interno_id');
    }

    /**
     * Responsables de la radicación interna.
     */
    public function responsables()
    {
        return $this->hasMany(VentanillaRadicaInternoResponsable::class, 'radica_interno_id');
    }

    /**
     * Proyectores de la radicación interna.
     */
    public function proyectores()
    {
        return $this->hasMany(VentanillaRadicaInternoProyectores::class, 'radica_interno_id');
    }

    /**
     * Firmantes de la radicación interna.
     */
    public function firmantes()
    {
        return $this->hasMany(VentanillaRadicaInternoFirmantes::class, 'radica_interno_id');
    }

    /**
     * Respuestas (radicados recibidos asociados) de la radicación interna.
     */
    public function respuestas()
    {
        return $this->hasMany(VentanillaRadicaInternoRespuesta::class, 'radica_interno_id');
    }

    /**
     * Historial de cambios de clasificación documental.
     */
    public function historialClasificacion()
    {
        return $this->hasMany(VentanillaRadicaInternosHistorialClasificacionDocumental::class, 'radica_interno_id');
    }

    /**
     * Archivos de la radicación interna.
     */
    public function archivos()
    {
        return $this->hasMany(VentanillaRadicaInternoArchivos::class, 'radicado_id');
    }

    /**
     * Eventos de firma electrónica asociados a la radicación interna.
     */
    public function firmaEventos()
    {
        return $this->morphMany(FirmaEvento::class, 'documentable');
    }

    /**
     * Obtiene la línea de tiempo de la radicación interna.
     */
    public function getLineaTiempo(): array
    {
        return [
            'fecha_creacion' => $this->created_at,
            'fecha_actualizacion' => $this->updated_at,
            'fecha_eliminacion' => $this->deleted_at,
        ];
    }

    /**
     * Obtiene los documentos relacionados con la radicación interna (archivo principal y adicionales).
     */
    public function getDocumentosRelacionados(bool $incluirMetadatos = false): array
    {
        $archivoPrincipal = null;
        if ($this->archivo_digital) {
            $archivoPrincipal = $this->getInfoArchivo($incluirMetadatos);
            if ($archivoPrincipal && $this->usuarioSubido) {
                $archivoPrincipal['subido_por'] = trim($this->usuarioSubido->nombres.' '.($this->usuarioSubido->apellidos ?? ''));
            }
        }

        $archivosRelacion = $this->relationLoaded('archivos') ? $this->archivos : $this->archivos()->get();
        $archivosAdicionales = collect();

        foreach ($archivosRelacion as $archivo) {
            $info = $archivo->getInfoArchivo($incluirMetadatos);
            if ($info) {
                $info['fecha_subida'] = $archivo->created_at;
                $archivosAdicionales->push($info);
            }
        }

        $countArchivosAdicionales = $archivosAdicionales->count();
        $totalArchivos = ($archivoPrincipal ? 1 : 0) + $countArchivosAdicionales;

        return [
            'archivo_principal' => $archivoPrincipal,
            'archivos_adicionales' => $archivosAdicionales,
            'total_archivos' => $totalArchivos,
            'tiene_archivo_principal' => $archivoPrincipal !== null,
            'tiene_archivos_adicionales' => $countArchivosAdicionales > 0,
            'fecha_creacion' => $this->created_at,
            'fecha_actualizacion' => $this->updated_at,
        ];
    }

    /**
     * Carga la clasificación documental con su jerarquía recursiva.
     */
    public function loadClasificacionConJerarquia()
    {
        return $this->load(['clasificacionDocumental' => fn ($q) => $q->with(['parent' => fn ($q) => $q->with('parent')])]);
    }

    /**
     * Obtiene la clasificación documental con su jerarquía recursiva.
     */
    public function getClasificacionDocumentalInfo(): ?array
    {
        $clasif = $this->clasificacionDocumental;
        if (! $clasif) {
            return null;
        }

        return [
            'id' => $clasif->id,
            'cod' => $clasif->cod,
            'nom' => $clasif->nom,
            'tipo' => $clasif->tipo,
            'dependencia_id' => $clasif->dependencia_id,
            'jerarquia' => $clasif->getJerarquia(),
            'codigo_completo' => $clasif->getCodigoCompleto(),
            'nombre_completo' => $clasif->getNombreCompleto(),
        ];
    }

    /**
     * Obtiene la información de un archivo de la radicación interna.
     */
    public function getInfoArchivo(bool $incluirMetadatos = false): ?array
    {
        $info = [
            'nombre' => $this->nom_origi ?: basename($this->archivo_digital),
            'ruta' => $this->archivo_digital,
            'url' => $this->getUrlArchivoDigital(),
            'extension' => pathinfo($this->archivo_digital, PATHINFO_EXTENSION),
        ];

        // Solo acceder al filesystem si se solicita explícitamente (para descarga/detalles)
        if ($incluirMetadatos) {
            try {
                if (Storage::disk('radicados_internos')->exists($this->archivo_digital)) {
                    $info['tamaño'] = Storage::disk('radicados_internos')->size($this->archivo_digital);
                    $info['tipo'] = Storage::disk('radicados_internos')->mimeType($this->archivo_digital);
                }
            } catch (\Exception $e) {
                // Si hay error al obtener información del archivo, continuar sin esos datos
            }
        }

        return $info;
    }

    // ================ SCOPES ================

    public function scopeActivo($query)
    {
        return $query->where('estado', true);
    }

    public function scopeInactivo($query)
    {
        return $query->where('estado', false);
    }

    public function scopeEstadoTrabajo($query, string $estado)
    {
        return $query->where('estado_trabajo', $estado);
    }

    public function scopeVencidos($query)
    {
        return $query->where('fec_venci', '<', CalendarioHelper::hoy()->toDateString());
    }

    public function scopeProximosAVencer($query, int $dias = 5)
    {
        return $query->whereBetween('fec_venci', [CalendarioHelper::hoy()->toDateString(), CalendarioHelper::hoy()->addDays($dias)->toDateString()]);
    }

    // ================ ESTADO DE TRABAJO ================

    public function actualizarEstadoTrabajo(): bool
    {
        $nuevoEstado = $this->calcularEstadoTrabajo();

        if ($this->estado_trabajo !== $nuevoEstado) {
            $this->updateQuietly(['estado_trabajo' => $nuevoEstado]);

            return true;
        }

        return false;
    }

    public function calcularEstadoTrabajo(): ?string
    {
        if (! $this->fec_venci) {
            return null;
        }

        $grupo = MiBandejaTemp::where('radicado_id', $this->id)
            ->where('radicado_tipo', 'interno')
            ->first();

        if ($grupo && $grupo->estado_grupo === 'finalizado') {
            return RadicadoEstadoTrabajoService::ESTADO_TRAMITADO;
        }

        if ($grupo) {
            return RadicadoEstadoTrabajoService::ESTADO_EN_PROCESO;
        }

        return RadicadoEstadoTrabajoService::ESTADO_RECIBIDO;
    }

    public function getEstadoTrabajoInfo(): array
    {
        $service = new RadicadoEstadoTrabajoService;

        return $service->getEstadoInfo($this->estado_trabajo ?? RadicadoEstadoTrabajoService::ESTADO_RECIBIDO);
    }

    // ================ ARCHIVOS ================

    public function tieneArchivoDigital()
    {
        return ! empty($this->archivo_digital);
    }

    public function tieneArchivos()
    {
        return $this->tieneArchivoDigital() || $this->archivos()->exists();
    }

    public function getDiasParaVencerAttribute()
    {
        if (! $this->fec_venci) {
            return null;
        }

        return CalendarioHelper::diasHasta($this->fec_venci);
    }

    public function isVencida()
    {
        if (! $this->fec_venci) {
            return false;
        }

        return CalendarioHelper::vencida($this->fec_venci);
    }

    public function getUrlArchivoDigital()
    {
        return $this->getArchivoUrl('archivo_digital', 'radicados_internos');
    }

    public function getArchivoUrl(string $campo, string $disk): ?string
    {
        return ArchivoHelper::obtenerUrl($this->{$campo} ?? null, $disk);
    }

    public function getInfoArchivoDigital()
    {
        if (! $this->archivo_digital) {
            return null;
        }

        return [
            'nombre' => basename($this->archivo_digital),
            'ruta' => $this->archivo_digital,
            'url' => $this->getUrlArchivoDigital(),
            'tamaño' => $this->archivo_peso,
            'tipo' => $this->archivo_tipo,
            'extension' => pathinfo($this->archivo_digital, PATHINFO_EXTENSION),
        ];
    }

    public function getInformacionCompleta(bool $incluirMetadatosArchivos = false): array
    {
        return [
            'documentos' => $this->getDocumentosRelacionados($incluirMetadatosArchivos),
            'usuario_creo_radicado' => $this->getInfoUsuarioCrea(),
            ...$this->getResponsablesInfo(),
        ];
    }

    public function getInfoUsuarioCrea(): ?array
    {
        return $this->usuarioCrea?->getInfoUsuario();
    }

    public function getResponsablesInfo(): array
    {
        $responsablesRelacion = $this->relationLoaded('responsables') ? $this->responsables : $this->responsables()->with(['userCargo.user', 'userCargo.cargo'])->get();

        $responsablesInfo = collect();
        $totalCustodios = 0;

        foreach ($responsablesRelacion as $responsable) {
            $info = $responsable->getInfoResponsable();
            if ($info) {
                $responsablesInfo->push($info);
                if (! empty($info['custodio']) && $info['custodio']) {
                    $totalCustodios++;
                }
            }
        }

        return [
            'responsables' => $responsablesInfo,
            'total_responsables' => $responsablesInfo->count(),
            'total_custodios' => $totalCustodios,
        ];
    }

    /**
     * Determina si un usuario tiene acceso para consultar este radicado interno.
     * Acceso otorgado si:
     * - Es el usuario creador del radicado.
     * - Es un responsable asignado (activo).
     * - Es un destinatario asignado.
     * - Es un proyector asignado.
     * - Es un firmante asignado.
     * - Pertenece a la misma dependencia/oficina según jerarquía de Mi Bandeja (si aplica).
     */
    public function tieneAccesoUsuario(int|User $user): bool
    {
        $userId = $user instanceof User ? $user->id : (int) $user;

        if ((int) $this->usuario_crea === $userId) {
            return true;
        }

        // Responsable
        $esResponsable = $this->responsables()
            ->whereHas('userCargo', fn ($q) => $q->where('user_id', $userId))
            ->exists();
        if ($esResponsable) {
            return true;
        }

        // Destinatario
        $esDestinatario = $this->destinatarios()
            ->whereHas('userCargo', fn ($q) => $q->where('user_id', $userId))
            ->exists();
        if ($esDestinatario) {
            return true;
        }

        // Proyector
        $esProyector = $this->proyectores()
            ->whereHas('userCargo', fn ($q) => $q->where('user_id', $userId))
            ->exists();
        if ($esProyector) {
            return true;
        }

        // Firmante
        $esFirmante = DB::table('ventanilla_radica_internos_firmantes')
            ->where('radica_interno_id', $this->id)
            ->where('users_id', $userId)
            ->exists();
        if ($esFirmante) {
            return true;
        }

        // Jerarquía de Mi Bandeja (si es jefe de dependencia u oficina)
        $userIdsJerarquia = MiBandejaFiltroService::getUserIdsParaFiltro($userId);
        if ($userIdsJerarquia) {
            $responsableEnJerarquia = $this->responsables()
                ->whereHas('userCargo', fn ($q) => $q->whereIn('user_id', $userIdsJerarquia))
                ->exists();
            if ($responsableEnJerarquia) {
                return true;
            }
        }

        return false;
    }
}
