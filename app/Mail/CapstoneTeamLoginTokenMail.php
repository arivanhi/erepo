<?php

namespace App\Mail;

use App\Models\CapstoneTeam;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CapstoneTeamLoginTokenMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public CapstoneTeam $team,
        public string $plainToken,
        public string $expiresAtFormatted,
        public string $memberNames
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[EREPO] Kode Token Login Kelompok Capstone (' . $this->team->group_code . ')',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.capstone_team_token',
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
