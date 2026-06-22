<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RespuestaRadicadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public object $radicado;

    public string $mensaje;

    public array $emailOriginal;

    public function __construct(array $data)
    {
        $this->radicado = $data['radicado'];
        $this->mensaje = $data['mensaje'];
        $this->emailOriginal = $data['email_original'];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Respuesta a radicado {$this->radicado->num_radicado}"
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtml()
        );
    }

    private function buildHtml(): string
    {
        $numRadicado = e($this->radicado->num_radicado);
        $mensaje = nl2br(e($this->mensaje));
        $asunto = e($this->emailOriginal['asunto'] ?? '');
        $remitente = e($this->emailOriginal['remitente'] ?? '');

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8"></head>
        <body style="font-family: Arial, sans-serif; color: #333;">
            <div style="background: #1a5276; color: white; padding: 15px; text-align: center;">
                <h2 style="margin: 0;">OCOBO - Respuesta a Radicado</h2>
            </div>
            <div style="padding: 20px;">
                <p><strong>Estimado/a {$remitente},</strong></p>
                <p>En respuesta a su correo "{$asunto}", le informamos que se ha generado el radicado:</p>
                <div style="background: #f0f0f0; padding: 15px; border-radius: 5px; text-align: center; margin: 20px 0;">
                    <h3 style="color: #1a5276; margin: 0;">{$numRadicado}</h3>
                </div>
                <p><strong>Mensaje:</strong></p>
                <div style="background: #fafafa; padding: 15px; border-left: 3px solid #1a5276; margin: 10px 0;">
                    {$mensaje}
                </div>
                <p style="color: #666; font-size: 12px; margin-top: 30px;">
                    Este es un correo automático del Sistema de Gestión Documental - OCOBO.
                </p>
            </div>
        </body>
        </html>
        HTML;
    }
}
