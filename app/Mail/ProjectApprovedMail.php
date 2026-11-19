<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProjectApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $project;

    public string $projectType;

    public string $memberNames;

    public string $reviewerLabel;

    public function __construct($project, string $projectType, string $memberNames = '', string $reviewerLabel = 'Tim Verifikator')
    {
        $this->project = $project;
        $this->projectType = $projectType;
        $this->memberNames = $memberNames !== ''
            ? $memberNames
            : ($project->user->name ?? 'Mahasiswa');
        $this->reviewerLabel = $reviewerLabel;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[EREPO] Dokumen ' . $this->projectType . ' Telah Diverifikasi & Disetujui: ' . $this->project->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.project_approved',
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
