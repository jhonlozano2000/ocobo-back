<?php

namespace App\Events\MiBandeja\TempReci;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento cuando un comentario es creado.
 *
 * Se emite via WebSocket para notificar
 * a otros usuarios en tiempo real.
 */
class ComentarioCreado implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @var int ID del documento */
    public int $documentoId;

    /** @var array Datos del comentario */
    public array $comentario;

    /**
     * Crea una nueva instancia del evento.
     */
    public function __construct(int $documentoId, array $comentario)
    {
        $this->documentoId = $documentoId;
        $this->comentario = $comentario;
    }

    /**
     * Canales donde broadcastear.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("documentos.{$this->documentoId}"),
        ];
    }

    /**
     * Datos a enviar con el evento.
     */
    public function broadcastWith(): array
    {
        return [
            'documento_id' => $this->documentoId,
            'comentario' => $this->comentario,
            'timestamp' => now()->toISOString(),
        ];
    }
}
