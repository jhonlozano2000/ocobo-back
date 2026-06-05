<?php

namespace App\Mail;

use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PqrsNotificacionEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $pqrs;
    public $mensajePersonalizado;
    public $asuntoPersonalizado;

    public function __construct(VentanillaPqrs $pqrs, string $mensajePersonalizado = '', ?string $asuntoPersonalizado = null)
    {
        $this->pqrs = $pqrs;
        $this->mensajePersonalizado = $mensajePersonalizado;
        $this->asuntoPersonalizado = $asuntoPersonalizado;
    }

    public function envelope(): Envelope
    {
        $tipo = $this->pqrs->tipoPqrs?->nombre ?? 'PQRS';
        $numRadicado = $this->pqrs->radicado?->num_radicado ?? 'N/A';

        return new Envelope(
            subject: $this->asuntoPersonalizado ?? "Notificación {$tipo} - Radicado {$numRadicado}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pqrs-notificacion',
            with: [
                'pqrs' => $this->pqrs,
                'mensajePersonalizado' => $this->mensajePersonalizado,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
