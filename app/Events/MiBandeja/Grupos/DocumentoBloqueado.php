<?php

namespace App\Events\MiBandeja\Grupos;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentoBloqueado implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $grupoId;
    public int $bloqueadoPorUserId;
    public string $nombreUsuario;
    public string $fechaBloqueo;

    public function __construct(int $grupoId, int $bloqueadoPorUserId, string $nombreUsuario, string $fechaBloqueo)
    {
        $this->grupoId = $grupoId;
        $this->bloqueadoPorUserId = $bloqueadoPorUserId;
        $this->nombreUsuario = $nombreUsuario;
        $this->fechaBloqueo = $fechaBloqueo;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("grupo-colaborativo.{$this->grupoId}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'grupo_id' => $this->grupoId,
            'bloqueado_por_user_id' => $this->bloqueadoPorUserId,
            'nombre_usuario' => $this->nombreUsuario,
            'fecha_bloqueo' => $this->fechaBloqueo,
            'timestamp' => now()->toISOString(),
        ];
    }
}
