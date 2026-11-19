<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SmtpTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $triggeredByName,
        public string $smtpHost,
        public string $smtpUsername
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[EREPO] Tes Koneksi Google SMTP Berhasil',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.smtp_test',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
