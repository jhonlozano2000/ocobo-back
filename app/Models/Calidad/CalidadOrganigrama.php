<?php

namespace App\Models\Calidad;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalidadOrganigrama extends Model
{
    use HasFactory;

    protected $table = 'calidad_organigrama';
    protected $fillable = ['tipo', 'nombre', 'cod_organico', 'cod_corres', 'parent'];

    public function children()
    {
        return $this->hasMany(CalidadOrganigrama::class, 'parent')->with('children');
    }

    public function parent()
    {
        return $this->belongsTo(CalidadOrganigrama::class, 'parent');
    }
}
