<?php

namespace App\Events\MiBandeja\Grupos;

use App\Models\MiBandeja\MiBandejaTempNota;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotaCreada implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $notaId;
    public int $grupoId;
    public string $contenido;
    public int $userId;
    public array $user;
    public string $createdAt;

    public function __construct(MiBandejaTempNota $nota)
    {
        $nota->loadMissing('user');

        $this->notaId = $nota->id;
        $this->grupoId = $nota->grupo_id;
        $this->contenido = $nota->contenido;
        $this->userId = $nota->user_id;

        $userModel = $nota->user;
        $this->user = $userModel
            ? [
                'id' => $userModel->id,
                'nombres' => $userModel->nombres ?? '',
                'apellidos' => $userModel->apellidos ?? '',
                'avatar' => $userModel->avatar ?: null,
            ]
            : [
                'id' => $nota->user_id,
                'nombres' => '',
                'apellidos' => '',
                'avatar' => null,
            ];

        $this->createdAt = $nota->created_at?->toISOString() ?? now()->toISOString();
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
            'id' => $this->notaId,
            'grupo_id' => $this->grupoId,
            'contenido' => $this->contenido,
            'user_id' => $this->userId,
            'user' => $this->user,
            'created_at' => $this->createdAt,
        ];
    }
}
