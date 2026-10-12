<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class NuevoInicioSesionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $nombre,
        public string $ip,
        public string $dispositivo,
        public Carbon $fecha,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nuevo inicio de sesión en tu cuenta - OCOBO',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.nuevo-inicio-sesion',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
