<?php

namespace App\Services;

use App\Mail\ApprovalRequestMail;
use App\Mail\CapstoneAcceptedWithNoteMail;
use App\Mail\CapstoneTeamLoginTokenMail;
use App\Mail\ProjectApprovedMail;
use App\Mail\PublicDownloadTokenMail;
use App\Mail\ScriptDownloadPermissionMail;
use App\Mail\StudentSubmissionStatusMail;
use App\Models\CapstoneProject;
use App\Models\CapstoneTeam;
use App\Models\PublicDownloadRequest;
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
     * v1.2: Notify group students AND Dosen Pembimbing when Koordinator TA / Admin accepts incomplete PDF with note.
     */
    public function notifyAcceptedWithNote(
        CapstoneProject $project,
        User $reviewer,
        string $acceptanceNote
    ): void {
        $project->loadMissing(['members', 'user', 'dosen']);
        $memberNames = $this->resolveMemberNames($project);
        $dosenName = $this->resolveDosenName($project);
        $reviewerLabel = $this->formatReviewerLabel($reviewer);
        $aiMissing = $project->ai_check_result['missing_items'] ?? [];

        $rawRecipients = collect($this->resolveStudentEmails($project));
        if ($project->dosen?->email) {
            $rawRecipients->push($project->dosen->email);
        }
        $recipients = $this->sanitizeRecipients($rawRecipients);

        $this->queueToMany($recipients, fn () => new CapstoneAcceptedWithNoteMail(
            $project,
            $memberNames,
            $dosenName,
            $reviewerLabel,
            $acceptanceNote,
            is_array($aiMissing) ? $aiMissing : []
        ));
    }

    /**
     * v1.2: Send 1-month Capstone Team login token to all team members.
     */
    public function sendCapstoneTeamLoginToken(CapstoneTeam $team, string $plainToken): int
    {
        $team->loadMissing('members');
        $memberNames = $team->members->pluck('name')->filter()->join(', ') ?: $team->group_code;
        $expiresFormatted = ($team->login_token_expires_at ?? now()->addDays(30))
            ->timezone('Asia/Jakarta')
            ->format('d M Y H:i');

        $recipients = $this->sanitizeRecipients($team->members->pluck('email'));

        $this->queueToMany($recipients, fn () => new CapstoneTeamLoginTokenMail(
            $team,
            $plainToken,
            $expiresFormatted,
            $memberNames
        ));

        return count($recipients);
    }

    /**
     * v1.2: Send 1-day download token to UDINUS requester (for PDF or approved Script).
     */
    public function sendPublicDownloadToken(
        PublicDownloadRequest $downloadReq,
        string $documentTypeLabel,
        string $projectTitle,
        string $groupCode,
        string $directDownloadUrl
    ): void {
        $recipients = $this->sanitizeRecipients(collect([$downloadReq->requester_email]));
        $expiresFormatted = ($downloadReq->expires_at ?? now()->addDay())
            ->timezone('Asia/Jakarta')
            ->format('d M Y H:i');

        $this->queueToMany($recipients, fn () => new PublicDownloadTokenMail(
            $documentTypeLabel,
            $projectTitle,
            $groupCode,
            (string) $downloadReq->download_code,
            $directDownloadUrl,
            $expiresFormatted
        ));
    }

    /**
     * v1.2: Send Script download permission request to Koordinator TA, Dosen Pembimbing, and Mahasiswa owners.
     */
    public function sendScriptDownloadPermissionRequest(
        ScriptProject $script,
        PublicDownloadRequest $downloadReq,
        string $approvalPageUrl
    ): int {
        $script->loadMissing(['user', 'capstoneProject.members', 'capstoneProject.dosen']);
        $memberNames = $this->resolveMemberNames($script);

        $rawEmails = collect($this->resolveStudentEmails($script));

        if ($script->capstoneProject?->dosen?->email) {
            $rawEmails->push($script->capstoneProject->dosen->email);
        }

        $staffEmails = User::query()
            ->whereIn('role', ['koordinator_ta', 'admin'])
            ->pluck('email');

        $rawEmails = $rawEmails->concat($staffEmails);
        $recipients = $this->sanitizeRecipients($rawEmails);

        $this->queueToMany($recipients, fn () => new ScriptDownloadPermissionMail(
            $script,
            $downloadReq,
            $memberNames,
            $approvalPageUrl
        ));

        return count($recipients);
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
