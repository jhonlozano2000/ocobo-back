<?php

namespace App\Events\VentanillaUnica;

use App\Models\VentanillaUnica\Recibidos\RadicadoRespuesta;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Auth;

class RespuestaEditing implements ShouldBroadcast
{
    use InteractsWithSockets;

    public RadicadoRespuesta $respuesta;
    public string $evento;
    public ?int $userId;
    public ?array $data;

    public function __construct(RadicadoRespuesta $respuesta, string $evento, array $data = [])
    {
        $this->respuesta = $respuesta;
        $this->evento = $evento;
        $this->userId = Auth::id();
        $this->data = $data;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('respuesta.' . $this->respuesta->id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'evento' => $this->evento,
            'respuesta_id' => $this->respuesta->id,
            'user_id' => $this->userId,
            'user_nombre' => Auth::user()?->name,
            'data' => $this->data,
            'timestamp' => now()->toISOString(),
        ];
    }
}