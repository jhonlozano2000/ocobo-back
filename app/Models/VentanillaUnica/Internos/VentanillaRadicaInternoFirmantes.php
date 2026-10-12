<?php

namespace App\Models\VentanillaUnica\Internos;

use App\Models\User;
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

    // M03-19: firmado/fecha_firma/otp_utilizado los escribe FirmaService::marcarFirmante
    protected $casts = [
        'firmado' => 'boolean',
        'fecha_firma' => 'datetime',
    ];

    public function radicado()
    {
        return $this->belongsTo(VentanillaRadicaInterno::class, 'radica_interno_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    public function getInfoFirmante(): ?array
    {
        $user = $this->relationLoaded('user') ? $this->user : null;

        if (! $user) {
            return null;
        }

        return [
            'id' => $this->id,
            'firmado' => (bool) $this->firmado,
            'fecha_firma' => $this->fecha_firma?->format('Y-m-d H:i:s'),
            'usuario' => [
                'id' => $user->id,
                'nombres' => $user->nombres,
                'apellidos' => $user->apellidos,
            ],
        ];
    }
}
