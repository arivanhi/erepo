<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StudentSubmissionStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public $project;

    public string $projectType;

    /**
     * One of: 'uploaded_checking' | 'pending_verification' | 'reupload_ai' | 'reupload_reviewer'
     */
    public string $statusType;

    public string $memberNames;

    /** @var array<int, string> */
    public array $missingItems;

    public ?string $revisionNote;

    public ?string $reviewerLabel;

    /**
     * @param  array<int, string>  $missingItems
     */
    public function __construct(
        $project,
        string $projectType,
        string $statusType,
        string $memberNames = '',
        array $missingItems = [],
        ?string $revisionNote = null,
        ?string $reviewerLabel = null
    ) {
        $this->project = $project;
        $this->projectType = $projectType;
        $this->statusType = $statusType;
        $this->memberNames = $memberNames !== ''
            ? $memberNames
            : ($project->user->name ?? 'Mahasiswa');
        $this->missingItems = $missingItems;
        $this->revisionNote = $revisionNote;
        $this->reviewerLabel = $reviewerLabel;
    }

    public function envelope(): Envelope
    {
        $subject = match ($this->statusType) {
            'uploaded_checking' => '[EREPO] Dokumen Berhasil Dikirim (Sedang Diperiksa AI): ' . $this->project->title,
            'pending_verification' => '[EREPO] Menunggu Hasil Verifikasi (' . $this->projectType . '): ' . $this->project->title,
            'reupload_ai' => '[EREPO] Wajib Upload Ulang (Pemeriksaan AI): ' . $this->project->title,
            'reupload_reviewer' => '[EREPO] Diminta Upload Ulang oleh ' . ($this->reviewerLabel ?: 'Pemeriksa') . ': ' . $this->project->title,
            default => '[EREPO] Status Dokumen ' . $this->projectType . ': ' . $this->project->title,
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.student_submission_status',
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
