<?php

namespace App\Mail;

use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

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
        $subject = match($this->tipo) {
            'asignacion' => 'Nuevo radicado interno asignado - ' . $this->radicado->num_radicado,
            'actualizacion' => 'Radicado interno actualizado - ' . $this->radicado->num_radicado,
            default => 'Notificación de radicado interno - ' . $this->radicado->num_radicado,
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
            ],
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        if ($this->radicado->archivo_digital && Storage::disk('ventanilla_radica_interno_archivos')->exists($this->radicado->archivo_digital)) {
            $nombreArchivo = $this->radicado->nom_origi ?: basename($this->radicado->archivo_digital);
            $attachments[] = \Illuminate\Mail\Mailables\Attachment::fromStorageDisk(
                'ventanilla_radica_interno_archivos',
                $this->radicado->archivo_digital
            )->as($nombreArchivo);
        }

        return $attachments;
    }
}