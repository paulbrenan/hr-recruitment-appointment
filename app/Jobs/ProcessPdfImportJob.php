<?php

namespace App\Jobs;

use App\Models\PdfImportBatch;
use App\Services\PositionBlockDetector;
use App\Services\PositionBlockExpander;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessPdfImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes hard cap, in case OCR hangs on a bad file

    public function __construct(public int $batchId)
    {
    }

    public function handle(): void
    {
        $batch = PdfImportBatch::find($this->batchId);

        if (!$batch || empty($batch->pdf_path) || !file_exists($batch->pdf_path)) {
            $batch?->update([
                'status' => 'failed',
                'error_message' => 'Uploaded PDF file could not be found for processing.',
            ]);
            return;
        }

        $pdfPath = $batch->pdf_path;
        $tmpDir  = dirname($pdfPath);

        try {
            $pageTexts = $this->extractPageTexts($pdfPath, $tmpDir);

            $detector = new PositionBlockDetector(config('job_titles.titles', []));
            $blocks = $detector->detect($pageTexts);

            if (empty($blocks)) {
                $batch->update([
                    'status' => 'failed',
                    'error_message' => 'No recognizable position headings were found in this PDF. '
                        . 'It may not be a vacancy announcement in the expected format, '
                        . 'or OCR quality was too poor to detect headings.',
                ]);
                return;
            }

            $expander = new PositionBlockExpander();
            $candidates = $expander->expand($blocks);

            // Copy the source memo PDF to permanent public storage now,
            // while it still exists on disk. cleanupTmp() below (in the
            // finally block) deletes $pdfPath and everything else in
            // $tmpDir unconditionally, so this can't be deferred to
            // confirm() -- by the time the user confirms the import,
            // this tmp file is long gone.
            $memoPdfPath = 'memos/' . $batch->id . '-' . Str::slug(
                pathinfo($batch->original_filename ?? 'memo', PATHINFO_FILENAME)
            ) . '.pdf';
            Storage::disk('public')->put($memoPdfPath, file_get_contents($pdfPath));

            $batch->update([
                'candidates' => self::sanitizeUtf8($candidates),
                'status' => 'ready',
                'memo_pdf_path' => $memoPdfPath,
            ]);
        } catch (\Throwable $e) {
            $batch->update([
                'status' => 'failed',
                'error_message' => 'Processing error: ' . $e->getMessage(),
            ]);
        } finally {
            $this->cleanupTmp($tmpDir);
        }
    }

    /**
     * Same pipeline as before: try pdftotext first (near-instant for
     * digitally-generated PDFs), fall back to pdftoppm + parallel Tesseract
     * OCR for scanned PDFs.
     */
    private function extractPageTexts(string $pdfPath, string $tmpDir): array
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
            // Bumped from 150 -> 300 DPI: confirmed against a real vacancy PDF
            // that this alone roughly doubles the number of correctly-read
            // table rows (150dpi read 29/89 rows, 300dpi read 75/89 on the
            // same table) because the row-number column is small text that
            // 150dpi renders too coarsely for Tesseract to read reliably.
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
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->cleanupTmp($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}