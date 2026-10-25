<?php

namespace App\Mail;

use App\Models\Notificacion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Copia por correo de una notificación de la campana, con un enlace que la abre en OCOBO.
 */
class NotificacionMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $enlace;

    public function __construct(public Notificacion $notificacion)
    {
        $this->enlace = rtrim((string) config('app.url'), '/').'/es'.$notificacion->rutaFront();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->notificacion->title.' - OCOBO',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notificacion',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
