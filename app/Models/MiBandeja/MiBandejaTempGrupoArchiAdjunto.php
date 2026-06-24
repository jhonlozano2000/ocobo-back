<?php

namespace App\Models\MiBandeja;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo para adjuntos de grupos colaborativos temporales.
 * Representa un archivo adjunto subido a un grupo de trabajo temporal.
 */
class MiBandejaTempGrupoArchiAdjunto extends Model
{
    use HasFactory;

    protected $table = 'mi_bandeja_temp_grupo_archi_adjuntos';

    protected $fillable = [
        'grupo_id',
        'archivo',
        'nombre_original',
        'tipo_mime',
        'peso',
        'hash_sha256',
        'subido_por',
    ];

    protected $casts = [
        'peso' => 'integer',
    ];

    /**
     * Relación con el grupo colaborativo temporal.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(MiBandejaTemp::class, 'grupo_id');
    }

    /**
     * Relación con el usuario que subió el archivo.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }
}