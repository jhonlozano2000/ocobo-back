<?php

namespace App\Models\OfiArchivo;

use Illuminate\Database\Eloquent\Model;

/**
 * B19: tomo (carpeta física) de un expediente. La foliación continúa de un tomo al siguiente;
 * el tomo empieza en `folio_inicial` y termina en el último folio de sus registros.
 *
 * @property int $expediente_id
 * @property int $numero
 * @property int $folio_inicial
 */
class OfiArchivoExpedienteTomo extends Model
{
    protected $table = 'ofi_archivo_expediente_tomos';

    protected $fillable = ['expediente_id', 'numero', 'folio_inicial', 'ubicacion_fisica', 'caja', 'carpeta', 'usuario_id'];

    protected $casts = [
        'expediente_id' => 'integer',
        'numero' => 'integer',
        'folio_inicial' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $tomo) {
            if ($tomo->isDirty(['expediente_id', 'numero', 'folio_inicial'])) {
                throw new \LogicException('El número y el folio inicial del tomo no pueden modificarse.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Los tomos del expediente no pueden eliminarse.'));
    }

    public function registros()
    {
        return $this->hasMany(OfiArchivoExpedienteHojaControl::class, 'tomo_id')->orderBy('orden');
    }
}
