<?php

namespace App\Mail;

use App\Models\CapstoneProject;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CapstoneAcceptedWithNoteMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, string>  $aiMissingItems
     */
    public function __construct(
        public CapstoneProject $project,
        public string $memberNames,
        public string $dosenName,
        public string $reviewerLabel,
        public string $acceptanceNote,
        public array $aiMissingItems = []
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[EREPO] Dokumen Capstone Diterima dengan Catatan: ' . $this->project->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.capstone_accepted_with_note',
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
