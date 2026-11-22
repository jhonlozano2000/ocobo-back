<?php

namespace App\Events\MiBandeja\TempReci;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class CamposGrupoActualizados implements ShouldBroadcast
{
    public function __construct(public int $documentoId) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("documentos.{$this->documentoId}");
    }

    public function broadcastAs(): string
    {
        return 'campos-grupo.actualizados';
    }
}
