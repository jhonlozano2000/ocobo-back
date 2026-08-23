<?php

namespace App\Models\VentanillaUnica\Pqrs;

use App\Models\ControlAcceso\UserCargo;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo VentanillaPqrsResponsable - Responsable de un radicado PQRS
 *
 * Representa la relación entre un PQRS y un usuario-cargo (UserCargo) que actúa
 * como responsable del trámite. Permite designar un custodio principal y registrar
 * la fecha/hora en que el responsable visualizó el radicado.
 *
 * @property int $id
 * @property int $pqrs_id
 * @property int $users_cargos_id
 * @property bool $custodio
 * @property \Carbon\Carbon|null $fechor_visto
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read VentanillaPqrs $pqrs
 * @property-read UserCargo $userCargo
 *
 * @author Jhon Javer Lozano Arce
 * @date 2026-08-20
 */
class VentanillaPqrsResponsable extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_pqrs_responsables';

    /**
     * Atributos asignables masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'pqrs_id',
        'users_cargos_id',
        'custodio',
        'fechor_visto',
    ];

    /**
     * Conversión automática de tipos de atributos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'custodio' => 'boolean',
        'fechor_visto' => 'datetime',
    ];

    /**
     * Relación: PQRS al que pertenece este responsable.
     */
    public function pqrs(): BelongsTo
    {
        return $this->belongsTo(VentanillaPqrs::class, 'pqrs_id');
    }

    /**
     * Relación: UserCargo (usuario + cargo) del responsable.
     */
    public function userCargo(): BelongsTo
    {
        return $this->belongsTo(UserCargo::class, 'users_cargos_id');
    }

    /**
     * Scope: Filtrar solo custodios (responsables principales).
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeCustodios(Builder $query): Builder
    {
        return $query->where('custodio', true);
    }

    /**
     * Scope: Filtrar responsables que NO son custodios.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeNoCustodios(Builder $query): Builder
    {
        return $query->where('custodio', false);
    }

    /**
     * Marca el responsable como "visto" registrando la fecha/hora actual.
     *
     * @return bool true si se registró el acuse, false si ya estaba marcado
     */
    public function marcarComoVisto(): bool
    {
        if (! $this->fechor_visto) {
            $this->update(['fechor_visto' => now()]);

            return true;
        }

        return false;
    }

    /**
     * Desmarca el responsable como "visto" (limpia la fecha).
     *
     * @return void
     */
    public function desmarcarComoVisto(): void
    {
        $this->update(['fechor_visto' => null]);
    }

    /**
     * Verifica si este responsable es el custodio principal.
     *
     * @return bool
     */
    public function isCustodio(): bool
    {
        return (bool) $this->custodio;
    }

    /**
     * Obtiene información resumida del responsable para APIs/respuestas.
     *
     * @return array{
     *     user: \App\Models\User|null,
     *     cargo: \App\Models\Calidad\CalidadOrganigrama|null,
     *     custodio: bool,
     *     fechor_visto: \Carbon\Carbon|null
     * }
     */
    public function getInfoResponsable(): array
    {
        $userCargo = $this->userCargo;
        return [
            'user' => $userCargo?->user,
            'cargo' => $userCargo?->cargo,
            'custodio' => $this->custodio,
            'fechor_visto' => $this->fechor_visto,
        ];
    }
}