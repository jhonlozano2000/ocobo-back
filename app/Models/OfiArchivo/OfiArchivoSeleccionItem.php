<?php

namespace App\Models\OfiArchivo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** B22: expediente de un lote de selección: si el método lo propuso y si al final se conserva. */
class OfiArchivoSeleccionItem extends Model
{
    public $timestamps = false;

    protected $table = 'ofi_archivo_seleccion_items';

    protected $fillable = ['lote_id', 'expediente_id', 'orden', 'propuesto', 'seleccionado'];

    protected $casts = [
        'orden' => 'integer',
        'propuesto' => 'boolean',
        'seleccionado' => 'boolean',
    ];

    public function lote(): BelongsTo
    {
        return $this->belongsTo(OfiArchivoSeleccionLote::class, 'lote_id');
    }

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(OfiArchivoExpediente::class, 'expediente_id');
    }
}
