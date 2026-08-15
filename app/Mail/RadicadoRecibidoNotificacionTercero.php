<?php

namespace App\Mail;

use App\Helpers\MailAdjuntosHelper;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

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
        // Fecha límite de respuesta
        $fecVenci = $this->radicado->fec_venci
            ? Carbon::parse($this->radicado->fec_venci)->format('d/m/Y')
            : 'No definida';

        return new Content(
            view: 'emails.radicado-recibido-tercero',
            with: [
                'radicado' => $this->radicado,
                'nombreEntidad' => $this->nombreEntidad,
                'numRadicado' => $this->radicado->num_radicado,
                'asunto' => $this->radicado->asunto,
                'fechaRadicado' => $this->radicado->created_at->format('d/m/Y H:i'),
                'fecVenci' => $fecVenci,
                'codVerifica' => $this->radicado->cod_verifica,
                'nombreTercero' => $this->radicado->tercero?->nom_razo_soci ?? 'Ciudadano',
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
            'radicados_recibidos',
            $this->radicado->archivo_digital
                ? ['path' => $this->radicado->archivo_digital, 'nombre' => $this->radicado->nom_origi]
                : null,
            $this->radicado->archivos ?? collect()
        );
    }
}
