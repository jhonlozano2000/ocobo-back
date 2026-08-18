<?php

namespace App\Models\VentanillaUnica\Internos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class VentanillaRadicaInternoHistorialNotificacion extends Model
{
    protected $table = 'ventanilla_radica_internos_historial_notificaciones';

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
        return $this->belongsTo(VentanillaRadicaInterno::class, 'radicado_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}