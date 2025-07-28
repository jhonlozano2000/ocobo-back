<?php

namespace App\Models\Calidad;

use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class CalidadOrganigrama extends Model
{
    use HasFactory;

    protected $table = 'calidad_organigrama';

    protected $fillable = [
        'tipo',
        'nom_organico',
        'cod_organico',
        'observaciones',
        'parent'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relación recursiva: Un nodo puede tener varios hijos.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children()
    {
        return $this->hasMany(CalidadOrganigrama::class, 'parent')->with('children');
    }

    /**
     * Relación con el nodo padre.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(CalidadOrganigrama::class, 'parent');
    }

    /**
     * Obtener SOLO dependencias dentro de una dependencia padre.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function childrenDependencias()
    {
        return $this->hasMany(CalidadOrganigrama::class, 'parent')
            ->where('tipo', 'Dependencia')
            ->with('childrenDependencias');
    }

    /**
     * Obtener SOLO oficinas dentro de una dependencia.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function childrenOficinas()
    {
        return $this->hasMany(CalidadOrganigrama::class, 'parent')
            ->where('tipo', 'Oficina');
    }

    /**
     * Obtener SOLO cargos dentro de una dependencia o una oficina.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function childrenCargos()
    {
        return $this->hasMany(CalidadOrganigrama::class, 'parent')
            ->where('tipo', 'Cargo');
    }

    /**
     * Obtener SOLO las dependencias principales (sin padres).
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeDependenciasRaiz(Builder $query): Builder
    {
        return $query->whereNull('parent')->where('tipo', 'Dependencia');
    }

    /**
     * Filtrar por tipo específico.
     *
     * @param Builder $query
     * @param string $tipo
     * @return Builder
     */
    public function scopePorTipo(Builder $query, string $tipo): Builder
    {
        return $query->where('tipo', $tipo);
    }

    /**
     * Filtrar elementos sin padre (nivel raíz).
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeNivelRaiz(Builder $query): Builder
    {
        return $query->whereNull('parent');
    }

    /**
     * Filtrar elementos con padre (niveles inferiores).
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeConPadre(Builder $query): Builder
    {
        return $query->whereNotNull('parent');
    }

    /**
     * Buscar por nombre o código orgánico.
     *
     * @param Builder $query
     * @param string $search
     * @return Builder
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
     *
     * @param int $id
     * @return CalidadOrganigrama|null
     */
    public static function findDependenciaById(int $id): ?CalidadOrganigrama
    {
        return self::where('id', $id)
            ->where('tipo', 'Dependencia')
            ->first();
    }

    /**
     * Encontrar una dependencia por código orgánico.
     *
     * @param string $codigo
     * @return CalidadOrganigrama|null
     */
    public static function findDependenciaByCodOrganico(string $codigo): ?CalidadOrganigrama
    {
        return self::where('cod_organico', $codigo)
            ->where('tipo', 'Dependencia')
            ->first();
    }

    /**
     * Obtener la jerarquía completa de un nodo.
     *
     * @return array
     */
    public function getJerarquiaCompleta(): array
    {
        $jerarquia = [];
        $nodoActual = $this;

        while ($nodoActual) {
            array_unshift($jerarquia, [
                'id' => $nodoActual->id,
                'tipo' => $nodoActual->tipo,
                'nom_organico' => $nodoActual->nom_organico,
                'cod_organico' => $nodoActual->cod_organico
            ]);
            $nodoActual = $nodoActual->parent;
        }

        return $jerarquia;
    }

    /**
     * Verificar si el nodo es una dependencia raíz.
     *
     * @return bool
     */
    public function isDependenciaRaiz(): bool
    {
        return $this->tipo === 'Dependencia' && is_null($this->parent);
    }

    /**
     * Verificar si el nodo es una oficina.
     *
     * @return bool
     */
    public function isOficina(): bool
    {
        return $this->tipo === 'Oficina';
    }

    /**
     * Verificar si el nodo es un cargo.
     *
     * @return bool
     */
    public function isCargo(): bool
    {
        return $this->tipo === 'Cargo';
    }

    /**
     * Verificar si el nodo puede tener hijos.
     *
     * @return bool
     */
    public function puedeTenerHijos(): bool
    {
        return $this->tipo !== 'Cargo';
    }

    /**
     * Obtener el nivel jerárquico del nodo.
     *
     * @return int
     */
    public function getNivel(): int
    {
        $nivel = 1;
        $nodoActual = $this->parent;

        while ($nodoActual) {
            $nivel++;
            $nodoActual = $nodoActual->parent;
        }

        return $nivel;
    }

    /**
     * Relación con las TRDs asociadas a esta dependencia.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function trds()
    {
        return $this->hasMany(ClasificacionDocumentalTRD::class, 'dependencia_id');
    }
}
