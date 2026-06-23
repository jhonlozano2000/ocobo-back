<?php

namespace App\Mail;

use App\Helpers\MailConfigHelper;
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
        MailConfigHelper::configureFromConfigVarias();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'C�digo de Seguridad para Firma Electr�nica - Ocobo SGDEA',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.otp_firma',
        );
    }
}
