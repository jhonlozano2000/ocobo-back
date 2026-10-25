<?php

namespace App\Models\MiBandeja;

use App\Models\Concerns\RegistraMetricaArchivo;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiBandejaTempArchivoVersion extends Model
{
    use HasFactory, RegistraMetricaArchivo;

    protected $table = 'mi_bandeja_temp_archivo_versiones';

    protected $fillable = [
        'grupo_id',
        'version',
        'nombre_original',
        'nombre_archivo',
        'ruta_completa',
        'peso',
        'extension',
        'mime_type',
        'hash_seguridad',
        'user_subio_id',
        'bloqueado_por_user_id',
        'fecha_bloqueo',
        'comentario_version',
    ];

    protected $casts = [
        'peso' => 'decimal:2',
        'fecha_bloqueo' => 'datetime',
    ];

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(MiBandejaTemp::class, 'grupo_id');
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_subio_id');
    }

    public function bloqueadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bloqueado_por_user_id');
    }

    public function estaDisponible(): bool
    {
        if ($this->bloqueado_por_user_id === null) {
            return true;
        }

        if ($this->fecha_bloqueo !== null && $this->fecha_bloqueo->lt(Carbon::now()->subHours(24))) {
            return true;
        }

        return false;
    }

    public function estaBloqueadoPor(int $userId): bool
    {
        return $this->bloqueado_por_user_id === $userId && ! $this->estaDisponible();
    }

    public function bloqueoExpirado(): bool
    {
        return $this->fecha_bloqueo !== null
            && $this->fecha_bloqueo->lt(Carbon::now()->subHours(24));
    }

    /**
     * Métricas de archivos: versión del documento colaborativo (peso en KB).
     */
    public function datosMetricaArchivo(): array
    {
        return [
            'modulo' => 'mi_bandeja',
            'submodulo' => 'documentos_versiones',
            'ruta' => $this->ruta_completa,
            'bytes' => (float) $this->peso * 1024,
            'extension' => $this->extension,
            'mime' => $this->mime_type,
            'user_id' => $this->user_subio_id,
        ];
    }
}
