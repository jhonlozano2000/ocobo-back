<?php

namespace App\Models\VentanillaUnica;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class VentanillaRadicaReciOptimizedView extends Model
{
    /**
     * La tabla asociada con el modelo (vista).
     */
    protected $table = 'ventanilla_radica_reci_view';

    /**
     * Indica si el modelo debe ser marcado con timestamps.
     */
    public $timestamps = false;

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'fec_venci' => 'date',
        'ultima_visualizacion' => 'datetime',
        'total_archivos' => 'integer',
        'total_responsables' => 'integer',
        'total_custodios' => 'integer',
    ];

    /**
     * Los atributos que son asignables en masa.
     */
    protected $fillable = [
        'id',
        'num_radicado',
        'created_at',
        'fec_venci',
        'asunto',
        'clasifica_documen_id',
        'tercero_id',
        'medio_recep_id',
        'config_server_id',
        'clasificacion_cod',
        'clasificacion_nom',
        'clasificacion_tipo',
        'clasificacion_parent_cod',
        'clasificacion_parent_nom',
        'clasificacion_parent_tipo',
        'clasificacion_grandparent_cod',
        'clasificacion_grandparent_nom',
        'clasificacion_grandparent_tipo',
        'tercero_tipo_documento',
        'tercero_numero_documento',
        'tercero_nombre_completo',
        'tercero_telefono',
        'tercero_email',
        'medio_recepcion_nombre',
        'servidor_host',
        'servidor_ruta',
        'servidor_detalle',
        'total_archivos',
        'total_responsables',
        'total_custodios',
        'total_custodios_activos',
        'total_custodios_con_nombres',
        'total_responsables_con_nombres',
        'archivos_nombres',
        'ultima_visualizacion',
    ];

    /**
     * Los atributos que deben estar ocultos para la serialización.
     */
    protected $hidden = [
        'servidor_host',
        'servidor_ruta',
        'total_custodios_activos',
    ];

    /**
     * Scope para búsqueda por texto en número de radicado y asunto.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('num_radicado', 'like', "%{$search}%")
                ->orWhere('asunto', 'like', "%{$search}%");
        });
    }

    /**
     * Scope para filtrar por rango de fechas.
     */
    public function scopeFechaEntre(Builder $query, ?string $fechaDesde, ?string $fechaHasta): Builder
    {
        if (empty($fechaDesde) || empty($fechaHasta)) {
            return $query;
        }

        return $query->whereBetween('created_at', [$fechaDesde, $fechaHasta]);
    }

    /**
     * Scope para filtrar por clasificación documental.
     */
    public function scopeClasificacionDocumental(Builder $query, ?int $clasificaDocumenId): Builder
    {
        if (empty($clasificaDocumenId)) {
            return $query;
        }

        return $query->where('clasifica_documen_id', $clasificaDocumenId);
    }

    /**
     * Scope para filtrar por tercero.
     */
    public function scopeTercero(Builder $query, ?int $terceroId): Builder
    {
        if (empty($terceroId)) {
            return $query;
        }

        return $query->where('tercero_id', $terceroId);
    }

    /**
     * Scope para filtrar por medio de recepción.
     */
    public function scopeMedioRecepcion(Builder $query, ?int $medioRecepId): Builder
    {
        if (empty($medioRecepId)) {
            return $query;
        }

        return $query->where('medio_recep_id', $medioRecepId);
    }

    /**
     * Scope para ordenar por fecha de creación (más recientes primero).
     */
    public function scopeOrdenadoPorFecha(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Scope para radicados con archivos adjuntos.
     */
    public function scopeConArchivos(Builder $query): Builder
    {
        return $query->where('total_archivos', '>', 0);
    }

    /**
     * Scope para radicados sin archivos adjuntos.
     */
    public function scopeSinArchivos(Builder $query): Builder
    {
        return $query->where('total_archivos', 0);
    }

    /**
     * Scope para radicados vencidos.
     */
    public function scopeVencidos(Builder $query): Builder
    {
        return $query->whereNotNull('fec_venci')
            ->where('fec_venci', '<', now());
    }

    /**
     * Scope para radicados próximos a vencer (en los próximos N días).
     */
    public function scopeProximosAVencer(Builder $query, int $dias = 7): Builder
    {
        return $query->whereNotNull('fec_venci')
            ->whereBetween('fec_venci', [now(), now()->addDays($dias)]);
    }

    /**
     * Accessor para obtener la jerarquía completa de clasificación documental.
     */
    public function getClasificacionJerarquiaCompletaAttribute(): string
    {
        $jerarquia = [];

        if ($this->clasificacion_grandparent_nom) {
            $jerarquia[] = $this->clasificacion_grandparent_cod . ' - ' . $this->clasificacion_grandparent_nom;
        }

        if ($this->clasificacion_parent_nom) {
            $jerarquia[] = $this->clasificacion_parent_cod . ' - ' . $this->clasificacion_parent_nom;
        }

        if ($this->clasificacion_nom) {
            $jerarquia[] = $this->clasificacion_cod . ' - ' . $this->clasificacion_nom;
        }

        return implode(' > ', $jerarquia);
    }

    /**
     * Accessor para obtener el nombre completo del tercero.
     */
    public function getTerceroCompletoAttribute(): string
    {
        return $this->tercero_documento . ' - ' . $this->tercero_nombre;
    }

    /**
     * Accessor para verificar si el radicado está vencido.
     */
    public function getEstaVencidoAttribute(): bool
    {
        return $this->fec_venci && $this->fec_venci < now();
    }

    /**
     * Accessor para obtener los días restantes hasta el vencimiento.
     */
    public function getDiasParaVencimientoAttribute(): ?int
    {
        if (!$this->fec_venci) {
            return null;
        }

        return now()->diffInDays($this->fec_venci, false);
    }

    /**
     * Accessor para verificar si tiene archivos adjuntos.
     */
    public function getTieneArchivosAttribute(): bool
    {
        return $this->total_archivos > 0;
    }

    /**
     * Accessor para verificar si ha sido visualizado.
     */
    public function getHaSidoVisualizadoAttribute(): bool
    {
        return !is_null($this->ultima_visualizacion);
    }

    /**
     * Accessor para obtener la lista de archivos como array.
     */
    public function getArchivosListaAttribute(): array
    {
        if (empty($this->archivos_nombres)) {
            return [];
        }

        return array_filter(explode(', ', $this->archivos_nombres));
    }

    /**
     * Accessor para obtener información de responsables custodios.
     */
    public function getCustodiosInfoAttribute(): array
    {
        return [
            'total' => $this->total_custodios_activos,
            'con_nombres' => $this->total_custodios_con_nombres,
            'tiene_custodios' => $this->total_custodios_activos > 0
        ];
    }

    /**
     * Accessor para obtener información de todos los responsables.
     */
    public function getResponsablesInfoAttribute(): array
    {
        return [
            'total' => $this->total_responsables,
            'con_nombres' => $this->total_responsables_con_nombres,
            'tiene_responsables' => $this->total_responsables > 0
        ];
    }

    /**
     * Accessor para obtener información completa de archivos.
     */
    public function getArchivosInfoAttribute(): array
    {
        return [
            'total' => $this->total_archivos,
            'nombres' => $this->archivos_lista,
            'tiene_archivos' => $this->tiene_archivos
        ];
    }
}
