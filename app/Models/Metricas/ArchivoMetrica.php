<?php

namespace App\Models\Metricas;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evento de métrica de un archivo (cargado o eliminado). Solo se inserta; nunca se actualiza.
 */
class ArchivoMetrica extends Model
{
    public const CARGADO = 'cargado';

    public const ELIMINADO = 'eliminado';

    public const UPDATED_AT = null;

    protected $table = 'archivos_metricas';

    protected $guarded = ['id'];

    protected $casts = [
        'bytes' => 'integer',
        'ocurrido_en' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
