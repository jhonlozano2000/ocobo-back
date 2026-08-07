<?php

namespace App\Models\VentanillaUnica\Recibidos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class VentanillaRadicaHistorialNotificacion extends Model
{
    protected $table = 'ventanilla_radica_historial_notificaciones';

    protected $fillable = [
        'radicado_id',
        'tipo',
        'destinatarios',
        'total_enviados',
        'user_id',
    ];

    protected $casts = [
        'destinatarios' => 'array',
        'total_enviados' => 'integer',
    ];

    public function radicado()
    {
        return $this->belongsTo(VentanillaRadicaReci::class, 'radicado_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
