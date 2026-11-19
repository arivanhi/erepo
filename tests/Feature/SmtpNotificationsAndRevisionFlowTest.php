<?php

namespace Tests\Feature;

use App\Mail\ApprovalRequestMail;
use App\Mail\ProjectApprovedMail;
use App\Mail\SmtpTestMail;
use App\Mail\StudentSubmissionStatusMail;
use App\Models\CapstoneProject;
use App\Models\ScriptProject;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\CapstonePdfCheckerService;
use App\Services\SmtpConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

class SmtpNotificationsAndRevisionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Safety guard: never run RefreshDatabase against live MySQL
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_only_admin_can_configure_and_test_google_smtp_settings(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin.elektro@dinus.ac.id']);
        $koordinator = User::factory()->create(['role' => 'koordinator_ta']);
        $student = User::factory()->create(['role' => 'mahasiswa']);

        // Non-admin does not receive smtpSettings prop and is forbidden from SMTP endpoints
        $this->actingAs($koordinator)
            ->get('/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings')
                ->where('smtpSettings', null)
            );

        $this->actingAs($koordinator)->post('/settings/smtp', [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'erepo.elektro@gmail.com',
            'password' => 'abcd efgh ijkl mnop',
            'from_name' => 'EREPO Elektro',
        ])->assertForbidden();

        $this->actingAs($student)->post('/settings/smtp/test', [
            'test_email' => 'target@dinus.ac.id',
        ])->assertForbidden();

        // Admin saves Google SMTP with spaced 16-char App Password
        $this->actingAs($admin)->post('/settings/smtp', [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'erepo.elektro@gmail.com',
            'password' => 'abcd efgh ijkl mnop',
            'from_address' => 'erepo.elektro@gmail.com',
            'from_name' => 'EREPO Teknik Elektro',
        ])->assertRedirect();

        // Password stored encrypted in DB and spaces stripped for Gmail
        $rawEncrypted = SystemSetting::find('smtp_password_encrypted')?->value;
        $this->assertNotEmpty($rawEncrypted);
        $this->assertNotSame('abcdefghijklmnop', $rawEncrypted);

        $smtpService = app(SmtpConfigurationService::class);
        $this->assertTrue($smtpService->isConfigured());
        $resolved = $smtpService->getResolvedSettings();
        $this->assertSame('abcdefghijklmnop', $resolved['password']);

        // Admin triggers test email
        $this->actingAs($admin)->post('/settings/smtp/test', [
            'test_email' => 'cek.inbox@dinus.ac.id',
        ])->assertRedirect();

        Mail::assertSent(SmtpTestMail::class, fn (SmtpTestMail $m) => $m->hasTo('cek.inbox@dinus.ac.id'));
    }

    public function test_full_capstone_and_script_email_lifecycle_for_verifiers_and_group_students(): void
    {
        Storage::fake('public');
        Mail::fake();

        $admin = User::factory()->create(['name' => 'Admin EREPO', 'role' => 'admin', 'email' => 'admin.erepo@dinus.ac.id']);
        $koordinator = User::factory()->create(['name' => 'Dewi Koordinator', 'role' => 'koordinator_ta', 'email' => 'koordinator@dsn.dinus.ac.id']);
        $dosen = User::factory()->create(['name' => 'Dr. Pembimbing', 'role' => 'dosen', 'email' => 'pembimbing@dsn.dinus.ac.id']);
        $studentA = User::factory()->create(['name' => 'Mahasiswa A', 'role' => 'mahasiswa', 'email' => 'mhsa@mhs.dinus.ac.id']);
        $studentB = User::factory()->create(['name' => 'Mahasiswa B', 'role' => 'mahasiswa', 'email' => 'mhsb@mhs.dinus.ac.id']);

        $aiMissing = ['Halaman Pengesahan (Hal. 6): TTD Kaprodi belum terisi.'];

        $this->mock(CapstonePdfCheckerService::class, function (MockInterface $mock) use ($aiMissing) {
            // 1st upload: held by AI
            $mock->shouldReceive('check')->once()->andReturn([
                'passed' => false,
                'summary' => 'Dokumen ditahan.',
                'missing_items' => $aiMissing,
                'toc_check' => ['passed' => true, 'pages' => [10], 'issues' => []],
                'signature_check' => ['passed' => false, 'pages' => [], 'issues' => $aiMissing],
                'page_count' => 60,
                'model' => 'qwen3.8:27b',
            ]);

            // 2nd upload: passes AI
            $mock->shouldReceive('check')->once()->andReturn([
                'passed' => true,
                'summary' => 'Dokumen lengkap.',
                'missing_items' => [],
                'toc_check' => ['passed' => true, 'pages' => [10], 'issues' => []],
                'signature_check' => ['passed' => true, 'pages' => [], 'issues' => []],
                'page_count' => 60,
                'model' => 'qwen3.8:27b',
            ]);
        });

        // --- STEP 1: Student A uploads incomplete Capstone PDF ---
        $this->actingAs($studentA)->post('/repository/capstone', [
            'title' => 'Sistem Monitoring Gardu Distribusi',
            'academic_year' => '2025-2026',
            'group_id' => 'E11-VI-77',
            'dosen_id' => $dosen->id,
            'member_ids' => [$studentA->id, $studentB->id],
            'document' => UploadedFile::fake()->create('draft1.pdf', 300, 'application/pdf'),
        ])->assertRedirect();

        $capstone = CapstoneProject::firstOrFail();
        $this->assertSame('rejected', $capstone->status);

        // Both students got 'uploaded_checking' AND 'reupload_ai' emails; verifiers not spammed yet
        foreach ([$studentA->email, $studentB->email] as $sEmail) {
            Mail::assertQueued(
                StudentSubmissionStatusMail::class,
                fn (StudentSubmissionStatusMail $m) => $m->hasTo($sEmail) && $m->statusType === 'uploaded_checking'
            );
            Mail::assertQueued(
                StudentSubmissionStatusMail::class,
                fn (StudentSubmissionStatusMail $m) => $m->hasTo($sEmail) && $m->statusType === 'reupload_ai' && $m->missingItems === $aiMissing
            );
        }
        Mail::assertNotQueued(ApprovalRequestMail::class);

        // --- STEP 2: Student B re-uploads complete PDF -> passes AI ---
        Mail::fake();
        $this->actingAs($studentB)->post('/repository/capstone/' . $capstone->id . '/update', [
            'title' => 'Sistem Monitoring Gardu Distribusi',
            'academic_year' => '2025-2026',
            'group_id' => 'E11-VI-77',
            'dosen_id' => $dosen->id,
            'member_ids' => [$studentA->id, $studentB->id],
            'document' => UploadedFile::fake()->create('lengkap.pdf', 350, 'application/pdf'),
        ])->assertRedirect();

        $this->assertSame('pending', $capstone->fresh()->status);

        // Students notified it is waiting verification
        foreach ([$studentA->email, $studentB->email] as $sEmail) {
            Mail::assertQueued(
                StudentSubmissionStatusMail::class,
                fn (StudentSubmissionStatusMail $m) => $m->hasTo($sEmail) && $m->statusType === 'pending_verification'
            );
        }

        // Dosen Pembimbing, Koordinator TA, AND Admin ALL receive ApprovalRequestMail
        foreach ([$dosen->email, $koordinator->email, $admin->email] as $vEmail) {
            Mail::assertQueued(
                ApprovalRequestMail::class,
                fn (ApprovalRequestMail $m) => $m->hasTo($vEmail) && $m->projectType === 'Capstone Design'
            );
        }

        // --- STEP 3: Koordinator TA requests manual re-upload with revision note ---
        Mail::fake();
        $this->actingAs($koordinator)->post('/repository/capstone/' . $capstone->id . '/request-reupload', [
            'revision_note' => 'Lampiran datasheet komponen pada seri E-300 belum dilampirkan.',
        ])->assertRedirect();

        $capstone->refresh();
        $this->assertSame('rejected', $capstone->status);
        $this->assertSame('koordinator_ta', $capstone->reviewer_role);
        $this->assertStringContainsString('E-300', (string) $capstone->revision_note);

        foreach ([$studentA->email, $studentB->email] as $sEmail) {
            Mail::assertQueued(
                StudentSubmissionStatusMail::class,
                fn (StudentSubmissionStatusMail $m) => $m->hasTo($sEmail)
                    && $m->statusType === 'reupload_reviewer'
                    && str_contains((string) $m->reviewerLabel, 'Koordinator TA')
                    && str_contains((string) $m->revisionNote, 'E-300')
            );
        }

        // --- STEP 4: Script upload, Dosen Pembimbing re-upload request, re-upload, and approval ---
        Mail::fake();
        $this->actingAs($studentA)->post('/repository/script', [
            'title' => 'Firmware ESP32 & Dashboard Web',
            'capstone_project_id' => $capstone->id,
            'archive' => UploadedFile::fake()->create('source_code.zip', 500, 'application/zip'),
        ])->assertRedirect();

        $script = ScriptProject::firstOrFail();
        $this->assertSame('pending', $script->status);

        // Both students notified of waiting verification & all 3 verifiers notified
        foreach ([$studentA->email, $studentB->email] as $sEmail) {
            Mail::assertQueued(
                StudentSubmissionStatusMail::class,
                fn (StudentSubmissionStatusMail $m) => $m->hasTo($sEmail) && $m->statusType === 'pending_verification'
            );
        }
        foreach ([$dosen->email, $koordinator->email, $admin->email] as $vEmail) {
            Mail::assertQueued(
                ApprovalRequestMail::class,
                fn (ApprovalRequestMail $m) => $m->hasTo($vEmail) && $m->projectType === 'Script / Source Code'
            );
        }

        // Dosen Pembimbing requests Script re-upload
        Mail::fake();
        $this->actingAs($dosen)->post('/repository/script/' . $script->id . '/request-reupload', [
            'revision_note' => 'Mohon tambahkan file README.md cara instalasi dan skema database.',
        ])->assertRedirect();

        $this->assertSame('rejected', $script->fresh()->status);
        foreach ([$studentA->email, $studentB->email] as $sEmail) {
            Mail::assertQueued(
                StudentSubmissionStatusMail::class,
                fn (StudentSubmissionStatusMail $m) => $m->hasTo($sEmail)
                    && $m->statusType === 'reupload_reviewer'
                    && str_contains((string) $m->reviewerLabel, 'Dosen Pembimbing')
            );
        }

        // Student B re-uploads Script archive -> back to pending -> Admin approves -> both students receive ProjectApprovedMail
        Mail::fake();
        $this->actingAs($studentB)->post('/repository/script/' . $script->id . '/update', [
            'title' => 'Firmware ESP32 & Dashboard Web',
            'capstone_project_id' => $capstone->id,
            'archive' => UploadedFile::fake()->create('source_code_v2.zip', 550, 'application/zip'),
        ])->assertRedirect();

        $this->assertSame('pending', $script->fresh()->status);

        Mail::fake();
        $this->actingAs($admin)->post('/repository/script/' . $script->id . '/approve')->assertRedirect();
        $this->assertSame('approved', $script->fresh()->status);

        foreach ([$studentA->email, $studentB->email] as $sEmail) {
            Mail::assertQueued(
                ProjectApprovedMail::class,
                fn (ProjectApprovedMail $m) => $m->hasTo($sEmail) && str_contains($m->reviewerLabel, 'Admin')
            );
        }
    }
}
