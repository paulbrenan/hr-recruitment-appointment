<?php
/**
 * debug_page_lengths.php
 *
 * Standalone diagnostic — NOT a patch. Runs the same extraction pipeline
 * as ProcessPdfImportJob, then prints each page's raw text length and a
 * short preview, so a page that OCR'd almost nothing (a likely cause of
 * a whole chunk of table rows going missing) is easy to spot.
 *
 * Usage (run from your Laravel project root):
 *   php debug_page_lengths.php "C:\path\to\the.pdf"
 *
 * Optionally pass a second argument to dump one page's FULL raw text:
 *   php debug_page_lengths.php "C:\path\to\the.pdf" 7
 */

if ($argc < 2) {
    fwrite(STDERR, "Usage: php debug_page_lengths.php <path-to-pdf> [page-number-to-dump-in-full]\n");
    exit(1);
}

$pdfPath = $argv[1];
$dumpPage = isset($argv[2]) ? (int) $argv[2] : null;

if (!file_exists($pdfPath)) {
    fwrite(STDERR, "ERROR: PDF not found at $pdfPath\n");
    exit(1);
}

$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'debug_pagelen_' . uniqid();
mkdir($tmpDir, 0777, true);

function extractPageTexts(string $pdfPath, string $tmpDir): array
{
    $pdftotextCmd = sprintf(
        '"C:\\poppler\\Library\\bin\\pdftotext.exe" -layout %s - 2>&1',
        escapeshellarg($pdfPath)
    );
    $directText = shell_exec($pdftotextCmd);

    if ($directText !== null && strlen(trim($directText)) > 200) {
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
            $processes[$index] = ['proc' => $proc, 'pipes' => $pipes, 'outBase' => $outBase, 'image' => $imagePath];
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
        $pageTexts[$index] = ['number' => $index + 1, 'text' => $text, 'image' => $p['image']];
    }
    ksort($pageTexts);
    return array_values($pageTexts);
}

$pageTexts = extractPageTexts($pdfPath, $tmpDir);

echo str_repeat('=', 60) . "\n";
echo "PAGE-BY-PAGE RAW OCR LENGTH SUMMARY\n";
echo str_repeat('=', 60) . "\n";
foreach ($pageTexts as $page) {
    $len = strlen($page['text']);
    $preview = trim(preg_replace('/\s+/', ' ', substr($page['text'], 0, 80)));
    $flag = $len < 200 ? '  <-- SUSPICIOUSLY SHORT' : '';
    printf("Page %2d | %5d chars | %s%s\n", $page['number'], $len, $preview, $flag);
}
echo str_repeat('=', 60) . "\n";

if ($dumpPage !== null) {
    foreach ($pageTexts as $page) {
        if ($page['number'] === $dumpPage) {
            echo "\nFULL RAW TEXT OF PAGE $dumpPage:\n";
            echo str_repeat('-', 60) . "\n";
            echo $page['text'] . "\n";
            echo str_repeat('-', 60) . "\n";
            if (isset($page['image'])) {
                $savedImage = __DIR__ . "/debug_page_{$dumpPage}.png";
                copy($page['image'], $savedImage);
                echo "\nSaved the source image for this page to: $savedImage\n";
                echo "(open it and visually check whether the table content is actually there)\n";
            }
            break;
        }
    }
}

// Cleanup OCR temp files (but keep any saved debug images, which were copied out already)
$items = @scandir($tmpDir);
if ($items) {
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        @unlink($tmpDir . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($tmpDir);
}
