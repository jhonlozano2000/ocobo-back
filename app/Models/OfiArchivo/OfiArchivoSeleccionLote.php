<?php

namespace App\Models\OfiArchivo;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * B22: selección documental de una serie o subserie de disposición S. El sistema propone la
 * muestra con el método de la TRD; el archivo la ajusta y, al confirmarla, lo seleccionado se
 * conserva y el resto pasa a eliminación.
 */
class OfiArchivoSeleccionLote extends Model
{
    public const METODOS = ['aleatorio', 'sistematico', 'cualitativo'];

    public const PROPUESTA = 'propuesta';

    public const CONFIRMADA = 'confirmada';

    public const ANULADA = 'anulada';

    protected $table = 'ofi_archivo_seleccion_lotes';

    protected $fillable = [
        'codigo', 'trd_id', 'trd_cod', 'trd_nom', 'metodo', 'porcentaje', 'intervalo', 'semilla',
        'estado', 'justificacion', 'user_id', 'confirmado_por_id', 'confirmado_at',
    ];

    protected $casts = [
        'porcentaje' => 'integer',
        'intervalo' => 'integer',
        'semilla' => 'integer',
        'confirmado_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OfiArchivoSeleccionItem::class, 'lote_id')->orderBy('orden');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function confirmadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmado_por_id');
    }
}
