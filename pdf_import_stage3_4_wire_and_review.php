<?php
// pdf_import_stage3_4_wire_and_review.php
// Run once from your Laravel project root:
//   php pdf_import_stage3_4_wire_and_review.php
//
// Wires VacancyTableParser + PositionBlockDetector (already on disk, untouched
// by this script) into JobPostingImportController, adds a new
// PositionBlockExpander service (expands each detected position block into one
// candidate row per school for table-type place-of-assignment, or one row for
// "To be determined" positions), adds a temporary pdf_import_batches table to
// hold parsed results between the upload and confirm requests, and builds the
// review/edit/confirm screen (grouped by position, checkboxes, editable fields,
// select-all/deselect-all per group).
//
// Imported postings are created with status=draft so HR reviews before publishing.
//
// Backs up files it overwrites to .bak (or .bak2, .bak3...) before writing.
// Fails loudly without writing if a patch target is not found exactly once.
// Safe to delete after running.

function die_loud($msg) {
    fwrite(STDERR, "
[ABORTED] $msg

");
    exit(1);
}

function backup_file($path) {
    if (!file_exists($path)) {
        die_loud("Expected file not found: $path");
    }
    $backupPath = $path . ".bak";
    $n = 1;
    while (file_exists($backupPath)) {
        $n++;
        $backupPath = $path . ".bak" . $n;
    }
    if (!copy($path, $backupPath)) {
        die_loud("Could not create backup at $backupPath");
    }
    echo "Backed up " . $path . " -> " . basename($backupPath) . "
";
}

function apply_patch($content, $old, $new, $label) {
    $count = substr_count($content, $old);
    if ($count !== 1) {
        die_loud("Patch ${label} expected exactly 1 match but found $count. The file may have drifted -- please re-paste it so the patch can be updated.");
    }
    return str_replace($old, $new, $content);
}

$root = __DIR__;

// --- 1. New migration ---
$migDir = $root . '/database/migrations';
$migPath = $migDir . '/2026_06_26_000000_create_pdf_import_batches_table.php';
if (file_exists($migPath)) {
    die_loud("Already exists: $migPath");
}
$migContent = <<<'MIGCODE'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('original_filename');
            $table->json('candidates'); // array of candidate posting rows, see PdfImportBatch model docblock
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_import_batches');
    }
};
MIGCODE;
file_put_contents($migPath, $migContent);
echo "Created database/migrations/2026_06_26_000000_create_pdf_import_batches_table.php
";

// --- 2. New model ---
$modelPath = $root . '/app/Models/PdfImportBatch.php';
if (file_exists($modelPath)) {
    die_loud("Already exists: $modelPath");
}
$modelContent = <<<'MODELCODE'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PdfImportBatch
 *
 * A transient holding area for parsed PDF import results, between the
 * OCR+parse request (JobPostingImportController::extract) and the final
 * confirm request (JobPostingImportController::confirm), which bulk-creates
 * real JobPosting rows from whichever candidates the user checked.
 *
 * The `candidates` column is a JSON array. Each candidate row looks like:
 *   [
 *       'title' => string,
 *       'salary_grade' => string,
 *       'qualification_education' => ?string,
 *       'qualification_training' => ?string,
 *       'qualification_experience' => ?string,
 *       'qualification_eligibility' => ?string,
 *       'duties_responsibilities' => ?string,
 *       'vacancies' => int,
 *       'place_of_assignment' => string,
 *       'group_key' => string, // groups schools belonging to the same detected position block, for the review screen's collapsible sections
 *   ]
 *
 * Rows expire after 24 hours (see expires_at) -- a scheduled cleanup or
 * manual artisan command can purge expired batches. Confirmed/rejected
 * batches are deleted immediately after the confirm request completes,
 * regardless of expires_at.
 */
class PdfImportBatch extends Model
{
    protected $fillable = [
        'original_filename',
        'candidates',
        'expires_at',
    ];

    protected $casts = [
        'candidates' => 'array',
        'expires_at' => 'datetime',
    ];
}
MODELCODE;
file_put_contents($modelPath, $modelContent);
echo "Created app/Models/PdfImportBatch.php
";

// --- 3. New service: PositionBlockExpander ---
$expanderPath = $root . '/app/Services/PositionBlockExpander.php';
if (file_exists($expanderPath)) {
    die_loud("Already exists: $expanderPath");
}
if (!is_dir($root . '/app/Services')) {
    die_loud("app/Services directory not found -- expected VacancyTableParser.php and PositionBlockDetector.php to already be there.");
}
$expanderContent = <<<'EXPANDERCODE'
<?php

namespace App\Services;

/**
 * PositionBlockExpander
 *
 * Takes PositionBlockDetector::detect()'s output (one entry per DETECTED
 * POSITION, with place_of_assignment either a single "To be determined"
 * value or a full table of schools) and expands it into a flat list of
 * candidate job_postings rows -- one row per school for table-type blocks
 * (each with vacancies = 1, since each school slot is its own real
 * vacancy), or one row for single-value blocks (keeping the original
 * vacancy count).
 *
 * Each expanded row carries a 'group_key' so the review screen can group
 * all schools belonging to the same original position block together
 * under one collapsible section.
 */
class PositionBlockExpander
{
    /**
     * @param array $blocks Output of PositionBlockDetector::detect()
     * @return array<int, array> Flat list of candidate job_postings rows
     */
    public function expand(array $blocks): array
    {
        $candidates = [];

        foreach ($blocks as $blockIndex => $block) {
            $groupKey = 'block_' . $blockIndex;
            $shared = [
                'title' => $block['title'],
                'canonical_title' => $block['canonical_title'],
                'salary_grade' => $block['salary_grade'],
                'qualification_education' => $block['qualification_education'],
                'qualification_training' => $block['qualification_training'],
                'qualification_experience' => $block['qualification_experience'],
                'qualification_eligibility' => $block['qualification_eligibility'],
                'duties_responsibilities' => $block['duties_responsibilities'],
                'group_key' => $groupKey,
                'group_label' => $block['title'] . ' (' . $block['salary_grade'] . ')',
            ];

            $placeOfAssignment = $block['place_of_assignment'];

            if ($placeOfAssignment['type'] === 'single') {
                $candidates[] = array_merge($shared, [
                    'vacancies' => $block['vacancies'] ?? 1,
                    'place_of_assignment' => $placeOfAssignment['value'],
                ]);
                continue;
            }

            // Table type: one candidate row per school.
            foreach ($placeOfAssignment['schools'] as $schoolRow) {
                $candidates[] = array_merge($shared, [
                    'vacancies' => 1,
                    'place_of_assignment' => $this->formatPlaceOfAssignment($schoolRow),
                    'school_row_number' => $schoolRow['number'],
                ]);
            }
        }

        return $candidates;
    }

    /**
     * Builds the place_of_assignment display/storage string from a parsed
     * school row, including the adopted school(s) and municipality if
     * present, e.g. "Amuyong Elementary School (Alfonso)" or
     * "Area J ES, Bulihan ES (General Mariano Alvarez)".
     */
    private function formatPlaceOfAssignment(array $schoolRow): string
    {
        $school = $schoolRow['school'] ?? '';
        $adopted = $schoolRow['adopted'] ?? null;
        $municipality = $schoolRow['municipality'] ?? null;

        $name = $school;
        if (!empty($adopted)) {
            $name .= ', ' . $adopted;
        }
        if (!empty($municipality)) {
            $name .= ' (' . $municipality . ')';
        }

        return trim($name);
    }
}
EXPANDERCODE;
file_put_contents($expanderPath, $expanderContent);
echo "Created app/Services/PositionBlockExpander.php
";

// --- 4. Verify the two existing parser services are present (not overwritten) ---
$detectorPath = $root . '/app/Services/PositionBlockDetector.php';
$tableParserPath = $root . '/app/Services/VacancyTableParser.php';
if (!file_exists($detectorPath)) {
    die_loud("app/Services/PositionBlockDetector.php not found. This script does not create it -- it must already exist.");
}
if (!file_exists($tableParserPath)) {
    die_loud("app/Services/VacancyTableParser.php not found. This script does not create it -- it must already exist.");
}
echo "Confirmed: PositionBlockDetector.php and VacancyTableParser.php are present (left untouched).
";

// --- 5. Overwrite JobPostingImportController.php with the fully wired version ---
$controllerPath = $root . '/app/Http/Controllers/JobPostingImportController.php';
$controllerContent = <<<'CTRLCODE'
<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Models\PdfImportBatch;
use App\Services\PositionBlockDetector;
use App\Services\PositionBlockExpander;
use Illuminate\Http\Request;

class JobPostingImportController extends Controller
{
    // ── Upload form ───────────────────────────────────────────────────────────
    public function create()
    {
        return view('job-postings.import.upload');
    }

    // ── OCR extraction + parsing pipeline ─────────────────────────────────────
    public function extract(Request $request)
    {
        $request->validate([
            'pdf_file' => 'required|file|mimes:pdf|max:20480',
        ]);

        // ── 1. Save uploaded PDF to a dedicated temp directory ────────────────
        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hr_ocr_' . uniqid();
        if (!mkdir($tmpDir, 0755, true)) {
            return back()->withErrors(['pdf_file' => 'Could not create temporary directory for processing.']);
        }

        $pdfPath      = $tmpDir . DIRECTORY_SEPARATOR . 'input.pdf';
        $originalName = $request->file('pdf_file')->getClientOriginalName();
        $request->file('pdf_file')->move($tmpDir, 'input.pdf');

        // ── 2. Convert PDF pages to PNG images via pdftoppm ──────────────────
        $imagePrefix = $tmpDir . DIRECTORY_SEPARATOR . 'page';

        $pdftoppmCmd = sprintf(
            '"C:\\poppler-26.02.0\\Library\\bin\\pdftoppm.exe" -r 200 -png %s %s 2>&1',
            escapeshellarg($pdfPath),
            escapeshellarg($imagePrefix)
        );

        $pdftoppmOutput = shell_exec($pdftoppmCmd);
        $imageFiles     = glob($tmpDir . DIRECTORY_SEPARATOR . 'page-*.png');

        if (empty($imageFiles)) {
            $this->cleanupTmp($tmpDir);
            return back()->withErrors([
                'pdf_file' => 'pdftoppm could not convert the PDF to images. '
                            . 'pdftoppm output: ' . ($pdftoppmOutput ?: '(none)'),
            ]);
        }

        natsort($imageFiles);
        $imageFiles = array_values($imageFiles);

        // ── 3. Run Tesseract on each page image ───────────────────────────────
        $pageTexts = [];

        foreach ($imageFiles as $index => $imagePath) {
            $outBase = $tmpDir . DIRECTORY_SEPARATOR . 'ocr_page_' . ($index + 1);

            $tesseractCmd = sprintf(
                '"C:\\Program Files\\Tesseract-OCR\\tesseract.exe" %s %s -l eng 2>&1',
                escapeshellarg($imagePath),
                escapeshellarg($outBase)
            );

            shell_exec($tesseractCmd);

            $txtFile = $outBase . '.txt';
            $text    = file_exists($txtFile) ? file_get_contents($txtFile) : '';

            $pageTexts[] = [
                'number' => $index + 1,
                'text'   => $text,
            ];
        }

        // ── 4. Clean up all temp files ────────────────────────────────────────
        $this->cleanupTmp($tmpDir);

        // ── 5. Parse OCR'd text into structured position blocks ──────────────
        $detector = new PositionBlockDetector(config('job_titles.titles', []));
        $blocks = $detector->detect($pageTexts);

        if (empty($blocks)) {
            return back()->withErrors([
                'pdf_file' => 'No recognizable position headings were found in this PDF. '
                            . 'It may not be a vacancy announcement in the expected format, '
                            . 'or OCR quality was too poor to detect headings.',
            ]);
        }

        // ── 6. Expand each block into flat per-row candidates ─────────────────
        $expander = new PositionBlockExpander();
        $candidates = $expander->expand($blocks);

        // ── 7. Store as a temporary batch for the review screen ───────────────
        $batch = PdfImportBatch::create([
            'original_filename' => $originalName,
            'candidates' => $candidates,
            'expires_at' => now()->addDay(),
        ]);

        return redirect()->route('job-postings.import.review', $batch->id);
    }

    // ── Review screen ─────────────────────────────────────────────────────────
    public function review($batchId)
    {
        $batch = PdfImportBatch::findOrFail($batchId);

        $grouped = collect($batch->candidates)
            ->groupBy('group_key')
            ->map(function ($rows) {
                return [
                    'label' => $rows->first()['group_label'] ?? 'Untitled position',
                    'rows' => $rows->values(),
                ];
            });

        return view('job-postings.import.review', [
            'batch' => $batch,
            'grouped' => $grouped,
        ]);
    }

    // ── Confirm: bulk-create real job_postings from checked candidates ────────
    public function confirm(Request $request, $batchId)
    {
        $batch = PdfImportBatch::findOrFail($batchId);

        $validated = $request->validate([
            'selected' => ['nullable', 'array'],
            'selected.*' => ['integer'],
            'rows' => ['required', 'array'],
        ]);

        $selectedIndexes = array_flip($validated['selected'] ?? []);
        $editedRows = $validated['rows'];

        $created = 0;

        foreach ($editedRows as $index => $rowData) {
            if (!isset($selectedIndexes[$index])) {
                continue;
            }

            JobPosting::create([
                'title' => $rowData['title'],
                'salary_grade' => $rowData['salary_grade'] ?? null,
                'qualification_education' => $rowData['qualification_education'] ?? null,
                'qualification_training' => $rowData['qualification_training'] ?? null,
                'qualification_experience' => $rowData['qualification_experience'] ?? null,
                'qualification_eligibility' => $rowData['qualification_eligibility'] ?? null,
                'duties_responsibilities' => $rowData['duties_responsibilities'] ?? null,
                'place_of_assignment' => $rowData['place_of_assignment'] ?? null,
                'vacancies' => max(1, (int) ($rowData['vacancies'] ?? 1)),
                'employment_type' => 'Regular',
                'status' => 'draft',
            ]);

            $created++;
        }

        $batch->delete();

        return redirect()
            ->route('job-postings.index')
            ->with('success', "Imported {$created} job posting(s) from PDF.");
    }

    // ── Helper: recursively delete temp directory ─────────────────────────────
    private function cleanupTmp(string $dir): void
    {
        if (!is_dir($dir)) return;

        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->cleanupTmp($path) : unlink($path);
        }
        rmdir($dir);
    }
}
CTRLCODE;
backup_file($controllerPath);
file_put_contents($controllerPath, $controllerContent);
echo "Updated app/Http/Controllers/JobPostingImportController.php
";

// --- 6. Update upload.blade.php copy ---
$uploadPath = $root . '/resources/views/job-postings/import/upload.blade.php';
$uploadContent = <<<'UPLOADCODE'
@extends('layouts.app')

@section('title', 'Import job postings from PDF')
@section('page-title', 'Import job postings from PDF')

@section('content')
<div class="card">
    <div class="card-body p-4">
        <p class="text-muted small mb-3">
            Upload a "Call for Application" PDF (e.g. a DepEd Division Memorandum). The system will
            run OCR, detect each vacant position, and take you to a review screen where you can check,
            edit, and confirm which postings to actually import.
        </p>

        @if ($errors->any())
        <div class="alert alert-danger small">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
        @endif

        <form method="POST" action="{{ route('job-postings.import.extract') }}" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label class="form-label small fw-medium">PDF file</label>
                <input type="file" class="form-control" name="pdf_file" accept="application/pdf" required>
                <div class="form-text" style="font-size: 0.72rem;">Max 20MB. OCR processing may take a moment for multi-page documents.</div>
            </div>
            <button type="submit" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">
                <i class="bi bi-file-earmark-text me-1"></i> Upload and process
            </button>
            <a href="{{ route('job-postings.index') }}" class="btn btn-sm btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>
@endsection
UPLOADCODE;
backup_file($uploadPath);
file_put_contents($uploadPath, $uploadContent);
echo "Updated resources/views/job-postings/import/upload.blade.php
";

// --- 7. New review screen ---
$reviewPath = $root . '/resources/views/job-postings/import/review.blade.php';
if (file_exists($reviewPath)) {
    die_loud("Already exists: $reviewPath");
}
$reviewContent = <<<'REVIEWCODE'
@extends('layouts.app')

@section('title', 'Review imported postings')
@section('page-title', 'Review imported postings')

@section('content')
@if ($errors->any())
<div class="alert alert-danger small">
    @foreach ($errors->all() as $error)
        <div>{{ $error }}</div>
    @endforeach
</div>
@endif

<div class="card mb-3">
    <div class="card-body p-3">
        <div class="fw-medium small">{{ $batch->original_filename }}</div>
        <div class="text-muted small">
            {{ collect($batch->candidates)->count() }} candidate posting(s) detected across {{ $grouped->count() }} position(s).
            Review and edit the fields below, check the ones you want to import, then confirm.
            Imported postings are created as <span class="badge text-bg-secondary">Draft</span> so you can publish them when ready.
        </div>
    </div>
</div>

<form method="POST" action="{{ route('job-postings.import.confirm', $batch->id) }}" id="importForm">
    @csrf

    @php $globalIndex = 0; @endphp
    @foreach ($grouped as $groupKey => $group)
    <div class="card mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#group_{{ $loop->index }}">
            <div>
                <span class="fw-medium">{{ $group['label'] }}</span>
                <span class="badge text-bg-light text-dark border ms-2">{{ $group['rows']->count() }} row{{ $group['rows']->count() === 1 ? '' : 's' }}</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary select-all-btn" data-group="{{ $loop->index }}">Select all</button>
                <button type="button" class="btn btn-sm btn-outline-secondary deselect-all-btn" data-group="{{ $loop->index }}">Deselect all</button>
                <i class="bi bi-chevron-down"></i>
            </div>
        </div>
        <div class="collapse show" id="group_{{ $loop->index }}">
            <div class="card-body p-3">
                @foreach ($group['rows'] as $row)
                @php $i = $globalIndex; $globalIndex++; @endphp
                <div class="border rounded p-3 mb-2 candidate-row" data-group="{{ $loop->parent->index }}">
                    <div class="d-flex align-items-start gap-2 mb-2">
                        <input type="checkbox" class="form-check-input mt-1" name="selected[]" value="{{ $i }}" checked>
                        <div class="flex-grow-1 row g-2">
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-1">Title</label>
                                <input type="text" class="form-control form-control-sm" name="rows[{{ $i }}][title]" value="{{ $row['title'] }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-muted mb-1">SG</label>
                                <input type="text" class="form-control form-control-sm" name="rows[{{ $i }}][salary_grade]" value="{{ $row['salary_grade'] }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-muted mb-1">Vacancies</label>
                                <input type="number" class="form-control form-control-sm" name="rows[{{ $i }}][vacancies]" value="{{ $row['vacancies'] }}" min="1">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-muted mb-1">Status on import</label>
                                <input type="text" class="form-control form-control-sm" value="Draft" disabled>
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-muted mb-1">Place of assignment</label>
                                <input type="text" class="form-control form-control-sm" name="rows[{{ $i }}][place_of_assignment]" value="{{ $row['place_of_assignment'] }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1">Education</label>
                                <textarea class="form-control form-control-sm" name="rows[{{ $i }}][qualification_education]" rows="2">{{ $row['qualification_education'] }}</textarea>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1">Training</label>
                                <textarea class="form-control form-control-sm" name="rows[{{ $i }}][qualification_training]" rows="2">{{ $row['qualification_training'] }}</textarea>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1">Experience</label>
                                <textarea class="form-control form-control-sm" name="rows[{{ $i }}][qualification_experience]" rows="2">{{ $row['qualification_experience'] }}</textarea>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1">Eligibility</label>
                                <textarea class="form-control form-control-sm" name="rows[{{ $i }}][qualification_eligibility]" rows="2">{{ $row['qualification_eligibility'] }}</textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-muted mb-1">Duties and responsibilities</label>
                                <textarea class="form-control form-control-sm" name="rows[{{ $i }}][duties_responsibilities]" rows="2">{{ $row['duties_responsibilities'] }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endforeach

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn" style="background-color: var(--hr-primary); color: #fff;">
            <i class="bi bi-check-lg me-1"></i> Import selected postings
        </button>
        <a href="{{ route('job-postings.import.create') }}" class="btn btn-outline-secondary">Cancel and discard</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('.select-all-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const group = btn.getAttribute('data-group');
            document.querySelectorAll('.candidate-row[data-group="' + group + '"] input[type="checkbox"]').forEach(function (cb) {
                cb.checked = true;
            });
        });
    });

    document.querySelectorAll('.deselect-all-btn').forEach(function (btn) {
        btn.addEventListener('click', function (event) {
            event.stopPropagation();
            const group = btn.getAttribute('data-group');
            document.querySelectorAll('.candidate-row[data-group="' + group + '"] input[type="checkbox"]').forEach(function (cb) {
                cb.checked = false;
            });
        });
    });

    // Prevent the select-all/deselect-all buttons from also toggling the
    // collapse, since they sit inside the clickable card-header.
    document.querySelectorAll('.select-all-btn, .deselect-all-btn').forEach(function (btn) {
        btn.addEventListener('click', function (event) {
            event.stopPropagation();
        });
    });
</script>
@endpush
REVIEWCODE;
file_put_contents($reviewPath, $reviewContent);
echo "Created resources/views/job-postings/import/review.blade.php
";

// --- 8. Patch routes/web.php: add review + confirm routes ---
$routesPath = $root . '/routes/web.php';
$routesContent = file_get_contents($routesPath);
if ($routesContent === false) { die_loud("Could not read $routesPath"); }

$routesOld = <<<'ROUTES_OLD'
Route::post('/job-postings/import/extract', [JobPostingImportController::class, 'extract'])->name('job-postings.import.extract');
ROUTES_OLD;

$routesNew = <<<'ROUTES_NEW'
Route::post('/job-postings/import/extract', [JobPostingImportController::class, 'extract'])->name('job-postings.import.extract');
Route::get('/job-postings/import/{batch}/review', [JobPostingImportController::class, 'review'])->name('job-postings.import.review');
Route::post('/job-postings/import/{batch}/confirm', [JobPostingImportController::class, 'confirm'])->name('job-postings.import.confirm');
ROUTES_NEW;

$routesContent = apply_patch($routesContent, $routesOld, $routesNew, 'import-review-confirm-routes');

backup_file($routesPath);
file_put_contents($routesPath, $routesContent);
echo "Updated routes/web.php
";

echo "\nDone.\n";
echo "Next steps:\n";
echo "  1. Run: php artisan migrate\n";
echo "     (creates the pdf_import_batches temp table)\n";
echo "  2. Visit /job-postings and click \"Import from PDF\".\n";
echo "  3. Upload one of your real Call for Application PDFs.\n";
echo "  4. You should land directly on a review screen, grouped by detected\n";
echo "     position, with checkboxes and editable fields for every candidate\n";
echo "     row -- including one row per school for table-type positions.\n";
echo "  5. Try unchecking a few rows, editing a field, then clicking\n";
echo "     \"Import selected postings\" -- confirm the right number of new\n";
echo "     Job Postings appear (as Draft status) and unchecked rows did NOT\n";
echo "     get created.\n";
echo "  6. Check a position with a real school table specifically -- confirm\n";
echo "     each school became its own posting row with vacancies=1, and the\n";
echo "     place_of_assignment text looks correct (school, adopted school if\n";
echo "     any, municipality in parentheses).\n";
echo "  7. Delete this script once confirmed.\n";
