<?php

namespace App\Models\OfiArchivo;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * B19: documento físico relacionado en la hoja de control del expediente. Su orden y su rango
 * de folios no cambian: si el documento sale del expediente, el registro se anula y los folios
 * no se renumeran.
 *
 * @property int $expediente_id
 * @property int $tomo_id
 * @property int $orden
 * @property int $folio_inicial
 * @property int $folio_final
 * @property Carbon|null $anulado_en
 */
class OfiArchivoExpedienteHojaControl extends Model
{
    protected $table = 'ofi_archivo_expediente_hoja_control';

    /** Lo que identifica el documento en la hoja; solo cambia la anulación. */
    public const INMUTABLES = [
        'expediente_id', 'tomo_id', 'orden', 'tipo_documental_id', 'tipo_documental', 'fecha_documento', 'folio_inicial', 'folio_final',
    ];

    protected $fillable = [
        'expediente_id',
        'tomo_id',
        'orden',
        'tipo_documental_id',
        'tipo_documental',
        'fecha_documento',
        'folio_inicial',
        'folio_final',
        'observaciones',
        'usuario_id',
        'anulado_en',
        'motivo_anulacion',
        'anulado_por',
    ];

    protected $casts = [
        'expediente_id' => 'integer',
        'tomo_id' => 'integer',
        'orden' => 'integer',
        'tipo_documental_id' => 'integer',
        'folio_inicial' => 'integer',
        'folio_final' => 'integer',
        'fecha_documento' => 'date:Y-m-d',
        'anulado_en' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $registro) {
            if ($registro->isDirty(self::INMUTABLES)) {
                throw new \LogicException('Los registros de la hoja de control no pueden modificarse; se anulan.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Los registros de la hoja de control no pueden eliminarse; se anulan.'));
    }

    public function scopeActivos($query)
    {
        return $query->whereNull('anulado_en');
    }

    public function folios(): int
    {
        return $this->folio_final - $this->folio_inicial + 1;
    }

    public function tomo()
    {
        return $this->belongsTo(OfiArchivoExpedienteTomo::class, 'tomo_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
