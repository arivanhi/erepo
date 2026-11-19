<?php

namespace Tests\Feature;

use App\Mail\CapstoneAcceptedWithNoteMail;
use App\Mail\CapstoneTeamLoginTokenMail;
use App\Mail\PublicDownloadTokenMail;
use App\Mail\ScriptDownloadPermissionMail;
use App\Models\CapstoneProject;
use App\Models\CapstoneTeam;
use App\Models\PublicDownloadRequest;
use App\Models\ScriptProject;
use App\Models\User;
use App\Services\CapstonePdfCheckerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

class ErepoV12PortalTeamAndTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_public_portal_loads_without_login_with_anti_turnitin_headers_and_udinus_pdf_token(): void
    {
        Storage::fake('public');
        Mail::fake();

        $dosen = User::factory()->create(['role' => 'dosen', 'name' => 'Dr. Pembimbing']);
        $mhs = User::factory()->create(['role' => 'mahasiswa', 'name' => 'Budi Elektro']);

        Storage::disk('public')->put('upload/capston/E11-VI-01/laporan.pdf', '%PDF-1.4 dummy content');

        $approvedCapstone = CapstoneProject::create([
            'title' => 'Sistem Kendali Motor Induksi Tiga Fasa',
            'academic_year' => '2025-2026',
            'group_id' => 'E11-VI-01',
            'status' => 'approved',
            'document_path' => 'upload/capston/E11-VI-01/laporan.pdf',
            'user_id' => $mhs->id,
            'dosen_id' => $dosen->id,
        ]);
        $approvedCapstone->members()->sync([$mhs->id]);

        $pendingCapstone = CapstoneProject::create([
            'title' => 'Draft Belum Disetujui',
            'academic_year' => '2025-2026',
            'group_id' => 'E11-VI-02',
            'status' => 'pending',
            'user_id' => $mhs->id,
            'dosen_id' => $dosen->id,
        ]);

        // 1. Public Dashboard at '/' loads without redirecting to /login and sets X-Robots-Tag
        $res = $this->get('/');
        $res->assertOk();
        $this->assertStringContainsString('noindex', (string) $res->headers->get('X-Robots-Tag'));
        $res->assertInertia(fn (Assert $page) => $page
            ->component('Public/Dashboard')
            ->where('stats.totalCapstone', 1)
        );

        // 2. Public Repository PDF shows only approved items and supports custom per_page (25)
        $this->get('/explore/capstone?per_page=25')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Capstone')
                ->where('filters.per_page', 25)
                ->has('projects.data', 1)
                ->where('projects.data.0.id', $approvedCapstone->id)
            );

        // 3. Non-UDINUS email is rejected when requesting 1-day PDF download token
        $this->from('/explore/capstone')
            ->post('/explore/capstone/' . $approvedCapstone->id . '/request-token', [
                'email' => 'pengunjung@gmail.com',
            ])
            ->assertRedirect('/explore/capstone')
            ->assertSessionHasErrors('email');

        Mail::assertNotQueued(PublicDownloadTokenMail::class);

        // 4. Valid @mhs.dinus.ac.id email receives 1-day download token and can download
        $this->post('/explore/capstone/' . $approvedCapstone->id . '/request-token', [
            'email' => '1112025001@mhs.dinus.ac.id',
        ])->assertRedirect();

        Mail::assertQueued(PublicDownloadTokenMail::class, fn (PublicDownloadTokenMail $m) => $m->hasTo('1112025001@mhs.dinus.ac.id'));

        $dlReq = PublicDownloadRequest::where('type', 'capstone_pdf')->firstOrFail();
        $this->assertTrue($dlReq->isTokenValid());

        $this->post('/explore/download/verify', [
            'type' => 'capstone_pdf',
            'project_id' => $approvedCapstone->id,
            'email' => '1112025001@mhs.dinus.ac.id',
            'token' => $dlReq->download_code,
        ])->assertRedirect(route('public.download.direct', $dlReq->download_url_token));

        $downloadRes = $this->get(route('public.download.direct', $dlReq->download_url_token));
        $downloadRes->assertOk();
        $this->assertStringContainsString('noindex', (string) $downloadRes->headers->get('X-Robots-Tag'));
    }

    public function test_public_script_download_requires_authenticated_verifier_account_via_download_requests_menu(): void
    {
        Storage::fake('public');
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@dsn.dinus.ac.id']);
        $koordinator = User::factory()->create(['role' => 'koordinator_ta', 'email' => 'koord@dsn.dinus.ac.id']);
        $dosen = User::factory()->create(['role' => 'dosen', 'email' => 'pembimbing@dsn.dinus.ac.id']);
        $otherDosen = User::factory()->create(['role' => 'dosen', 'email' => 'dosen.lain@dsn.dinus.ac.id']);
        $mhsA = User::factory()->create(['role' => 'mahasiswa', 'email' => 'mhsa@mhs.dinus.ac.id']);
        $mhsB = User::factory()->create(['role' => 'mahasiswa', 'email' => 'mhsb@mhs.dinus.ac.id']);

        $capstone = CapstoneProject::create([
            'title' => 'Alat Ukur Kualitas Air Tambak',
            'academic_year' => '2025-2026',
            'group_id' => 'E11-VI-30',
            'status' => 'approved',
            'user_id' => $mhsA->id,
            'dosen_id' => $dosen->id,
        ]);
        $capstone->members()->sync([$mhsA->id, $mhsB->id]);

        Storage::disk('public')->put('upload/script/E11-VI-30/program.zip', 'PK-dummy-zip');
        $script = ScriptProject::create([
            'title' => 'Source Code ESP32 & Laravel',
            'academic_year' => '2025-2026',
            'group_id' => 'E11-VI-30',
            'status' => 'approved',
            'archive_path' => 'upload/script/E11-VI-30/program.zip',
            'user_id' => $mhsA->id,
            'capstone_project_id' => $capstone->id,
        ]);

        // Visitor submits Script access request with UDINUS email
        $this->post('/explore/script/' . $script->id . '/request-access', [
            'requester_name' => 'Adik Tingkat',
            'requester_email' => 'adik@mhs.dinus.ac.id',
            'purpose' => 'Referensi komunikasi MQTT ESP32 untuk penelitian lanjutan.',
        ])->assertRedirect();

        // Notification email sent to Dosen Pembimbing, Koordinator TA, Admin (+ Mahasiswa owners)
        foreach ([$admin->email, $koordinator->email, $dosen->email, $mhsA->email, $mhsB->email] as $notifiedEmail) {
            Mail::assertQueued(
                ScriptDownloadPermissionMail::class,
                fn (ScriptDownloadPermissionMail $m) => $m->hasTo($notifiedEmail)
            );
        }
        Mail::assertNotQueued(PublicDownloadTokenMail::class);

        $permReq = PublicDownloadRequest::where('type', 'script_archive')->firstOrFail();
        $this->assertSame('pending', $permReq->status);

        // Unauthenticated visitor is redirected to /login; mahasiswa & unrelated dosen get 403
        $this->get('/download-requests')->assertRedirect('/login');
        $this->actingAs($mhsA)->get('/download-requests')->assertForbidden();
        $this->actingAs($otherDosen)
            ->get('/download-requests')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/DownloadRequests')
                ->has('requests.data', 0)
            );
        $this->actingAs($otherDosen)
            ->post('/download-requests/' . $permReq->id . '/decide', ['decision' => 'approve'])
            ->assertForbidden();

        // Assigned Dosen Pembimbing & Koordinator TA see the request in Verifikasi Permintaan Download table
        $this->actingAs($dosen)
            ->get('/download-requests')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/DownloadRequests')
                ->has('requests.data', 1)
                ->where('requests.data.0.script_title', 'Source Code ESP32 & Laravel')
            );

        // Dosen Pembimbing approves via authenticated /download-requests/{id}/decide -> sends 1-day token to requester
        Mail::fake();
        $this->actingAs($dosen)
            ->post('/download-requests/' . $permReq->id . '/decide', ['decision' => 'approve'])
            ->assertRedirect();

        $permReq->refresh();
        $this->assertSame('approved', $permReq->status);
        $this->assertTrue($permReq->isTokenValid());

        Mail::assertQueued(PublicDownloadTokenMail::class, fn (PublicDownloadTokenMail $m) => $m->hasTo('adik@mhs.dinus.ac.id'));

        $this->get(route('public.download.direct', $permReq->download_url_token))->assertOk();
    }

    public function test_koordinator_and_admin_can_accept_ai_incomplete_pdf_with_note_and_capstone_team_token_login(): void
    {
        Storage::fake('public');
        Mail::fake();

        $koordinator = User::factory()->create(['name' => 'Koordinator TA', 'role' => 'koordinator_ta', 'email' => 'koordinator@dsn.dinus.ac.id']);
        $dosen = User::factory()->create(['name' => 'Dr. Pembimbing', 'role' => 'dosen', 'email' => 'dosen.wali@dsn.dinus.ac.id']);

        // 1. Koordinator creates a Capstone Team via Add Capstone Team (with inline new students!)
        $this->actingAs($koordinator)->post('/capstone-teams', [
            'group_code' => 'e11-vi-105',
            'team_name' => 'Smart Grid Inverter',
            'academic_year' => '2026-2027',
            'dosen_id' => $dosen->id,
            'new_members' => [
                ['name' => 'Raka Pratama', 'identity_number' => 'E11.2023.02001', 'email' => 'raka@mhs.dinus.ac.id'],
                ['name' => 'Sinta Dewi', 'identity_number' => 'E11.2023.02002', 'email' => 'sinta@mhs.dinus.ac.id'],
            ],
            'send_token_now' => true,
        ])->assertRedirect();

        $team = CapstoneTeam::with('members')->where('group_code', 'E11-VI-105')->firstOrFail();
        $this->assertCount(2, $team->members);
        $this->assertTrue($team->hasActiveToken());

        // Both students received 1-month CapstoneTeamLoginTokenMail
        Mail::assertQueued(CapstoneTeamLoginTokenMail::class, fn (CapstoneTeamLoginTokenMail $m) => $m->hasTo('raka@mhs.dinus.ac.id'));
        Mail::assertQueued(CapstoneTeamLoginTokenMail::class, fn (CapstoneTeamLoginTokenMail $m) => $m->hasTo('sinta@mhs.dinus.ac.id'));

        $plainToken = $team->getPlainToken();
        $this->assertNotEmpty($plainToken);

        // 2. Logout Koordinator, then Student logs in using ONLY Kode Capstone + 1-Month Token!
        $this->post('/logout');
        $this->post('/login/capstone', [
            'group_code' => 'E11-VI-105',
            'token' => $plainToken,
        ])->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        // 3. Student uploads an incomplete PDF (auto-populating team metadata!)
        $this->mock(CapstonePdfCheckerService::class, function (MockInterface $mock) {
            $mock->shouldReceive('check')->once()->andReturn([
                'passed' => false,
                'summary' => 'TTD Kaprodi belum ada.',
                'missing_items' => ['Halaman Pengesahan (Hal. 6): TTD Kaprodi kosong.'],
                'toc_check' => ['passed' => true, 'pages' => [10], 'issues' => []],
                'signature_check' => ['passed' => false, 'pages' => [], 'issues' => ['Halaman Pengesahan (Hal. 6): TTD Kaprodi kosong.']],
                'page_count' => 70,
                'model' => 'qwen3.8:27b',
            ]);
        });

        $this->post('/repository/capstone', [
            'title' => 'Rancang Bangun Smart Grid Inverter Satu Fasa',
            'document' => UploadedFile::fake()->create('laporan_tim105.pdf', 400, 'application/pdf'),
        ])->assertRedirect();

        $project = CapstoneProject::with('members')->firstOrFail();
        $this->assertSame('E11-VI-105', $project->group_id);
        $this->assertSame('2026-2027', $project->academic_year);
        $this->assertSame($dosen->id, (int) $project->dosen_id);
        $this->assertCount(2, $project->members);
        $this->assertSame('rejected', $project->status);

        // Regular Dosen cannot override AI hold with accept-with-note
        $this->actingAs($dosen)->post('/repository/capstone/' . $project->id . '/accept-with-note', [
            'acceptance_note' => 'Coba override oleh dosen biasa',
        ])->assertForbidden();

        // 4. Koordinator TA overrides AI hold using "Terima dengan Catatan" -> saved & emailed to Mahasiswa + Pembimbing!
        Mail::fake();
        $this->actingAs($koordinator)->post('/repository/capstone/' . $project->id . '/accept-with-note', [
            'acceptance_note' => 'Lembar pengesahan basah asli sudah diserahkan langsung ke ruang Koordinator TA.',
        ])->assertRedirect();

        $project->refresh();
        $this->assertSame('approved', $project->status);
        $this->assertStringContainsString('diserahkan langsung', (string) $project->acceptance_note);

        // Both group students AND Dosen Pembimbing receive CapstoneAcceptedWithNoteMail!
        foreach (['raka@mhs.dinus.ac.id', 'sinta@mhs.dinus.ac.id', $dosen->email] as $targetEmail) {
            Mail::assertQueued(
                CapstoneAcceptedWithNoteMail::class,
                fn (CapstoneAcceptedWithNoteMail $m) => $m->hasTo($targetEmail)
                    && str_contains($m->acceptanceNote, 'diserahkan langsung')
            );
        }
    }
}
