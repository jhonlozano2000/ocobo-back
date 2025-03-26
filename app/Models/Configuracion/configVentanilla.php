<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class configVentanilla extends Model
{
    use HasFactory;

    protected $fillable = ['sede_id', 'nombre', 'descripcion', 'numeracion_unificada', 'estado'];

    public function sede()
    {
        return $this->belongsTo(configSede::class);
    }
}
