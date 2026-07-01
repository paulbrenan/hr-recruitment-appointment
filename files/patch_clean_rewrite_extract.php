<?php
/**
 * One-shot patch v4: clean rewrite of extract() method.
 *
 * The file had accumulated some mess from manual copy-paste across earlier
 * patches: a dead duplicate pdftotext call sitting inside the OCR fallback
 * block (doing nothing), and the DPI never actually got changed to 150.
 *
 * This patch replaces the ENTIRE extract() method body with one clean,
 * correct version:
 *   - pdftotext-first shortcut (skips OCR for digitally-generated PDFs)
 *   - pdftoppm at 150 DPI (only runs when OCR is actually needed)
 *   - Tesseract run in parallel across pages, with --oem 1 --psm 6 flags
 *     (faster + more reliable than default engine on typed documents)
 *   - proper pipe draining so tesseract.exe can't stall on full buffers
 *
 * Run from your Laravel project root:
 *   php patch_clean_rewrite_extract.php
 */

$target = __DIR__ . '/app/Http/Controllers/JobPostingImportController.php';

if (!file_exists($target)) {
    fwrite(STDERR, "ERROR: Could not find $target\nRun this script from your Laravel project root.\n");
    exit(1);
}

$contents = file_get_contents($target);
$original = $contents;

// ── Backup ──────────────────────────────────────────────────────────────
$backupPath = $target . '.bak_' . date('Ymd_His');
if (!copy($target, $backupPath)) {
    fwrite(STDERR, "ERROR: Could not create backup at $backupPath\n");
    exit(1);
}
echo "Backed up original to: $backupPath\n";

// ── Match the whole extract() method, from its doc comment marker
//    through the closing brace right before the next method's comment. ──
$pattern = '/\/\/ ── OCR extraction \+ parsing pipeline.*?(?=\n    \/\/ ── Review screen)/s';

$newMethod = <<<'PHP'
    // ── OCR extraction + parsing pipeline ─────────────────────────────────────
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

        $pdfPath      = $tmpDir . DIRECTORY_SEPARATOR . 'input.pdf';
        $originalName = $request->file('pdf_file')->getClientOriginalName();
        $request->file('pdf_file')->move($tmpDir, 'input.pdf');

        // ── 1.5. Try direct text extraction first (near-instant) ──────────────
        // Many vacancy PDFs are exported straight from Word and already have a
        // text layer -- no OCR needed at all for those. We only fall through to
        // the slower pdftoppm + Tesseract pipeline below if this comes back
        // empty, meaning the PDF is a scanned image with no text layer.
        $pageTexts = [];
        $haveDirectText = false;

        $pdftotextCmd = sprintf(
            '"C:\\poppler\\Library\\bin\\pdftotext.exe" -layout %s - 2>&1',
            escapeshellarg($pdfPath)
        );
        $directText = shell_exec($pdftotextCmd);

        if ($directText !== null && strlen(trim($directText)) > 200) {
            // pdftotext separates pages with a form-feed character (\f)
            $pages = explode("\f", $directText);
            if (count($pages) > 1 && trim(end($pages)) === '') {
                array_pop($pages);
            }

            foreach ($pages as $i => $pageText) {
                $pageTexts[] = [
                    'number' => $i + 1,
                    'text'   => $pageText,
                ];
            }

            $haveDirectText = true;
        }

        if (!$haveDirectText) {
            // ── 2. Convert PDF pages to PNG images via pdftoppm ────────────────
            $imagePrefix = $tmpDir . DIRECTORY_SEPARATOR . 'page';

            $pdftoppmCmd = sprintf(
                '"C:\\poppler\\Library\\bin\\pdftoppm.exe" -r 150 -png %s %s 2>&1',
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

            natsort($imageFiles);
            $imageFiles = array_values($imageFiles);

            // ── 3. Run Tesseract on each page image IN PARALLEL ────────────────
            // --oem 1 = LSTM engine (accurate), --psm 6 = assume a single
            // uniform block of text, which is faster and more reliable than
            // Tesseract's default auto page-segmentation on typed documents.
            $processes = [];

            foreach ($imageFiles as $index => $imagePath) {
                $outBase = $tmpDir . DIRECTORY_SEPARATOR . 'ocr_page_' . ($index + 1);

                $tesseractCmd = sprintf(
                    '"C:\\Program Files\\Tesseract-OCR\\tesseract.exe" %s %s -l eng --oem 1 --psm 6',
                    escapeshellarg($imagePath),
                    escapeshellarg($outBase)
                );

                $descriptorSpec = [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ];

                $proc = proc_open($tesseractCmd, $descriptorSpec, $pipes);

                if (is_resource($proc)) {
                    fclose($pipes[0]);
                    $processes[$index] = [
                        'proc'    => $proc,
                        'pipes'   => $pipes,
                        'outBase' => $outBase,
                    ];
                }
            }

            $ocrPageTexts = [];

            foreach ($processes as $index => $p) {
                stream_get_contents($p['pipes'][1]);
                stream_get_contents($p['pipes'][2]);
                fclose($p['pipes'][1]);
                fclose($p['pipes'][2]);
                proc_close($p['proc']);

                $txtFile = $p['outBase'] . '.txt';
                $text    = file_exists($txtFile) ? file_get_contents($txtFile) : '';

                $ocrPageTexts[$index] = [
                    'number' => $index + 1,
                    'text'   => $text,
                ];
            }

            ksort($ocrPageTexts);
            $pageTexts = array_values($ocrPageTexts);
        }

        // ── 4. Clean up all temp files ────────────────────────────────────────
        $this->cleanupTmp($tmpDir);

        // ── 5. Parse OCR'd text into structured position blocks ──────────────
        $detector = new PositionBlockDetector(config('job_titles.titles', []));
        $blocks = $detector->detect($pageTexts);

        if (empty($blocks)) {
            return back()->withErrors([
                'pdf_file' => 'No recognizable position headings were found in this PDF. '
                            . 'It may not be a vacancy announcement in the expected format, '
                            . 'or OCR quality was too poor to detect headings.',
            ]);
        }

        // ── 6. Expand each block into flat per-row candidates ─────────────────
        $expander = new PositionBlockExpander();
        $candidates = $expander->expand($blocks);

        // ── 7. Store as a temporary batch for the review screen ───────────────
        $batch = PdfImportBatch::create([
            'original_filename' => $originalName,
            'candidates' => $candidates,
            'expires_at' => now()->addDay(),
        ]);

        return redirect()->route('job-postings.import.review', $batch->id);
    }

PHP;

$count = 0;
$contents = preg_replace($pattern, $newMethod, $contents, 1, $count);

if ($count !== 1) {
    fwrite(STDERR, "ERROR: Could not match the extract() method boundaries.\n");
    fwrite(STDERR, "No changes written. Restore from backup if needed:\n  cp \"$backupPath\" \"$target\"\n");
    exit(1);
}

$openBraces  = substr_count($contents, '{');
$closeBraces = substr_count($contents, '}');
if ($openBraces !== $closeBraces) {
    fwrite(STDERR, "ERROR: Brace mismatch after patching ({$openBraces} open vs {$closeBraces} close). Not writing file.\n");
    exit(1);
}

if ($contents === $original) {
    echo "No changes were applied. Nothing written.\n";
    exit(0);
}

file_put_contents($target, $contents);
echo "\nDone. Cleanly rewrote extract(). Patched: $target\n";
echo "Backup kept at: $backupPath\n\n";

echo "What changed vs before:\n";
echo "  - Removed the dead duplicate pdftotext call inside the OCR fallback block\n";
echo "  - DPI is now actually 150 (was still 200 due to a previous patch not landing)\n";
echo "  - Tesseract now runs with --oem 1 --psm 6 (faster + more consistent on typed docs)\n";
echo "  - Pages OCR in parallel with proper pipe draining\n\n";

echo "Next steps:\n";
echo "1. Upload the SAME scanned PDF that took ~42s and compare timing.\n";
echo "2. If text quality drops noticeably, remove '--oem 1 --psm 6' and retest\n";
echo "   (some documents OCR better with Tesseract's default settings).\n";
echo "3. Restore backup if needed:\n";
echo "   cp \"$backupPath\" \"$target\"\n";
