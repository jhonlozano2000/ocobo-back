<?php

namespace App\Models\VentanillaUnica\Internos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class VentanillaRadicaInterno extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_internos';

    protected $fillable = [
        'num_radicado',
        'clasifica_documen_id',
        'usuario_crea',
        'fec_docu',
        'fec_venci',
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
     * Usuario que creó la radicación interna.
     */
    public function usuarioCrea()
    {
        return $this->belongsTo(\App\Models\User::class, 'usuario_crea');
    }

    public function usuarioSubido()
    {
        return $this->belongsTo(\App\Models\User::class, 'subido_por');
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
        return $this->hasMany(VentanillaRadicaInternoResponsa::class, 'radica_interno_id');
    }

    /**
     * Proyectores de la radicación interna.
     */
    public function proyectores()
    {
        return $this->hasMany(VentanillaRadicaInternoProyectores::class, 'radica_interno_id');
    }

    /**
     * Archivos de la radicación interna.
     */
    public function archivos()
    {
        return $this->hasMany(VentanillaRadicaInternoArchivos::class, 'radica_interno_id');
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
     * Obtiene los documentos relacionados con la radicación interna.
     */
    public function getDocumentosRelacionados(bool $incluirMetadatos = false): array
    {
        $documentos = [];
        foreach ($this->archivos as $archivo) {
            $documentos[] = $archivo->getInfoArchivo($incluirMetadatos);
        }
        return $documentos;
    }

    /**
     * Carga la clasificación documental con su jerarquía recursiva.
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

    /**
     * Obtiene la información de un archivo de la radicación interna.
     */
    public function getInfoArchivo(bool $incluirMetadatos = false): ?array
    {
        $info = [
            'nombre' => basename($this->archivo_digital),
            'ruta' => $this->archivo_digital,
            'url' => $this->getArchivoUrl(),
            'extension' => pathinfo($this->archivo_digital, PATHINFO_EXTENSION),
        ];

        // Solo acceder al filesystem si se solicita explícitamente (para descarga/detalles)
        if ($incluirMetadatos) {
            try {
                if (Storage::disk('ventanilla_radica_interno_archivos')->exists($this->archivo_digital)) {
                    $info['tamaño'] = Storage::disk('ventanilla_radica_interno_archivos')->size($this->archivo_digital);
                    $info['tipo'] = Storage::disk('ventanilla_radica_interno_archivos')->mimeType($this->archivo_digital);
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
        return $this->getArchivoUrl('archivo_digital', 'ventanilla_radica_interno_archivos');
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
            'nombre' => basename($this->archivo_digital),
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
}
