<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpFirmaMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;
    public $nombre;

    public function __construct($otp, $nombre)
    {
        $this->otp = $otp;
        $this->nombre = $nombre;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Código de Seguridad para Firma Electrónica - Ocobo SGDEA",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: "emails.otp_firma",
        );
    }
}
