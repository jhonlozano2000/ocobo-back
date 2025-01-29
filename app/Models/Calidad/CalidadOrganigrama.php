<?php

namespace App\Models\Calidad;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    /**
     * Relación recursiva: Un nodo puede tener varios hijos.
     */
    public function children()
    {
        return $this->hasMany(CalidadOrganigrama::class, 'parent')->with('children');
    }

    /**
     * Relación con el nodo padre.
     */
    public function parent()
    {
        return $this->belongsTo(CalidadOrganigrama::class, 'parent');
    }

    /**
     * Obtener SOLO dependencias dentro de una dependencia padre.
     */
    public function childrenDependencias()
    {
        return $this->hasMany(CalidadOrganigrama::class, 'parent')
            ->where('tipo', 'Dependencia')
            ->with('childrenDependencias');
    }

    /**
     * Obtener SOLO oficinas dentro de una dependencia.
     */
    public function childrenOficinas()
    {
        return $this->hasMany(CalidadOrganigrama::class, 'parent')
            ->where('tipo', 'Oficina');
    }

    /**
     * Obtener SOLO cargos dentro de una dependencia o una oficina.
     */
    public function childrenCargos()
    {
        return $this->hasMany(CalidadOrganigrama::class, 'parent')
            ->where('tipo', 'Cargo');
    }

    /**
     * Obtener SOLO las dependencias principales (sin padres).
     */
    public function scopeDependenciasRaiz($query)
    {
        return $query->whereNull('parent')->where('tipo', 'Dependencia');
    }
}
