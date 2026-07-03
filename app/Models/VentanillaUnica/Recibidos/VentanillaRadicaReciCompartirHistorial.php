<?php

namespace App\Models\VentanillaUnica\Recibidos;

use App\Models\ControlAcceso\UserCargo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentanillaRadicaReciCompartirHistorial extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_reci_compartir_historial';

    protected $fillable = [
        'radica_reci_id',
        'usuario_origen_id',
        'users_cargos_destino_id',
        'usuario_destino_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con el radicado.
     */
    public function radicado()
    {
        return $this->belongsTo(VentanillaRadicaReci::class, 'radica_reci_id');
    }

    /**
     * Usuario que originó el compartir (puede ser null).
     */
    public function usuarioOrigen()
    {
        return $this->belongsTo(User::class, 'usuario_origen_id');
    }

    /**
     * Usuario destino del compartir.
     */
    public function usuarioDestino()
    {
        return $this->belongsTo(User::class, 'usuario_destino_id');
    }

    /**
     * Relación users_cargos del usuario destino.
     */
    public function usersCargosDestino()
    {
        return $this->belongsTo(UserCargo::class, 'users_cargos_destino_id');
    }

    /**
     * Devuelve la info formateada del historial para respuestas API.
     */
    public function getInfoHistorialAttribute(): array
    {
        $cargoRel = $this->relationLoaded('usersCargosDestino') ? $this->usersCargosDestino : $this->usersCargosDestino()->first();
        $cargo = $cargoRel && $cargoRel->relationLoaded('cargo') ? $cargoRel->cargo : ($cargoRel ? $cargoRel->cargo()->first() : null);

        return [
            'id' => $this->id,
            'fecha' => $this->created_at,
            'radica_reci_id' => $this->radica_reci_id,
            'usuario_origen' => $this->usuarioOrigen?->getInfoUsuario(),
            'usuario_destino' => $this->usuarioDestino?->getInfoUsuario(),
            'users_cargos_destino_id' => $this->users_cargos_destino_id,
            'cargo_destino' => $cargo ? [
                'id' => $cargo->id,
                'nombre' => $cargo->nom_organico,
                'codigo' => $cargo->cod_organico,
            ] : null,
        ];
    }
}