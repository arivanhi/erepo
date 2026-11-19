<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class CapstonePdfCheckerService
{
    /**
     * Official E100-E500 structure per Buku Pedoman Pelaksanaan Tugas Akhir Capstone Design
     * Program Studi Sarjana Teknik Elektro UDINUS.
     *
     * @var array<string, array{code: string, title: string, required_items: array<int, string>}>
     */
    public const ESERIES_DEFINITIONS = [
        'E100' => [
            'code' => 'E100',
            'title' => 'Project Proposal (Problem Definition & Concept)',
            'required_items' => [
                'Pernyataan Masalah (Problem Statement)',
                'Bukti Data & Skenario Kasus',
                'Identifikasi Pemangku Kepentingan (Stakeholders)',
                'Analisis Konstrain Perekayasaan (minimal 3 aspek batasan realistis)',
                'Analisis dan Pemilihan Alternatif Solusi (minimal 3 alternatif solusi & solusi terpilih)',
            ],
        ],
        'E200' => [
            'code' => 'E200',
            'title' => 'System Specifications & Requirements',
            'required_items' => [
                'Deskripsi Fungsional / Abstrak Spesifikasi (Abstract)',
                'Parameter Teknis Kuantitatif & Terukur (Verifiable)',
                'Ketertelusuran Spesifikasi terhadap Masalah E100 (Traceable)',
                'Definisi Spesifikasi Jelas & Tidak Ambigu (Non-Ambiguous)',
                'Rencana Pengujian / Metode Verifikasi Spesifikasi',
            ],
        ],
        'E300' => [
            'code' => 'E300',
            'title' => 'Detailed Design & System Architecture',
            'required_items' => [
                'Arsitektur Sistem Hierarkis / Diagram Blok Sistem',
                'Desain Perangkat Keras (Skematik Elektrik, Perhitungan, PCB / Desain Mekanik)',
                'Desain Perangkat Lunak Tertanam / Firmware (Flowchart / FSM / Komunikasi Data)',
                'Verifikasi Desain (Simulasi atau Rancangan Pengujian Modul)',
                'Rincian Spesifikasi Komponen / Bill of Materials (BOM)',
                'Rencana Implementasi & Jadwal Kegiatan (Gantt Chart / Kurva-S / Tabel Jadwal)',
            ],
        ],
        'E400' => [
            'code' => 'E400',
            'title' => 'Implementation & Prototyping',
            'required_items' => [
                'Realisasi & Pengujian Mandiri Subsistem (Bottom-Up Implementation)',
                'Dokumentasi Pabrikasi & Realisasi Fisik Perangkat Keras',
                'Implementasi Kode Program / Firmware & Antarmuka Sistem',
                'Evaluasi / Analisis Kesesuaian & Deviasi Realisasi terhadap Desain E300',
            ],
        ],
        'E500' => [
            'code' => 'E500',
            'title' => 'Testing, Verification, and Validation',
            'required_items' => [
                'Pengujian Fungsional Sistem Terintegrasi dengan Instrumen Ukur',
                'Pengujian Batas Kerja / Ketahanan / Kinerja Catu Daya & Operasional (Stress Test)',
                'Analisis Kuantitatif / Statistik Data Hasil Uji terhadap Spesifikasi E200',
                'Uji Validasi Pemangku Kepentingan / Pengguna di Lapangan',
            ],
        ],
    ];

    /**
     * Check a Capstone PDF document for completeness:
     * 1. Approval / validation signatures (TTD Halaman Pengesahan / Persetujuan / Dokumentasi Produk TA)
     * 2. Table of Contents completeness (Daftar Isi terisi semua nomor halamannya)
     * 3. Completeness of E100–E500 documents & mandatory items per Buku Pedoman Capstone Design
     *
     * @param  string  $absolutePdfPath
     * @param  array<string, mixed>  $context
     * @return array{
     *     passed: bool,
     *     summary: string,
     *     missing_items: array<int, string>,
     *     toc_check: array<string, mixed>,
     *     signature_check: array<string, mixed>,
     *     eseries_check: array<string, mixed>,
     *     page_count: int,
     *     model: string
     * }
     */
    public function check(string $absolutePdfPath, array $context = []): array
    {
        if (! is_file($absolutePdfPath) || filesize($absolutePdfPath) === 0) {
            return [
                'passed' => false,
                'summary' => 'File PDF tidak ditemukan atau kosong.',
                'missing_items' => ['File PDF tidak dapat dibaca atau berukuran kosong (0 byte). Silakan unggah ulang file PDF yang valid.'],
                'toc_check' => ['passed' => false, 'pages' => [], 'issues' => ['File PDF kosong.']],
                'signature_check' => ['passed' => false, 'pages' => [], 'issues' => ['File PDF kosong.']],
                'eseries_check' => ['passed' => false, 'documents' => [], 'issues' => ['File PDF kosong.']],
                'page_count' => 0,
                'model' => $this->resolveModelName(),
            ];
        }

        $pageCount = $this->getPdfPageCount($absolutePdfPath);
        if ($pageCount <= 0) {
            return [
                'passed' => false,
                'summary' => 'File PDF rusak atau terkunci password sehingga tidak dapat diperiksa.',
                'missing_items' => ['File PDF rusak, terenkripsi password, atau formatnya tidak valid. Mohon ekspor ulang laporan ke PDF standar lalu unggah kembali.'],
                'toc_check' => ['passed' => false, 'pages' => [], 'issues' => ['PDF tidak dapat dibaca.']],
                'signature_check' => ['passed' => false, 'pages' => [], 'issues' => ['PDF tidak dapat dibaca.']],
                'eseries_check' => ['passed' => false, 'documents' => [], 'issues' => ['PDF tidak dapat dibaca.']],
                'page_count' => 0,
                'model' => $this->resolveModelName(),
            ];
        }

        $pagesText = $this->extractPerPageText($absolutePdfPath, $pageCount);
        [$baseUrl, $model] = $this->resolveOllamaEndpointAndModel();

        $tmpDir = storage_path('app/tmp_pdf_check/' . Str::uuid());
        File::ensureDirectoryExists($tmpDir);

        try {
            // 1. Identify Table of Contents (Daftar Isi) pages from selectable text
            $tocPages = $this->findTocPages($pagesText, $pageCount);

            // 2. Select candidate pages for Qwen Vision (approval pages + scanned front/divider pages)
            $visionPageNumbers = $this->selectVisionCandidatePages($pagesText, $tocPages, $pageCount);

            // 3. Inspect candidate pages via Qwen Vision (signatures + any scanned TOC pages)
            $visionPagesResult = $this->inspectPagesWithVision($absolutePdfPath, $visionPageNumbers, $tmpDir, $baseUrl, $model, $context);

            // 4. Evaluate Table of Contents completeness (deterministic + Qwen text + scanned TOC fallback)
            $tocCheck = $this->evaluateTocCompleteness($tocPages, $visionPagesResult, $baseUrl, $model);

            // 5. Evaluate Approval / Signature completeness
            $signatureCheck = $this->evaluateSignatureCompleteness($visionPagesResult);

            // 6. Evaluate E100-E500 structure & mandatory items per Buku Pedoman Capstone Design
            $eseriesCheck = $this->evaluateESeriesCompleteness($pagesText, $visionPagesResult, $pageCount, $baseUrl, $model, $context);

            $missingItems = array_values(array_unique(array_merge(
                $signatureCheck['issues'],
                $tocCheck['issues'],
                $eseriesCheck['issues']
            )));

            $passed = count($missingItems) === 0;

            $summary = $passed
                ? 'Dokumen PDF lengkap: tanda tangan pengesahan/persetujuan, nomor halaman Daftar Isi, serta kelengkapan butir dokumen seri E100–E500 sesuai Pedoman TA telah terpenuhi.'
                : sprintf('Dokumen ditahan karena ditemukan %d item kelengkapan yang belum terpenuhi. Wajib unggah ulang PDF yang sudah diperbaiki.', count($missingItems));

            return [
                'passed' => $passed,
                'summary' => $summary,
                'missing_items' => $missingItems,
                'toc_check' => $tocCheck,
                'signature_check' => $signatureCheck,
                'eseries_check' => $eseriesCheck,
                'page_count' => $pageCount,
                'model' => $model,
            ];
        } finally {
            File::deleteDirectory($tmpDir);
        }
    }

    protected function getPdfPageCount(string $pdfPath): int
    {
        $process = new Process(['pdfinfo', $pdfPath]);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            Log::warning('pdfinfo gagal membaca PDF', [
                'path' => $pdfPath,
                'error' => $process->getErrorOutput(),
            ]);
            return 0;
        }

        if (preg_match('/^Pages:\s+(\d+)/mi', $process->getOutput(), $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * Extract text per 1-indexed PDF page using pdftotext -layout.
     *
     * @return array<int, string>
     */
    protected function extractPerPageText(string $pdfPath, int $pageCount): array
    {
        $process = new Process(['pdftotext', '-layout', $pdfPath, '-']);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Gagal mengekstrak teks PDF: ' . trim($process->getErrorOutput()));
        }

        $rawChunks = explode("\f", $process->getOutput());
        $pages = [];
        for ($i = 1; $i <= $pageCount; $i++) {
            $pages[$i] = $rawChunks[$i - 1] ?? '';
        }

        return $pages;
    }

    /**
     * Locate all "DAFTAR ISI" / "TABLE OF CONTENTS" pages in the document.
     *
     * @param  array<int, string>  $pagesText
     * @return array<int, string>
     */
    protected function findTocPages(array $pagesText, int $pageCount): array
    {
        $tocPages = [];
        $inToc = false;
        $tocContinuationCount = 0;

        for ($p = 1; $p <= $pageCount; $p++) {
            $text = $pagesText[$p] ?? '';
            $nonWsLen = mb_strlen(preg_replace('/\s+/u', '', $text) ?? '');

            $firstLines = implode("\n", array_slice(
                array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: []))),
                0,
                10
            ));

            $isExplicitTocStart = (bool) preg_match('/^\s*(DAFTAR\s+ISI(?:\s+[A-Z0-9\-]+)?|TABLE\s+OF\s+CONTENTS)\s*$/imu', $firstLines)
                && ! preg_match('/^\s*DAFTAR\s+(GAMBAR|TABEL|LAMPIRAN|PUSTAKA|SINGKATAN|SIMBOL|NOTASI)/imu', $firstLines);

            if ($isExplicitTocStart) {
                $tocPages[$p] = $text;
                $inToc = true;
                $tocContinuationCount = 0;
                continue;
            }

            if ($inToc && $tocContinuationCount < 3 && $nonWsLen >= 60) {
                $startsNewSection = (bool) preg_match(
                    '/^\s*(DAFTAR\s+(GAMBAR|TABEL|LAMPIRAN|PUSTAKA|SINGKATAN|SIMBOL|NOTASI)|BAB\s+[IVX1-9]+\b|CHAPTER\s+[IVX1-9]+\b|ABSTRAK\b|ABSTRACT\b|KATA\s+PENGANTAR\b|HALAMAN\s+(PENGESAHAN|PERSETUJUAN|PERNYATAAN)|1\.\s*PENDAHULUAN\b|I\.\s*PENDAHULUAN\b)/imu',
                    $firstLines
                );

                // Count lines ending with typical page numbering or dotted leaders
                $lines = preg_split('/\R/u', $text) ?: [];
                $tocLikeLines = 0;
                foreach ($lines as $line) {
                    $trimmed = trim($line);
                    if ($trimmed === '') {
                        continue;
                    }
                    if (
                        preg_match('/(?:\.\s*){3,}/u', $trimmed)
                        || preg_match('/\s{2,}([ivxlcdm]+|\d+|[A-Z]-\d+)\s*$/iu', $trimmed)
                    ) {
                        $tocLikeLines++;
                    }
                }

                if (! $startsNewSection && $tocLikeLines >= 3) {
                    $tocPages[$p] = $text;
                    $tocContinuationCount++;
                    continue;
                }
            }

            $inToc = false;
            $tocContinuationCount = 0;
        }

        return $tocPages;
    }

    /**
     * Select candidate PDF pages for Qwen Vision inspection:
     * - Pages with digital approval headings (catches unsigned Word exports)
     * - Low/zero-text scanned pages in front matter (pages 1..15) or right before section TOCs/chapters (catches CamScanner signed pages)
     *
     * @param  array<int, string>  $pagesText
     * @param  array<int, string>  $tocPages
     * @return array<int, int>
     */
    protected function selectVisionCandidatePages(array $pagesText, array $tocPages, int $pageCount): array
    {
        $candidates = [];
        $tocPageNums = array_keys($tocPages);

        // Identify section starts (TOC pages or BAB I / 1. PENDAHULUAN) to catch preceding scanned cover/approval sheets
        $sectionStartPages = $tocPageNums;
        for ($p = 1; $p <= $pageCount; $p++) {
            $firstLines = implode("\n", array_slice(
                array_values(array_filter(array_map('trim', preg_split('/\R/u', $pagesText[$p] ?? '') ?: []))),
                0,
                8
            ));
            if (preg_match('/^\s*(BAB\s+I\b|BAB\s+1\b|1\.\s*PENDAHULUAN\b)/imu', $firstLines)) {
                $sectionStartPages[] = $p;
            }
        }
        $sectionStartLookup = array_flip($sectionStartPages);

        for ($p = 1; $p <= $pageCount; $p++) {
            if (isset($tocPages[$p])) {
                continue;
            }

            $text = $pagesText[$p] ?? '';
            $nonWsLen = mb_strlen(preg_replace('/\s+/u', '', $text) ?? '');

            $firstLines = implode("\n", array_slice(
                array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: []))),
                0,
                10
            ));

            // Skip Daftar Gambar / Daftar Tabel / Daftar Pustaka
            if (preg_match('/^\s*DAFTAR\s+(GAMBAR|TABEL|LAMPIRAN|PUSTAKA)/imu', $firstLines)) {
                continue;
            }

            // Rule A: Selectable text contains official approval/validation page headers
            $hasApprovalHeading = (bool) preg_match(
                '/\b(HALAMAN\s+PENGESAHAN|LEMBAR\s+PENGESAHAN|HALAMAN\s+PERSETUJUAN|LEMBAR\s+PERSETUJUAN|DOKUMENTASI\s+PRODUK\s+TUGAS\s+AKHIR)\b/iu',
                $text
            );
            $hasApprovalRoleKeywords = (bool) preg_match(
                '/\b(Menyetujui|Mengesahkan|Dosen\s+Pembimbing|Pembimbing|Dewan\s+Penguji|Penguji|Ketua\s+Program\s+Studi|Kaprodi|Dekan|Koordinator\s+TA|Koordinator\s+Tugas\s+Akhir|Disetujui|Diperiksa)\b/iu',
                $text
            );

            if ($hasApprovalHeading && ($p <= 20 || $nonWsLen < 1800 || $hasApprovalRoleKeywords)) {
                $candidates[$p] = true;
                continue;
            }

            // Rule B: Scanned / image-only pages (< 60 non-whitespace chars)
            if ($nonWsLen < 60) {
                $inFrontMatter = $p <= min(15, $pageCount);
                $precedesSectionStart = isset($sectionStartLookup[$p + 1]) || isset($sectionStartLookup[$p + 2]);

                if ($inFrontMatter || $precedesSectionStart) {
                    $candidates[$p] = true;
                }
            }
        }

        // Fallback: if zero candidates were found at all in a short PDF, inspect pages 1..min(6, pageCount)
        if (empty($candidates)) {
            $limit = min(6, $pageCount);
            for ($p = 1; $p <= $limit; $p++) {
                if (! isset($tocPages[$p])) {
                    $candidates[$p] = true;
                }
            }
        }

        $selected = array_keys($candidates);
        sort($selected);

        return array_slice($selected, 0, 12);
    }

    /**
     * Render selected PDF pages to PNG and inspect them in small batches using Qwen Vision.
     *
     * @param  array<int, int>  $pageNumbers
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    protected function inspectPagesWithVision(
        string $pdfPath,
        array $pageNumbers,
        string $tmpDir,
        string $baseUrl,
        string $model,
        array $context = []
    ): array {
        if (empty($pageNumbers)) {
            return [];
        }

        $renderedImages = [];
        foreach ($pageNumbers as $pageNum) {
            $pngPath = $this->renderPageToPng($pdfPath, $pageNum, $tmpDir);
            if ($pngPath && is_file($pngPath)) {
                $renderedImages[$pageNum] = base64_encode((string) file_get_contents($pngPath));
            }
        }

        if (empty($renderedImages)) {
            return [];
        }

        $resultsByPage = [];
        $batches = array_chunk(array_keys($renderedImages), 3);

        $systemPrompt = <<<'PROMPT'
Anda adalah asisten pemeriksa kelengkapan dokumen Tugas Akhir / Capstone Design S1 Teknik Elektro.
Tugas Anda adalah memeriksa gambar halaman PDF yang diberikan secara teliti dan objektif, lalu mengembalikan JSON valid.

ATURAN KLASIFIKASI HALAMAN (`page_type`):
- `"pengesahan"`: Halaman Pengesahan / Lembar Pengesahan (biasanya memuat Dewan Penguji / Ketua Sidang / Penguji, Kaprodi, dan/atau Dekan).
- `"persetujuan"`: Halaman Persetujuan / Lembar Persetujuan (biasanya memuat persetujuan Dosen Pembimbing).
- `"dokumentasi_produk_ta"`: Halaman cover/tabel "Dokumentasi Produk Tugas Akhir" (seri E-100, E-200, E-300, E-400, E-500) yang memiliki tabel tanda tangan "Ditulis / Diperiksa / Disetujui" (Tim Capstone, Dosen Pembimbing, Koordinator TA).
- `"daftar_isi"`: Halaman Daftar Isi hasil scan gambar.
- `"lainnya"`: Halaman lain seperti Cover Judul biasa, Pernyataan Orisinalitas, Persetujuan Publikasi, Kata Pengantar, Abstrak, atau isi bab.

ATURAN PEMERIKSAAN TANDA TANGAN (KHUSUS `pengesahan`, `persetujuan`, dan `dokumentasi_produk_ta`):
1. Periksa setiap kolom/slot tanda tangan pejabat, dosen, koordinator TA, atau tim mahasiswa yang tersedia pada halaman pengesahan/persetujuan tersebut.
2. Slot tanda tangan dianggap **SUDAH TERISI (`signed`)** apabila terlihat salah satu dari:
   - Goresan tanda tangan tangan (basah/pulpen/scan), meskipun tipis atau sedikit menumpuk pada teks nama/stempel, ATAU
   - Gambar tanda tangan digital / paraf, ATAU
   - Kode QR Tanda Tangan Elektronik (TTE), ATAU
   - Stempel resmi disertai goresan tanda tangan/paraf.
3. Slot tanda tangan dianggap **BELUM TERISI / KOSONG** HANYA apabila ruang tanda tangan di atas/sekitar nama atau di dalam sel "Tanda Tangan" benar-benar kosong melompong (putih polos atau hanya garis/titik-titik kosong tanpa goresan tanda tangan maupun QR TTE).
4. Untuk halaman `"lainnya"` (seperti Cover, Kata Pengantar, Pernyataan, Abstrak), set `"is_approval_page": false` dan `"missing_signatures": []`.
5. Jika halaman berupa `"daftar_isi"` hasil scan, periksa apakah ada baris sub-bab/bab yang nomor halamannya kosong, lalu tulis judul barisnya di `"toc_missing_entries"`.
6. Jika halaman merupakan `"dokumentasi_produk_ta"`, isi `"doc_series"` dengan kode seri yang tertera pada halaman tersebut (`"E100"`, `"E200"`, `"E300"`, `"E400"`, atau `"E500"`), atau `null` bila bukan seri E100–E500.

Kembalikan HANYA objek JSON dengan struktur persis:
{
  "pages": [
    {
      "pdf_page": 5,
      "page_type": "persetujuan",
      "page_label": "Halaman Persetujuan",
      "doc_series": null,
      "is_approval_page": true,
      "all_signatures_present": true,
      "missing_signatures": [],
      "toc_missing_entries": []
    }
  ]
}
PROMPT;

        foreach ($batches as $batchPageNums) {
            $imagesBase64 = [];
            $mappingLines = [];
            foreach ($batchPageNums as $idx => $pNum) {
                $imagesBase64[] = $renderedImages[$pNum];
                $mappingLines[] = sprintf('- Gambar ke-%d adalah Halaman PDF %d', $idx + 1, $pNum);
            }

            $userPrompt = "Periksa " . count($batchPageNums) . " gambar halaman PDF berikut secara berurutan:\n"
                . implode("\n", $mappingLines) . "\n\n"
                . (! empty($context['title']) ? "Judul Dokumen: " . $context['title'] . "\n" : '')
                . "Pastikan `pdf_page` pada setiap elemen `pages` sesuai urutan halaman PDF di atas dan sebutkan jabatan/peran yang tanda tangannya masih kosong pada `missing_signatures` dalam Bahasa Indonesia.";

            $parsed = $this->callOllamaChat($baseUrl, $model, $systemPrompt, $userPrompt, $imagesBase64);
            $returnedPages = $parsed['pages'] ?? [];

            if (is_array($returnedPages)) {
                foreach ($returnedPages as $idx => $pageData) {
                    if (! is_array($pageData)) {
                        continue;
                    }
                    $resolvedPage = isset($pageData['pdf_page']) && in_array((int) $pageData['pdf_page'], $batchPageNums, true)
                        ? (int) $pageData['pdf_page']
                        : ($batchPageNums[$idx] ?? null);

                    if ($resolvedPage !== null) {
                        $pageData['pdf_page'] = $resolvedPage;
                        $resultsByPage[$resolvedPage] = $pageData;
                    }
                }
            }
        }

        ksort($resultsByPage);

        return $resultsByPage;
    }

    protected function renderPageToPng(string $pdfPath, int $pageNum, string $tmpDir): ?string
    {
        $outPrefix = $tmpDir . DIRECTORY_SEPARATOR . 'page_' . $pageNum;
        $process = new Process([
            'pdftoppm',
            '-png',
            '-r',
            '130',
            '-f',
            (string) $pageNum,
            '-l',
            (string) $pageNum,
            '-singlefile',
            $pdfPath,
            $outPrefix,
        ]);
        $process->setTimeout(30);
        $process->run();

        $pngFile = $outPrefix . '.png';
        if (! $process->isSuccessful() || ! is_file($pngFile)) {
            Log::warning('pdftoppm gagal merender halaman PDF', [
                'page' => $pageNum,
                'error' => $process->getErrorOutput(),
            ]);
            return null;
        }

        return $pngFile;
    }

    /**
     * Evaluate Table of Contents completeness using deterministic checks + Qwen text verification.
     *
     * @param  array<int, string>  $tocPages
     * @param  array<int, array<string, mixed>>  $visionPagesResult
     * @return array{passed: bool, pages: array<int, int>, issues: array<int, string>}
     */
    protected function evaluateTocCompleteness(
        array $tocPages,
        array $visionPagesResult,
        string $baseUrl,
        string $model
    ): array {
        $issues = [];
        $scannedTocPages = [];

        foreach ($visionPagesResult as $pNum => $vPage) {
            if (($vPage['page_type'] ?? '') === 'daftar_isi') {
                $scannedTocPages[] = $pNum;
                foreach (($vPage['toc_missing_entries'] ?? []) as $missingEntry) {
                    if (is_string($missingEntry) && trim($missingEntry) !== '') {
                        $issues[] = sprintf('Daftar Isi (Hal. %d): Nomor halaman belum terisi pada bagian "%s".', $pNum, trim($missingEntry));
                    }
                }
            }
        }

        if (empty($tocPages) && empty($scannedTocPages)) {
            return [
                'passed' => false,
                'pages' => [],
                'issues' => ['Halaman Daftar Isi tidak ditemukan di dalam dokumen PDF. Pastikan dokumen memuat Daftar Isi lengkap beserta nomor halamannya.'],
            ];
        }

        if (empty($tocPages)) {
            return [
                'passed' => count($issues) === 0,
                'pages' => $scannedTocPages,
                'issues' => $issues,
            ];
        }

        // Step A: Deterministic scan for broken bookmarks or dotted lines missing trailing page numbers
        $deterministicFindings = [];
        foreach ($tocPages as $pNum => $text) {
            $lines = array_values(preg_split('/\R/u', $text) ?: []);
            $lineCount = count($lines);

            for ($i = 0; $i < $lineCount; $i++) {
                $line = trim($lines[$i]);
                if ($line === '') {
                    continue;
                }

                if (preg_match('/Error!\s*Bookmark\s*not\s*defined|Kesalahan!\s*Bookmark\s*tidak\s*ditentukan/iu', $line)) {
                    $cleanLabel = trim((string) preg_replace('/(?:\.\s*){2,}.*$/u', '', $line));
                    $deterministicFindings[] = sprintf(
                        'Daftar Isi (Hal. %d): Terdapat error nomor halaman otomatis "%s" (Error! Bookmark not defined).',
                        $pNum,
                        Str::limit($cleanLabel ?: $line, 80)
                    );
                    continue;
                }

                if (preg_match('/^(.+?)(?:\.\s*){4,}(\?\?+|#+|\[[^\]]*\]|-\s*)\s*$/u', $line, $m)) {
                    $deterministicFindings[] = sprintf(
                        'Daftar Isi (Hal. %d): Nomor halaman belum valid/masih placeholder pada entri "%s".',
                        $pNum,
                        Str::limit(trim($m[1]), 80)
                    );
                    continue;
                }

                // Line ends with >= 5 leader dots and NOTHING after the dots
                if (preg_match('/^(.+?)(?:\.\s*){5,}\s*$/u', $line, $m)) {
                    // Check if the next non-empty line is merely a wrapped page number token
                    $nextNonEmpty = '';
                    for ($j = $i + 1; $j < $lineCount; $j++) {
                        if (trim($lines[$j]) !== '') {
                            $nextNonEmpty = trim($lines[$j]);
                            break;
                        }
                    }
                    $nextIsLonePageNum = $nextNonEmpty !== '' && preg_match('/^([ivxlcdm]+|\d+|[A-Z]-\d+)$/iu', $nextNonEmpty);
                    if (! $nextIsLonePageNum) {
                        $deterministicFindings[] = sprintf(
                            'Daftar Isi (Hal. %d): Nomor halaman belum terisi (kosong) pada entri "%s".',
                            $pNum,
                            Str::limit(trim($m[1]), 80)
                        );
                    }
                }
            }
        }

        // Step B: Qwen text check on extracted Daftar Isi layout text
        $formattedTocBlocks = [];
        foreach ($tocPages as $pNum => $text) {
            $formattedTocBlocks[] = "=== [HALAMAN PDF {$pNum}] ===\n" . trim($text);
        }
        $combinedTocText = Str::limit(implode("\n\n", $formattedTocBlocks), 24000, "\n...[dipotong]");

        $tocSystemPrompt = <<<'PROMPT'
Anda adalah pemeriksa Daftar Isi laporan Tugas Akhir / Capstone Design S1 Teknik Elektro.
Periksa teks halaman Daftar Isi (diekstrak dengan layout kolom asli) dan tentukan apakah SEMUA item/entri Daftar Isi sudah memiliki nomor halaman.

ATURAN PENTING AGAR TIDAK SALAH DETEKSI:
1. Nomor halaman yang VALID bisa berupa angka Arab (`1`, `12`, `105`), angka Romawi (`i`, `ii`, `iv`, `xi`), maupun kode halaman lampiran/seri (`I-1`, `A-1`, `L-1`, `E100-1`). Semua format tersebut VALID.
2. Judul bab/sub-bab yang panjang sering terpotong menjadi 2 baris, di mana baris pertama hanya awal judul dan baris kedua berisi lanjutan judul diikuti titik-titik serta nomor halaman (atau sebaliknya). Itu VALID dan JANGAN dianggap kurang nomor halaman.
3. Judul halaman sendiri seperti `DAFTAR ISI`, `DAFTAR ISI E-100`, header kolom seperti `Halaman`, atau sub-kelompok `LAMPIRAN` tanpa titik-titik yang rinciannya di bawahnya sudah bernomor adalah VALID.
4. Tandai sebagai `missing_entries` HANYA JIKA ada baris item bab/sub-bab/bagian pada Daftar Isi yang seharusnya memiliki nomor halaman tetapi kolom nomor halamannya kosong, berhenti di titik-titik `.....` tanpa angka/romawi di ujungnya, atau berisi `??` / `Error! Bookmark not defined`.

Kembalikan HANYA objek JSON dengan format:
{
  "all_entries_numbered": true,
  "missing_entries": [
    {
      "pdf_page": 10,
      "entry": "2.3 Perancangan Sistem",
      "reason": "Nomor halaman kosong di akhir baris"
    }
  ]
}
PROMPT;

        $aiTocIssues = [];
        try {
            $parsedToc = $this->callOllamaChat(
                $baseUrl,
                $model,
                $tocSystemPrompt,
                "Berikut teks seluruh halaman Daftar Isi pada dokumen PDF:\n\n" . $combinedTocText
            );

            foreach (($parsedToc['missing_entries'] ?? []) as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $entry = trim((string) ($item['entry'] ?? ''));
                $page = (int) ($item['pdf_page'] ?? array_key_first($tocPages));
                if ($entry !== '') {
                    $aiTocIssues[] = sprintf(
                        'Daftar Isi (Hal. %d): Nomor halaman belum terisi pada bagian "%s".',
                        $page,
                        Str::limit($entry, 90)
                    );
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Pemeriksaan teks Daftar Isi via Qwen gagal, menggunakan hasil deterministik.', [
                'error' => $e->getMessage(),
            ]);
        }

        // Merge issues without duplicating the same TOC entry on the same page
        $mergedRaw = array_merge($issues, $deterministicFindings, $aiTocIssues);
        $seenEntryKeys = [];
        $allTocIssues = [];
        foreach ($mergedRaw as $msg) {
            if (preg_match('/Hal\.\s*(\d+).*"([^"]+)"/u', $msg, $m)) {
                $normKey = $m[1] . '|' . mb_strtolower(preg_replace('/\s+/u', ' ', trim($m[2])) ?? '');
                if (isset($seenEntryKeys[$normKey])) {
                    continue;
                }
                $seenEntryKeys[$normKey] = true;
            }
            $allTocIssues[] = $msg;
        }
        $allTocIssues = array_values(array_unique($allTocIssues));
        $allTocPageNums = array_values(array_unique(array_merge(array_keys($tocPages), $scannedTocPages)));
        sort($allTocPageNums);

        return [
            'passed' => count($allTocIssues) === 0,
            'pages' => $allTocPageNums,
            'issues' => $allTocIssues,
        ];
    }

    /**
     * Evaluate signature completeness across detected approval/validation pages.
     *
     * @param  array<int, array<string, mixed>>  $visionPagesResult
     * @return array{passed: bool, pages: array<int, array<string, mixed>>, issues: array<int, string>}
     */
    protected function evaluateSignatureCompleteness(array $visionPagesResult): array
    {
        $approvalTypes = ['pengesahan', 'persetujuan', 'dokumentasi_produk_ta'];
        $approvalPages = [];
        $issues = [];

        foreach ($visionPagesResult as $pNum => $pageData) {
            $type = strtolower((string) ($pageData['page_type'] ?? 'lainnya'));
            $isApproval = in_array($type, $approvalTypes, true) || ! empty($pageData['is_approval_page']);

            if (! $isApproval || ! in_array($type, $approvalTypes, true)) {
                continue;
            }

            $label = trim((string) ($pageData['page_label'] ?? ''));
            if ($label === '') {
                $label = match ($type) {
                    'pengesahan' => 'Halaman Pengesahan',
                    'persetujuan' => 'Halaman Persetujuan',
                    'dokumentasi_produk_ta' => 'Lembar Dokumentasi Produk TA',
                    default => 'Halaman Pengesahan',
                };
            }

            $missingSigs = array_values(array_filter(array_map(
                fn ($s) => is_string($s) ? trim($s) : '',
                is_array($pageData['missing_signatures'] ?? null) ? $pageData['missing_signatures'] : []
            )));

            $allPresent = ($pageData['all_signatures_present'] ?? true) === true && count($missingSigs) === 0;

            $approvalPages[] = [
                'pdf_page' => $pNum,
                'page_type' => $type,
                'page_label' => $label,
                'all_signatures_present' => $allPresent,
                'missing_signatures' => $missingSigs,
            ];

            if (! $allPresent) {
                if (count($missingSigs) > 0) {
                    $issues[] = sprintf(
                        '%s (Hal. %d): Tanda tangan belum lengkap / masih kosong pada bagian %s.',
                        $label,
                        $pNum,
                        implode(', ', $missingSigs)
                    );
                } else {
                    $issues[] = sprintf(
                        '%s (Hal. %d): Tanda tangan pengesahan/persetujuan belum terisi lengkap.',
                        $label,
                        $pNum
                    );
                }
            }
        }

        if (empty($approvalPages)) {
            $issues[] = 'Halaman Pengesahan / Persetujuan tidak ditemukan di dalam dokumen PDF. Pastikan Halaman Pengesahan yang telah ditandatangani disertakan dalam file PDF.';
        }

        return [
            'passed' => count($issues) === 0,
            'pages' => $approvalPages,
            'issues' => $issues,
        ];
    }

    /**
     * Evaluate completeness of Dokumen Seri E100–E500 and their mandatory substantive items
     * per Buku Pedoman Pelaksanaan Tugas Akhir Capstone Design S1 Teknik Elektro UDINUS.
     *
     * @param  array<int, string>  $pagesText
     * @param  array<int, array<string, mixed>>  $visionPagesResult
     * @param  array<string, mixed>  $context
     * @return array{passed: bool, documents: array<string, array<string, mixed>>, issues: array<int, string>}
     */
    protected function evaluateESeriesCompleteness(
        array $pagesText,
        array $visionPagesResult,
        int $pageCount,
        string $baseUrl,
        string $model,
        array $context = []
    ): array {
        $totalNonWs = 0;
        foreach ($pagesText as $txt) {
            $totalNonWs += mb_strlen(preg_replace('/\s+/u', '', $txt) ?? '');
        }

        $segments = $this->segmentESeriesPages($pagesText, $visionPagesResult, $pageCount);
        $presentCodes = array_keys(array_filter($segments, fn (array $seg) => $seg['present'] === true));

        $documents = [];
        foreach (self::ESERIES_DEFINITIONS as $code => $def) {
            $seg = $segments[$code];
            $documents[$code] = [
                'code' => $code,
                'title' => $def['title'],
                'present' => $seg['present'],
                'start_page' => $seg['start_page'],
                'end_page' => $seg['end_page'],
                'passed' => false,
                'missing_butir' => [],
            ];
        }

        if ($totalNonWs < 300) {
            $scanMsg = 'Isi dokumen E100–E500 tidak dapat diperiksa karena seluruh halaman PDF berupa gambar scan tanpa teks yang dapat dibaca. Mohon ekspor naskah laporan langsung dari Word/LaTeX ke PDF dan gabungkan hanya lembar pengesahan bertanda tangan.';
            return [
                'passed' => false,
                'documents' => $documents,
                'issues' => [$scanMsg],
            ];
        }

        if (empty($presentCodes)) {
            $issues = [];
            foreach (self::ESERIES_DEFINITIONS as $code => $def) {
                $documents[$code]['missing_butir'] = [
                    [
                        'butir' => 'Keberadaan Dokumen ' . $code,
                        'reason' => 'Dokumen seri ' . $code . ' tidak ditemukan di dalam file PDF.',
                    ],
                ];
                $issues[] = sprintf(
                    'Dokumen %s (%s): Dokumen seri %s belum disertakan / tidak ditemukan di dalam file PDF (sesuai Pedoman TA wajib memuat lengkap E100 s.d. E500).',
                    $code,
                    $def['title'],
                    $code
                );
            }

            return [
                'passed' => false,
                'documents' => $documents,
                'issues' => $issues,
            ];
        }

        $issues = [];
        $digestsForAi = [];

        foreach (self::ESERIES_DEFINITIONS as $code => $def) {
            $seg = $segments[$code];
            if (! $seg['present']) {
                $documents[$code]['passed'] = false;
                $documents[$code]['missing_butir'] = [
                    [
                        'butir' => 'Keberadaan Dokumen ' . $code,
                        'reason' => 'Dokumen seri ' . $code . ' tidak ditemukan di dalam file PDF.',
                    ],
                ];
                $issues[] = sprintf(
                    'Dokumen %s (%s): Dokumen seri %s belum disertakan / tidak ditemukan di dalam file PDF.',
                    $code,
                    $def['title'],
                    $code
                );
                continue;
            }

            $digest = $this->buildESeriesDigest($code, (int) $seg['start_page'], (int) $seg['end_page'], $pagesText);
            if ($digest['substantive_char_count'] < 200) {
                $documents[$code]['passed'] = false;
                $documents[$code]['missing_butir'] = [
                    [
                        'butir' => 'Isi Pembahasan ' . $code,
                        'reason' => 'Halaman isi pembahasan ' . $code . ' masih kosong atau hanya berisi cover/daftar isi tanpa uraian substansi.',
                    ],
                ];
                $issues[] = sprintf(
                    'Dokumen %s (Hal. %d–%d): Isi pembahasan dokumen %s masih kosong / belum memuat uraian butir wajib sesuai Pedoman TA.',
                    $code,
                    $seg['start_page'],
                    $seg['end_page'],
                    $code
                );
                continue;
            }

            $digestsForAi[$code] = $digest['formatted_block'];
        }

        if (! empty($digestsForAi)) {
            $aiEvaluation = $this->inspectESeriesDigestsWithAi($digestsForAi, $baseUrl, $model, $context);

            foreach ($digestsForAi as $code => $_block) {
                $seg = $segments[$code];
                $docEval = $aiEvaluation[$code] ?? null;
                $rawMissing = is_array($docEval['missing_butir'] ?? null) ? $docEval['missing_butir'] : [];

                $normalizedMissing = [];
                foreach ($rawMissing as $entry) {
                    if (is_string($entry) && trim($entry) !== '') {
                        $normalizedMissing[] = [
                            'butir' => 'Butir Wajib ' . $code,
                            'reason' => trim($entry),
                        ];
                        continue;
                    }

                    if (is_array($entry)) {
                        $butir = trim((string) ($entry['butir'] ?? ''));
                        $reason = trim((string) ($entry['reason'] ?? ''));
                        if ($butir !== '' || $reason !== '') {
                            $normalizedMissing[] = [
                                'butir' => $butir !== '' ? $butir : ('Butir Wajib ' . $code),
                                'reason' => $reason !== '' ? $reason : 'Belum termuat secara lengkap sesuai Pedoman Capstone Design.',
                            ];
                        }
                    }
                }

                $docPassed = count($normalizedMissing) === 0;
                $documents[$code]['passed'] = $docPassed;
                $documents[$code]['missing_butir'] = $normalizedMissing;

                foreach ($normalizedMissing as $mItem) {
                    $cleanReason = rtrim($mItem['reason'], " \t\n\r\0\x0B.");
                    $issues[] = sprintf(
                        'Dokumen %s (Hal. %d–%d) — %s: %s.',
                        $code,
                        $seg['start_page'],
                        $seg['end_page'],
                        $mItem['butir'],
                        $cleanReason
                    );
                }
            }
        }

        $issues = array_values(array_unique($issues));

        return [
            'passed' => count($issues) === 0,
            'documents' => $documents,
            'issues' => $issues,
        ];
    }

    /**
     * Segment PDF pages into E100, E200, E300, E400, and E500 ranges.
     *
     * @param  array<int, string>  $pagesText
     * @param  array<int, array<string, mixed>>  $visionPagesResult
     * @return array<string, array{present: bool, start_page: int|null, end_page: int|null}>
     */
    protected function segmentESeriesPages(array $pagesText, array $visionPagesResult, int $pageCount): array
    {
        $priority1 = []; // Explicit series cover sheet or series-specific DAFTAR ISI E-X00
        $visionAnchors = []; // Scanned Dokumentasi Produk TA cover detected via Vision
        $priority2 = []; // Standalone divider heading or single-series TOC
        $priority3 = []; // Prodi template "1.1 Ringkasan Dokumen: Dokumen E-X00..."

        foreach ($visionPagesResult as $pNum => $vData) {
            $vType = strtolower((string) ($vData['page_type'] ?? ''));
            if ($vType !== 'dokumentasi_produk_ta') {
                continue;
            }

            $seriesCandidate = (string) ($vData['doc_series'] ?? '') . ' ' . (string) ($vData['page_label'] ?? '');
            if (preg_match('/\bE-?([1-5]00)\b/iu', $seriesCandidate, $m)) {
                $code = 'E' . $m[1];
                $visionAnchors[$code] = isset($visionAnchors[$code]) ? min($visionAnchors[$code], $pNum) : $pNum;
            }
        }

        for ($p = 1; $p <= $pageCount; $p++) {
            $text = $pagesText[$p] ?? '';
            if (trim($text) === '') {
                continue;
            }

            $nonEmptyLines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: [])));
            $firstLines = implode("\n", array_slice($nonEmptyLines, 0, 10));

            preg_match_all('/\bE-?([1-5]00)\b/iu', $text, $codeMatches);
            $distinctNums = array_values(array_unique($codeMatches[1] ?? []));

            // Signal 1: Selectable "DOKUMENTASI PRODUK TUGAS AKHIR" cover page for a specific E-series
            if (preg_match('/DOKUMENTASI\s+PRODUK\s+TUGAS\s+AKHIR/iu', $text) && count($distinctNums) === 1) {
                $code = 'E' . $distinctNums[0];
                $priority1[$code] = isset($priority1[$code]) ? min($priority1[$code], $p) : $p;
                continue;
            }

            // Signal 2a: Explicit "DAFTAR ISI E-100" .. "DAFTAR ISI E-500"
            if (preg_match('/^\s*DAFTAR\s+ISI\s+(?:DOKUMEN\s+)?E-?([1-5]00)\b/imu', $firstLines, $m)) {
                $code = 'E' . $m[1];
                $startCandidate = $this->resolveSeriesStartBeforeToc($p, $pagesText);
                $priority1[$code] = isset($priority1[$code]) ? min($priority1[$code], $startCandidate) : $startCandidate;
                continue;
            }

            // Signal 2b: Generic "DAFTAR ISI" page that exclusively references a single E-series (e.g. "DAFTAR GAMBAR E-200")
            if (
                preg_match('/^\s*DAFTAR\s+ISI\s*$/imu', $firstLines)
                && ! preg_match('/^\s*DAFTAR\s+(GAMBAR|TABEL)/imu', $firstLines)
                && count($distinctNums) === 1
            ) {
                $code = 'E' . $distinctNums[0];
                $startCandidate = $this->resolveSeriesStartBeforeToc($p, $pagesText);
                $priority2[$code] ??= $startCandidate;
                continue;
            }

            // Signal 3: Standalone chapter/document header (excluding master TOCs & Daftar Gambar/Tabel)
            if (
                ! preg_match('/^\s*DAFTAR\s+(ISI|GAMBAR|TABEL|SINGKATAN|LAMPIRAN)/imu', $firstLines)
                && count($distinctNums) === 1
            ) {
                $topFive = implode("\n", array_slice($nonEmptyLines, 0, 5));
                if (preg_match('/^\s*(?:BAB\s+[IVX1-9]+\s*[:\.\-]?\s*)?(?:DOKUMEN\s+|SERI\s+|LAMPIRAN\s+[A-E0-9]*\s*[:\.\-]?\s*)?E-?([1-5]00)\b(?:\s*[:\-\/]\s*.+)?$/imu', $topFive, $m)) {
                    $code = 'E' . $m[1];
                    $priority2[$code] ??= $p;
                    continue;
                }
            }

            // Signal 4: Prodi Word Template "1. PENGANTAR" / "1.1 Ringkasan Dokumen" stating "Dokumen E-100..."
            if (
                preg_match('/\b(1\.\s*PENGANTAR|1\.1\s+Ringkasan\s+Dokumen)\b/iu', $firstLines)
                && preg_match('/\bDokumen\s+E-?([1-5]00)\b/iu', mb_substr($text, 0, 1000), $m)
            ) {
                $code = 'E' . $m[1];
                $walkedStart = $p;
                for ($back = $p - 1; $back >= max(1, $p - 6); $back--) {
                    $backText = $pagesText[$back] ?? '';
                    if (preg_match('/DOKUMENTASI\s+PRODUK\s+TUGAS\s+AKHIR/iu', $backText)) {
                        $walkedStart = $back;
                        break;
                    }
                    if (preg_match('/^\s*DAFTAR\s+ISI\b/imu', $backText)) {
                        // Ignore master TOC that lists multiple E-series at once
                        preg_match_all('/\bE-?([1-5]00)\b/iu', $backText, $backCodes);
                        if (count(array_unique($backCodes[1] ?? [])) <= 1) {
                            $walkedStart = $this->resolveSeriesStartBeforeToc($back, $pagesText);
                            break;
                        }
                    }
                }
                $priority3[$code] ??= $walkedStart;
            }
        }

        $detectedStarts = [];
        foreach (array_keys(self::ESERIES_DEFINITIONS) as $code) {
            $anchor = $priority1[$code] ?? $visionAnchors[$code] ?? $priority2[$code] ?? $priority3[$code] ?? null;
            if ($anchor !== null) {
                $detectedStarts[$code] = $anchor;
            }
        }

        asort($detectedStarts);
        $orderedCodes = array_keys($detectedStarts);
        $orderedCount = count($orderedCodes);

        $result = [];
        foreach (array_keys(self::ESERIES_DEFINITIONS) as $code) {
            $result[$code] = [
                'present' => false,
                'start_page' => null,
                'end_page' => null,
            ];
        }

        for ($i = 0; $i < $orderedCount; $i++) {
            $code = $orderedCodes[$i];
            $start = $detectedStarts[$code];
            $nextStart = ($i + 1 < $orderedCount) ? $detectedStarts[$orderedCodes[$i + 1]] : ($pageCount + 1);
            $end = max($start, $nextStart - 1);

            $result[$code] = [
                'present' => true,
                'start_page' => $start,
                'end_page' => $end,
            ];
        }

        return $result;
    }

    /**
     * Check if the page immediately preceding a series TOC is its scanned or digital cover sheet.
     *
     * @param  array<int, string>  $pagesText
     */
    protected function resolveSeriesStartBeforeToc(int $tocPage, array $pagesText): int
    {
        if ($tocPage <= 1) {
            return $tocPage;
        }

        $prevText = $pagesText[$tocPage - 1] ?? '';
        $prevNonWs = mb_strlen(preg_replace('/\s+/u', '', $prevText) ?? '');

        if ($prevNonWs < 120 || preg_match('/DOKUMENTASI\s+PRODUK\s+TUGAS\s+AKHIR/iu', $prevText)) {
            return $tocPage - 1;
        }

        return $tocPage;
    }

    /**
     * Build a structured, token-efficient digest covering the ENTIRE page range of a single E-series document:
     * - TOC + Daftar Gambar + Daftar Tabel (reveals all sections, diagrams, PCB layouts, schematics & data tables)
     * - All section headings & figure/table captions across body pages
     * - Evenly distributed substantive body text excerpts across every body page of the series
     *
     * @param  array<int, string>  $pagesText
     * @return array{substantive_char_count: int, formatted_block: string}
     */
    protected function buildESeriesDigest(string $code, int $startPage, int $endPage, array $pagesText): array
    {
        $def = self::ESERIES_DEFINITIONS[$code];
        $tocPagesText = [];
        $figTableLists = [];
        $sectionHeadings = [];
        $figureTableCaptions = [];
        $bodyPagesCleaned = [];
        $substantiveCharCount = 0;

        for ($p = $startPage; $p <= $endPage; $p++) {
            $cleaned = $this->stripBoilerplatePageLines($pagesText[$p] ?? '');
            if ($cleaned === '') {
                continue;
            }

            $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $cleaned) ?: [])));
            $topLines = implode("\n", array_slice($lines, 0, 6));

            // Skip cover metadata table ("DOKUMENTASI PRODUK TUGAS AKHIR")
            if (preg_match('/DOKUMENTASI\s+PRODUK\s+TUGAS\s+AKHIR/iu', $topLines)) {
                continue;
            }

            // Capture TOC vs Daftar Gambar/Tabel separately so DAFTAR ISI is never crowded out
            if (preg_match('/^\s*DAFTAR\s+ISI\b/imu', $topLines) && ! preg_match('/^\s*DAFTAR\s+(GAMBAR|TABEL)/imu', $topLines)) {
                $tocPagesText[] = "[Hal. {$p}]\n" . $cleaned;
                continue;
            }

            if (preg_match('/^\s*DAFTAR\s+(GAMBAR|TABEL)\b/imu', $topLines)) {
                $figTableLists[] = "[Hal. {$p}]\n" . $cleaned;
                continue;
            }

            // Skip non-substantive administrative pages (Revisi Dokumen, Daftar Singkatan, Referensi, CV Lampiran)
            if (preg_match('/^\s*(REVISI\s+DOKUMEN|DAFTAR\s+SINGKATAN|REFERENSI|DAFTAR\s+PUSTAKA|CV\s+ANGGOTA)\b/imu', $topLines)) {
                continue;
            }
            if (preg_match('/^\s*LAMPIRAN\s*$/imu', $topLines) && mb_strlen(preg_replace('/\s+/u', '', $cleaned) ?? '') < 120) {
                continue;
            }

            foreach ($lines as $line) {
                $normalizedLine = trim((string) preg_replace('/[ \t]{2,}/u', ' ', $line));
                if (preg_match('/^(?:BAB\s+[IVX0-9]+|[1-9]\.(?:\d+(?:\.\d+)?)?)\s+[A-Z0-9]/u', $normalizedLine)) {
                    $sectionHeadings[] = "[Hal. {$p}] " . Str::limit($normalizedLine, 120);
                } elseif (preg_match('/^(?:Gambar|Tabel|Figure|Table)\s*\d+[\.\s\-]*\d*/iu', $normalizedLine)) {
                    $figureTableCaptions[] = "[Hal. {$p}] " . Str::limit($normalizedLine, 130);
                }
            }

            $compactBody = trim((string) preg_replace('/[ \t]{2,}/u', ' ', $cleaned));
            $nonWs = mb_strlen(preg_replace('/\s+/u', '', $compactBody) ?? '');
            if ($nonWs >= 25) {
                $substantiveCharCount += $nonWs;
                $bodyPagesCleaned[$p] = $compactBody;
            }
        }

        $tocSection = Str::limit(implode("\n\n", $tocPagesText), 3200, "\n...[dipotong]");
        $figTableRaw = implode("\n\n", $figTableLists);
        if (mb_strlen($figTableRaw) > 2600) {
            $figTableSection = trim(mb_substr($figTableRaw, 0, 1400)) . "\n...\n" . trim(mb_substr($figTableRaw, -1100));
        } else {
            $figTableSection = $figTableRaw;
        }

        $headingsSection = Str::limit(implode("\n", array_values(array_unique($sectionHeadings))), 2400, "\n...[dipotong]");

        $uniqueCaptions = array_values(array_unique($figureTableCaptions));
        $captionsRaw = implode("\n", $uniqueCaptions);
        if (mb_strlen($captionsRaw) > 2800) {
            $captionsSection = trim(mb_substr($captionsRaw, 0, 1500)) . "\n...\n" . trim(mb_substr($captionsRaw, -1200));
        } else {
            $captionsSection = $captionsRaw;
        }

        $bodyExcerpts = [];
        $bodyPageCount = count($bodyPagesCleaned);
        if ($bodyPageCount > 0) {
            $totalBudget = 6800;
            $usableBudget = max(1200, $totalBudget - ($bodyPageCount * 14));
            $perPageBudget = max(90, (int) floor($usableBudget / $bodyPageCount));

            foreach ($bodyPagesCleaned as $p => $pageText) {
                if (mb_strlen($pageText) <= $perPageBudget) {
                    $bodyExcerpts[] = "[Hal. {$p}] " . $pageText;
                } else {
                    $headBudget = (int) floor($perPageBudget * 0.65);
                    $tailBudget = max(35, $perPageBudget - $headBudget);
                    $slice = trim(mb_substr($pageText, 0, $headBudget))
                        . ' ... '
                        . trim(mb_substr($pageText, -$tailBudget));
                    $bodyExcerpts[] = "[Hal. {$p}] " . $slice;
                }
            }
        }

        $bodySection = Str::limit(implode("\n", $bodyExcerpts), 7600, "\n...[dipotong]");

        $formatted = "========================================\n"
            . "DOKUMEN {$code}: {$def['title']} (Halaman PDF {$startPage} s/d {$endPage})\n"
            . "========================================\n"
            . "--- [A1] DAFTAR ISI {$code} ---\n"
            . ($tocSection !== '' ? $tocSection : '(Tidak terdapat halaman Daftar Isi khusus pada bagian ini)') . "\n\n"
            . "--- [A2] DAFTAR GAMBAR & TABEL {$code} ---\n"
            . ($figTableSection !== '' ? $figTableSection : '(Tidak terdapat halaman Daftar Gambar/Tabel terpisah)') . "\n\n"
            . "--- [B1] DAFTAR BAB & SUB-BAB {$code} ---\n"
            . ($headingsSection !== '' ? $headingsSection : '(Tidak terdeteksi sub-bab bernomor)') . "\n\n"
            . "--- [B2] DAFTAR CAPTION GAMBAR & TABEL {$code} ---\n"
            . ($captionsSection !== '' ? $captionsSection : '(Tidak terdeteksi caption gambar/tabel)') . "\n\n"
            . "--- [C] CUPLIKAN ISI SUBSTANSI PER HALAMAN {$code} ---\n"
            . ($bodySection !== '' ? $bodySection : '(Kosong)');

        return [
            'substantive_char_count' => $substantiveCharCount,
            'formatted_block' => $formatted,
        ];
    }

    /**
     * Strip repeated UDINUS Prodi copyright footers and page counters so digest budget focuses on content.
     */
    protected function stripBoilerplatePageLines(string $rawPageText): string
    {
        if (trim($rawPageText) === '') {
            return '';
        }

        $lines = preg_split('/\R/u', $rawPageText) ?: [];
        $kept = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            if (
                preg_match('/^©\s*20\d{2}\s+Prodi\s+Teknik\s+Elektro/iu', $trimmed)
                || preg_match('/^atas\s+ijin\s+tertulis\s+Prodi\s+Teknik\s+Elektro/iu', $trimmed)
                || preg_match('/^Hal\.\s*\d+\s*dari\s*\d+\s*$/iu', $trimmed)
                || preg_match('/^\d+\s*$/u', $trimmed)
                || preg_match('/^[ivxlcdm]+\s*$/iu', $trimmed)
            ) {
                continue;
            }

            $kept[] = $trimmed;
        }

        return implode("\n", $kept);
    }

    /**
     * Run Qwen structured evaluation of present E100-E500 digests against Buku Pedoman Capstone Design.
     *
     * @param  array<string, string>  $digestsForAi
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function inspectESeriesDigestsWithAi(
        array $digestsForAi,
        string $baseUrl,
        string $model,
        array $context = []
    ): array {
        $presentList = implode(', ', array_keys($digestsForAi));

        $systemPrompt = <<<'PROMPT'
Anda adalah auditor akademik resmi Tugas Akhir Capstone Design Program Studi Sarjana Teknik Elektro Universitas Dian Nuswantoro (UDINUS).
Tugas Anda adalah memeriksa apakah setiap dokumen seri Capstone Design (E100 s.d. E500) yang diberikan telah memuat SELURUH butir substansi wajib sesuai **Buku Pedoman Pelaksanaan Tugas Akhir Capstone Design**.

PRINSIP EVALUASI PENTING (WAJIB DIPATUHI):
1. Evaluasi berdasarkan **SUBSTANSI REKAYASA & KESETARAAN MAKNA**, baik menggunakan istilah Buku Pedoman maupun struktur **Template Word Resmi Prodi Teknik Elektro UDINUS** (contoh: "Profil Perusahaan/Mitra" = Pemangku Kepentingan; "Bab 3 Analisis Aspek Ekonomi/Manufaktur/Keberlanjutan/dll." = Analisis Konstrain; "Bab 3 Pengujian" pada E300 = Verifikasi Desain/Modul; "Tabel Perbandingan/Spesifikasi Komponen" di E300 atau "Rincian Biaya Alat & Bahan" di E100/E300/E500 = BOM; "Analisis/%Error" pada E400 = Evaluasi Kesesuaian/Deviasi Realisasi).
2. Perhatikan daftar `Gambar` dan `Tabel` pada Daftar Gambar/Tabel dan caption halaman! Gambar skematik rangkaian, diagram blok, layout PCB, desain 3D casing, flowchart, dan foto perakitan fisik sering berupa gambar di PDF sehingga dibuktikan dari judul sub-bab, caption `Gambar ...`, dan penjelasan pengantarnya.
3. Tandai suatu butir ke dalam `missing_butir` **HANYA JIKA** butir wajib tersebut **benar-benar tidak ada**, sub-babnya kosong/placeholder, ATAU melanggar syarat minimum eksplisit Pedoman di bawah ini.

RUBRIK BUTIR WAJIB BUKU PEDOMAN CAPSTONE DESIGN (E100 – E500):

1. **E100: Project Proposal (Problem Definition & Concept)**
   - `Pernyataan Masalah (Problem Statement)`: Rumusan permasalahan nyata dari sudut pandang pengguna/mitra (Latar Belakang / Permasalahan).
   - `Bukti Data & Skenario Kasus`: Data pendukung/kuantitatif lapangan dan skenario/kondisi nyata terjadinya masalah.
   - `Identifikasi Pemangku Kepentingan (Stakeholders)`: Profil pengguna, mitra, institusi, atau pihak sasaran produk.
   - `Analisis Konstrain Perekayasaan`: Wajib menganalisis **minimal 3 aspek batasan realistis** (misal: Ekonomi, Manufakturabilitas/Manufaktur, Lingkungan, Keberlanjutan, Kesehatan & Keselamatan Kerja/K3, Etika/Hukum/Standar, Efektivitas/Teknologi/Operasional). *(Jika kurang dari 3 aspek konstrain, masukkan ke `missing_butir`!)*.
   - `Analisis dan Pemilihan Alternatif Solusi`: Wajib mengajukan **minimal 3 alternatif konsep solusi** teknik beserta analisis kelayakan/perbandingan objektif (rubrik/matriks perbandingan/analisis aspek) dan penetapan **Solusi Terpilih**. *(Jika alternatif solusi < 3 atau tidak ada pemilihan solusi terpilih, masukkan ke `missing_butir`!)*.

2. **E200: System Specifications & Requirements**
   - `Deskripsi Fungsional / Abstrak Spesifikasi`: Mendeskripsikan fungsi dan capaian yang harus dipenuhi sistem/alat (Spesifikasi Sistem / Alat / Antarmuka).
   - `Parameter Teknis Kuantitatif & Terukur (Verifiable)`: Memuat parameter teknis berupa angka/besaran terukur dengan satuan jelas (misal V, A, W, mAh, Hz, dB, pH, lux, °C, cm, ms, %, akurasi). *(Jika seluruh spesifikasi hanya kalimat kualitatif tanpa angka/besaran teknis terukur, masukkan ke `missing_butir`!)*.
   - `Ketertelusuran Spesifikasi (Traceable)`: Keterkaitan/alasan rasional spesifikasi terhadap kebutuhan pengguna atau masalah pada E100.
   - `Definisi Spesifikasi Jelas (Non-Ambiguous)`: Kriteria batas kerja/target sistem dinyatakan spesifik.
   - `Rencana Pengujian / Metode Verifikasi Spesifikasi`: Menjelaskan **metode/cara pengujian, instrumen/alat ukur, atau parameter keberhasilan** untuk memverifikasi butir spesifikasi (biasanya pada Bab Verifikasi). *(PENTING: Jika Bab Verifikasi hanya mendeskripsikan pengertian komponen hardware/database tanpa menjelaskan cara/metode pengujian atau parameter verifikasi spesifikasi, maka butir ini BELUM LENGKAP dan WAJIB dimasukkan ke `missing_butir`!)*.

3. **E300: Detailed Design & System Architecture**
   - `Arsitektur Sistem / Diagram Blok`: Konsep sistem dan diagram blok fungsional / interkoneksi antar-modul.
   - `Desain Perangkat Keras (Hardware Design)`: Skematik/diagram elektrik rangkaian, perhitungan/pemilihan teknis komponen, serta desain PCB dan/atau desain mekanik/rancangan fisik alat (casing/enclosure).
   - `Desain Perangkat Lunak / Firmware`: Diagram alir (*flowchart*), diagram status (FSM), atau perancangan perangkat lunak/protokol komunikasi data (*Tingkah Laku Sistem*).
   - `Verifikasi Desain / Simulasi atau Rancangan Pengujian Modul`: Hasil simulasi rangkaian/sistem ATAU rancangan/prosedur pengujian awal komponen/modul (misal *Bab 3 Pengujian* pada E300).
   - `Rincian Spesifikasi Komponen / Bill of Materials (BOM)`: Daftar/tabel spesifikasi komponen pada E300 dan/atau tabel rincian biaya alat & bahan (boleh berada di E300, E100, atau E500).
   - `Rencana Implementasi & Jadwal Kegiatan`: Tabel jadwal kegiatan pelaksanaan / *Gantt Chart* / Kurva-S.

4. **E400: Implementation & Prototyping**
   - `Realisasi & Pengujian Mandiri Subsistem (Bottom-Up)`: Implementasi dan pengujian fungsi tiap blok/subsistem secara bertahap.
   - `Dokumentasi Pabrikasi & Realisasi Fisik`: Bukti/penjelasan dan gambar realisasi fisik perangkat keras, rangkaian/PCB, perakitan, atau integrasi alat.
   - `Implementasi Perangkat Lunak / Firmware & Antarmuka`: Bukti atau uraian bahwa pemrograman mikrokontroler/kendali/pengolahan sinyal/antarmuka (misal pembacaan ADC/sensor pada mikrokontroler, logika kontrol/PWM, tampilan OLED/LCD, komunikasi serial/IoT, atau subsistem website/aplikasi) telah diimplementasikan dan diuji pada subsistem (listing kode sumber mentah tidak wajib dilampirkan di dalam PDF karena diunggah terpisah pada Repository Script).
   - `Evaluasi / Analisis Kesesuaian & Deviasi Realisasi`: Analisis hasil realisasi/pengujian subsistem dibandingkan terhadap rancangan E300 (analisis kesesuaian, *%Error*, kendala/perubahan komponen, atau *S-Diagram*).

5. **E500: Testing, Verification, and Validation**
   - `Pengujian Fungsional Sistem Terintegrasi`: Pengujian fungsi teknis sistem/subsistem menggunakan alat ukur beserta tabel data uji.
   - `Pengujian Batas Kerja / Ketahanan / Kinerja Catu Daya & Operasional`: Pengujian daya tahan catu daya/baterai, operasi durasi kontinu, variasi kondisi/beban kerja, atau batas kerja operasional sistem.
   - `Analisis Kuantitatif / Statistik Data Uji`: Analisis kuantitatif atas tabel hasil pengukuran (rata-rata, galat/*error*, akurasi, persentase keberhasilan, atau ketercapaian terhadap spesifikasi E200).
   - `Uji Validasi Pemangku Kepentingan / Lapangan`: Uji coba implementasi langsung pada pengguna/mitra/objek nyata di lapangan atau evaluasi penilaian pemangku kepentingan untuk membuktikan solusi menjawab masalah E100.

Kembalikan HANYA objek JSON valid dengan format persis:
{
  "documents": {
    "E100": {
      "passed": true,
      "missing_butir": []
    },
    "E200": {
      "passed": false,
      "missing_butir": [
        {
          "butir": "Rencana Pengujian / Metode Verifikasi Spesifikasi",
          "reason": "Bab Verifikasi hanya mendeskripsikan komponen hardware dan database tanpa menjelaskan metode pengujian, alat ukur, maupun kriteria keberhasilan verifikasi spesifikasi"
        }
      ]
    }
  }
}
PROMPT;

        $userPrompt = (! empty($context['title']) ? "Judul Capstone Design: {$context['title']}\n" : '')
            . "Dokumen seri yang terdeteksi di dalam PDF untuk diperiksa: {$presentList}\n\n"
            . "Periksa kelengkapan seluruh butir wajib untuk masing-masing dokumen seri berikut dan kembalikan hasilnya pada key `documents`:\n\n"
            . implode("\n\n", array_values($digestsForAi));

        try {
            $parsed = $this->callOllamaChat($baseUrl, $model, $systemPrompt, $userPrompt);
            $docs = $parsed['documents'] ?? $parsed;
            return is_array($docs) ? $docs : [];
        } catch (\Throwable $e) {
            Log::error('Pemeriksaan kelengkapan E100-E500 via Qwen gagal', [
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException('Pemeriksaan kelengkapan butir E100–E500 via AI gagal: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Resolve reachable Ollama base URL and active Qwen Vision model.
     *
     * @return array{0: string, 1: string}
     */
    protected function resolveOllamaEndpointAndModel(): array
    {
        $configuredBase = rtrim((string) config('services.ollama.base_url', 'http://host.docker.internal:11434'), '/');
        $configuredModel = (string) config('services.ollama.model', 'qwen3.8:27b');

        $candidates = array_values(array_unique(array_filter([
            $configuredBase,
            'http://host.docker.internal:11434',
            'http://127.0.0.1:11434',
        ])));

        foreach ($candidates as $base) {
            try {
                $response = Http::timeout(5)->get($base . '/api/tags');
                if ($response->successful()) {
                    $models = collect($response->json('models', []))
                        ->pluck('name')
                        ->filter()
                        ->values()
                        ->all();

                    $chatModels = array_values(array_filter(
                        $models,
                        fn (string $m) => ! str_contains(strtolower($m), 'embed')
                    ));

                    $selectedModel = in_array($configuredModel, $models, true)
                        ? $configuredModel
                        : ($chatModels[0] ?? $configuredModel);

                    return [$base, $selectedModel];
                }
            } catch (\Throwable) {
                // Try next candidate
            }
        }

        throw new RuntimeException('Layanan AI Qwen (Ollama) sedang tidak dapat dihubungi pada ' . $configuredBase);
    }

    protected function resolveModelName(): string
    {
        return (string) config('services.ollama.model', 'qwen3.8:27b');
    }

    /**
     * Call Ollama /api/chat in JSON mode with optional base64 PNG images.
     *
     * @param  array<int, string>  $imagesBase64
     * @return array<string, mixed>
     */
    protected function callOllamaChat(
        string $baseUrl,
        string $model,
        string $systemPrompt,
        string $userPrompt,
        array $imagesBase64 = []
    ): array {
        $userMessage = [
            'role' => 'user',
            'content' => $userPrompt,
        ];

        if (! empty($imagesBase64)) {
            $userMessage['images'] = array_values($imagesBase64);
        }

        $response = Http::timeout(180)
            ->acceptJson()
            ->post($baseUrl . '/api/chat', [
                'model' => $model,
                'stream' => false,
                'think' => false,
                'format' => 'json',
                'options' => [
                    'temperature' => 0.1,
                ],
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    $userMessage,
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                sprintf('Ollama merespons dengan HTTP %d: %s', $response->status(), Str::limit($response->body(), 250))
            );
        }

        $rawContent = (string) ($response->json('message.content') ?? '');

        return $this->parseJsonObject($rawContent);
    }

    /**
     * Resilient JSON object extractor.
     *
     * @return array<string, mixed>
     */
    protected function parseJsonObject(string $raw): array
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            throw new RuntimeException('Respons JSON dari AI kosong.');
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/iu', $trimmed, $m)) {
            $decoded = json_decode(trim($m[1]), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $firstBrace = strpos($trimmed, '{');
        $lastBrace = strrpos($trimmed, '}');
        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            $slice = substr($trimmed, $firstBrace, $lastBrace - $firstBrace + 1);
            $decoded = json_decode($slice, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new RuntimeException('Gagal mengurai format JSON dari respons AI.');
    }
}
