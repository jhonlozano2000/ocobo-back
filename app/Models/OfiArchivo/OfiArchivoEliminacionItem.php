<?php

namespace App\Models\OfiArchivo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * B18: expediente de un lote de eliminación, con los datos de su inventario tal como se publicaron.
 */
class OfiArchivoEliminacionItem extends Model
{
    public $timestamps = false;

    protected $table = 'ofi_archivo_eliminacion_items';

    protected $fillable = [
        'lote_id', 'expediente_id', 'orden', 'numero_expediente', 'nombre_publico', 'fecha_inicial', 'fecha_final',
        'folios', 'soporte', 'archivos_borrados', 'archivos_compartidos',
    ];

    protected $casts = [
        'fecha_inicial' => 'date:Y-m-d',
        'fecha_final' => 'date:Y-m-d',
        'folios' => 'integer',
        'archivos_borrados' => 'integer',
        'archivos_compartidos' => 'integer',
    ];

    public function lote(): BelongsTo
    {
        return $this->belongsTo(OfiArchivoEliminacionLote::class, 'lote_id');
    }

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(OfiArchivoExpediente::class, 'expediente_id');
    }
}
