<?php

namespace App\Models\ClasificacionDocumental;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fondo acumulado (M11-02): documentación producida antes de la TRD, propia de la entidad o
 * recibida de una entidad fusionada o liquidada. Se valora con la TVD por períodos.
 */
class FondoAcumulado extends Model
{
    public const ORIGENES = ['propio', 'recibido'];

    protected $table = 'fondos_acumulados';

    protected $fillable = [
        'codigo',
        'nombre',
        'entidad_productora',
        'origen',
        'fecha_inicio',
        'fecha_fin',
        'descripcion',
        'user_register',
    ];

    protected $casts = [
        'fecha_inicio' => 'date:Y-m-d',
        'fecha_fin' => 'date:Y-m-d',
    ];

    public function periodos(): HasMany
    {
        return $this->hasMany(FondoAcumuladoPeriodo::class, 'fondo_id')->orderBy('fecha_inicio');
    }

    public function userRegister(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_register');
    }
}
