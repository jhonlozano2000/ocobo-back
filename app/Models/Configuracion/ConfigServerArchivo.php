<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class ConfigServerArchivo extends Model
{
    use HasFactory;

    protected $table = 'config_server_archivos';

    protected $fillable = ['proceso_id', 'host', 'ruta', 'user', 'password', 'detalle', 'estado'];

    protected $hidden = ['password']; // Evita que el password se devuelva en las respuestas

    // Mutator para encriptar el password antes de guardarlo
    public function setPasswordAttribute($value)
    {
        // Solo hashear si el valor no está vacío
        if (!empty($value)) {
            $this->attributes['password'] = Hash::make($value);
        }
    }

    public function proceso()
    {
        return $this->belongsTo(ConfigListaDetalle::class, 'proceso_id');
    }
}
