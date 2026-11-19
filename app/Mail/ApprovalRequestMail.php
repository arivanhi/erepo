<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApprovalRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public $project;

    public string $projectType;

    public string $memberNames;

    public string $dosenName;

    public function __construct($project, string $projectType, string $memberNames = '', string $dosenName = '')
    {
        $this->project = $project;
        $this->projectType = $projectType;
        $this->memberNames = $memberNames !== ''
            ? $memberNames
            : ($project->user->name ?? 'Mahasiswa');
        $this->dosenName = $dosenName !== ''
            ? $dosenName
            : ($project->dosen->name ?? $project->capstoneProject?->dosen?->name ?? '-');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[EREPO] Permintaan Verifikasi ' . $this->projectType . ': ' . $this->project->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.approval_request',
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
