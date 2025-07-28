<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class configVentanilla extends Model
{
    use HasFactory;

    protected $fillable = ['sede_id', 'nombre', 'codigo', 'descripcion', 'estado'];

    public function sede()
    {
        return $this->belongsTo(configSede::class);
    }
}
