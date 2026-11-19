<?php

namespace App\Jobs;

use App\Models\CapstoneProject;
use App\Services\CapstonePdfCheckerService;
use App\Services\RepositoryNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CheckCapstonePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 280;

    public function __construct(
        public int $capstoneProjectId
    ) {}

    public function handle(CapstonePdfCheckerService $checker, RepositoryNotificationService $notifier): void
    {
        $project = CapstoneProject::with(['user', 'dosen', 'members'])->find($this->capstoneProjectId);
        if (! $project) {
            return;
        }

        if (! $project->document_path || ! Storage::disk('public')->exists($project->document_path)) {
            $missing = ['File PDF laporan belum tersedia di penyimpanan. Silakan unggah ulang file PDF lengkap.'];
            $project->update([
                'status' => 'rejected',
                'ai_check_status' => 'failed',
                'ai_check_result' => [
                    'passed' => false,
                    'summary' => 'Dokumen ditahan karena file PDF belum diunggah atau tidak ditemukan.',
                    'missing_items' => $missing,
                ],
                'ai_checked_at' => now(),
                'reviewer_role' => 'ai',
                'reviewed_by' => null,
                'reviewed_at' => now(),
            ]);
            $notifier->notifyStudentsAiReuploadRequired($project, $missing);
            return;
        }

        $absolutePath = Storage::disk('public')->path($project->document_path);
        $memberNames = $project->members->pluck('name')->filter()->values()->all();

        try {
            $result = $checker->check($absolutePath, [
                'title' => $project->title,
                'group_id' => $project->group_id,
                'member_names' => $memberNames,
                'dosen_name' => $project->dosen?->name,
            ]);

            if ($result['passed']) {
                $project->update([
                    'status' => 'pending',
                    'ai_check_status' => 'passed',
                    'ai_check_result' => $result,
                    'ai_checked_at' => now(),
                    'revision_note' => null,
                    'reviewed_by' => null,
                    'reviewer_role' => null,
                    'reviewed_at' => null,
                ]);

                // 1. Inform group students that AI check passed & submission is waiting verification
                $notifier->notifyStudentsPendingVerification($project, 'Capstone Design');

                // 2. Inform Dosen Pembimbing, Koordinator TA, and Admin that document is ready to verify
                $notifier->notifyVerifiersNewSubmission($project, 'Capstone Design');
            } else {
                // Hold ("ditahan") submission so students must re-upload a complete PDF
                $project->update([
                    'status' => 'rejected',
                    'ai_check_status' => 'failed',
                    'ai_check_result' => $result,
                    'ai_checked_at' => now(),
                    'reviewer_role' => 'ai',
                    'reviewed_by' => null,
                    'reviewed_at' => now(),
                ]);

                // Inform group students of AI-detected missing items requiring re-upload
                $notifier->notifyStudentsAiReuploadRequired($project, $result['missing_items'] ?? []);
            }
        } catch (Throwable $e) {
            Log::error('CheckCapstonePdfJob gagal memproses PDF Capstone', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            $isSyncOrFinalAttempt = ($this->job === null)
                || ($this->job->getConnectionName() === 'sync')
                || ($this->attempts() >= $this->tries);

            if ($isSyncOrFinalAttempt) {
                $errItems = [
                    'Pemeriksaan otomatis ke layanan AI Qwen gagal diselesaikan (' . $e->getMessage() . '). Silakan klik "Cek Ulang AI" atau unggah ulang file PDF.',
                ];
                $project->update([
                    'status' => 'rejected',
                    'ai_check_status' => 'error',
                    'ai_check_result' => [
                        'passed' => false,
                        'summary' => 'Pemeriksaan otomatis AI mengalami kendala teknis: ' . $e->getMessage(),
                        'missing_items' => $errItems,
                    ],
                    'ai_checked_at' => now(),
                    'reviewer_role' => 'ai',
                    'reviewed_at' => now(),
                ]);
            } else {
                throw $e;
            }
        }
    }
}
