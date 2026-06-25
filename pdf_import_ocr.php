<?php
/**
 * pdf_import_ocr.php
 * Replaces the extract() method in JobPostingImportController with an OCR
 * pipeline: pdftoppm (PDF→images) + tesseract (images→text).
 * Drop in project root, run once: php pdf_import_ocr.php
 * Delete after confirming the import page works.
 */

$controllerPath = __DIR__ . '/app/Http/Controllers/JobPostingImportController.php';

if (!file_exists($controllerPath)) {
    die("ERROR: Cannot find JobPostingImportController.php\n");
}

$original = file_get_contents($controllerPath);

// ── Backup ────────────────────────────────────────────────────────────────────
$bak = $controllerPath . '.bak';
$i   = 2;
while (file_exists($bak)) { $bak = $controllerPath . '.bak' . $i++; }
file_put_contents($bak, $original);
echo "Backed up to: $bak\n";

// ── apply_patch ───────────────────────────────────────────────────────────────
function apply_patch(string &$src, string $find, string $replace, string $label): void {
    $count = substr_count($src, $find);
    if ($count === 0) { die("ERROR [$label]: Target string not found — aborting, no changes written.\n"); }
    if ($count  > 1) { die("ERROR [$label]: Found $count matches (expected 1) — aborting.\n"); }
    $src = str_replace($find, $replace, $src);
    echo "OK [$label]\n";
}

// ── New controller content ────────────────────────────────────────────────────
// We replace the entire class body so there's no fragile method-level patching.

$newController = <<<'CONTROLLER'
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class JobPostingImportController extends Controller
{
    // ── Upload form ───────────────────────────────────────────────────────────
    public function create()
    {
        return view('job-postings.import.upload');
    }

    // ── OCR extraction pipeline ───────────────────────────────────────────────
    public function extract(Request $request)
    {
        $request->validate([
            'pdf_file' => 'required|file|mimes:pdf|max:20480',
        ]);

        // ── 1. Save uploaded PDF to a dedicated temp directory ────────────────
        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hr_ocr_' . uniqid();
        if (!mkdir($tmpDir, 0755, true)) {
            return back()->withErrors(['pdf_file' => 'Could not create temporary directory for processing.']);
        }

        $pdfPath = $tmpDir . DIRECTORY_SEPARATOR . 'input.pdf';
        $request->file('pdf_file')->move($tmpDir, 'input.pdf');

        // ── 2. Convert PDF pages to PNG images via pdftoppm ──────────────────
        //    Output files will be named: page-1.png, page-2.png, etc.
        $imagePrefix = $tmpDir . DIRECTORY_SEPARATOR . 'page';

        // -r 200  → 200 DPI (good OCR quality without being huge)
        // -png    → PNG output
        $pdftoppmCmd = sprintf(
            'pdftoppm -r 200 -png %s %s 2>&1',
            escapeshellarg($pdfPath),
            escapeshellarg($imagePrefix)
        );

        $pdftoppmOutput = shell_exec($pdftoppmCmd);
        $imageFiles     = glob($tmpDir . DIRECTORY_SEPARATOR . 'page-*.png');

        if (empty($imageFiles)) {
            $this->cleanupTmp($tmpDir);
            return back()->withErrors([
                'pdf_file' => 'pdftoppm could not convert the PDF to images. '
                            . 'pdftoppm output: ' . ($pdftoppmOutput ?: '(none)'),
            ]);
        }

        // Sort by page number (glob order may not be numeric)
        natsort($imageFiles);
        $imageFiles = array_values($imageFiles);

        // ── 3. Run Tesseract on each page image ───────────────────────────────
        $pages = [];

        foreach ($imageFiles as $index => $imagePath) {
            $outBase = $tmpDir . DIRECTORY_SEPARATOR . 'ocr_page_' . ($index + 1);

            // tesseract writes to outBase.txt automatically
            $tesseractCmd = sprintf(
                'tesseract %s %s -l eng 2>&1',
                escapeshellarg($imagePath),
                escapeshellarg($outBase)
            );

            shell_exec($tesseractCmd);

            $txtFile = $outBase . '.txt';
            $text    = file_exists($txtFile) ? file_get_contents($txtFile) : '';

            $pages[] = [
                'number' => $index + 1,
                'text'   => $text,
            ];
        }

        // ── 4. Clean up all temp files ────────────────────────────────────────
        $this->cleanupTmp($tmpDir);

        // ── 5. Pass to view ───────────────────────────────────────────────────
        return view('job-postings.import.extracted', compact('pages'));
    }

    // ── Helper: recursively delete temp directory ─────────────────────────────
    private function cleanupTmp(string $dir): void
    {
        if (!is_dir($dir)) return;

        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->cleanupTmp($path) : unlink($path);
        }
        rmdir($dir);
    }
}
CONTROLLER;

// ── Write ─────────────────────────────────────────────────────────────────────
file_put_contents($controllerPath, $newController);
echo "OK [replaced JobPostingImportController with OCR pipeline]\n";

echo "\nDone. No migration needed.\n";
echo "Visit /job-postings/import, upload a sample PDF, and check the extracted text per page.\n";
echo "Delete this script when confirmed working.\n";
