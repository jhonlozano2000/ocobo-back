<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AcuseReciboRadicado extends Mailable
{
    use Queueable, SerializesModels;

    public mixed $radicado;
    public string $tipo;
    public string $nombreEntidad;

    public function __construct(mixed $radicado, string $tipo = 'recibida')
    {
        $this->radicado      = $radicado;
        $this->tipo          = $tipo;
        $this->nombreEntidad = config('app.nombre_entidad', config('app.name', 'Entidad'));
    }

    public function envelope(): Envelope
    {
        $subject = match($this->tipo) {
            'recibida' => 'Acuse de recibo — Radicado ' . ($this->radicado->num_radicado ?? ''),
            'enviada'  => 'Comunicación oficial — ' . ($this->radicado->num_radicado ?? ''),
            default    => 'Notificación de radicado — ' . ($this->radicado->num_radicado ?? ''),
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.acuse-recibo-radicado',
            with: [
                'radicado'      => $this->radicado,
                'tipo'          => $this->tipo,
                'nombreEntidad' => $this->nombreEntidad,
                'fechaRadicado' => $this->radicado->created_at->format('d/m/Y H:i'),
                'numRadicado'   => $this->radicado->num_radicado ?? '',
                'codVerifica'   => $this->radicado->cod_verifica ?? null,
                'asunto'        => $this->radicado->asunto ?? null,
                'fecVenci'      => $this->radicado->fec_venci
                    ? \Carbon\Carbon::parse($this->radicado->fec_venci)->format('d/m/Y')
                    : 'No definida',
                'nombreTercero' => $this->radicado->tercero?->nom_razo_soci ?? 'Ciudadano',
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
