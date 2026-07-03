<?php

namespace App\Events\MiBandeja\Grupos;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotaTyping implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $grupoId;
    public int $userId;
    public string $userName;
    public ?string $userAvatar;
    public bool $typing;

    public function __construct(int $grupoId, int $userId, string $userName, ?string $userAvatar, bool $typing)
    {
        $this->grupoId = $grupoId;
        $this->userId = $userId;
        $this->userName = $userName;
        $this->userAvatar = $userAvatar;
        $this->typing = $typing;
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
            'user_name' => $this->userName,
            'user_avatar' => $this->userAvatar,
            'typing' => $this->typing,
        ];
    }
}
