<?php
/**
 * test_pdf_extract.php
 * Quick diagnostic — tests raw text extraction from a PDF.
 * Usage: php test_pdf_extract.php yourfile.pdf
 * Drop in project root, run once, delete after.
 */

require __DIR__ . '/vendor/autoload.php';

$file = $argv[1] ?? null;

if (!$file) {
    die("Usage: php test_pdf_extract.php yourfile.pdf\n");
}

if (!file_exists($file)) {
    die("ERROR: File not found: $file\n");
}

echo "Parsing: $file\n";
echo str_repeat('-', 60) . "\n";

try {
    $parser = new \Smalot\PdfParser\Parser();
    $pdf    = $parser->parseFile($file);
    $pages  = $pdf->getPages();

    echo "Total pages detected: " . count($pages) . "\n\n";

    foreach ($pages as $i => $page) {
        $text = $page->getText();
        $len  = strlen(trim($text));
        echo "=== PAGE " . ($i + 1) . " (trimmed length: $len chars) ===\n";
        if ($len === 0) {
            echo "[EMPTY — no text extracted from this page]\n";
        } else {
            // Print first 800 chars so we can see structure without flooding terminal
            echo substr($text, 0, 800);
            if (strlen($text) > 800) {
                echo "\n... [truncated, " . strlen($text) . " chars total]\n";
            }
        }
        echo "\n\n";
    }

    // Also dump the whole-document getText() for comparison
    echo str_repeat('=', 60) . "\n";
    echo "WHOLE DOCUMENT getText() (first 1000 chars):\n";
    echo str_repeat('=', 60) . "\n";
    $all = $pdf->getText();
    echo strlen(trim($all)) === 0
        ? "[EMPTY — whole-document extraction also blank]\n"
        : substr($all, 0, 1000) . (strlen($all) > 1000 ? "\n... [truncated]\n" : "");

} catch (\Exception $e) {
    echo "PARSER EXCEPTION: " . $e->getMessage() . "\n";
}
