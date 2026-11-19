<?php

namespace Tests\Feature;

use App\Mail\ApprovalRequestMail;
use App\Models\CapstoneProject;
use App\Models\User;
use App\Services\CapstonePdfCheckerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

class ErepoCoordinatorGroupAndAiGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Safety guard: never run RefreshDatabase against live MySQL
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_koordinator_ta_cannot_manage_accounts_while_admin_can_create_koordinator_ta(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $koordinator = User::factory()->create(['role' => 'koordinator_ta']);
        $student = User::factory()->create(['role' => 'mahasiswa']);

        // Koordinator TA is strictly forbidden from all account management routes
        $this->actingAs($koordinator)->get('/assign-account')->assertForbidden();
        $this->actingAs($koordinator)->post('/assign-account/add', [
            'nama_lengkap' => 'Test User',
            'email' => 'newuser@example.test',
            'nim_npp' => 'NPP-001',
            'role' => 'mahasiswa',
        ])->assertForbidden();
        $this->actingAs($koordinator)->put('/assign-account/' . $student->id, [
            'nama_lengkap' => 'Edited Name',
            'email' => $student->email,
            'nim_npp' => 'NIM-999',
            'role' => 'mahasiswa',
        ])->assertForbidden();
        $this->actingAs($koordinator)->delete('/assign-account/' . $student->id)->assertForbidden();

        // Admin can access /assign-account and create a koordinator_ta user
        $this->actingAs($admin)->get('/assign-account')->assertOk();
        $this->actingAs($admin)->post('/assign-account/add', [
            'nama_lengkap' => 'Dewi Koordinator TA',
            'email' => 'koordinator.ta@dsn.dinus.ac.id',
            'nim_npp' => '0686.11.2015.999',
            'role' => 'koordinator_ta',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'koordinator.ta@dsn.dinus.ac.id',
            'role' => 'koordinator_ta',
        ]);
    }

    public function test_group_members_automatically_receive_shared_capstone_and_script_access(): void
    {
        Storage::fake('public');
        Mail::fake();

        $this->mock(CapstonePdfCheckerService::class, function (MockInterface $mock) {
            $mock->shouldReceive('check')->once()->andReturn([
                'passed' => true,
                'summary' => 'Dokumen PDF lengkap.',
                'missing_items' => [],
                'toc_check' => ['passed' => true, 'pages' => [10], 'issues' => []],
                'signature_check' => ['passed' => true, 'pages' => [['pdf_page' => 6, 'page_label' => 'Halaman Pengesahan', 'all_signatures_present' => true]], 'issues' => []],
                'page_count' => 80,
                'model' => 'qwen3.8:27b',
            ]);
        });

        $dosen = User::factory()->create(['name' => 'Dr. Pembimbing', 'role' => 'dosen']);
        $studentA = User::factory()->create(['name' => 'Andi Pratama', 'role' => 'mahasiswa', 'identity_number' => 'E11.2023.00001']);
        $studentB = User::factory()->create(['name' => 'Budi Santoso', 'role' => 'mahasiswa', 'identity_number' => 'E11.2023.00002']);
        $studentC = User::factory()->create(['name' => 'Citra Lestari', 'role' => 'mahasiswa', 'identity_number' => 'E11.2023.00003']);
        $outsider = User::factory()->create(['name' => 'Mahasiswa Lain', 'role' => 'mahasiswa', 'identity_number' => 'E11.2023.00999']);
        $koordinator = User::factory()->create(['name' => 'Koordinator TA', 'role' => 'koordinator_ta']);

        // Student A submits Capstone and includes Student B & Student C as teammates
        $pdf = UploadedFile::fake()->create('laporan_ta_lengkap.pdf', 512, 'application/pdf');

        $this->actingAs($studentA)->post('/repository/capstone', [
            'title' => 'Sistem Kendali Cerdas Berbasis IoT',
            'academic_year' => '2025-2026',
            'group_id' => 'E11-VI-99',
            'dosen_id' => $dosen->id,
            'member_ids' => [$studentB->id, $studentC->id], // Student A should also be auto-included
            'document' => $pdf,
        ])->assertRedirect();

        $project = CapstoneProject::with('members')->firstOrFail();
        $this->assertSame('pending', $project->status);
        $this->assertEqualsCanonicalizing(
            [$studentA->id, $studentB->id, $studentC->id],
            $project->members->pluck('id')->all()
        );

        // Student B's dashboard automatically reflects the shared group project
        $this->actingAs($studentB)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Dashboard')
                ->where('stats.uploaded', 1)
                ->where('stats.pending', 1)
            );

        // Student B sees the pending Capstone in /repository/capstone with all 3 member names
        $this->actingAs($studentB)
            ->get('/repository/capstone')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Repository/Capstone')
                ->has('projects.data', 1)
                ->has('projects.data.0.members', 3)
            );

        // Student C sees the shared Capstone in /repository/script Connected Capstone dropdown
        $this->actingAs($studentC)
            ->get('/repository/script')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Repository/Script')
                ->has('capstones', 1)
                ->where('capstones.0.id', $project->id)
            );

        // Outsider student cannot see unapproved group project
        $this->actingAs($outsider)
            ->get('/repository/capstone')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Repository/Capstone')
                ->has('projects.data', 0)
            );

        // Koordinator TA has full admin-level visibility and can approve the pending project
        $this->actingAs($koordinator)
            ->get('/repository/capstone')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('projects.data', 1));

        $this->actingAs($koordinator)
            ->post('/repository/capstone/' . $project->id . '/approve')
            ->assertRedirect();

        $this->assertSame('approved', $project->fresh()->status);
    }

    public function test_incomplete_pdf_is_held_with_missing_details_and_requires_reupload(): void
    {
        Storage::fake('public');
        Mail::fake();

        $missingReasons = [
            'Halaman Pengesahan (Hal. 6): Tanda tangan belum lengkap / masih kosong pada bagian Ketua Program Studi, Dekan Fakultas Teknik.',
            'Daftar Isi (Hal. 10): Nomor halaman belum terisi pada bagian "2.3 Perancangan Perangkat Keras".',
        ];

        $this->mock(CapstonePdfCheckerService::class, function (MockInterface $mock) use ($missingReasons) {
            $mock->shouldReceive('check')->once()->andReturn([
                'passed' => false,
                'summary' => 'Dokumen ditahan karena ditemukan 2 item kelengkapan yang belum terpenuhi.',
                'missing_items' => $missingReasons,
                'toc_check' => ['passed' => false, 'pages' => [10], 'issues' => [$missingReasons[1]]],
                'signature_check' => ['passed' => false, 'pages' => [], 'issues' => [$missingReasons[0]]],
                'page_count' => 75,
                'model' => 'qwen3.8:27b',
            ]);

            $mock->shouldReceive('check')->once()->andReturn([
                'passed' => true,
                'summary' => 'Dokumen PDF lengkap.',
                'missing_items' => [],
                'toc_check' => ['passed' => true, 'pages' => [10], 'issues' => []],
                'signature_check' => ['passed' => true, 'pages' => [['pdf_page' => 6, 'page_label' => 'Halaman Pengesahan', 'all_signatures_present' => true]], 'issues' => []],
                'page_count' => 75,
                'model' => 'qwen3.8:27b',
            ]);
        });

        $dosen = User::factory()->create(['role' => 'dosen']);
        $koordinator = User::factory()->create(['role' => 'koordinator_ta']);
        $studentA = User::factory()->create(['role' => 'mahasiswa']);
        $studentB = User::factory()->create(['role' => 'mahasiswa']);

        // 1. Upload incomplete PDF -> held ("rejected") with itemized missing reasons
        $incompletePdf = UploadedFile::fake()->create('draft_belum_ttd.pdf', 400, 'application/pdf');
        $this->actingAs($studentA)->post('/repository/capstone', [
            'title' => 'Purwarupa Robot Pemadam Api',
            'academic_year' => '2025-2026',
            'group_id' => 'E11-VI-12',
            'dosen_id' => $dosen->id,
            'member_ids' => [$studentA->id, $studentB->id],
            'document' => $incompletePdf,
        ])->assertRedirect();

        $project = CapstoneProject::firstOrFail();
        $this->assertSame('rejected', $project->status);
        $this->assertSame('failed', $project->ai_check_status);
        $this->assertSame($missingReasons, $project->ai_check_result['missing_items']);

        // Dosen notification email is NOT queued while held
        Mail::assertNotQueued(ApprovalRequestMail::class);

        // Koordinator TA / Dosen cannot approve while held
        $this->actingAs($koordinator)
            ->post('/repository/capstone/' . $project->id . '/approve')
            ->assertRedirect();
        $this->assertSame('rejected', $project->fresh()->status);

        // Teammate (Student B) cannot bypass hold by saving without re-uploading a PDF
        $this->actingAs($studentB)
            ->from('/repository/capstone')
            ->post('/repository/capstone/' . $project->id . '/update', [
                'title' => 'Purwarupa Robot Pemadam Api',
                'academic_year' => '2025-2026',
                'group_id' => 'E11-VI-12',
                'dosen_id' => $dosen->id,
                'member_ids' => [$studentA->id, $studentB->id],
            ])
            ->assertRedirect('/repository/capstone')
            ->assertSessionHasErrors('document');

        // 2. Teammate (Student B) re-uploads corrected PDF -> passes AI check and enters "pending"
        $fixedPdf = UploadedFile::fake()->create('laporan_sudah_lengkap.pdf', 450, 'application/pdf');
        $this->actingAs($studentB)
            ->post('/repository/capstone/' . $project->id . '/update', [
                'title' => 'Purwarupa Robot Pemadam Api',
                'academic_year' => '2025-2026',
                'group_id' => 'E11-VI-12',
                'dosen_id' => $dosen->id,
                'member_ids' => [$studentA->id, $studentB->id],
                'document' => $fixedPdf,
            ])
            ->assertRedirect();

        $project->refresh();
        $this->assertSame('pending', $project->status);
        $this->assertSame('passed', $project->ai_check_status);
        Mail::assertQueued(ApprovalRequestMail::class);
    }
}
