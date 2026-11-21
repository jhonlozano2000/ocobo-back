<?php

namespace App\Events\Ia;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Evento en tiempo real emitido vía Laravel Reverb al completarse una proyección de IA en segundo plano.
 *
 * Utiliza ShouldBroadcastNow para envío inmediato sin re-encolar en Redis/sync.
 */
class BorradorIaGenerado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  int  $userId  ID del usuario destinatario
     * @param  string  $jobId  UUID o identificador único de la solicitud
     * @param  array  $payload  Resultado de la proyección y metadatos de auditoría
     */
    public function __construct(
        public int $userId,
        public string $jobId,
        public array $payload
    ) {}

    /**
     * Canal privado por usuario para entrega segura.
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('ia-asistente.'.$this->userId);
    }

    /**
     * Nombre del evento escuchado en el frontend.
     */
    public function broadcastAs(): string
    {
        return 'borrador.generado';
    }

    /**
     * Datos transferidos al cliente en tiempo real.
     */
    public function broadcastWith(): array
    {
        return array_merge([
            'job_id' => $this->jobId,
            'timestamp' => now()->toISOString(),
        ], $this->payload);
    }
}
