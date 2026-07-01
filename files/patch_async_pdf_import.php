<?php
/**
 * One-shot patch v5: move PDF OCR import to a background queued job with a
 * polling progress screen, instead of blocking the upload request.
 *
 * This patch creates/modifies several files:
 *
 *   NEW   database/migrations/..._add_status_to_pdf_import_batches_table.php
 *   NEW   app/Jobs/ProcessPdfImportJob.php
 *   NEW   resources/views/job-postings/import/processing.blade.php
 *   EDIT  app/Models/PdfImportBatch.php          (add status/error fields)
 *   EDIT  app/Http/Controllers/JobPostingImportController.php
 *         (extract() now just saves + dispatches the job + redirects to a
 *          processing page; new status() JSON endpoint for polling; review()
 *          redirects back to processing if not ready yet)
 *   EDIT  routes/web.php                          (new status + processing routes)
 *
 * IMPORTANT -- READ BEFORE RUNNING:
 * This requires an actual queue worker running, or jobs will never process.
 * In your .env, set:
 *     QUEUE_CONNECTION=database
 * Then run once:
 *     php artisan queue:table
 *     php artisan migrate
 * And keep a worker running while developing:
 *     php artisan queue:work
 * (If QUEUE_CONNECTION=sync, the job runs immediately/inline -- you'll keep
 * the old blocking behavior, just routed through the new code path. That's
 * fine for testing the wiring, but won't give you the "instant response"
 * behavior until you switch to database/redis + a running worker.)
 *
 * Run from your Laravel project root:
 *   php patch_async_pdf_import.php
 */

$root = __DIR__;

function backup_and_read(string $path): ?string
{
    if (!file_exists($path)) {
        return null;
    }
    $backup = $path . '.bak_' . date('Ymd_His');
    copy($path, $backup);
    echo "Backed up: $path\n  -> $backup\n";
    return file_get_contents($path);
}

function write_new_file(string $path, string $contents): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    if (file_exists($path)) {
        echo "SKIPPED (already exists, not overwriting): $path\n";
        return;
    }
    file_put_contents($path, $contents);
    echo "Created: $path\n";
}

// ════════════════════════════════════════════════════════════════════════
// 1. Migration: add status tracking columns to pdf_import_batches
// ════════════════════════════════════════════════════════════════════════
$migrationName = date('Y_m_d_His') . '_add_status_to_pdf_import_batches_table.php';
$migrationPath = $root . '/database/migrations/' . $migrationName;

$migrationContents = <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdf_import_batches', function (Blueprint $table) {
            // processing = job queued/running, ready = candidates are populated,
            // failed = job errored out (see error_message)
            $table->string('status')->default('processing')->after('original_filename');
            $table->text('error_message')->nullable()->after('status');
            $table->string('pdf_path')->nullable()->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('pdf_import_batches', function (Blueprint $table) {
            $table->dropColumn(['status', 'error_message', 'pdf_path']);
        });
    }
};
PHP;

write_new_file($migrationPath, $migrationContents);

// ════════════════════════════════════════════════════════════════════════
// 2. Job class: does the actual OCR work, runs in the background
// ════════════════════════════════════════════════════════════════════════
$jobPath = $root . '/app/Jobs/ProcessPdfImportJob.php';

$jobContents = <<<'PHP'
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

            $batch->update([
                'candidates' => $candidates,
                'status' => 'ready',
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
            '"C:\\poppler\\Library\\bin\\pdftoppm.exe" -r 150 -png %s %s 2>&1',
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
PHP;

write_new_file($jobPath, $jobContents);

// ════════════════════════════════════════════════════════════════════════
// 3. Processing view: polls the status endpoint, redirects when ready
// ════════════════════════════════════════════════════════════════════════
$viewPath = $root . '/resources/views/job-postings/import/processing.blade.php';

$viewContents = <<<'BLADE'
@extends('layouts.app')

@section('content')
<div class="max-w-xl mx-auto py-16 text-center">
    <h1 class="text-xl font-semibold mb-4">Processing your PDF…</h1>
    <p class="text-gray-500 mb-8">This usually takes under a minute. You can leave this page open --
        it'll redirect automatically once it's done.</p>

    <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden mb-6">
        <div class="h-2 bg-blue-500 animate-pulse" style="width: 100%"></div>
    </div>

    <p id="status-text" class="text-sm text-gray-400">Status: processing…</p>
</div>

<script>
(function poll() {
    fetch('{{ route("job-postings.import.status", $batch->id) }}')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'ready') {
                window.location.href = '{{ route("job-postings.import.review", $batch->id) }}';
            } else if (data.status === 'failed') {
                document.getElementById('status-text').innerHTML =
                    '<span class="text-red-500">' + (data.error_message || 'Something went wrong.') + '</span>';
            } else {
                setTimeout(poll, 2000);
            }
        })
        .catch(() => setTimeout(poll, 3000));
})();
</script>
@endsection
BLADE;

write_new_file($viewPath, $viewContents);

// ════════════════════════════════════════════════════════════════════════
// 4. Patch PdfImportBatch model -- add new fillable fields
// ════════════════════════════════════════════════════════════════════════
$modelPath = $root . '/app/Models/PdfImportBatch.php';
$modelContents = backup_and_read($modelPath);

if ($modelContents !== null) {
    $oldFillable = <<<'PHP'
    protected $fillable = [
        'original_filename',
        'candidates',
        'expires_at',
    ];
PHP;

    $newFillable = <<<'PHP'
    protected $fillable = [
        'original_filename',
        'candidates',
        'expires_at',
        'status',
        'error_message',
        'pdf_path',
    ];
PHP;

    if (strpos($modelContents, $oldFillable) !== false) {
        $modelContents = str_replace($oldFillable, $newFillable, $modelContents);
        file_put_contents($modelPath, $modelContents);
        echo "Patched: $modelPath (added status/error_message/pdf_path to fillable)\n";
    } else {
        echo "MANUAL STEP NEEDED in $modelPath:\n";
        echo "  Add 'status', 'error_message', 'pdf_path' to the \$fillable array.\n";
    }
}

// ════════════════════════════════════════════════════════════════════════
// 5. Patch JobPostingImportController -- async extract(), new status(),
//    review() bounces back to processing if not ready
// ════════════════════════════════════════════════════════════════════════
$controllerPath = $root . '/app/Http/Controllers/JobPostingImportController.php';
$controllerContents = backup_and_read($controllerPath);

if ($controllerContents !== null) {
    // Add the Job import
    $useAnchor = "use App\Models\PdfImportBatch;";
    if (strpos($controllerContents, $useAnchor) !== false && strpos($controllerContents, 'use App\Jobs\ProcessPdfImportJob;') === false) {
        $controllerContents = str_replace(
            $useAnchor,
            $useAnchor . "\nuse App\\Jobs\\ProcessPdfImportJob;",
            $controllerContents
        );
        echo "Patched: added ProcessPdfImportJob import\n";
    }

    // Replace the entire extract() method with a thin dispatch-and-redirect version
    $extractPattern = '/\/\/ ── OCR extraction \+ parsing pipeline.*?(?=\n    \/\/ ── Review screen)/s';

    $newExtract = <<<'PHP'
    // ── OCR extraction + parsing pipeline ─────────────────────────────────────
    // This now just saves the upload and queues the actual OCR work as a
    // background job, returning immediately instead of blocking the request
    // for 25-45+ seconds. See app/Jobs/ProcessPdfImportJob.php for the work,
    // and status()/processing.blade.php below for the polling progress page.
    public function extract(Request $request)
    {
        $request->validate([
            'pdf_file' => 'required|file|mimes:pdf|max:20480',
        ]);

        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hr_ocr_' . uniqid();
        if (!mkdir($tmpDir, 0755, true)) {
            return back()->withErrors(['pdf_file' => 'Could not create temporary directory for processing.']);
        }

        $pdfPath      = $tmpDir . DIRECTORY_SEPARATOR . 'input.pdf';
        $originalName = $request->file('pdf_file')->getClientOriginalName();
        $request->file('pdf_file')->move($tmpDir, 'input.pdf');

        $batch = PdfImportBatch::create([
            'original_filename' => $originalName,
            'candidates' => [],
            'expires_at' => now()->addDay(),
            'status' => 'processing',
            'pdf_path' => $pdfPath,
        ]);

        ProcessPdfImportJob::dispatch($batch->id);

        return redirect()->route('job-postings.import.processing', $batch->id);
    }

    // ── Processing screen: shown immediately, polls status() until ready ──────
    public function processing($batchId)
    {
        $batch = PdfImportBatch::findOrFail($batchId);

        if ($batch->status === 'ready') {
            return redirect()->route('job-postings.import.review', $batch->id);
        }

        return view('job-postings.import.processing', compact('batch'));
    }

    // ── JSON status endpoint, polled by the processing screen ─────────────────
    public function status($batchId)
    {
        $batch = PdfImportBatch::findOrFail($batchId);

        return response()->json([
            'status' => $batch->status,
            'error_message' => $batch->error_message,
        ]);
    }

PHP;

    $count = 0;
    $controllerContents = preg_replace($extractPattern, $newExtract, $controllerContents, 1, $count);

    if ($count === 1) {
        echo "Patched: replaced extract() with async dispatch + added processing()/status()\n";
    } else {
        echo "ERROR: Could not match extract() method boundaries in the controller.\n";
        echo "Manual step needed -- see the job/migration/view files already created,\n";
        echo "and wire extract()/processing()/status() yourself following the pattern above.\n";
    }

    // Patch review() to bounce back to processing if the batch isn't ready yet,
    // and show the failure message if it failed.
    $oldReview = <<<'PHP'
    public function review($batchId)
    {
        $batch = PdfImportBatch::findOrFail($batchId);

        $grouped = collect($batch->candidates)
PHP;

    $newReview = <<<'PHP'
    public function review($batchId)
    {
        $batch = PdfImportBatch::findOrFail($batchId);

        if ($batch->status === 'processing') {
            return redirect()->route('job-postings.import.processing', $batch->id);
        }

        if ($batch->status === 'failed') {
            return redirect()
                ->route('job-postings.import.create')
                ->withErrors(['pdf_file' => $batch->error_message ?? 'PDF processing failed.']);
        }

        $grouped = collect($batch->candidates)
PHP;

    if (strpos($controllerContents, $oldReview) !== false) {
        $controllerContents = str_replace($oldReview, $newReview, $controllerContents);
        echo "Patched: review() now redirects back to processing/failure state\n";
    } else {
        echo "MANUAL STEP NEEDED: in review(), add a check that redirects to\n";
        echo "'job-postings.import.processing' if \$batch->status === 'processing',\n";
        echo "and back to the upload form with an error if \$batch->status === 'failed'.\n";
    }

    $openBraces  = substr_count($controllerContents, '{');
    $closeBraces = substr_count($controllerContents, '}');
    if ($openBraces !== $closeBraces) {
        echo "WARNING: brace mismatch in controller after patching ({$openBraces} vs {$closeBraces}).\n";
        echo "Review the file carefully before testing -- a backup is available.\n";
    }

    file_put_contents($controllerPath, $controllerContents);
}

// ════════════════════════════════════════════════════════════════════════
// 6. Patch routes/web.php -- add processing + status routes
// ════════════════════════════════════════════════════════════════════════
$routesPath = $root . '/routes/web.php';
$routesContents = backup_and_read($routesPath);

if ($routesContents !== null) {
    $anchor = "Route::post('/job-postings/import/extract', [JobPostingImportController::class, 'extract'])->name('job-postings.import.extract');";

    if (strpos($routesContents, $anchor) !== false && strpos($routesContents, 'job-postings.import.processing') === false) {
        $newRoutes = $anchor . "\n"
            . "Route::get('/job-postings/import/{batch}/processing', [JobPostingImportController::class, 'processing'])->name('job-postings.import.processing');\n"
            . "Route::get('/job-postings/import/{batch}/status', [JobPostingImportController::class, 'status'])->name('job-postings.import.status');";

        $routesContents = str_replace($anchor, $newRoutes, $routesContents);
        file_put_contents($routesPath, $routesContents);
        echo "Patched: $routesPath (added processing + status routes)\n";
    } else {
        echo "MANUAL STEP NEEDED in $routesPath:\n";
        echo "  Add these two lines after the import/extract route:\n";
        echo "    Route::get('/job-postings/import/{batch}/processing', [JobPostingImportController::class, 'processing'])->name('job-postings.import.processing');\n";
        echo "    Route::get('/job-postings/import/{batch}/status', [JobPostingImportController::class, 'status'])->name('job-postings.import.status');\n";
    }
}

echo "\n────────────────────────────────────────────────────────────\n";
echo "Done. Next steps:\n";
echo "1. In .env set: QUEUE_CONNECTION=database\n";
echo "2. Run:\n";
echo "     php artisan queue:table\n";
echo "     php artisan migrate\n";
echo "3. Keep a worker running while you test:\n";
echo "     php artisan queue:work\n";
echo "4. Upload a PDF -- the page should redirect almost instantly to a\n";
echo "   processing screen, then auto-redirect to review once the job finishes.\n";
echo "5. If something looks off, all original files were backed up with a\n";
echo "   .bak_<timestamp> suffix next to each modified file.\n";
