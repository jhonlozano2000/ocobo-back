<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FileClassificationLevel extends Model
{
    use HasFactory;

    protected $table = 'config_file_classification_levels';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'color_hex',
        'nivel_sensibilidad',
        'plazo_retencion_meses',
        'es_eliminable',
        'estado',
    ];

    protected $casts = [
        'nivel_sensibilidad' => 'integer',
        'plazo_retencion_meses' => 'integer',
        'es_eliminable' => 'boolean',
        'estado' => 'boolean',
    ];

    public static function getDefaultClassification()
    {
        return self::where('codigo', 'PUBLICO')->first();
    }

    public static function getByCode(string $code)
    {
        return self::where('codigo', $code)->first();
    }
}
