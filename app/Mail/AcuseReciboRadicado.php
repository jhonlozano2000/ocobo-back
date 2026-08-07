<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class AcuseReciboRadicado extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public mixed $radicado;

    public string $tipo;

    public bool $conAdjuntos;

    public string $nombreEntidad;

    // Reintentos y backoff
    public $tries = 3;

    public $backoff = [30, 60, 120];

    public function __construct(mixed $radicado, string $tipo = 'recibida', bool $conAdjuntos = false)
    {
        $this->radicado = $radicado;
        $this->tipo = $tipo;
        $this->conAdjuntos = $conAdjuntos;
        $this->nombreEntidad = config('app.nombre_entidad', config('app.name', 'Entidad'));
    }

    public function envelope(): Envelope
    {
        $subject = match ($this->tipo) {
            'recibida' => 'Acuse de recibo - Radicado '.($this->radicado->num_radicado ?? ''),
            'enviada' => 'Comunicación oficial - '.($this->radicado->num_radicado ?? ''),
            default => 'Notificación de radicado - '.($this->radicado->num_radicado ?? ''),
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.acuse-recibo-radicado',
            with: [
                'radicado' => $this->radicado,
                'tipo' => $this->tipo,
                'nombreEntidad' => $this->nombreEntidad,
                'fechaRadicado' => $this->radicado->created_at->format('d/m/Y H:i'),
                'numRadicado' => $this->radicado->num_radicado ?? '',
                'codVerifica' => $this->radicado->cod_verifica ?? null,
                'asunto' => $this->radicado->asunto ?? null,
                'fecVenci' => $this->radicado->fec_venci
                    ? Carbon::parse($this->radicado->fec_venci)->format('d/m/Y')
                    : 'No definida',
                'nombreTercero' => $this->radicado->tercero?->nom_razo_soci ?? 'Ciudadano',
            ],
        );
    }

    public function attachments(): array
    {
        if (! $this->conAdjuntos) {
            return [];
        }

        $attachments = [];

        // Archivo digital principal
        if ($this->radicado->archivo_digital && Storage::disk('radicados_recibidos')->exists($this->radicado->archivo_digital)) {
            $nombreArchivo = $this->radicado->nom_origi ?: basename($this->radicado->archivo_digital);
            $attachments[] = Attachment::fromStorageDisk(
                'radicados_recibidos',
                $this->radicado->archivo_digital
            )->as($nombreArchivo);
        }

        // Archivos adjuntos adicionales
        if ($this->radicado->relationLoaded('archivos') && $this->radicado->archivos) {
            foreach ($this->radicado->archivos as $archivo) {
                if (Storage::disk('radicados_recibidos')->exists($archivo->archivo)) {
                    $name = $archivo->nom_origi ?: basename($archivo->archivo);
                    $attachments[] = Attachment::fromStorageDisk('radicados_recibidos', $archivo->archivo)->as($name);
                }
            }
        }

        return $attachments;
    }
}
