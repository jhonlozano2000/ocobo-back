<?php

namespace App\Mail;

use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
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

        // Archivo digital principal
        if ($this->radicado->archivo_digital && Storage::disk($disk)->exists($this->radicado->archivo_digital)) {
            $nombreArchivo = $this->radicado->nom_origi ?: basename($this->radicado->archivo_digital);
            $attachments[] = Attachment::fromStorageDisk(
                $disk,
                $this->radicado->archivo_digital
            )->as($nombreArchivo);
        }

        // Archivos adicionales
        if ($this->radicado->relationLoaded('archivos') && $this->radicado->archivos) {
            foreach ($this->radicado->archivos as $archivo) {
                if (Storage::disk($disk)->exists($archivo->archivo)) {
                    $nombreArchivo = $archivo->nom_origi ?: basename($archivo->archivo);
                    $attachments[] = Attachment::fromStorageDisk($disk, $archivo->archivo)->as($nombreArchivo);
                }
            }
        }

        return $attachments;
    }
}
