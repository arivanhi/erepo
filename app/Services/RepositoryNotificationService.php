<?php

namespace App\Services;

use App\Mail\ApprovalRequestMail;
use App\Mail\ProjectApprovedMail;
use App\Mail\StudentSubmissionStatusMail;
use App\Models\CapstoneProject;
use App\Models\ScriptProject;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class RepositoryNotificationService
{
    public function __construct(
        private readonly SmtpConfigurationService $smtpConfig
    ) {}

    public static function formatRoleName(?string $role): string
    {
        return match ($role) {
            'koordinator_ta' => 'Koordinator TA',
            'dosen' => 'Dosen Pembimbing',
            'kaprodi' => 'Kaprodi',
            'admin' => 'Admin',
            'ai' => 'Sistem Pemeriksa AI',
            default => 'Pemeriksa',
        };
    }

    public function formatReviewerLabel(?User $reviewer, ?string $fallbackRole = null): string
    {
        $roleLabel = self::formatRoleName($reviewer?->role ?? $fallbackRole);
        if ($reviewer && $reviewer->name) {
            return "{$roleLabel} ({$reviewer->name})";
        }

        return $roleLabel;
    }

    public function resolveMemberNames(CapstoneProject|ScriptProject $project): string
    {
        if ($project instanceof CapstoneProject) {
            $project->loadMissing(['members', 'user']);
            if ($project->members->isNotEmpty()) {
                return $project->members->pluck('name')->filter()->join(', ');
            }
            return $project->user?->name ?? 'Mahasiswa';
        }

        $project->loadMissing(['capstoneProject.members', 'user']);
        if ($project->capstoneProject && $project->capstoneProject->members->isNotEmpty()) {
            return $project->capstoneProject->members->pluck('name')->filter()->join(', ');
        }

        return $project->user?->name ?? 'Mahasiswa';
    }

    public function resolveDosenName(CapstoneProject|ScriptProject $project): string
    {
        if ($project instanceof CapstoneProject) {
            $project->loadMissing('dosen');
            return $project->dosen?->name ?? '-';
        }

        $project->loadMissing('capstoneProject.dosen');
        return $project->capstoneProject?->dosen?->name ?? '-';
    }

    /**
     * Resolve all group student emails for a Capstone or Script project.
     *
     * @return array<int, string>
     */
    public function resolveStudentEmails(CapstoneProject|ScriptProject $project): array
    {
        $emails = collect();

        if ($project instanceof CapstoneProject) {
            $project->loadMissing(['members', 'user']);
            $emails = $emails->concat($project->members->pluck('email'));
            if ($project->user?->email) {
                $emails->push($project->user->email);
            }
        } else {
            $project->loadMissing(['capstoneProject.members', 'user']);
            if ($project->capstoneProject) {
                $emails = $emails->concat($project->capstoneProject->members->pluck('email'));
            }
            if ($project->user?->email) {
                $emails->push($project->user->email);
            }
        }

        return $this->sanitizeRecipients($emails);
    }

    /**
     * Resolve verifier emails: Dosen Pembimbing + all Koordinator TA + all Admin.
     *
     * @return array<int, string>
     */
    public function resolveVerifierEmails(CapstoneProject|ScriptProject $project): array
    {
        $emails = collect();

        if ($project instanceof CapstoneProject) {
            $project->loadMissing('dosen');
            if ($project->dosen?->email) {
                $emails->push($project->dosen->email);
            }
        } else {
            $project->loadMissing('capstoneProject.dosen');
            if ($project->capstoneProject?->dosen?->email) {
                $emails->push($project->capstoneProject->dosen->email);
            }
        }

        $staffEmails = User::query()
            ->whereIn('role', ['koordinator_ta', 'admin'])
            ->pluck('email');

        $emails = $emails->concat($staffEmails);

        return $this->sanitizeRecipients($emails);
    }

    /**
     * 1. Notify group students that their Capstone PDF was uploaded and is being checked by AI.
     */
    public function notifyStudentsUploadedChecking(CapstoneProject $project): void
    {
        $memberNames = $this->resolveMemberNames($project);
        $recipients = $this->resolveStudentEmails($project);

        $this->queueToMany($recipients, fn () => new StudentSubmissionStatusMail(
            $project,
            'Capstone Design',
            'uploaded_checking',
            $memberNames
        ));
    }

    /**
     * 2. Notify group students that their submission is waiting for verification (Capstone passed AI or Script uploaded).
     */
    public function notifyStudentsPendingVerification(CapstoneProject|ScriptProject $project, string $projectType): void
    {
        $memberNames = $this->resolveMemberNames($project);
        $recipients = $this->resolveStudentEmails($project);

        $this->queueToMany($recipients, fn () => new StudentSubmissionStatusMail(
            $project,
            $projectType,
            'pending_verification',
            $memberNames
        ));
    }

    /**
     * 3. Notify Dosen Pembimbing, Koordinator TA, and Admin that a submission is ready for verification.
     */
    public function notifyVerifiersNewSubmission(CapstoneProject|ScriptProject $project, string $projectType): void
    {
        $memberNames = $this->resolveMemberNames($project);
        $dosenName = $this->resolveDosenName($project);
        $recipients = $this->resolveVerifierEmails($project);

        $this->queueToMany($recipients, fn () => new ApprovalRequestMail(
            $project,
            $projectType,
            $memberNames,
            $dosenName
        ));
    }

    /**
     * 4a. Notify group students that AI held the Capstone PDF and re-upload is required.
     *
     * @param  array<int, string>  $missingItems
     */
    public function notifyStudentsAiReuploadRequired(CapstoneProject $project, array $missingItems): void
    {
        $memberNames = $this->resolveMemberNames($project);
        $recipients = $this->resolveStudentEmails($project);

        $this->queueToMany($recipients, fn () => new StudentSubmissionStatusMail(
            $project,
            'Capstone Design',
            'reupload_ai',
            $memberNames,
            $missingItems
        ));
    }

    /**
     * 4b. Notify group students that Dosen Pembimbing, Koordinator TA, or Admin requested a re-upload.
     */
    public function notifyStudentsReviewerReuploadRequired(
        CapstoneProject|ScriptProject $project,
        string $projectType,
        User $reviewer,
        string $revisionNote
    ): void {
        $memberNames = $this->resolveMemberNames($project);
        $reviewerLabel = $this->formatReviewerLabel($reviewer);
        $recipients = $this->resolveStudentEmails($project);

        $this->queueToMany($recipients, fn () => new StudentSubmissionStatusMail(
            $project,
            $projectType,
            'reupload_reviewer',
            $memberNames,
            [],
            $revisionNote,
            $reviewerLabel
        ));
    }

    /**
     * 5. Notify group students that their submission has been verified & approved.
     */
    public function notifyStudentsApproved(
        CapstoneProject|ScriptProject $project,
        string $projectType,
        ?User $reviewer = null
    ): void {
        $memberNames = $this->resolveMemberNames($project);
        $reviewerLabel = $this->formatReviewerLabel($reviewer);
        $recipients = $this->resolveStudentEmails($project);

        $this->queueToMany($recipients, fn () => new ProjectApprovedMail(
            $project,
            $projectType,
            $memberNames,
            $reviewerLabel
        ));
    }

    /**
     * @param  Collection<int, mixed>  $rawEmails
     * @return array<int, string>
     */
    private function sanitizeRecipients(Collection $rawEmails): array
    {
        $isTesting = app()->environment('testing');
        $reservedDomains = ['example.com', 'example.org', 'example.net', 'example.test'];

        return $rawEmails
            ->filter(fn ($e) => is_string($e) && trim($e) !== '')
            ->map(fn (string $e) => strtolower(trim($e)))
            ->unique()
            ->filter(function (string $email) use ($isTesting, $reservedDomains) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    return false;
                }

                if (! $isTesting) {
                    $domain = substr(strrchr($email, '@') ?: '', 1);
                    if (in_array($domain, $reservedDomains, true)) {
                        return false;
                    }
                }

                return true;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $recipients
     * @param  callable(): Mailable  $mailableFactory
     */
    private function queueToMany(array $recipients, callable $mailableFactory): void
    {
        if (empty($recipients)) {
            return;
        }

        if (! app()->environment('testing') && ! $this->smtpConfig->isConfigured()) {
            Log::info('Pengiriman email dilewati karena kredensial Google SMTP belum dikonfigurasi di Settings.', [
                'recipients_count' => count($recipients),
            ]);
            return;
        }

        $this->smtpConfig->applyRuntimeConfig();

        foreach ($recipients as $email) {
            try {
                Mail::to($email)->queue($mailableFactory());
            } catch (Throwable $e) {
                Log::warning('Gagal mengantrekan pengiriman email EREPO', [
                    'recipient' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
