<?php

namespace App\Mail;

use App\Models\VentanillaUnica\VentanillaRadicaReci;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class RadicadoNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $radicado;
    public $tipo;

    /**
     * Create a new message instance.
     */
    public function __construct(VentanillaRadicaReci $radicado, string $tipo = 'asignacion')
    {
        $this->radicado = $radicado;
        $this->tipo = $tipo;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match($this->tipo) {
            'asignacion' => 'Nuevo radicado asignado - ' . $this->radicado->num_radicado,
            'actualizacion' => 'Radicado actualizado - ' . $this->radicado->num_radicado,
            'vencimiento' => 'Radicado próximo a vencer - ' . $this->radicado->num_radicado,
            default => 'Notificación de radicado - ' . $this->radicado->num_radicado,
        };

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
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

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        // Verificar si el radicado tiene archivo digital asociado
        if ($this->radicado->archivo_digital && Storage::disk('radicados_recibidos')->exists($this->radicado->archivo_digital)) {
            $attachments[] = \Illuminate\Mail\Mailables\Attachment::fromStorageDisk(
                'radicados_recibidos',
                $this->radicado->archivo_digital
            )->as(basename($this->radicado->archivo_digital));
        }

        // Agregar archivos adicionales de la tabla ventanilla_radica_reci_archivos
        if ($this->radicado->archivos) {
            foreach ($this->radicado->archivos as $archivo) {
                if (Storage::disk('radicados_recibidos')->exists($archivo->archivo)) {
                    $attachments[] = \Illuminate\Mail\Mailables\Attachment::fromStorageDisk(
                        'radicados_recibidos',
                        $archivo->archivo
                    )->as(basename($archivo->archivo));
                }
            }
        }

        return $attachments;
    }
}