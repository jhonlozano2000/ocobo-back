<?php

namespace App\Models\Calidad;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
