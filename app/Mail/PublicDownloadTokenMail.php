<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PublicDownloadTokenMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $documentTypeLabel,
        public string $projectTitle,
        public string $groupCode,
        public string $downloadCode,
        public string $directDownloadUrl,
        public string $expiresAtFormatted
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[EREPO] Token Download ' . $this->documentTypeLabel . ' (Berlaku 1 Hari): ' . $this->projectTitle,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.public_download_token',
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
