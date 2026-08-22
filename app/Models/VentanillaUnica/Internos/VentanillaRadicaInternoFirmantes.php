<?php

namespace App\Models\VentanillaUnica\Internos;

use App\Models\ControlAcceso\UserCargo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentanillaRadicaInternoFirmantes extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_radica_internos_firmantes';

    protected $fillable = [
        'radica_interno_id',
        'users_id',
    ];

    public function radicado()
    {
        return $this->belongsTo(VentanillaRadicaInterno::class, 'radica_interno_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'users_id');
    }

    public function getInfoFirmante(): ?array
    {
        $user = $this->relationLoaded('user') ? $this->user : null;

        if (! $user) {
            return null;
        }

        return [
            'id' => $this->id,
            'usuario' => [
                'id' => $user->id,
                'nombres' => $user->nombres,
                'apellidos' => $user->apellidos,
            ],
        ];
    }
}
