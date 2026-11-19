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
     * Check a Capstone PDF document for completeness:
     * 1. Approval / validation signatures (TTD Halaman Pengesahan / Persetujuan / Dokumentasi Produk TA)
     * 2. Table of Contents completeness (Daftar Isi terisi semua nomor halamannya)
     *
     * @param  string  $absolutePdfPath
     * @param  array<string, mixed>  $context
     * @return array{
     *     passed: bool,
     *     summary: string,
     *     missing_items: array<int, string>,
     *     toc_check: array<string, mixed>,
     *     signature_check: array<string, mixed>,
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

            $missingItems = array_values(array_unique(array_merge(
                $signatureCheck['issues'],
                $tocCheck['issues']
            )));

            $passed = count($missingItems) === 0;

            $summary = $passed
                ? 'Dokumen PDF lengkap: tanda tangan pengesahan/persetujuan dan nomor halaman Daftar Isi telah terisi.'
                : sprintf('Dokumen ditahan karena ditemukan %d item kelengkapan yang belum terpenuhi. Wajib unggah ulang PDF yang sudah diperbaiki.', count($missingItems));

            return [
                'passed' => $passed,
                'summary' => $summary,
                'missing_items' => $missingItems,
                'toc_check' => $tocCheck,
                'signature_check' => $signatureCheck,
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

Kembalikan HANYA objek JSON dengan struktur persis:
{
  "pages": [
    {
      "pdf_page": 5,
      "page_type": "persetujuan",
      "page_label": "Halaman Persetujuan",
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
