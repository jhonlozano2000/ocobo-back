<?php

namespace App\Mail;

use App\Models\VentanillaUnica\VentanillaRadicaReci;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class RadicadoRecibidoNotificacionTercero extends Mailable
{
    use Queueable, SerializesModels;

    public VentanillaRadicaReci $radicado;
    public string $nombreEntidad;

    public function __construct(VentanillaRadicaReci $radicado)
    {
        $this->radicado = $radicado;
        $this->nombreEntidad = config('app.nombre_entidad', config('app.name', 'Entidad'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Acuse de recibo - Radicado {$this->radicado->num_radicado}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.radicado-recibido-tercero',
            with: [
                'radicado' => $this->radicado,
                'nombreEntidad' => $this->nombreEntidad,
                'numRadicado' => $this->radicado->num_radicado,
                'asunto' => $this->radicado->asunto,
                'fechaRadicado' => $this->radicado->created_at->format('d/m/Y H:i'),
                'codVerifica' => $this->radicado->cod_verifica,
                'nombreTercero' => $this->radicado->tercero?->nom_razo_soci ?? 'Ciudadano',
            ],
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        // Archivo digital principal
        if ($this->radicado->archivo_digital && Storage::disk('radicados_recibidos')->exists($this->radicado->archivo_digital)) {
            $nombreArchivo = $this->radicado->nom_origi ?: basename($this->radicado->archivo_digital);
            $attachments[] = \Illuminate\Mail\Mailables\Attachment::fromStorageDisk(
                'radicados_recibidos',
                $this->radicado->archivo_digital
            )->as($nombreArchivo);
        }

        // Archivos adicionales
        if ($this->radicado->relationLoaded('archivos') && $this->radicado->archivos) {
            foreach ($this->radicado->archivos as $archivo) {
                if (Storage::disk('radicados_recibidos')->exists($archivo->archivo)) {
                    $nombreArchivo = $archivo->nom_origi ?: basename($archivo->archivo);
                    $attachments[] = \Illuminate\Mail\Mailables\Attachment::fromStorageDisk(
                        'radicados_recibidos',
                        $archivo->archivo
                    )->as($nombreArchivo);
                }
            }
        }

        return $attachments;
    }
}
