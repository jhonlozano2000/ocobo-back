<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigSede extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'codigo', 'direccion', 'telefono', 'email', 'ubicacion', 'estado'];

    public function ventanillas()
    {
        return $this->hasMany(ConfigVentanilla::class);
    }
}
