<?php

namespace Tests\Feature;

use App\Mail\ApprovalRequestMail;
use App\Mail\CapstoneAcceptedWithNoteMail;
use App\Mail\StudentSubmissionStatusMail;
use App\Models\CapstoneProject;
use App\Models\User;
use App\Services\CapstonePdfCheckerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CapstoneESeriesPedomanCheckerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Safety guard: never run RefreshDatabase against live MySQL
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_missing_eseries_documents_or_incomplete_pedoman_butir_holds_pdf_and_emails_students(): void
    {
        Storage::fake('public');
        Mail::fake();

        // Subclass CapstonePdfCheckerService only to bypass external Poppler binary on fake PDF bytes
        // while executing the REAL findTocPages, evaluateTocCompleteness, evaluateSignatureCompleteness,
        // segmentESeriesPages, buildESeriesDigest, and inspectESeriesDigestsWithAi logic!
        $fakePagesText = [
            1 => "HALAMAN PENGESAHAN\nMenyetujui Dosen Pembimbing, Ketua Program Studi, Dekan.",
            2 => "DAFTAR ISI\nHALAMAN PENGESAHAN .................... 1\nE-100 .................... 3\nE-200 .................... 6\nE-300 .................... 9",
            3 => "DOKUMENTASI PRODUK TUGAS AKHIR\nJenis Dokumen: E-100 / PROPOSAL\nCapstone ID: E11-VI-90",
            4 => "DAFTAR ISI E-100\n1. PENGANTAR .................... 1\n2. PENDAHULUAN .................... 2",
            5 => "1. PENGANTAR\n1.1 Ringkasan Dokumen\nDokumen E-100 ini membahas proposal perancangan alat ukur arus beban motor industri. "
                . "2. PENDAHULUAN\nPermasalahan pada bengkel mitra PT Maju Teknik adalah ketiadaan pemantauan arus beban berlebih. "
                . "Hanya diajukan 1 solusi tunggal tanpa alternatif solusi pembanding dan baru membahas 1 aspek ekonomi saja.",
            6 => "DOKUMENTASI PRODUK TUGAS AKHIR\nJenis Dokumen: E-200 / SPESIFIKASI\nCapstone ID: E11-VI-90",
            7 => "DAFTAR ISI E-200\n1. PENGANTAR .................... 1\n2. SPESIFIKASI .................... 2\n3. VERIFIKASI .................... 3",
            8 => "1. PENGANTAR\nDokumen E-200 menjelaskan spesifikasi sistem ukur arus hingga 30A dengan akurasi 1% untuk menjawab masalah E-100. "
                . "3. VERIFIKASI\nBagian ini hanya menjelaskan pengertian mikrokontroler ESP32 dan database MySQL tanpa metode pengujian maupun alat ukur.",
            9 => "DOKUMENTASI PRODUK TUGAS AKHIR\nJenis Dokumen: E-300 / DESAIN SISTEM\nCapstone ID: E11-VI-90",
            10 => "1. PENGANTAR\nDokumen E-300 memuat diagram blok arsitektur sistem hierarkis level 0 hingga level 2, skematik rangkaian elektrik catu daya dan pengkondisi sinyal lengkap, desain tata letak PCB 2 layer dengan ground plane, desain mekanik casing 3D, flowchart firmware ESP32, hasil simulasi rangkaian pada Proteus, tabel Bill of Materials komponen rinci, serta tabel jadwal kegiatan pelaksanaan Gantt Chart.",
        ];

        $this->app->bind(CapstonePdfCheckerService::class, function () use ($fakePagesText) {
            return new class($fakePagesText) extends CapstonePdfCheckerService
            {
                /** @param array<int, string> $stubPages */
                public function __construct(private array $stubPages) {}

                protected function getPdfPageCount(string $pdfPath): int
                {
                    return count($this->stubPages);
                }

                protected function extractPerPageText(string $pdfPath, int $pageCount): array
                {
                    return $this->stubPages;
                }

                protected function renderPageToPng(string $pdfPath, int $pageNum, string $tmpDir): ?string
                {
                    $dummyPng = $tmpDir . DIRECTORY_SEPARATOR . "page_{$pageNum}.png";
                    file_put_contents($dummyPng, 'fake-png-bytes');
                    return $dummyPng;
                }
            };
        });

        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/api/tags')) {
                return Http::response([
                    'models' => [['name' => 'qwen3.8:27b']],
                ], 200);
            }

            $payload = $request->data();
            $messages = $payload['messages'] ?? [];
            $system = (string) ($messages[0]['content'] ?? '');
            $userMsg = $messages[1] ?? [];

            // 1. Vision call -> all signatures are present for every image in the batch!
            if (! empty($userMsg['images'])) {
                $pagesOut = [];
                foreach ($userMsg['images'] as $_idx => $_b64) {
                    $pagesOut[] = [
                        'page_type' => 'pengesahan',
                        'page_label' => 'Halaman Pengesahan',
                        'doc_series' => null,
                        'is_approval_page' => true,
                        'all_signatures_present' => true,
                        'missing_signatures' => [],
                        'toc_missing_entries' => [],
                    ];
                }
                return Http::response([
                    'message' => [
                        'content' => json_encode(['pages' => $pagesOut]),
                    ],
                ], 200);
            }

            // 2. TOC text call -> all TOC page numbers are filled!
            if (str_contains($system, 'pemeriksa Daftar Isi')) {
                return Http::response([
                    'message' => [
                        'content' => json_encode([
                            'all_entries_numbered' => true,
                            'missing_entries' => [],
                        ]),
                    ],
                ], 200);
            }

            // 3. E100-E500 Pedoman check call -> flags E100 & E200 missing items while E300 passes
            return Http::response([
                'message' => [
                    'content' => json_encode([
                        'documents' => [
                            'E100' => [
                                'passed' => false,
                                'missing_butir' => [
                                    [
                                        'butir' => 'Analisis Konstrain Perekayasaan',
                                        'reason' => 'Baru membahas 1 aspek ekonomi (wajib minimal 3 aspek batasan realistis)',
                                    ],
                                    [
                                        'butir' => 'Analisis dan Pemilihan Alternatif Solusi',
                                        'reason' => 'Hanya terdapat 1 solusi tunggal tanpa minimal 3 alternatif konsep solusi dan matriks pemilihan',
                                    ],
                                ],
                            ],
                            'E200' => [
                                'passed' => false,
                                'missing_butir' => [
                                    [
                                        'butir' => 'Rencana Pengujian / Metode Verifikasi Spesifikasi',
                                        'reason' => 'Bab Verifikasi belum menjelaskan metode pengujian, instrumen ukur, dan parameter keberhasilan spesifikasi',
                                    ],
                                ],
                            ],
                            'E300' => [
                                'passed' => true,
                                'missing_butir' => [],
                            ],
                        ],
                    ]),
                ],
            ], 200);
        });

        $dosen = User::factory()->create(['name' => 'Dr. Pembimbing', 'role' => 'dosen', 'email' => 'dosen@dsn.dinus.ac.id']);
        $koordinator = User::factory()->create(['name' => 'Koordinator TA', 'role' => 'koordinator_ta', 'email' => 'koor@dsn.dinus.ac.id']);
        $studentA = User::factory()->create(['name' => 'Raka Mahasiswa', 'role' => 'mahasiswa', 'email' => 'raka@mhs.dinus.ac.id']);
        $studentB = User::factory()->create(['name' => 'Sinta Mahasiswa', 'role' => 'mahasiswa', 'email' => 'sinta@mhs.dinus.ac.id']);

        $this->actingAs($studentA)->post('/repository/capstone', [
            'title' => 'Sistem Pemantauan Arus Beban Motor Industri',
            'academic_year' => '2025-2026',
            'group_id' => 'E11-VI-90',
            'dosen_id' => $dosen->id,
            'member_ids' => [$studentA->id, $studentB->id],
            'document' => UploadedFile::fake()->createWithContent('laporan_belum_lengkap_eseries.pdf', "%PDF-1.4\n%Mock Capstone PDF content\n%%EOF"),
        ])->assertRedirect();

        $project = CapstoneProject::firstOrFail();

        // Signatures & TOC both passed, YET document is held ('rejected') because E100-E500 Pedoman items are incomplete!
        $this->assertSame('rejected', $project->status);
        $this->assertSame('failed', $project->ai_check_status);
        $this->assertTrue($project->ai_check_result['signature_check']['passed']);
        $this->assertTrue($project->ai_check_result['toc_check']['passed']);
        $this->assertFalse($project->ai_check_result['eseries_check']['passed']);

        $docs = $project->ai_check_result['eseries_check']['documents'];
        $this->assertFalse($docs['E100']['passed']);
        $this->assertFalse($docs['E200']['passed']);
        $this->assertTrue($docs['E300']['passed']);
        $this->assertFalse($docs['E400']['present']);
        $this->assertFalse($docs['E500']['present']);

        $joinedIssues = implode("\n", $project->ai_check_result['missing_items']);
        $this->assertStringContainsString('Dokumen E100', $joinedIssues);
        $this->assertStringContainsString('Analisis Konstrain Perekayasaan', $joinedIssues);
        $this->assertStringContainsString('Dokumen E200', $joinedIssues);
        $this->assertStringContainsString('Rencana Pengujian / Metode Verifikasi Spesifikasi', $joinedIssues);
        $this->assertStringContainsString('Dokumen E400', $joinedIssues);
        $this->assertStringContainsString('Dokumen E500', $joinedIssues);

        // Both teammates received reupload_ai email with the exact E100-E500 missing items; verifiers were NOT spammed
        foreach ([$studentA->email, $studentB->email] as $sEmail) {
            Mail::assertQueued(
                StudentSubmissionStatusMail::class,
                fn (StudentSubmissionStatusMail $m) => $m->hasTo($sEmail)
                    && $m->statusType === 'reupload_ai'
                    && count($m->missingItems) === 5
            );
        }
        Mail::assertNotQueued(ApprovalRequestMail::class);

        // Koordinator TA can still override & accept with note if warranted
        Mail::fake();
        $this->actingAs($koordinator)->post('/repository/capstone/' . $project->id . '/accept-with-note', [
            'acceptance_note' => 'Diterima dengan catatan revisi lampiran E400-E500 diserahkan pada berkas yudisium.',
        ])->assertRedirect();

        $project->refresh();
        $this->assertSame('approved', $project->status);
        $this->assertNotEmpty($project->acceptance_note);

        foreach ([$studentA->email, $studentB->email, $dosen->email] as $targetEmail) {
            Mail::assertQueued(
                CapstoneAcceptedWithNoteMail::class,
                fn (CapstoneAcceptedWithNoteMail $m) => $m->hasTo($targetEmail)
            );
        }
    }
}
