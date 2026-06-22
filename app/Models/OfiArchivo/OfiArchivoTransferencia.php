<?php

namespace App\Models\OfiArchivo;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo de Transferencia Documental.
 *
 * Registra transferencias primarias (archivo gestión → archivo central)
 * y secundarias (archivo central → archivo histórico).
 * Acuerdo AGN 004/2019 — Disposición final de documentos.
 *
 * @property int $id
 * @property int $expediente_id
 * @property string $tipo primaria|secundaria
 * @property string $origen
 * @property string $destino
 * @property int $responsable_origen_id
 * @property int|null $responsable_destino_id
 * @property Carbon $fecha_transferencia
 * @property string $estado pendiente|aprobada|rechazada|completada
 * @property string|null $fuid_path Ruta del FUID generado
 */
class OfiArchivoTransferencia extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ofi_archivo_transferencias';

    protected $fillable = [
        'expediente_id',
        'tipo',
        'origen',
        'destino',
        'responsable_origen_id',
        'responsable_destino_id',
        'fecha_transferencia',
        'estado',
        'fuid_path',
        'observaciones',
        'usuario_registro_id',
    ];

    protected $casts = [
        'fecha_transferencia' => 'datetime',
    ];

    public function expediente()
    {
        return $this->belongsTo(OfiArchivoExpediente::class, 'expediente_id');
    }

    public function responsableOrigen()
    {
        return $this->belongsTo(User::class, 'responsable_origen_id');
    }

    public function responsableDestino()
    {
        return $this->belongsTo(User::class, 'responsable_destino_id');
    }

    public function usuarioRegistro()
    {
        return $this->belongsTo(User::class, 'usuario_registro_id');
    }

    /** Scope: Transferencias primarias (gestión → central) */
    public function scopePrimarias($query)
    {
        return $query->where('tipo', 'primaria');
    }

    /** Scope: Transferencias secundarias (central → histórico) */
    public function scopeSecundarias($query)
    {
        return $query->where('tipo', 'secundaria');
    }

    /** Scope: Filtrar por estado */
    public function scopePorEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }
}
