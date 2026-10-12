<?php

namespace App\Models;

use App\Events\NotificationPushed;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Notificacion extends Model
{
    protected $table = 'notifications';

    /**
     * M18-01: toda notificación creada se emite por WebSocket (NotificationPushed) cuando se
     * confirma la transacción, para que la campana se actualice sin esperar el polling.
     * Un fallo del broadcaster no debe tumbar la operación que la generó.
     */
    protected static function booted(): void
    {
        static::created(function (self $notificacion) {
            DB::afterCommit(function () use ($notificacion) {
                try {
                    event(new NotificationPushed($notificacion));
                } catch (\Throwable $e) {
                    Log::warning('No se pudo emitir la notificación en tiempo real', [
                        'notification_id' => $notificacion->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
        });
    }

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'notifiable_type',
        'notifiable_id',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function notifiable()
    {
        return $this->morphTo();
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function markAsRead(): void
    {
        if (! $this->isRead()) {
            $this->update(['read_at' => now()]);
        }
    }
}
