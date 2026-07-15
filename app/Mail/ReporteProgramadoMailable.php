<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReporteProgramadoMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        protected string $filePath,
        protected string $subjectText,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectText,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.reporte-programado',
            with: [
                'subjectText' => $this->subjectText,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->filePath),
        ];
    }
}
