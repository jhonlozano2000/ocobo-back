<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class configSede extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'codigo', 'ubicacion', 'estado'];

    public function ventanillas()
    {
        return $this->hasMany(configVentanillas::class);
    }
}
