<?php
/**
 * debug_dump_table_text.php
 *
 * Standalone diagnostic — NOT a patch. Mirrors ProcessPdfImportJob's exact
 * extraction pipeline (same pdftotext/pdftoppm/tesseract commands, same
 * DPI) so we can see EXACTLY what raw text is available for a given
 * position block, before VacancyTableParser ever touches it.
 *
 * Use this to answer: "is row 41's content actually in the OCR text at
 * all, or is the text there but the parser is missing it?"
 *
 * Usage (run from your Laravel project root):
 *   php debug_dump_table_text.php "C:\path\to\the.pdf" "PROJECT DEVELOPMENT OFFICER"
 *
 * The second argument is a case-insensitive substring to find the right
 * position heading (doesn't need to be exact/full).
 *
 * Output:
 *   - Prints the LAST ~1500 characters of that block's raw text (i.e. the
 *     tail end of the table, right up to wherever extraction stopped) so
 *     you can see with your own eyes whether "41" and its school name are
 *     in there.
 *   - Prints every standalone occurrence of "41" found anywhere in the
 *     block, with 80 characters of context around each.
 *   - Saves the FULL block text to debug_block_dump.txt next to this
 *     script for closer inspection / to paste back.
 */

if ($argc < 3) {
    fwrite(STDERR, "Usage: php debug_dump_table_text.php <path-to-pdf> <heading-substring>\n");
    fwrite(STDERR, "Example: php debug_dump_table_text.php \"C:\\uploads\\vacancy.pdf\" \"PROJECT DEVELOPMENT OFFICER\"\n");
    exit(1);
}

$pdfPath = $argv[1];
$headingSearch = $argv[2];

if (!file_exists($pdfPath)) {
    fwrite(STDERR, "ERROR: PDF not found at $pdfPath\n");
    exit(1);
}

$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'debug_dump_' . uniqid();
mkdir($tmpDir, 0777, true);
echo "Working dir: $tmpDir\n";

// ── Same extraction pipeline as ProcessPdfImportJob::extractPageTexts() ──

function extractPageTexts(string $pdfPath, string $tmpDir): array
{
    $pdftotextCmd = sprintf(
        '"C:\\poppler\\Library\\bin\\pdftotext.exe" -layout %s - 2>&1',
        escapeshellarg($pdfPath)
    );
    $directText = shell_exec($pdftotextCmd);

    if ($directText !== null && strlen(trim($directText)) > 200) {
        echo "Extraction method: pdftotext (digital text layer)\n";
        $pages = explode("\f", $directText);
        if (count($pages) > 1 && trim(end($pages)) === '') {
            array_pop($pages);
        }
        $pageTexts = [];
        foreach ($pages as $i => $pageText) {
            $pageTexts[] = ['number' => $i + 1, 'text' => $pageText];
        }
        return $pageTexts;
    }

    echo "Extraction method: pdftoppm + Tesseract OCR (300 DPI)\n";
    $imagePrefix = $tmpDir . DIRECTORY_SEPARATOR . 'page';
    $pdftoppmCmd = sprintf(
        '"C:\\poppler\\Library\\bin\\pdftoppm.exe" -r 300 -png %s %s 2>&1',
        escapeshellarg($pdfPath),
        escapeshellarg($imagePrefix)
    );
    shell_exec($pdftoppmCmd);

    $imageFiles = glob($tmpDir . DIRECTORY_SEPARATOR . 'page-*.png');
    if (empty($imageFiles)) {
        throw new \RuntimeException('pdftoppm could not convert the PDF to images.');
    }
    natsort($imageFiles);
    $imageFiles = array_values($imageFiles);

    $processes = [];
    foreach ($imageFiles as $index => $imagePath) {
        $outBase = $tmpDir . DIRECTORY_SEPARATOR . 'ocr_page_' . ($index + 1);
        $tesseractCmd = sprintf(
            '"C:\\Program Files\\Tesseract-OCR\\tesseract.exe" %s %s -l eng --oem 1 --psm 6',
            escapeshellarg($imagePath),
            escapeshellarg($outBase)
        );
        $descriptorSpec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($tesseractCmd, $descriptorSpec, $pipes);
        if (is_resource($proc)) {
            fclose($pipes[0]);
            $processes[$index] = ['proc' => $proc, 'pipes' => $pipes, 'outBase' => $outBase];
        }
    }

    $pageTexts = [];
    foreach ($processes as $index => $p) {
        stream_get_contents($p['pipes'][1]);
        stream_get_contents($p['pipes'][2]);
        fclose($p['pipes'][1]);
        fclose($p['pipes'][2]);
        proc_close($p['proc']);
        $txtFile = $p['outBase'] . '.txt';
        $text = file_exists($txtFile) ? file_get_contents($txtFile) : '';
        $pageTexts[$index] = ['number' => $index + 1, 'text' => $text];
    }
    ksort($pageTexts);
    return array_values($pageTexts);
}

$pageTexts = extractPageTexts($pdfPath, $tmpDir);
echo "Extracted " . count($pageTexts) . " page(s)\n\n";

// ── Find the requested position block (same heading pattern PositionBlockDetector uses) ──

$fullText = '';
$pageBoundaries = [];
foreach ($pageTexts as $page) {
    $pageBoundaries[strlen($fullText)] = $page['number'];
    $fullText .= "\n" . $page['text'];
}

$headingPattern = '/^[A-Z]\.\s+((?i)[A-Za-z][A-Za-z\s.,\'\-]+?)\s*\((?i)sg-?\s*(\d{1,2})\)/m';
if (!preg_match_all($headingPattern, $fullText, $rawMatches, PREG_OFFSET_CAPTURE)) {
    fwrite(STDERR, "No position headings found at all — extraction may have failed. Dumping full text instead.\n");
    file_put_contents(__DIR__ . '/debug_block_dump.txt', $fullText);
    echo "Full text saved to debug_block_dump.txt (" . strlen($fullText) . " chars)\n";
    exit(0);
}

$matchIndex = null;
foreach ($rawMatches[1] as $i => $titleMatch) {
    if (stripos($titleMatch[0], $headingSearch) !== false) {
        $matchIndex = $i;
        break;
    }
}

if ($matchIndex === null) {
    echo "Could not find a heading containing \"$headingSearch\". Found these headings instead:\n";
    foreach ($rawMatches[1] as $titleMatch) {
        echo "  - " . trim($titleMatch[0]) . "\n";
    }
    exit(1);
}

$blockStart = $rawMatches[0][$matchIndex][1];
$blockEnd = $rawMatches[0][$matchIndex + 1][1] ?? strlen($fullText);
$blockText = substr($fullText, $blockStart, $blockEnd - $blockStart);

echo "Found block: " . trim($rawMatches[0][$matchIndex][0]) . "\n";
echo "Block length: " . strlen($blockText) . " chars\n\n";

file_put_contents(__DIR__ . '/debug_block_dump.txt', $blockText);
echo "Full block text saved to: " . __DIR__ . "/debug_block_dump.txt\n\n";

// ── Show the tail end of the block (where the table would stop) ──
$tailLength = 1500;
$tail = strlen($blockText) > $tailLength ? substr($blockText, -$tailLength) : $blockText;
echo str_repeat('=', 70) . "\n";
echo "LAST $tailLength CHARACTERS OF THE BLOCK (raw, unmodified):\n";
echo str_repeat('=', 70) . "\n";
echo $tail . "\n";
echo str_repeat('=', 70) . "\n\n";

// ── Find every standalone "41" occurrence with context ──
if (preg_match_all('/(?<![\d.\-])41(?![\d.\-])/', $blockText, $m, PREG_OFFSET_CAPTURE)) {
    echo "Found " . count($m[0]) . " standalone occurrence(s) of \"41\" in this block:\n\n";
    foreach ($m[0] as $occ) {
        $pos = $occ[1];
        $start = max(0, $pos - 40);
        $context = substr($blockText, $start, 120);
        echo "  ...at offset $pos: ..." . str_replace(["\n", "\r"], ' ', $context) . "...\n\n";
    }
} else {
    echo "No standalone \"41\" found anywhere in this block's OCR text.\n";
    echo "This means the number itself never rendered — the parser has\n";
    echo "nothing to find. Check the tail text above: is there a school\n";
    echo "name sitting there with NO number in front of it, or does the\n";
    echo "block's real content simply stop before a 41st row?\n";
}

// Cleanup temp OCR files (keep the dump)
$items = @scandir($tmpDir);
if ($items) {
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        @unlink($tmpDir . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($tmpDir);
}
