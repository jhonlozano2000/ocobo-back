<?php

namespace App\Events\MiBandeja\Grupos;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MiembroCumplido implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $grupoId;
    public int $userId;
    public string $nombreUsuario;
    public string $rol;
    public bool $todosCumplidos;
    public ?string $nuevoEstado;

    public function __construct(int $grupoId, int $userId, string $nombreUsuario, string $rol, bool $todosCumplidos, ?string $nuevoEstado = null)
    {
        $this->grupoId = $grupoId;
        $this->userId = $userId;
        $this->nombreUsuario = $nombreUsuario;
        $this->rol = $rol;
        $this->todosCumplidos = $todosCumplidos;
        $this->nuevoEstado = $nuevoEstado;
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
            'user_id' => $this->userId,
            'nombre_usuario' => $this->nombreUsuario,
            'rol' => $this->rol,
            'todos_cumplidos' => $this->todosCumplidos,
            'nuevo_estado' => $this->nuevoEstado,
            'timestamp' => now()->toISOString(),
        ];
    }
}
