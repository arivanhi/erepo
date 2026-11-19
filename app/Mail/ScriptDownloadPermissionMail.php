<?php

namespace App\Mail;

use App\Models\PublicDownloadRequest;
use App\Models\ScriptProject;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScriptDownloadPermissionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ScriptProject $scriptProject,
        public PublicDownloadRequest $downloadRequest,
        public string $memberNames,
        public string $approvalPageUrl
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[EREPO] Permohonan Izin Download Source Code: ' . $this->scriptProject->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.script_download_permission',
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
