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

    public function children()
    {
        return $this->hasMany(CalidadOrganigrama::class, 'parent')->with('children');
    }

    public function parent()
    {
        return $this->belongsTo(CalidadOrganigrama::class, 'parent');
    }

    // Relación recursiva para obtener los hijos de tipo "Dependencia"
    public function childrenDependencias()
    {
        return $this->hasMany(CalidadOrganigrama::class, 'parent')
            ->where('tipo', 'Dependencia')
            ->with('childrenDependencias'); // Asegura que la relación solo devuelva dependencias en todos los niveles
    }

    // Relación con la tabla pivote users_cargos
    public function users()
    {
        return $this->belongsToMany(User::class, 'users_cargos')
            ->withPivot('start_date', 'end_date')  // Fechas de inicio y fin del cargo
            ->withTimestamps();  // Tiempos de creación y actualización
    }
}
