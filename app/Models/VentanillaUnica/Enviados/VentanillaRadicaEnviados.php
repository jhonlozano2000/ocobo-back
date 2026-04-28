<?php

namespace App\Models\VentanillaUnica\Enviados;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentanillaRadicaEnviados extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_enviados';

    protected $fillable = [
        'num_radicado',
        'clasifica_documen_id',
        'usuario_crea',
        'tercero_id',
        'medio_enviado_id',
        'config_server_id',
        'tipo_respuesta_id',
        'subido_por',
        'fec_docu',
        'fec_venci',
        'num_folios',
        'num_anexos',
        'descrip_anexos',
        'asunto',
        'radicado_respuesta',
        'archivo_digital',
        'hash_sha256',
        'archivo_tipo',
        'archivo_peso',
        'nom_origi',
        'impri_rotulo',
        'es_pdf_a',
        'pdf_a_nivel',
        'ocr',
        'ocr_aplicado',
        'estado_trabajo',
        'usua_soli_anula_id',
        'observa_soli_anula',
        'usua_aprue_anula_id',
        'observa_aprue_anula',
    ];

    /**
     * Clasificación documental (recursiva: Serie > SubSerie > TipoDocumento).
     * Para cargar con jerarquía completa usar: load(['clasificacionDocumental' => fn($q) => $q->with(['parent' => fn($q) => $q->with('parent')])])
     */
    public function clasificacionDocumental()
    {
        return $this->belongsTo(\App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD::class, 'clasifica_documen_id');
    }

    /**
     * Carga clasificación documental con jerarquía completa (evita N+1 en getJerarquia).
     */
    public function loadClasificacionConJerarquia()
    {
        return $this->load(['clasificacionDocumental' => fn($q) => $q->with(['parent' => fn($q) => $q->with('parent')])]);
    }

    /**
     * Obtiene la clasificación documental con su jerarquía recursiva.
     */
    public function getClasificacionDocumentalInfo(): ?array
    {
        $clasif = $this->clasificacionDocumental;
        if (!$clasif) {
            return null;
        }
        return [
            'id' => $clasif->id,
            'cod' => $clasif->cod,
            'nom' => $clasif->nom,
            'tipo' => $clasif->tipo,
            'jerarquia' => $clasif->getJerarquia(),
            'codigo_completo' => $clasif->getCodigoCompleto(),
            'nombre_completo' => $clasif->getNombreCompleto(),
        ];
    }

    public function usuarioCreaRadicado()
    {
        return $this->belongsTo(\App\Models\User::class, 'usuario_crea');
    }

    public function usuario_soli_anula()
    {
        return $this->belongsTo(\App\Models\User::class, 'usua_soli_anula_id');
    }

    public function usuario_aprue_anula()
    {
        return $this->belongsTo(\App\Models\User::class, 'usua_aprue_anula_id');
    }

    public function tercero()
    {
        return $this->belongsTo(\App\Models\Gestion\GestionTercero::class, 'tercero_id');
    }

    public function terceroEnviado()
    {
        return $this->belongsTo(\App\Models\Gestion\GestionTercero::class, 'tercero_id');
    }

    public function medioEnvio()
    {
        return $this->belongsTo(\App\Models\Configuracion\ConfigListaDetalle::class, 'medio_enviado_id');
    }

    public function servidorArchivos()
    {
        return $this->belongsTo(\App\Models\Configuracion\ConfigServerArchivo::class, 'config_server_id');
    }

    public function tipoRespuesta()
    {
        return $this->belongsTo(\App\Models\Configuracion\ConfigListaDetalle::class, 'tipo_respuesta_id');
    }

    public function usuarioSubio()
    {
        return $this->belongsTo(\App\Models\User::class, 'subido_por');
    }

    /**
     * Expedientes a los que este radicado ha sido incorporado.
     */
    public function expedientes()
    {
        return $this->morphToMany(
            \App\Models\OfiArchivo\OfiArchivoExpediente::class,
            'documentable',
            'ofi_archivo_expedientes_documentos',
            'documentable_id',
            'expediente_id'
        )->withPivot('numero_folio', 'fecha_incorporacion');
    }

    public function responsables()
    {
        return $this->hasMany(VentanillaRadicaEnviadosRespona::class, 'radica_enviado_id');
    }

    public function usuariosResponsables()
    {
        return $this->belongsToMany(\App\Models\ControlAcceso\UserCargo::class, 'ventanilla_radica_enviados_responsa', 'radica_enviado_id', 'users_cargos_id')
            ->withPivot('custodio', 'fechor_visto')
            ->withTimestamps();
    }

    public function respuestas()
    {
        return $this->hasMany(VentanillaRadicaEnviadosRespuestas::class, 'radica_enviado_id');
    }

    public function usuariosRespuestas()
    {
        return $this->belongsToMany(\App\Models\ControlAcceso\UserCargo::class, 'ventanilla_radica_enviados_respuestas', 'radica_enviado_id', 'users_cargos_id')
            ->withTimestamps();
    }

    public function firmas()
    {
        return $this->hasMany(VentanillaRadicaEnviadosFirmas::class, 'radica_enviado_id');
    }

    public function usuariosFirmas()
    {
        return $this->belongsToMany(\App\Models\ControlAcceso\UserCargo::class, 'ventanilla_radica_enviados_firmas', 'radica_enviado_id', 'users_cargos_id')
            ->withTimestamps();
    }

    public function proyectores()
    {
        return $this->hasMany(VentanillaRadicaEnviadosProyectores::class, 'radica_enviado_id');
    }

    public function usuariosProyectores()
    {
        return $this->belongsToMany(\App\Models\ControlAcceso\UserCargo::class, 'ventanilla_radica_enviados_proyectores', 'radica_enviado_id', 'users_cargos_id')
            ->withTimestamps();
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
                if (!empty($info['custodio']) && $info['custodio']) {
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

    public function getInfoUsuarioCrea(): ?array
    {
        return $this->usuarioCreaRadicado?->getInfoUsuario();
    }

    public function getInfoUsuarioSubio(): ?array
    {
        return $this->usuarioSubio?->getInfoUsuario();
    }

    public function archivos()
    {
        return $this->hasMany(VentanillaRadicaEnviadosArchivos::class, 'radica_enviado_id');
    }

    public function metadatos()
    {
        return $this->hasMany(VentanillaRadicaEnviadosMetadata::class, 'radicado_id');
    }

    public function getArchivosInfo(bool $incluirMetadatos = false): array
    {
        $archivosRelacion = $this->relationLoaded('archivos') ? $this->archivos : $this->archivos()->with(['usuarioSubio'])->get();

        $archivosInfo = [];
        foreach ($archivosRelacion as $archivo) {
            $info = $archivo->getInfoArchivo($incluirMetadatos);
            if ($info) {
                $archivosInfo[] = $info;
            }
        }
        return $archivosInfo;
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
        return $query->where('fec_venci', '<', now()->toDateString());
    }

    public function scopeProximosAVencer($query, int $dias = 5)
    {
        return $query->whereBetween('fec_venci', [now()->toDateString(), now()->addDays($dias)->toDateString()]);
    }

    // ================ ESTADO DE TRABAJO ================

    public function actualizarEstadoTrabajo(): bool
    {
        $nuevoEstado = $this->calcularEstadoTrabajo();

        if ($this->estado_trabajo !== $nuevoEstado) {
            $this->update(['estado_trabajo' => $nuevoEstado]);
            return true;
        }

        return false;
    }

    public function calcularEstadoTrabajo(): string
    {
        if ($this->fec_venci && now()->parse($this->fec_venci)->isBefore(now()->startOfDay())) {
            return \App\Services\VentanillaUnica\RadicadoEstadoTrabajoService::ESTADO_VENCIDO;
        }

        if ($this->fec_venci && now()->parse($this->fec_venci)->lte(now()->addDays(5)->endOfDay())) {
            return \App\Services\VentanillaUnica\RadicadoEstadoTrabajoService::ESTADO_POR_VENCER;
        }

        if ($this->responsables()->exists()) {
            return \App\Services\VentanillaUnica\RadicadoEstadoTrabajoService::ESTADO_EN_PROCESO;
        }

        return \App\Services\VentanillaUnica\RadicadoEstadoTrabajoService::ESTADO_RECIBIDO;
    }

    public function getEstadoTrabajoInfo(): array
    {
        $service = new \App\Services\VentanillaUnica\RadicadoEstadoTrabajoService();
        return $service->getEstadoInfo($this->estado_trabajo ?? \App\Services\VentanillaUnica\RadicadoEstadoTrabajoService::ESTADO_RECIBIDO);
    }

    // ================ ARCHIVOS ================

    public function tieneArchivoDigital()
    {
        return !empty($this->archivo_digital);
    }

    public function tieneArchivos()
    {
        return $this->tieneArchivoDigital() || $this->archivos()->exists();
    }

    public function getDiasParaVencerAttribute()
    {
        if (!$this->fec_venci) {
            return null;
        }
        return now()->diffInDays($this->fec_venci, false);
    }

    public function isVencida()
    {
        if (!$this->fec_venci) {
            return false;
        }
        return now()->isAfter($this->fec_venci);
    }

    public function getUrlArchivoDigital()
    {
        return $this->getArchivoUrl('archivo_digital', 'radicados_enviados');
    }

    public function getArchivoUrl(string $campo, string $disk): ?string
    {
        return \App\Helpers\ArchivoHelper::obtenerUrl($this->{$campo} ?? null, $disk);
    }

    public function getInfoArchivoDigital()
    {
        if (!$this->archivo_digital) {
            return null;
        }

        return [
            'nombre' => $this->nom_origi ?: basename($this->archivo_digital),
            'ruta' => $this->archivo_digital,
            'url' => $this->getUrlArchivoDigital(),
            'tamaño' => $this->archivo_peso,
            'tipo' => $this->archivo_tipo,
            'extension' => pathinfo($this->archivo_digital, PATHINFO_EXTENSION)
        ];
    }

    public function getInformacionCompleta(bool $incluirMetadatosArchivos = false): array
    {
        return [
            'documentos' => $this->getArchivosInfo($incluirMetadatosArchivos),
            'usuario_creo_radicado' => $this->getInfoUsuarioCrea(),
            ...$this->getResponsablesInfo(),
        ];
    }
}
