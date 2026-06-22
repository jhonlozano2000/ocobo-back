<?php

namespace App\Models\Calidad;

use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRDVersion;
use App\Models\ControlAcceso\UserCargo;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalidadOrganigrama extends Model
{
    use HasFactory;

    protected $table = 'calidad_organigrama';

    protected $fillable = [
        'tipo',
        'nom_organico',
        'cod_organico',
        'observaciones',
        'parent',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación recursiva: Un nodo puede tener varios hijos.
     *
     * @return HasMany
     */
    public function children()
    {
        return $this->hasMany(CalidadOrganigrama::class, 'parent')->with('children');
    }

    /**
     * Relación con el nodo padre.
     *
     * @return BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(CalidadOrganigrama::class, 'parent');
    }

    /**
     * Obtener SOLO dependencias dentro de una dependencia padre.
     *
     * @return HasMany
     */
    public function childrenDependencias()
    {
        return $this->hasMany(CalidadOrganigrama::class, 'parent')
            ->where('tipo', 'Dependencia');
    }

    /**
     * Obtener SOLO oficinas dentro de una dependencia.
     *
     * @return HasMany
     */
    public function childrenOficinas()
    {
        return $this->hasMany(CalidadOrganigrama::class, 'parent')
            ->where('tipo', 'Oficina');
    }

    /**
     * Obtener SOLO cargos dentro de una dependencia o una oficina.
     *
     * @return HasMany
     */
    public function childrenCargos()
    {
        return $this->hasMany(CalidadOrganigrama::class, 'parent')
            ->where('tipo', 'Cargo');
    }

    /**
     * Obtener SOLO las dependencias principales (sin padres).
     */
    public function scopeDependenciasRaiz(Builder $query): Builder
    {
        return $query->whereNull('parent')->where('tipo', 'Dependencia');
    }

    /**
     * Filtrar por tipo específico.
     */
    public function scopePorTipo(Builder $query, string $tipo): Builder
    {
        return $query->where('tipo', $tipo);
    }

    /**
     * Filtrar elementos sin padre (nivel raíz).
     */
    public function scopeNivelRaiz(Builder $query): Builder
    {
        return $query->whereNull('parent');
    }

    /**
     * Filtrar elementos con padre (niveles inferiores).
     */
    public function scopeConPadre(Builder $query): Builder
    {
        return $query->whereNotNull('parent');
    }

    /**
     * Buscar por nombre o código orgánico.
     */
    public function scopeBuscar(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('nom_organico', 'like', "%{$search}%")
                ->orWhere('cod_organico', 'like', "%{$search}%");
        });
    }

    /**
     * Encontrar una dependencia por ID.
     */
    public static function findDependenciaById(int $id): ?CalidadOrganigrama
    {
        return self::where('id', $id)
            ->where('tipo', 'Dependencia')
            ->first();
    }

    /**
     * Encontrar una dependencia por código orgánico.
     */
    public static function findDependenciaByCodOrganico(string $codigo): ?CalidadOrganigrama
    {
        return self::where('cod_organico', $codigo)
            ->where('tipo', 'Dependencia')
            ->first();
    }

    /**
     * Obtener la jerarquía completa de un nodo.
     */
    public function getJerarquiaCompleta(): array
    {
        $jerarquia = [];
        $nodoActual = $this;
        $parentId = $this->attributes['parent'] ?? null;

        while ($nodoActual) {
            array_unshift($jerarquia, [
                'id' => $nodoActual->id,
                'tipo' => $nodoActual->tipo,
                'nom_organico' => $nodoActual->nom_organico,
                'cod_organico' => $nodoActual->cod_organico,
            ]);

            if ($parentId) {
                $nodoActual = self::find($parentId);
                if ($nodoActual) {
                    $parentId = $nodoActual->attributes['parent'] ?? null;
                } else {
                    break;
                }
            } else {
                break;
            }
        }

        return $jerarquia;
    }

    /**
     * Verificar si el nodo es una dependencia raíz.
     */
    public function isDependenciaRaiz(): bool
    {
        return $this->tipo === 'Dependencia' && is_null($this->attributes['parent'] ?? null);
    }

    /**
     * Verificar si el nodo es una oficina.
     */
    public function isOficina(): bool
    {
        return $this->tipo === 'Oficina';
    }

    /**
     * Verificar si el nodo es un cargo.
     */
    public function isCargo(): bool
    {
        return $this->tipo === 'Cargo';
    }

    /**
     * Verificar si el nodo puede tener hijos.
     */
    public function puedeTenerHijos(): bool
    {
        return $this->tipo !== 'Cargo';
    }

    /**
     * Obtener el nivel jerárquico del nodo.
     */
    public function getNivel(): int
    {
        $nivel = 1;
        $parentId = $this->attributes['parent'] ?? null;

        while ($parentId) {
            $nivel++;
            $parent = self::find($parentId);
            if (! $parent) {
                break;
            }
            $parentId = $parent->attributes['parent'] ?? null;
        }

        return $nivel;
    }

    /**
     * Relación con las TRDs asociadas a esta dependencia.
     *
     * @return HasMany
     */
    public function trds()
    {
        return $this->hasMany(ClasificacionDocumentalTRD::class, 'dependencia_id');
    }

    /**
     * Relación con las versiones TRD de esta dependencia.
     *
     * @return HasMany
     */
    public function trdVersiones()
    {
        return $this->hasMany(ClasificacionDocumentalTRDVersion::class, 'dependencia_id');
    }

    /**
     * Relación con las asignaciones de usuarios a este cargo.
     *
     * @return HasMany
     */
    public function asignaciones()
    {
        return $this->hasMany(UserCargo::class, 'cargo_id')
            ->with('user')
            ->orderBy('fecha_inicio', 'desc');
    }

    /**
     * Relación con los usuarios actualmente asignados a este cargo.
     *
     * @return HasMany
     */
    public function usuariosActivos()
    {
        return $this->hasMany(UserCargo::class, 'cargo_id')
            ->with('user')
            ->where('estado', true)
            ->whereNull('fecha_fin');
    }

    /**
     * Relación many-to-many con usuarios (para compatibilidad).
     *
     * @return BelongsToMany
     */
    public function usuarios()
    {
        return $this->belongsToMany(User::class, 'users_cargos', 'cargo_id', 'user_id')
            ->withPivot('fecha_inicio', 'fecha_fin', 'observaciones', 'estado')
            ->withTimestamps();
    }

    /**
     * Obtiene solo los usuarios activos en este cargo.
     *
     * @return BelongsToMany
     */
    public function usuariosActivosRelacion()
    {
        return $this->belongsToMany(User::class, 'users_cargos', 'cargo_id', 'user_id')
            ->withPivot('fecha_inicio', 'fecha_fin', 'observaciones', 'estado')
            ->wherePivot('estado', true)
            ->wherePivotNull('fecha_fin')
            ->withTimestamps();
    }

    /**
     * Verifica si este nodo es un cargo y puede tener usuarios asignados.
     */
    public function puedeAsignarUsuarios(): bool
    {
        return $this->tipo === 'Cargo';
    }

    /**
     * Obtiene el usuario actualmente asignado a este cargo (si es que hay uno).
     */
    public function getUsuarioActivo(): ?UserCargo
    {
        return $this->usuariosActivos()->first();
    }

    /**
     * Verifica si el cargo tiene usuarios asignados actualmente.
     */
    public function tieneUsuariosAsignados(): bool
    {
        return $this->usuariosActivos()->exists();
    }

    /**
     * Obtiene estadísticas de asignaciones para este cargo.
     */
    public function getEstadisticasAsignaciones(): array
    {
        $totalAsignaciones = $this->asignaciones()->count();
        $asignacionesActivas = $this->usuariosActivos()->count();
        $asignacionesFinalizadas = $this->asignaciones()->where('estado', false)->count();

        return [
            'total_asignaciones' => $totalAsignaciones,
            'asignaciones_activas' => $asignacionesActivas,
            'asignaciones_finalizadas' => $asignacionesFinalizadas,
            'tiene_usuario_activo' => $asignacionesActivas > 0,
            'cargo_disponible' => $asignacionesActivas === 0,
        ];
    }

    /**
     * Obtiene el historial completo de usuarios que han ocupado este cargo.
     *
     * @return Collection
     */
    public function getHistorialUsuarios()
    {
        return $this->asignaciones()
            ->with(['user:id,nombres,apellidos,email'])
            ->get()
            ->map(function ($asignacion) {
                return [
                    'id' => $asignacion->id,
                    'usuario' => $asignacion->user,
                    'fecha_inicio' => $asignacion->fecha_inicio,
                    'fecha_fin' => $asignacion->fecha_fin,
                    'duracion_dias' => $asignacion->getDuracionEnDias(),
                    'esta_activo' => $asignacion->estaActivo(),
                    'observaciones' => $asignacion->observaciones,
                ];
            });
    }

    /**
     * Scope para filtrar solo cargos que pueden tener usuarios asignados.
     */
    public function scopeCargosAsignables(Builder $query): Builder
    {
        return $query->where('tipo', 'Cargo');
    }

    /**
     * Scope para filtrar cargos con usuarios activos.
     */
    public function scopeConUsuariosActivos(Builder $query): Builder
    {
        return $query->whereHas('usuariosActivos');
    }

    /**
     * Scope para filtrar cargos disponibles (sin usuarios activos).
     */
    public function scopeDisponibles(Builder $query): Builder
    {
        return $query->where('tipo', 'Cargo')
            ->whereDoesntHave('usuariosActivos');
    }

    /**
     * Accessor para la propiedad parent.
     *
     * @return mixed
     */
    public function getParentAttribute()
    {
        return $this->attributes['parent'] ?? null;
    }

    /**
     * Obtiene todos los códigos orgánicos de los descendientes (hijos, nietos, etc.).
     */
    public function getDescendientesCodigos(): array
    {
        $codigos = [];

        foreach ($this->children as $child) {
            $codigos[] = $child->cod_organico;
            $codigos = array_merge($codigos, $child->getDescendientesCodigos());
        }

        return array_unique($codigos);
    }
}
