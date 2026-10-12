<?php

namespace App\Models\ClasificacionDocumental;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Período histórico de un fondo acumulado, con la estructura orgánica que tuvo la entidad entre
 * fecha_inicio y fecha_fin. El Comité aprueba la TVD de todo el período; aprobada, es inmutable.
 */
class FondoAcumuladoPeriodo extends Model
{
    protected $table = 'fondo_acumulado_periodos';

    protected $fillable = [
        'fondo_id',
        'nombre',
        'fecha_inicio',
        'fecha_fin',
        'acto_administrativo',
        'descripcion',
        'user_register',
    ];

    protected $casts = [
        'fecha_inicio' => 'date:Y-m-d',
        'fecha_fin' => 'date:Y-m-d',
        'fecha_aprobacion' => 'datetime',
    ];

    protected $appends = ['aprobado'];

    public function fondo(): BelongsTo
    {
        return $this->belongsTo(FondoAcumulado::class, 'fondo_id');
    }

    public function unidades(): HasMany
    {
        return $this->hasMany(FondoAcumuladoUnidad::class, 'periodo_id')->orderBy('codigo');
    }

    public function tvd(): HasMany
    {
        return $this->hasMany(ClasificacionDocumentalTVD::class, 'periodo_id');
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function getAprobadoAttribute(): bool
    {
        return $this->fecha_aprobacion !== null;
    }
}
