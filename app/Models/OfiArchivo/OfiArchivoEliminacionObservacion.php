<?php

namespace App\Models\OfiArchivo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * B18: observación de la ciudadanía al inventario publicado de un lote de eliminación.
 */
class OfiArchivoEliminacionObservacion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ofi_archivo_eliminacion_observaciones';

    protected $fillable = ['lote_id', 'item_id', 'nombre', 'identificacion', 'correo', 'observacion', 'ip'];

    protected $hidden = ['ip'];

    public function lote(): BelongsTo
    {
        return $this->belongsTo(OfiArchivoEliminacionLote::class, 'lote_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(OfiArchivoEliminacionItem::class, 'item_id');
    }
}
