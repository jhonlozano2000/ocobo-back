<?php

namespace App\Mail;

use App\Helpers\MailAdjuntosHelper;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RadicadoInternoNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $radicado;

    public $tipo;

    public function __construct(VentanillaRadicaInterno $radicado, string $tipo = 'asignacion')
    {
        $this->radicado = $radicado;
        $this->tipo = $tipo;
    }

    public function envelope(): Envelope
    {
        $subject = match ($this->tipo) {
            'asignacion' => 'Nuevo radicado interno asignado - '.$this->radicado->num_radicado,
            'actualizacion' => 'Radicado interno actualizado - '.$this->radicado->num_radicado,
            default => 'Notificación de radicado interno - '.$this->radicado->num_radicado,
        };

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.radicado-notification',
            with: [
                'radicado' => $this->radicado,
                'tipo' => $this->tipo,
                'hashArchivo' => $this->radicado->hash_sha256 ?? null,
                'adjuntosOmitidos' => $this->datosAdjuntos()['omitidos'],
            ],
        );
    }

    public function attachments(): array
    {
        return $this->datosAdjuntos()['attachments'];
    }

    private function datosAdjuntos(): array
    {
        return MailAdjuntosHelper::seleccionarAdjuntos(
            'radicados_internos',
            $this->radicado->archivo_digital
                ? ['path' => $this->radicado->archivo_digital, 'nombre' => $this->radicado->nom_origi]
                : null,
            collect()
        );
    }
}
