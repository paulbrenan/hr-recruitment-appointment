<?php
/**
 * Diagnostic only -- no project changes.
 * Runs Tesseract on a PDF (via pdftoppm first) and dumps the raw OCR
 * text to ocr_dump.txt, then shows the content around row numbers 1-15
 * to see exactly where the parser is losing track.
 *
 * Usage:
 *   php diagnose_ocr_output.php "C:\Users\Aj Bernal\Downloads\SGOD-2026-DM-0079.pdf"
 */

if ($argc < 2) {
    fwrite(STDERR, "Usage: php diagnose_ocr_output.php <path-to-pdf>\n");
    exit(1);
}

$pdfPath = realpath($argv[1]);
if (!$pdfPath || !file_exists($pdfPath)) {
    fwrite(STDERR, "ERROR: File not found: {$argv[1]}\n");
    exit(1);
}

$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hr_ocr_diag_' . uniqid();
mkdir($tmpDir, 0755, true);
echo "Working in temp dir: $tmpDir\n";

// 1. pdftoppm
$imagePrefix = $tmpDir . DIRECTORY_SEPARATOR . 'page';
$cmd = sprintf(
    '"C:\\poppler\\Library\\bin\\pdftoppm.exe" -r 150 -png %s %s 2>&1',
    escapeshellarg($pdfPath),
    escapeshellarg($imagePrefix)
);
echo "Running pdftoppm...\n";
shell_exec($cmd);

$imageFiles = glob($tmpDir . DIRECTORY_SEPARATOR . 'page-*.png');
if (empty($imageFiles)) {
    fwrite(STDERR, "ERROR: pdftoppm produced no images.\n");
    exit(1);
}
natsort($imageFiles);
$imageFiles = array_values($imageFiles);
echo "Got " . count($imageFiles) . " page image(s).\n";

// 2. Tesseract on each page
$allText = '';
foreach ($imageFiles as $index => $imagePath) {
    $outBase = $tmpDir . DIRECTORY_SEPARATOR . 'ocr_page_' . ($index + 1);
    $cmd = sprintf(
        '"C:\\Program Files\\Tesseract-OCR\\tesseract.exe" %s %s -l eng --oem 1 --psm 6 2>&1',
        escapeshellarg($imagePath),
        escapeshellarg($outBase)
    );
    shell_exec($cmd);
    $txt = file_exists($outBase . '.txt') ? file_get_contents($outBase . '.txt') : '';
    $allText .= "\n===PAGE " . ($index + 1) . "===\n" . $txt;
}

// 3. Save dump
$outFile = __DIR__ . DIRECTORY_SEPARATOR . 'ocr_dump.txt';
file_put_contents($outFile, $allText);
echo "\nSaved full OCR text to: $outFile\n";
echo "Total length: " . strlen($allText) . " chars\n\n";

// 4. Show where row numbers 1-15 appear (or don't)
echo "--- Row number detection (same pattern VacancyTableParser uses) ---\n\n";
for ($n = 1; $n <= 15; $n++) {
    $pattern = '/(?<![\d.\-])' . $n . '(?=\s*[_|=(\s])/';
    if (preg_match($pattern, $allText, $m, PREG_OFFSET_CAPTURE)) {
        $pos = $m[0][1];
        $snippet = substr($allText, max(0, $pos - 10), 100);
        $snippet = str_replace(["\r", "\n"], ['[LF]', '[CR]'], $snippet);
        echo "Row $n at offset $pos: ...{$snippet}...\n";
    } else {
        echo "Row $n: NOT FOUND\n";
    }
}

// 5. Cleanup
$items = array_diff(scandir($tmpDir), ['.', '..']);
foreach ($items as $item) {
    $path = $tmpDir . DIRECTORY_SEPARATOR . $item;
    is_dir($path) ? null : unlink($path);
}
rmdir($tmpDir);

echo "\nDone. Upload ocr_dump.txt so we can see the exact OCR text.\n";
