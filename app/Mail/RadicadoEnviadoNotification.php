<?php

namespace App\Mail;

use App\Models\VentanillaUnica\VentanillaRadicaEnviados;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

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
            'asignacion' => 'Nuevo radicado enviado asignado - ' . $this->radicado->num_radicado,
            'actualizacion' => 'Radicado enviado actualizado - ' . $this->radicado->num_radicado,
            default => 'Notificación de radicado enviado - ' . $this->radicado->num_radicado,
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
            ]
        );
    }

    public function attachments(): array
    {
        $attachments = [];
        $disk = 'radicados_enviados';

        if ($this->radicado->archivo_digital && Storage::disk($disk)->exists($this->radicado->archivo_digital)) {
            $attachments[] = Attachment::fromStorageDisk(
                $disk,
                $this->radicado->archivo_digital
            )->as(basename($this->radicado->archivo_digital));
        }

        return $attachments;
    }
}
