<?php

namespace App\Models\OfiArchivo;

use App\Models\User;
use App\Services\OfiArchivo\ExpedienteEventosService;
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
 * @property string $estado solicitado|aprobado|rechazado|cancelado|prestado|devuelto|vencido
 * @property string|null $observaciones Observaciones del préstamo
 * @property int $usuario_registro_id FK usuario que registró
 * @property int|null $tomo_id Tomo prestado; null = expediente completo (§3.10)
 * @property string $modalidad sala|fisico|electronico
 * @property int|null $folios_entrega Folios contados al entregar (sala y físico)
 * @property int|null $folios_devolucion Folios contados al devolver
 * @property bool $folios_no_coinciden Los folios devueltos no son los entregados
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
        'tomo_id',
        'modalidad',
        'motivo',
        'aprobador_id',
        'fecha_aprobacion',
        'motivo_rechazo',
        'fundamento_autorizacion',
        'version_publica',
        'folios_entrega',
        'entregado_por_id',
        'folios_devolucion',
        'recibido_por_id',
        'folios_no_coinciden',
    ];

    /** §3.10: en sala y físico el expediente sale del archivo; el electrónico es un acceso de solo lectura. */
    public const MODALIDADES = ['sala', 'fisico', 'electronico'];

    /** Pendientes de aprobar o de entregar: aún no salen del archivo. */
    public const ESTADOS_PENDIENTES = ['solicitado', 'aprobado'];

    /** Entregados y no devueltos. */
    public const ESTADOS_ACTIVOS = ['prestado', 'vencido'];

    protected $casts = [
        'fecha_prestamo' => 'datetime',
        'fecha_devolucion_esperada' => 'datetime',
        'fecha_devolucion_real' => 'datetime',
        'fecha_aprobacion' => 'datetime',
        'version_publica' => 'boolean',
        'folios_entrega' => 'integer',
        'folios_devolucion' => 'integer',
        'folios_no_coinciden' => 'boolean',
    ];

    /** B12: la salida y la devolución del expediente quedan en su historial; la solicitud no. */
    protected static function booted(): void
    {
        $prestado = fn (self $prestamo) => app(ExpedienteEventosService::class)->registrar($prestamo->expediente_id, 'prestamo', array_filter([
            'prestamo_id' => $prestamo->id,
            'modalidad' => $prestamo->modalidad,
            'tomo_id' => $prestamo->tomo_id,
            'solicitante_id' => $prestamo->solicitante_id,
            'folios' => $prestamo->folios_entrega,
            'fecha_devolucion_esperada' => $prestamo->fecha_devolucion_esperada?->toDateString(),
        ], fn ($v) => $v !== null));

        static::created(function (self $prestamo) use ($prestado) {
            if ($prestamo->estado === 'prestado') {
                $prestado($prestamo);
            }
        });
        static::updated(function (self $prestamo) use ($prestado) {
            if ($prestamo->wasChanged('estado') && $prestamo->estado === 'prestado') {
                $prestado($prestamo);
            }
            if ($prestamo->wasChanged('estado') && $prestamo->estado === 'devuelto') {
                app(ExpedienteEventosService::class)->registrar($prestamo->expediente_id, 'devolucion', array_filter([
                    'prestamo_id' => $prestamo->id,
                    'folios' => $prestamo->folios_devolucion,
                    'folios_entregados' => $prestamo->folios_no_coinciden ? $prestamo->folios_entrega : null,
                    'folios_no_coinciden' => $prestamo->folios_no_coinciden ?: null,
                    'acceso_expirado' => $prestamo->modalidad === 'electronico' && ! $prestamo->recibido_por_id ? true : null,
                ], fn ($v) => $v !== null));
            }
            // B15: archivo:marcar-prestamos-vencidos
            if ($prestamo->wasChanged('estado') && $prestamo->estado === 'vencido') {
                app(ExpedienteEventosService::class)->registrar($prestamo->expediente_id, 'prestamo_vencido', [
                    'prestamo_id' => $prestamo->id,
                    'fecha_devolucion_esperada' => $prestamo->fecha_devolucion_esperada?->toDateString(),
                ]);
            }
        });
    }

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

    public function tomo()
    {
        return $this->belongsTo(OfiArchivoExpedienteTomo::class, 'tomo_id');
    }

    public function aprobador()
    {
        return $this->belongsTo(User::class, 'aprobador_id');
    }

    public function entregadoPor()
    {
        return $this->belongsTo(User::class, 'entregado_por_id');
    }

    public function recibidoPor()
    {
        return $this->belongsTo(User::class, 'recibido_por_id');
    }

    /**
     * §3.10: préstamos en sala o físicos entregados y no devueltos; el expediente (o el tomo) está
     * fuera del archivo. El acceso electrónico no saca nada del archivo.
     */
    public function scopeFueraDelArchivo($query)
    {
        return $query->whereIn('estado', self::ESTADOS_ACTIVOS)->where('modalidad', '!=', 'electronico');
    }

    /** Scope: Préstamos activos (prestados y dentro del plazo de devolución) */
    public function scopeActivos($query)
    {
        return $query->where('estado', 'prestado')
            ->where('fecha_devolucion_esperada', '>=', now());
    }

    /**
     * Scope: Préstamos vencidos. B15: incluye los ya marcados por archivo:marcar-prestamos-vencidos
     * y los que vencieron después de su última ejecución, para que el conteo no dependa del job.
     */
    public function scopeVencidos($query)
    {
        // El acceso electrónico no vence: expira solo (§3.10)
        return $query->where('modalidad', '!=', 'electronico')->where(fn ($q) => $q->where('estado', 'vencido')
            ->orWhere(fn ($q) => $q->where('estado', 'prestado')->where('fecha_devolucion_esperada', '<', now())));
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
