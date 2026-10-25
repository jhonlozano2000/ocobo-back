<?php

namespace App\Models\MiBandeja;

use App\Models\Concerns\RegistraMetricaArchivo;
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
    use HasFactory, RegistraMetricaArchivo;

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
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(MiBandejaTemp::class, 'grupo_id');
    }

    /**
     * Relación con el usuario que subió el archivo.
     */
    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    /**
     * Métricas de archivos: adjunto del grupo colaborativo.
     */
    public function datosMetricaArchivo(): array
    {
        return [
            'modulo' => 'mi_bandeja',
            'submodulo' => 'grupos_adjuntos',
            'ruta' => $this->archivo,
            'bytes' => $this->peso,
            'extension' => null,
            'mime' => $this->tipo_mime,
            'user_id' => $this->subido_por,
        ];
    }
}
