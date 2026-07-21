<?php

namespace App\Jobs;

use App\Models\BudgetCircular;
use App\Models\SalaryGrade;
use App\Services\SalaryGradeTableParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessSalaryGradeImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    public function __construct(public int $circularId)
    {
    }

    public function handle(): void
    {
        $circular = BudgetCircular::find($this->circularId);

        if (!$circular || empty($circular->source_file_path) || !file_exists($circular->source_file_path)) {
            $circular?->update([
                'status' => 'failed',
                'error_message' => 'Uploaded file could not be found for processing.',
            ]);
            return;
        }

        $parser = new SalaryGradeTableParser();

        try {
            if ($circular->source_type === 'xlsx') {
                $rows = $parser->parseTableFromSpreadsheet($circular->source_file_path);
                $meta = ['circularNo' => null, 'subject' => null, 'effectiveDate' => null];
            } else {
                $pageTexts = $this->extractPageTexts($circular->source_file_path);
                $fullText = collect($pageTexts)->pluck('text')->implode("\n");
                $rows = $parser->parseTable($pageTexts);
                $meta = $parser->parseMeta($fullText);
            }

            if (empty($rows)) {
                $circular->update([
                    'status' => 'failed',
                    'error_message' => 'No salary grade rows were recognized in this file. '
                        . 'It may not match the expected Annex A table layout, or OCR quality was too poor.',
                ]);
                return;
            }

            DB::transaction(function () use ($circular, $rows, $meta) {
                foreach ($rows as $row) {
                    SalaryGrade::updateOrCreate(
                        [
                            'budget_circular_id' => $circular->id,
                            'grade' => $row['grade'],
                            'step' => $row['step'],
                        ],
                        ['amount' => $row['amount']]
                    );
                }

                $circular->update([
                    'circular_no' => $circular->circular_no ?: $meta['circularNo'],
                    'subject' => $circular->subject ?: $meta['subject'],
                    'effective_date' => $circular->effective_date ?: $meta['effectiveDate'],
                    // 'ready' = parsed and waiting for staff to review/confirm
                    // via SalaryGradeController::confirm(). Never auto-applied.
                    'status' => 'ready',
                ]);
            });
        } catch (\Throwable $e) {
            $circular->update([
                'status' => 'failed',
                'error_message' => 'Processing error: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Same pdftotext-first, Tesseract-OCR-fallback approach already used in
     * ProcessPdfImportJob, trimmed down for a single-purpose table extract.
     * Adjust the poppler/tesseract paths below if your XAMPP install differs.
     */
    private function extractPageTexts(string $pdfPath): array
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

        // Scanned/image-only PDF: fall back to pdftoppm + Tesseract, same
        // 300 DPI setting already validated in ProcessPdfImportJob for
        // reading small table text reliably.
        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sg_import_' . uniqid();
        mkdir($tmpDir, 0777, true);
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

        $pageTexts = [];
        foreach ($imageFiles as $index => $imagePath) {
            $outBase = $tmpDir . DIRECTORY_SEPARATOR . 'ocr_page_' . ($index + 1);
            $tesseractCmd = sprintf(
                '"C:\\Program Files\\Tesseract-OCR\\tesseract.exe" %s %s -l eng --oem 1 --psm 6',
                escapeshellarg($imagePath),
                escapeshellarg($outBase)
            );
            shell_exec($tesseractCmd);

            $txtFile = $outBase . '.txt';
            $pageTexts[] = [
                'number' => $index + 1,
                'text' => file_exists($txtFile) ? file_get_contents($txtFile) : '',
            ];
        }

        // Best-effort cleanup of the temp OCR dir.
        array_map('unlink', glob($tmpDir . DIRECTORY_SEPARATOR . '*'));
        @rmdir($tmpDir);

        return $pageTexts;
    }
}
