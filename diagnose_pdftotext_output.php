<?php
/**
 * Diagnostic only -- makes no changes to your project.
 * Dumps the raw pdftotext -layout output for a given PDF to a text file
 * so we can see exactly how the vacancy table is being rendered, to debug
 * why VacancyTableParser stops at row 6.
 *
 * Usage:
 *   php diagnose_pdftotext_output.php "C:\path\to\SGOD-2026-DM-0079.pdf"
 */

if ($argc < 2) {
    fwrite(STDERR, "Usage: php diagnose_pdftotext_output.php <path-to-pdf>\n");
    exit(1);
}

$pdfPath = $argv[1];

if (!file_exists($pdfPath)) {
    fwrite(STDERR, "ERROR: File not found: $pdfPath\n");
    exit(1);
}

$cmd = sprintf(
    '"C:\\poppler\\Library\\bin\\pdftotext.exe" -layout %s - 2>&1',
    escapeshellarg($pdfPath)
);

$output = shell_exec($cmd);

$outFile = __DIR__ . DIRECTORY_SEPARATOR . 'pdftotext_dump.txt';
file_put_contents($outFile, $output);

echo "Dumped pdftotext output to: $outFile\n";
echo "Total length: " . strlen($output) . " characters\n\n";

echo "--- Searching for rows 1-15 to inspect formatting around the break point ---\n\n";

// Print a slice around where rows 1-15 should appear, to eyeball the raw formatting
foreach (range(1, 15) as $n) {
    if (preg_match('/(?<![\d.\-])' . $n . '(?=\s*[_|=(\s])/', $output, $m, PREG_OFFSET_CAPTURE)) {
        $pos = $m[0][1];
        $snippet = substr($output, $pos, 80);
        $snippet = str_replace(["\r", "\n"], ['\\r', '\\n'], $snippet);
        echo "Row $n found at offset $pos: " . $snippet . "\n";
    } else {
        echo "Row $n: NOT FOUND by the matching pattern\n";
    }
}

echo "\nPlease upload pdftotext_dump.txt back so we can see the exact formatting.\n";
