<?php

namespace App\Jobs;

use App\Helpers\MailConfigHelper;
use App\Mail\NotificacionMail;
use App\Models\Notificacion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Correo de una notificación cuyo usuario activó el canal de correo de la categoría. En una cola real
 * se encola; con QUEUE_CONNECTION=sync se envía después de la respuesta para no esperar al SMTP.
 */
class EnviarCorreoNotificacion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public int $notificacionId) {}

    public static function programar(int $notificacionId): void
    {
        if (config('queue.default') === 'sync') {
            dispatch(new self($notificacionId))->afterResponse();

            return;
        }

        dispatch(new self($notificacionId));
    }

    public function handle(): void
    {
        $notificacion = Notificacion::with('user')->find($this->notificacionId);
        $email = $notificacion?->user?->email;

        if (! $email) {
            return;
        }

        try {
            MailConfigHelper::configureFromConfigVarias();
            Mail::to($email)->send(new NotificacionMail($notificacion));
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar el correo de la notificación', [
                'notification_id' => $this->notificacionId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
