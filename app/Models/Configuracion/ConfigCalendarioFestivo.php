<?php

namespace App\Models\Configuracion;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigCalendarioFestivo extends Model
{
    use HasFactory, Loggable;

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

    public const TIPO_NACIONAL = 'Nacional';
    public const TIPO_REGIONAL = 'Regional';
    public const TIPO_EMPRESARIAL = 'Empresarial';

    public static function getTipos(): array
    {
        return [
            self::TIPO_NACIONAL,
            self::TIPO_REGIONAL,
            self::TIPO_EMPRESARIAL,
        ];
    }

    protected static function getLogDescription(string $action, $model): string
    {
        $fecha = $model->fecha?->format('Y-m-d') ?? 'desconocida';
        return match ($action) {
            'create' => "creó el día no hábil {$fecha} - {$model->nombre}",
            'update' => "actualizó el día no hábil {$fecha} - {$model->nombre}",
            'delete' => "eliminó el día no hábil {$fecha} - {$model->nombre}",
            default => "modificó día no hábil {$fecha}",
        };
    }
}
