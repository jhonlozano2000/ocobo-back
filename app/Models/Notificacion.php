<?php

namespace App\Models;

use App\Events\NotificationPushed;
use App\Models\ControlAcceso\UserNotificationSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Notificacion extends Model
{
    protected $table = 'notifications';

    /** Lo que se le asigna al usuario: preferencia «Nuevas para ti». */
    public const TIPOS_NUEVAS_PARA_TI = [
        'asignacion_responsable', 'asignacion_proyector', 'asignacion_firmante', 'tarea.asignada',
        'radicado_asignado', 'asignacion',
    ];

    /** Alertas de control de archivo: llegan siempre, sin importar las preferencias. */
    public const TIPOS_OBLIGATORIOS = [
        'integridad_archivo', 'eliminacion_documental', 'disposicion_archivo', 'retencion_trd',
        'expediente_inactivo', 'prestamo_vencido', 'prestamo_folios_no_coinciden',
    ];

    /**
     * M18-01: toda notificación creada se emite por WebSocket (NotificationPushed) cuando se
     * confirma la transacción, para que la campana se actualice sin esperar el polling.
     * Un fallo del broadcaster no debe tumbar la operación que la generó.
     */
    protected static function booted(): void
    {
        // Si el usuario apagó la categoría en sus preferencias (canal app), no se crea ni se emite
        static::creating(fn (self $notificacion) => $notificacion->user_id === null
            || self::permitidaPara((int) $notificacion->user_id, (string) $notificacion->type));

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

    /**
     * Preferencia (columna de users_notification_settings) que controla el tipo; null si es obligatorio.
     */
    public static function preferenciaDe(string $tipo): ?string
    {
        return match (true) {
            in_array($tipo, self::TIPOS_OBLIGATORIOS, true) => null,
            str_starts_with($tipo, 'seguridad.') => 'new_browser_login',
            in_array($tipo, self::TIPOS_NUEVAS_PARA_TI, true) => 'new_for_you',
            default => 'account_activity',
        };
    }

    /**
     * Sin preferencias guardadas, todo está habilitado.
     */
    public static function permitidaPara(int $userId, string $tipo): bool
    {
        $preferencia = self::preferenciaDe($tipo);
        if ($preferencia === null) {
            return true;
        }

        $settings = UserNotificationSetting::forUser($userId);

        return ! $settings || $settings->isEnabled($preferencia);
    }

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
