<?php
/**
 * One-shot patch v3: skip OCR entirely when the PDF already has a text layer.
 *
 * Many DepEd vacancy PDFs are exported directly from Word and already contain
 * selectable text. For those, pdftotext extracts everything in well under a
 * second. We only fall back to the slow pdftoppm -> Tesseract OCR pipeline
 * (steps 2-3) when pdftotext comes back empty/too short, i.e. the PDF is a
 * scanned image with no text layer.
 *
 * This patch:
 *  1. Backs up app/Http/Controllers/JobPostingImportController.php
 *  2. Inserts a new "1.5: try pdftotext first" step after the upload-save step
 *  3. Wraps the existing pdftoppm + Tesseract block (steps 2-3) in an
 *     `if (!$haveDirectText)` so it's skipped when direct text extraction works
 *
 * Run from your Laravel project root:
 *   php patch_pdftotext_shortcut.php
 *
 * Requires pdftotext.exe to exist alongside your existing pdftoppm.exe
 * (same poppler install: C:\poppler\Library\bin\pdftotext.exe). If it's not
 * there, this patch's fast path will simply never trigger and everything
 * falls back to OCR as before -- nothing breaks.
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

// ── Anchor: right after the file is moved into the tmp dir ─────────────
$anchor = "\$request->file('pdf_file')->move(\$tmpDir, 'input.pdf');";

if (strpos($contents, $anchor) === false) {
    fwrite(STDERR, "ERROR: Could not find the upload-move line to anchor the patch on.\n");
    fwrite(STDERR, "No changes written. Restore from backup if needed:\n  cp \"$backupPath\" \"$target\"\n");
    exit(1);
}

$step1point5 = <<<'PHP'


        // ── 1.5. Try direct text extraction first (near-instant) ──────────────
        // Many vacancy PDFs are exported straight from Word and already have a
        // text layer -- no OCR needed at all for those. We only fall through to
        // the slow pdftoppm + Tesseract pipeline below if this comes back empty,
        // which means the PDF is a scanned image with no text layer.
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
            // Drop a possible trailing empty page from the final \f
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
PHP;

$contents = str_replace($anchor, $anchor . $step1point5, $contents, $count1);

if ($count1 !== 1) {
    fwrite(STDERR, "ERROR: anchor replacement for step 1.5 failed unexpectedly.\n");
    exit(1);
}
echo "Applied: inserted pdftotext-first shortcut (step 1.5)\n";

// ── Now close the `if (!$haveDirectText) {` block right before step 4 ──
$pattern = '/(\s*)(\/\/ ── 4\. Clean up all temp files)/';

$count2 = 0;
$contents = preg_replace_callback($pattern, function ($m) {
    return "\n        }\n" . $m[1] . $m[2];
}, $contents, 1, $count2);

if ($count2 !== 1) {
    fwrite(STDERR, "ERROR: Could not find '// ── 4. Clean up all temp files' marker to close the if-block.\n");
    fwrite(STDERR, "Restore from backup:\n  cp \"$backupPath\" \"$target\"\n");
    exit(1);
}
echo "Applied: closed OCR fallback block before step 4 (cleanup)\n";

// ── Sanity check: brace balance ─────────────────────────────────────────
$openBraces  = substr_count($contents, '{');
$closeBraces = substr_count($contents, '}');

if ($openBraces !== $closeBraces) {
    fwrite(STDERR, "ERROR: Brace mismatch after patching ({$openBraces} open vs {$closeBraces} close).\n");
    fwrite(STDERR, "Not writing the file. Restore from backup if it was partially written:\n  cp \"$backupPath\" \"$target\"\n");
    exit(1);
}

if ($contents === $original) {
    echo "No changes were applied. Nothing written.\n";
    exit(0);
}

file_put_contents($target, $contents);
echo "\nDone. Patched: $target\n";
echo "Backup kept at: $backupPath\n\n";

echo "IMPORTANT: open the file and check indentation/structure around step 1.5\n";
echo "and the existing OCR block -- the patch wraps your current Tesseract code\n";
echo "in an `if (!\$haveDirectText) { ... }` block, but does not re-indent it.\n";
echo "It will still run correctly even if indentation looks slightly off.\n\n";

echo "Next steps:\n";
echo "1. Confirm pdftotext.exe exists at C:\\poppler\\Library\\bin\\pdftotext.exe\n";
echo "   (it ships alongside pdftoppm.exe in the same poppler install).\n";
echo "2. Upload a digitally-generated (non-scanned) vacancy PDF -- it should now\n";
echo "   import in well under a second instead of ~45s.\n";
echo "3. Upload a scanned PDF too, to confirm it still falls back to OCR correctly.\n";
echo "4. If anything looks broken, restore the backup:\n";
echo "   cp \"$backupPath\" \"$target\"\n";
