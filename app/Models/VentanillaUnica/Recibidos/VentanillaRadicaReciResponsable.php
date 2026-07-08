<?php

namespace App\Models\VentanillaUnica\Recibidos;

use App\Models\ControlAcceso\UserCargo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentanillaRadicaReciResponsable extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_reci_responsa';

    protected $fillable = [
        'radica_reci_id',
        'users_cargos_id',
        'custodio',
        'fechor_visto',
    ];

    protected $casts = [
        'custodio' => 'boolean',
        'fechor_visto' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function userCargo()
    {
        return $this->belongsTo(UserCargo::class, 'users_cargos_id');
    }

    public function usuarioCargo()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function radicado()
    {
        return $this->belongsTo(VentanillaRadicaReci::class, 'radica_reci_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function scopeCustodios($query)
    {
        return $query->where('custodio', true);
    }

    public function scopeNoCustodios($query)
    {
        return $query->where('custodio', false);
    }

    public function isCustodio()
    {
        return $this->custodio;
    }

    public function marcarComoCustodio()
    {
        $this->update(['custodio' => true]);
    }

    public function desmarcarComoCustodio()
    {
        $this->update(['custodio' => false]);
    }

    public function getInfoResponsable(): ?array
    {
        $userCargo = $this->relationLoaded('userCargo') ? $this->userCargo : $this->userCargo()->with(['user', 'cargo'])->first();

        if (! $userCargo) {
            return null;
        }

        $user = $userCargo->relationLoaded('user') ? $userCargo->user : null;
        $cargo = $userCargo->relationLoaded('cargo') ? $userCargo->cargo : null;

        return [
            'id' => $this->id,
            'custodio' => $this->custodio,
            'fechor_visto' => $this->fechor_visto,
            'fecha_asignacion' => $this->created_at,
            'usuario' => $user ? $user->getInfoUsuario() : null,
            'cargo' => $cargo ? [
                'id' => $cargo->id,
                'nombre' => $cargo->nom_organico,
                'codigo' => $cargo->cod_organico,
                'tipo' => $cargo->tipo,
            ] : null,
        ];
    }
}
