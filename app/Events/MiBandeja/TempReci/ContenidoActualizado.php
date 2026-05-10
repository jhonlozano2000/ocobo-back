<?php

namespace App\Events\MiBandeja\TempReci;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento cuando el contenido de un documento es actualizado.
 *
 * Se emite via WebSocket para sincronizar cambios
 * en tiempo real entre clientes.
 */
class ContenidoActualizado implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @var int ID del documento */
    public int $documentoId;

    /** @var array Contenido Yjs */
    public array $contenido;

    /** @var string Hash del contenido */
    public string $hash;

    /** @var int ID del usuario */
    public int $userId;

    /**
     * Crea una nueva instancia del evento.
     *
     * @param int $documentoId
     * @param array $contenido
     * @param string $hash
     * @param int $userId
     */
    public function __construct(int $documentoId, array $contenido, string $hash, int $userId)
    {
        $this->documentoId = $documentoId;
        $this->contenido = $contenido;
        $this->hash = $hash;
        $this->userId = $userId;
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
            'contenido' => $this->contenido,
            'hash' => $this->hash,
            'user_id' => $this->userId,
            'timestamp' => now()->toISOString(),
        ];
    }
}