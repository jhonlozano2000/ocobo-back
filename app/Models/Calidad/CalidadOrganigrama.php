<?php

namespace App\Models\Calidad;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CalidadOrganigrama extends Model
{
    use HasFactory;

    protected $table = 'calidad_organigrama'; // Asegúrate de que este nombre sea correcto y en singular
    protected $fillable = ['tipo', 'nom_organico', 'cod_organico', 'observaciones', 'parent'];

    // Relación recursiva para obtener TODAS las subdependencias y cargos
    public function children()
    {
        return $this->hasMany(CalidadOrganigrama::class, 'parent')->with('children');
    }

    // Relación con la dependencia superior (Padre)
    public function parent()
    {
        return $this->belongsTo(CalidadOrganigrama::class, 'parent');
    }

    // Relación recursiva para obtener SOLO las subdependencias (sin cargos)
    public function childrenDependencias()
    {
        return $this->hasMany(CalidadOrganigrama::class, 'parent')
            ->where('tipo', 'Dependencia')
            ->with('childrenDependencias');
    }

    // Relación para obtener SOLO los cargos dentro de una dependencia
    public function childrenCargos()
    {
        return $this->hasMany(CalidadOrganigrama::class, 'parent')
            ->where('tipo', 'Cargo');
    }

    // Obtener SOLO las dependencias principales (sin padres)
    public function scopeDependenciasRaiz($query)
    {
        return $query->whereNull('parent')->where('tipo', 'Dependencia');
    }

    // Obtener SOLO los cargos
    public function scopeCargos($query)
    {
        return $query->where('tipo', 'Cargo');
    }
}
