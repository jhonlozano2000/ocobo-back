<?php

namespace App\Events\MiBandeja\TempReci;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento cuando un cursor es actualizado.
 *
 * Se emite via WebSocket para mostrar
 * la posición del cursor en tiempo real.
 */
class CursorActualizado implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @var int ID del documento */
    public int $documentoId;

    /** @var array Datos del cursor */
    public array $cursor;

    /**
     * Crea una nueva instancia del evento.
     *
     * @param int $documentoId
     * @param array $cursor
     */
    public function __construct(int $documentoId, array $cursor)
    {
        $this->documentoId = $documentoId;
        $this->cursor = $cursor;
    }

    /**
     * Canales donde broadcastear.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("documentos.{$this->documentoId}"),
        ];
    }

    /**
     * Datos a enviar con el evento.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return [
            'documento_id' => $this->documentoId,
            'cursor' => $this->cursor,
            'timestamp' => now()->toISOString(),
        ];
    }
}