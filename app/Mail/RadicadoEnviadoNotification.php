<?php

namespace App\Mail;

use App\Helpers\MailAdjuntosHelper;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RadicadoEnviadoNotification extends Mailable
{
    use Queueable, SerializesModels;

    public VentanillaRadicaEnviados $radicado;

    public string $tipo;

    public function __construct(VentanillaRadicaEnviados $radicado, string $tipo = 'asignacion')
    {
        $this->radicado = $radicado;
        $this->tipo = $tipo;
    }

    public function envelope(): Envelope
    {
        $subject = match ($this->tipo) {
            'asignacion' => 'Nuevo radicado enviado asignado - '.$this->radicado->num_radicado,
            'actualizacion' => 'Radicado enviado actualizado - '.$this->radicado->num_radicado,
            default => 'Notificación de radicado enviado - '.$this->radicado->num_radicado,
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.radicado-enviado-notification',
            with: [
                'radicado' => $this->radicado,
                'tipo' => $this->tipo,
                'hashArchivo' => $this->radicado->hash_sha256 ?? null,
                'adjuntosOmitidos' => $this->datosAdjuntos()['omitidos'],
            ]
        );
    }

    public function attachments(): array
    {
        return $this->datosAdjuntos()['attachments'];
    }

    private function datosAdjuntos(): array
    {
        return MailAdjuntosHelper::seleccionarAdjuntos(
            'radicados_enviados',
            $this->radicado->archivo_digital
                ? ['path' => $this->radicado->archivo_digital, 'nombre' => $this->radicado->nom_origi]
                : null,
            $this->radicado->archivos ?? collect()
        );
    }
}
