<?php

namespace App\Events\MiBandeja\TempReci;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UsuarioDesconectado implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $documentoId;
    public int $userId;
    public string $userName;

    public function __construct(int $documentoId, int $userId, string $userName)
    {
        $this->documentoId = $documentoId;
        $this->userId = $userId;
        $this->userName = $userName;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("documentos.{$this->documentoId}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'documento_id' => $this->documentoId,
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'timestamp' => now()->toISOString(),
        ];
    }
}