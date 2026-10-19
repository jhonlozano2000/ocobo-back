<?php

namespace App\Models\OfiArchivo;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * B12: evento del historial de un expediente. Solo inserción: la BD bloquea UPDATE y DELETE
 * con triggers y el modelo lo rechaza antes de llegar a ella.
 *
 * @property int $expediente_id
 * @property int $secuencia consecutivo del evento dentro del expediente (1, 2, 3...)
 * @property string $accion apertura|edicion|incorporacion|retiro|cierre|reapertura|transferencia|eliminacion|prestamo|devolucion
 * @property array|null $detalle
 * @property string|null $hash_anterior huella del evento anterior del mismo expediente
 * @property string $hash sha256 del evento encadenado con hash_anterior
 */
class OfiArchivoExpedienteEvento extends Model
{
    protected $table = 'ofi_archivo_expediente_eventos';

    public $timestamps = false;

    protected $fillable = [
        'expediente_id',
        'secuencia',
        'accion',
        'detalle',
        'usuario_id',
        'ip',
        'ocurrido_en',
        'hash_anterior',
        'hash',
    ];

    protected $casts = [
        'expediente_id' => 'integer',
        'secuencia' => 'integer',
        'detalle' => 'array',
        'ocurrido_en' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('El historial del expediente es inmutable.'));
        static::deleting(fn () => throw new \LogicException('El historial del expediente es inmutable.'));
    }

    public function expediente()
    {
        return $this->belongsTo(OfiArchivoExpediente::class, 'expediente_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
