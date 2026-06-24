<?php

namespace App\Events\MiBandeja\Grupos;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentoLiberado implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $grupoId;
    public string $nuevaVersion;
    public int $subidoPorUserId;
    public string $nombreUsuario;

    public function __construct(int $grupoId, string $nuevaVersion, int $subidoPorUserId, string $nombreUsuario)
    {
        $this->grupoId = $grupoId;
        $this->nuevaVersion = $nuevaVersion;
        $this->subidoPorUserId = $subidoPorUserId;
        $this->nombreUsuario = $nombreUsuario;
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
            'nueva_version' => $this->nuevaVersion,
            'subido_por_user_id' => $this->subidoPorUserId,
            'nombre_usuario' => $this->nombreUsuario,
            'timestamp' => now()->toISOString(),
        ];
    }
}
