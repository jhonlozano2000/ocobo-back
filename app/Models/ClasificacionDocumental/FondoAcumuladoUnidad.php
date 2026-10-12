<?php

namespace App\Models\ClasificacionDocumental;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Unidad administrativa histórica: una dependencia u oficina tal como existió en un período del
 * fondo, independiente del organigrama actual.
 */
class FondoAcumuladoUnidad extends Model
{
    protected $table = 'fondo_acumulado_unidades';

    protected $fillable = [
        'periodo_id',
        'codigo',
        'nombre',
        'parent',
    ];

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(FondoAcumuladoPeriodo::class, 'periodo_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent');
    }

    public function tvd(): HasMany
    {
        return $this->hasMany(ClasificacionDocumentalTVD::class, 'unidad_id');
    }
}
