<?php

namespace App\Models\ClasificacionDocumental;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClasificacionDocumentalTRDVersion extends Model
{
    use HasFactory;

    protected $table = 'clasificacion_documental_trd_versiones';

    protected $fillable = [
        'dependencia_id',
        'version',
        'estado_version',
        'observaciones',
        'aprobado_por',
        'fecha_aprobacion',
        'user_register'
    ];

    protected $casts = [
        'fecha_aprobacion' => 'datetime',
    ];

    /**
     * Relación con la dependencia.
     */
    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(CalidadOrganigrama::class, 'dependencia_id');
    }

    /**
     * Relación con el usuario que aprobó la versión.
     */
    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    /**
     * Relación con el usuario que registró la versión.
     */
    public function userRegister(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_register');
    }

    /**
     * Relación con los elementos TRD de esta versión.
     */
    public function trds(): HasMany
    {
        return $this->hasMany(ClasificacionDocumentalTRD::class, 'version_id');
    }

    // ==================== SCOPES ====================

    /**
     * Scope para filtrar por dependencia.
     */
    public function scopePorDependencia($query, int $dependenciaId)
    {
        return $query->where('dependencia_id', $dependenciaId);
    }

    /**
     * Scope para filtrar por estado de versión.
     */
    public function scopeEstado($query, string $estado)
    {
        return $query->where('estado_version', $estado);
    }

    /**
     * Scope para filtrar versiones activas.
     */
    public function scopeActivas($query)
    {
        return $query->where('estado_version', 'ACTIVO');
    }

    /**
     * Scope para filtrar versiones temporales.
     */
    public function scopeTemporales($query)
    {
        return $query->where('estado_version', 'TEMP');
    }

    /**
     * Scope para filtrar versiones históricas.
     */
    public function scopeHistoricas($query)
    {
        return $query->where('estado_version', 'HISTORICO');
    }

    /**
     * Scope para filtrar por rango de fechas.
     */
    public function scopePorFecha($query, $fechaInicio, $fechaFin = null)
    {
        $query->where('created_at', '>=', $fechaInicio);

        if ($fechaFin) {
            $query->where('created_at', '<=', $fechaFin);
        }

        return $query;
    }

    /**
     * Scope para ordenar por versión descendente.
     */
    public function scopeUltimaVersion($query)
    {
        return $query->orderBy('version', 'desc');
    }

    // ==================== MÉTODOS HELPER ====================

    /**
     * Verifica si la versión está activa.
     */
    public function isActiva(): bool
    {
        return $this->estado_version === 'ACTIVO';
    }

    /**
     * Verifica si la versión está temporal.
     */
    public function isTemporal(): bool
    {
        return $this->estado_version === 'TEMP';
    }

    /**
     * Verifica si la versión está histórica.
     */
    public function isHistorica(): bool
    {
        return $this->estado_version === 'HISTORICO';
    }

    /**
     * Verifica si la versión está aprobada.
     */
    public function isAprobada(): bool
    {
        return !is_null($this->aprobado_por);
    }

    /**
     * Obtiene el estado formateado de la versión.
     */
    public function getEstadoFormateado(): string
    {
        return match ($this->estado_version) {
            'ACTIVO' => 'Activa',
            'TEMP' => 'Temporal',
            'HISTORICO' => 'Histórica',
            default => 'Desconocido'
        };
    }

    /**
     * Obtiene estadísticas de la versión.
     */
    public function getEstadisticas(): array
    {
        $trds = $this->trds;

        return [
            'total_elementos' => $trds->count(),
            'total_series' => $trds->where('tipo', 'Serie')->count(),
            'total_subseries' => $trds->where('tipo', 'SubSerie')->count(),
            'total_tipos_documento' => $trds->where('tipo', 'TipoDocumento')->count(),
            'elementos_raiz' => $trds->whereNull('parent')->count(),
        ];
    }

    /**
     * Obtiene la versión anterior de la misma dependencia.
     */
    public function getVersionAnterior(): ?self
    {
        return static::where('dependencia_id', $this->dependencia_id)
            ->where('version', '<', $this->version)
            ->orderBy('version', 'desc')
            ->first();
    }

    /**
     * Obtiene la versión siguiente de la misma dependencia.
     */
    public function getVersionSiguiente(): ?self
    {
        return static::where('dependencia_id', $this->dependencia_id)
            ->where('version', '>', $this->version)
            ->orderBy('version', 'asc')
            ->first();
    }

    /**
     * Obtiene todas las versiones de la misma dependencia.
     */
    public function getTodasLasVersiones()
    {
        return static::where('dependencia_id', $this->dependencia_id)
            ->orderBy('version', 'asc')
            ->get();
    }

    /**
     * Verifica si es la versión más reciente de la dependencia.
     */
    public function isUltimaVersion(): bool
    {
        $ultimaVersion = static::where('dependencia_id', $this->dependencia_id)
            ->max('version');

        return $this->version == $ultimaVersion;
    }

    /**
     * Obtiene el tiempo transcurrido desde la creación.
     */
    public function getTiempoTranscurrido(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Obtiene el tiempo transcurrido desde la aprobación.
     */
    public function getTiempoDesdeAprobacion(): ?string
    {
        return $this->fecha_aprobacion ? $this->fecha_aprobacion->diffForHumans() : null;
    }

    /**
     * Obtiene información resumida de la versión.
     */
    public function getInformacionResumida(): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'estado' => $this->getEstadoFormateado(),
            'dependencia' => $this->dependencia->nom_organico ?? 'N/A',
            'fecha_creacion' => $this->created_at->format('d/m/Y H:i'),
            'aprobado_por' => $this->aprobadoPor->name ?? 'N/A',
            'fecha_aprobacion' => $this->fecha_aprobacion?->format('d/m/Y H:i') ?? 'N/A',
            'estadisticas' => $this->getEstadisticas(),
        ];
    }
}
