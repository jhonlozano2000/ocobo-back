<?php

namespace App\Events;

use App\Models\Notificacion;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class NotificationPushed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public Notificacion $notification;

    /**
     * @param  bool  $emergente  el usuario tiene activo el aviso emergente (canal browser) de la categoría
     */
    public function __construct(Notificacion $notification, public bool $emergente = false)
    {
        $this->notification = $notification->loadMissing('user');
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.'.$this->notification->user_id);
    }

    public function broadcastAs(): string
    {
        return 'notification.pushed';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'type' => $this->notification->type,
            'title' => $this->notification->title,
            'message' => $this->notification->message,
            'data' => $this->notification->data,
            'created_at' => $this->notification->created_at->toISOString(),
            'read_at' => null,
            'emergente' => $this->emergente,
        ];
    }
}
