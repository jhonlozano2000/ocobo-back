<?php

namespace App\Events\MiBandeja\Grupos;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Un miembro leyó notas que tenía pendientes: los autores ven el ✓✓ verde sin recargar.
 */
class NotasLeidas implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $grupoId, public int $userId, public string $leidoAt) {}

    /** El front escucha «.NotasLeidas»: sin esto Reverb lo emite con el nombre completo de la clase. */
    public function broadcastAs(): string
    {
        return 'NotasLeidas';
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
            'leido_at' => $this->leidoAt,
        ];
    }
}
