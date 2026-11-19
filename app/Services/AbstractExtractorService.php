<?php

namespace App\Services;

use App\Models\CapstoneProject;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class AbstractExtractorService
{
    /**
     * Resolve and cache rendered PNG pages containing ONLY the Abstract / Intisari of a Capstone PDF.
     *
     * @return array<int, string> Ordered list of absolute PNG file paths (0-indexed)
     */
    public function getAbstractPageImages(CapstoneProject $project): array
    {
        if (! $project->document_path || ! Storage::disk('public')->exists($project->document_path)) {
            return [];
        }

        $pdfPath = Storage::disk('public')->path($project->document_path);
        if (! is_file($pdfPath) || filesize($pdfPath) === 0) {
            return [];
        }

        $mtime = (int) @filemtime($pdfPath);
        $cacheKey = 'project_' . $project->id . '_' . substr(md5($project->document_path . '|' . $mtime), 0, 10);
        $cacheDir = storage_path('app/private/abstracts/' . $cacheKey);

        if (is_dir($cacheDir)) {
            $existing = glob($cacheDir . DIRECTORY_SEPARATOR . 'abstract_*.png') ?: [];
            sort($existing, SORT_NATURAL);
            if (! empty($existing)) {
                return array_values($existing);
            }
        }

        File::ensureDirectoryExists($cacheDir);

        $pageNums = $this->detectAbstractPageNumbers($pdfPath);
        $rendered = [];

        foreach (array_values($pageNums) as $idx => $pageNum) {
            $outPrefix = $cacheDir . DIRECTORY_SEPARATOR . sprintf('abstract_%02d', $idx + 1);
            $proc = new Process([
                'pdftoppm',
                '-png',
                '-r',
                '140',
                '-f',
                (string) $pageNum,
                '-l',
                (string) $pageNum,
                '-singlefile',
                $pdfPath,
                $outPrefix,
            ]);
            $proc->setTimeout(30);
            $proc->run();

            $pngFile = $outPrefix . '.png';
            if ($proc->isSuccessful() && is_file($pngFile)) {
                $rendered[] = $pngFile;
            }
        }

        return $rendered;
    }

    /**
     * Detect 1-indexed PDF page numbers for INTISARI / ABSTRAK / ABSTRACT.
     *
     * @return array<int, int>
     */
    public function detectAbstractPageNumbers(string $pdfPath): array
    {
        $infoProc = new Process(['pdfinfo', $pdfPath]);
        $infoProc->setTimeout(15);
        $infoProc->run();

        $totalPages = 1;
        if ($infoProc->isSuccessful() && preg_match('/^Pages:\s+(\d+)/mi', $infoProc->getOutput(), $m)) {
            $totalPages = max(1, (int) $m[1]);
        }

        $scanLimit = min(25, $totalPages);
        $txtProc = new Process(['pdftotext', '-f', '1', '-l', (string) $scanLimit, $pdfPath, '-']);
        $txtProc->setTimeout(30);
        $txtProc->run();

        if (! $txtProc->isSuccessful()) {
            return [1];
        }

        $chunks = explode("\f", $txtProc->getOutput());
        $abstractPages = [];

        for ($p = 1; $p <= $scanLimit; $p++) {
            $text = $chunks[$p - 1] ?? '';
            $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: [])));
            $topLines = implode("\n", array_slice($lines, 0, 8));

            // Exclude TOC pages
            if (preg_match('/^\s*(DAFTAR\s+ISI|TABLE\s+OF\s+CONTENTS)\b/imu', $topLines)) {
                continue;
            }

            if (preg_match('/^\s*(INTISARI|ABSTRAK|ABSTRACT|RINGKASAN)\s*$/imu', $topLines)) {
                $abstractPages[] = $p;

                // Check if abstract spills onto immediate next page (e.g. ending with Kata kunci / Keywords on p+1)
                if ($p + 1 <= $scanLimit) {
                    $nextText = $chunks[$p] ?? '';
                    $nextLines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $nextText) ?: [])));
                    $nextTop = implode("\n", array_slice($nextLines, 0, 6));

                    $startsOtherSection = (bool) preg_match(
                        '/^\s*(INTISARI|ABSTRAK|ABSTRACT|KATA\s+PENGANTAR|HALAMAN|LEMBAR|PERNYATAAN|DAFTAR\s+ISI|BAB\s+[IVX1-9]+)\b/imu',
                        $nextTop
                    );

                    $currentHasKeywords = (bool) preg_match('/\b(Kata\s*kunci|Keywords?)\s*:/iu', $text);
                    $nextHasKeywords = (bool) preg_match('/\b(Kata\s*kunci|Keywords?)\s*:/iu', $nextText);

                    if (! $startsOtherSection && ! $currentHasKeywords && $nextHasKeywords) {
                        $abstractPages[] = $p + 1;
                    }
                }
            }
        }

        $abstractPages = array_values(array_unique($abstractPages));
        sort($abstractPages);

        if (empty($abstractPages)) {
            return [1];
        }

        return array_slice($abstractPages, 0, 4);
    }
}
