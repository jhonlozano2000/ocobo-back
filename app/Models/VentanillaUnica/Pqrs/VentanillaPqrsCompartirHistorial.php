<?php

namespace App\Models\VentanillaUnica\Pqrs;

use App\Models\User;
use App\Models\ControlAcceso\UserCargo;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo VentanillaPqrsCompartirHistorial - Historial de compartidos (CC) de PQRS
 *
 * Registra cada vez que un PQRS se comparte (copia de conocimiento/CC) con otro
 * usuario/cargo. A diferencia de los pases, el compartido NO transfiere responsabilidad,
 * solo da visibilidad/lectura al destinatario. Es inmutable (solo INSERT).
 *
 * @property int $id
 * @property int $pqrs_id
 * @property int $usuario_origen_id
 * @property int $users_cargos_destino_id
 * @property int|null $usuario_destino_id
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
class VentanillaPqrsCompartirHistorial extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_pqrs_compartir_historial';

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
    ];

    /**
     * Relación: PQRS al que pertenece este compartido.
     */
    public function pqrs(): BelongsTo
    {
        return $this->belongsTo(VentanillaPqrs::class, 'pqrs_id');
    }

    /**
     * Relación: Usuario que origina el compartido (quien comparte).
     */
    public function usuarioOrigen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_origen_id');
    }

    /**
     * Relación: Usuario destino del compartido (quien recibe la copia).
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