<?php

namespace App\Models\VentanillaUnica\Comunes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Contador transaccional por tipo de radicado y vigencia.
 */
class RadicadoConsecutivo extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radicado_consecutivos';

    protected $fillable = [
        'tipo',
        'periodo',
        'consecutivo',
    ];

    protected function casts(): array
    {
        return [
            'periodo' => 'integer',
            'consecutivo' => 'integer',
        ];
    }
}
