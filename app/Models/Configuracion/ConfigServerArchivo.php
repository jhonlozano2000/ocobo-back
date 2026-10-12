<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigServerArchivo extends Model
{
    use HasFactory;

    protected $table = 'config_server_archivos';

    protected $fillable = ['proceso_id', 'host', 'ruta', 'user', 'password', 'detalle', 'estado'];

    protected $hidden = ['password']; // Evita que el password se devuelva en las respuestas

    // M15-04: cifrado reversible (APP_KEY); el password se necesita en claro para conectarse al servidor
    protected $casts = [
        'password' => 'encrypted',
    ];

    // Un password vacío no reemplaza el guardado
    public function setPasswordAttribute($value)
    {
        if (! empty($value)) {
            $this->attributes['password'] = $this->castAttributeAsEncryptedString('password', $value);
        }
    }

    public function proceso()
    {
        return $this->belongsTo(ConfigListaDetalle::class, 'proceso_id');
    }
}
