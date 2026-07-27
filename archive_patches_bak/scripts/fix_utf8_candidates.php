<?php

/**
 * fix_utf8_candidates.php
 *
 * Fixes: "Unable to encode attribute [candidates] for model
 * [App\Models\PdfImportBatch] to JSON: Malformed UTF-8 characters"
 *
 * Root cause: Tesseract OCR sometimes outputs invalid UTF-8 bytes
 * (e.g. for ñ, accented characters in school names). Laravel's JSON
 * cast then fails when trying to store the candidates array.
 *
 * Fix: sanitize the entire candidates array through mb_convert_encoding
 * before passing it to $batch->update() in ProcessPdfImportJob.
 */

define('ROOT', __DIR__);

function backup(string $path): void {
    if (!file_exists($path)) return;
    $bak = $path . '.bak';
    $i = 2;
    while (file_exists($bak)) { $bak = $path . '.bak' . $i++; }
    copy($path, $bak);
    echo "  [bak] $bak\n";
}

function apply_patch(string $path, string $old, string $new, string $label): void {
    if (!file_exists($path)) { echo "\n❌ File not found: $path\n"; exit(1); }
    $content = file_get_contents($path);
    $count = substr_count($content, $old);
    if ($count === 0) { echo "\n❌ PATCH ABORTED — content not found in:\n  $path\nLabel: $label\n"; exit(1); }
    if ($count > 1)   { echo "\n❌ PATCH ABORTED — found $count times in:\n  $path\nLabel: $label\n"; exit(1); }
    backup($path);
    file_put_contents($path, str_replace($old, $new, $content));
    echo "  [ok ] $label\n";
}

echo "\n=== fix_utf8_candidates.php ===\n\n";

$jobPath = ROOT . '/app/Jobs/ProcessPdfImportJob.php';

// Patch the batch update call to sanitize UTF-8 before storing
$oldUpdate = <<<'PHP'
            $batch->update([
                'candidates' => $candidates,
                'status' => 'ready',
                'memo_pdf_path' => $memoPdfPath,
            ]);
PHP;

$newUpdate = <<<'PHP'
            $batch->update([
                'candidates' => self::sanitizeUtf8($candidates),
                'status' => 'ready',
                'memo_pdf_path' => $memoPdfPath,
            ]);
PHP;

apply_patch($jobPath, $oldUpdate, $newUpdate, 'ProcessPdfImportJob: sanitize UTF-8 before storing candidates');

// Add the sanitizeUtf8 helper method before the cleanupTmp method
$oldCleanup = <<<'PHP'
    private function cleanupTmp(string $dir): void
PHP;

$newCleanup = <<<'PHP'
    /**
     * Recursively sanitize all string values in an array to valid UTF-8.
     * Tesseract can output invalid byte sequences for characters like ñ, é
     * which cause JSON encoding to fail when Laravel tries to store the array.
     */
    private static function sanitizeUtf8(mixed $value): mixed
    {
        if (is_string($value)) {
            // Convert to UTF-8, replacing invalid sequences with '?'
            $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            if ($converted === false || !mb_check_encoding($converted, 'UTF-8')) {
                // Strip any remaining invalid bytes as last resort
                $converted = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
                $converted = mb_convert_encoding($converted ?? '', 'UTF-8', 'ISO-8859-1');
            }
            return $converted;
        }

        if (is_array($value)) {
            return array_map([self::class, 'sanitizeUtf8'], $value);
        }

        return $value;
    }

    private function cleanupTmp(string $dir): void
PHP;

apply_patch($jobPath, $oldCleanup, $newCleanup, 'ProcessPdfImportJob: add sanitizeUtf8() helper');

echo <<<TEXT

✅ Done. No migration needed.

Re-upload the PDF — it should process without the UTF-8 error now.
The sanitizer handles ñ, accented characters, and any other invalid
byte sequences Tesseract outputs.

Also make sure .env has:
  QUEUE_CONNECTION=sync

Then run: php artisan config:clear

DELETE this script after running.

TEXT;
