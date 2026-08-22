<?php

namespace App\Models\VentanillaUnica\Pqrs;

use App\Models\User;
use App\Models\ControlAcceso\UserCargo;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo VentanillaPqrsPaseHistorial - Historial de pases/reasignaciones de PQRS
 *
 * Registra de forma inmutable cada movimiento (pase, asignación inicial, reasignación)
 * de un radicado PQRS entre usuarios/cargos. Es una tabla de auditoría que permite
 * reconstruir la trazabilidad completa del trámite.
 *
 * @property int $id
 * @property int $pqrs_id
 * @property int $usuario_origen_id
 * @property int $users_cargos_destino_id
 * @property int|null $usuario_destino_id
 * @property string $tipo (pase|asignacion_inicial|reasignacion)
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read VentanillaPqrs $pqrs
 * @property-read User $usuarioOrigen
 * @property-read User|null $usuarioDestino
 * @property-read UserCargo $usersCargosDestino
 *
 * @author Jhon Javer Lozano Arce
 * @date 2026-08-20
 */
class VentanillaPqrsPaseHistorial extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_pqrs_pase_historial';

    /**
     * Atributos asignables masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'pqrs_id',
        'usuario_origen_id',
        'users_cargos_destino_id',
        'usuario_destino_id',
        'tipo',
    ];

    /**
     * Constante: Tipo de movimiento - Pase temporal (envío a otro cargo para revisión/acción).
     */
    public const TIPO_PASE = 'pase';

    /**
     * Constante: Tipo de movimiento - Asignación inicial del PQRS a un responsable.
     */
    public const TIPO_ASIGNACION_INICIAL = 'asignacion_inicial';

    /**
     * Constante: Tipo de movimiento - Reasignación (cambio de responsable).
     */
    public const TIPO_REASIGNACION = 'reasignacion';

    /**
     * Relación: PQRS al que pertenece este pase.
     */
    public function pqrs(): BelongsTo
    {
        return $this->belongsTo(VentanillaPqrs::class, 'pqrs_id');
    }

    /**
     * Relación: Usuario que origina el pase (quien envía/reasigna).
     */
    public function usuarioOrigen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_origen_id');
    }

    /**
     * Relación: Usuario destino del pase (quien recibe).
     */
    public function usuarioDestino(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_destino_id');
    }

    /**
     * Relación: Cargo del usuario destino (UserCargo).
     */
    public function usersCargosDestino(): BelongsTo
    {
        return $this->belongsTo(UserCargo::class, 'users_cargos_destino_id');
    }
}