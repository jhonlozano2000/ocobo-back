<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigVarias extends Model
{
    use HasFactory;

    protected $table = 'config_varias';

    protected $fillable = ['clave', 'valor', 'descripcion'];

    public static function getValor($clave, $default = null)
    {
        $config = self::where('clave', $clave)->first();
        return $config ? $config->valor : $default;
    }
}
