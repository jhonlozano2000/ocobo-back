<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigCalendarioFestivo extends Model
{
    use HasFactory;

    protected $table = 'config_calendario_festivos';

    protected $fillable = [
        'fecha',
        'nombre',
        'tipo',
        'anio'
    ];

    protected $casts = [
        'fecha' => 'date'
    ];
}
