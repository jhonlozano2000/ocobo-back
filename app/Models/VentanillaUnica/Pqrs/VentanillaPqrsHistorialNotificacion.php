<?php

namespace App\Models\VentanillaUnica\Pqrs;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentanillaPqrsHistorialNotificacion extends Model
{
    use HasFactory;

    protected $table = 'ventanilla_pqrs_historial_notificaciones';

    protected $fillable = [
        'pqrs_id',
        'tipo',
        'destinatarios',
        'total_enviados',
        'user_id',
    ];

    protected $casts = [
        'destinatarios' => 'array',
        'total_enviados' => 'integer',
    ];

    public function pqrs(): BelongsTo
    {
        return $this->belongsTo(VentanillaPqrs::class, 'pqrs_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}