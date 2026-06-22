<?php

namespace App\Models\OfiArchivo;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo para gestión de préstamos de expedientes del archivo central.
 *
 * Controla el préstamo de documentos y expedientes físicos del archivo,
 * con registro de salida, responsable, fecha de devolución y estado.
 * Obligatorio según Acuerdo AGN 042/2002.
 *
 * @property int $id
 * @property int $expediente_id FK al expediente prestado
 * @property int $solicitante_id FK usuario que solicita
 * @property string|null $dependencia_destino Dependencia destino del préstamo
 * @property Carbon $fecha_prestamo Fecha y hora del préstamo
 * @property Carbon $fecha_devolucion_esperada Fecha límite de devolución
 * @property Carbon|null $fecha_devolucion_real Fecha real de devolución
 * @property string $estado prestatado|devuelto|vencido
 * @property string|null $observaciones Observaciones del préstamo
 * @property int $usuario_registro_id FK usuario que registró
 */
class OfiArchivoPrestamo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ofi_archivo_prestamos';

    protected $fillable = [
        'expediente_id',
        'solicitante_id',
        'dependencia_destino',
        'fecha_prestamo',
        'fecha_devolucion_esperada',
        'fecha_devolucion_real',
        'estado',
        'observaciones',
        'usuario_registro_id',
    ];

    protected $casts = [
        'fecha_prestamo' => 'datetime',
        'fecha_devolucion_esperada' => 'datetime',
        'fecha_devolucion_real' => 'datetime',
    ];

    /** Relación: Expediente prestado */
    public function expediente()
    {
        return $this->belongsTo(OfiArchivoExpediente::class, 'expediente_id');
    }

    /** Relación: Usuario solicitante */
    public function solicitante()
    {
        return $this->belongsTo(User::class, 'solicitante_id');
    }

    /** Relación: Usuario que registró el préstamo */
    public function usuarioRegistro()
    {
        return $this->belongsTo(User::class, 'usuario_registro_id');
    }

    /** Scope: Préstamos activos (estado = prestado) */
    public function scopeActivos($query)
    {
        return $query->where('estado', 'prestado');
    }

    /** Scope: Préstamos vencidos (prestado + fecha devolución pasada) */
    public function scopeVencidos($query)
    {
        return $query->where('estado', 'prestado')
            ->where('fecha_devolucion_esperada', '<', now());
    }

    /**
     * Calcula los días restantes para la devolución.
     *
     * @return int Días restantes (0 si ya venció o fue devuelto)
     */
    public function getDiasRestantesAttribute(): int
    {
        if ($this->estado !== 'prestado') {
            return 0;
        }

        return max(0, now()->diffInDays($this->fecha_devolucion_esperada, false));
    }

    /**
     * Determina el color del chip de estado según urgencia.
     *
     * @return string success|warning|error
     */
    public function getEstadoColorAttribute(): string
    {
        if ($this->estado === 'devuelto') {
            return 'success';
        }
        if ($this->estado === 'vencido') {
            return 'error';
        }
        $dias = $this->dias_restantes;
        if ($dias <= 1) {
            return 'error';
        }
        if ($dias <= 3) {
            return 'warning';
        }

        return 'success';
    }
}
