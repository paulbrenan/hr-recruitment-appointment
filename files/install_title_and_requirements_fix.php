<?php
/**
 * install_title_and_requirements_fix.php
 *
 * Two fixes for the PDF import pipeline. Run this from the SAME folder
 * where you placed the three companion service files:
 *   - JobTitleRegistrar.php       (new)
 *   - PositionBlockDetector.php   (revised, replaces existing)
 *   - RequirementsExtractor.php   (new)
 *
 * FIX 1 — Secondary/Elementary title variants no longer silently merge.
 *   When the detector finds a genuinely new prefixed variant (e.g.
 *   "Secondary School Principal III"), it now registers it as a real
 *   permanent entry in config/job_titles.php instead of collapsing it
 *   into the generic canonical title. This was causing real data loss
 *   (distinct postings collapsed into one) AND a save-blocking bug
 *   (editing an imported posting failed because its title didn't exist
 *   in the dropdown's validated option list).
 *
 * FIX 2 — Mandatory/Additional Requirements are now extracted from the
 *   real cover-memo text (pages 1-4) of each uploaded PDF instead of
 *   always using the hardcoded standard A-J default for every import.
 *
 * What this script does:
 *   1. Migration: adds 'requirements' and 'newly_registered_titles'
 *      JSON nullable columns to pdf_import_batches (confirmed via
 *      php artisan db:table this table currently has only 6 columns:
 *      id, original_filename, candidates, expires_at, created_at, updated_at)
 *   2. Installs JobTitleRegistrar.php
 *   3. Replaces PositionBlockDetector.php
 *   4. Installs RequirementsExtractor.php
 *   5. Patches JobPostingImportController.php (extract/review/confirm)
 *   6. Patches PdfImportBatch.php ($fillable + $casts)
 *
 * Usage:
 *   1. Place this script + the 3 companion .php files in the project root.
 *   2. php install_title_and_requirements_fix.php
 *   3. php artisan migrate
 *   4. Test an import end-to-end.
 *   5. Delete all 4 files + this script when confirmed working.
 */

function do_backup(string $path): void {
    if (!file_exists($path)) return;
    $bak = $path . '.bak';
    $i   = 2;
    while (file_exists($bak)) { $bak = $path . '.bak' . $i++; }
    file_put_contents($bak, file_get_contents($path));
    echo "  Backed up: $bak\n";
}

function apply_patch(string &$src, string $find, string $replace, string $label): void {
    $count = substr_count($src, $find);
    if ($count === 0) { die("ERROR [$label]: Target string not found — aborting, nothing written.\n"); }
    if ($count  > 1) { die("ERROR [$label]: Found $count matches (expected 1) — aborting.\n"); }
    $src = str_replace($find, $replace, $src);
    echo "  OK [$label]\n";
}

$root = __DIR__;

// ═════════════════════════════════════════════════════════════════════════════
// PART 1 — Migration: add requirements + newly_registered_titles columns
// ═════════════════════════════════════════════════════════════════════════════
echo "\n[1/6] Writing migration...\n";

$ts = date('Y_m_d_His');
$migPath = "$root/database/migrations/{$ts}_add_requirements_to_pdf_import_batches_table.php";

file_put_contents($migPath, <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdf_import_batches', function (Blueprint $table) {
            // Real Mandatory/Additional Requirements extracted from this
            // import's cover-memo pages (Fix 2). Nullable since older
            // batches (created before this fix) won't have it, and
            // extraction can legitimately find nothing in malformed PDFs.
            $table->json('requirements')->nullable()->after('candidates');

            // Any Secondary/Elementary title variants that were newly
            // registered into config/job_titles.php during this import
            // (Fix 1) — surfaced as a notice on the review screen so HR
            // staff knows a new dropdown option was just created.
            $table->json('newly_registered_titles')->nullable()->after('requirements');
        });
    }

    public function down(): void
    {
        Schema::table('pdf_import_batches', function (Blueprint $table) {
            $table->dropColumn(['requirements', 'newly_registered_titles']);
        });
    }
};
PHP);

echo "  Written: " . basename($migPath) . "\n";

// ═════════════════════════════════════════════════════════════════════════════
// PART 2 — Install JobTitleRegistrar.php
// ═════════════════════════════════════════════════════════════════════════════
echo "\n[2/6] Installing JobTitleRegistrar.php...\n";

$src1  = "$root/JobTitleRegistrar.php";
$dest1 = "$root/app/Services/JobTitleRegistrar.php";

if (!file_exists($src1)) {
    die("ERROR: JobTitleRegistrar.php not found next to this script. Make sure all 4 files are in the same folder.\n");
}
do_backup($dest1);
copy($src1, $dest1) or die("ERROR: Could not write $dest1\n");
echo "  Installed: app/Services/JobTitleRegistrar.php\n";

// ═════════════════════════════════════════════════════════════════════════════
// PART 3 — Replace PositionBlockDetector.php
// ═════════════════════════════════════════════════════════════════════════════
echo "\n[3/6] Replacing PositionBlockDetector.php...\n";

$src2  = "$root/PositionBlockDetector.php";
$dest2 = "$root/app/Services/PositionBlockDetector.php";

if (!file_exists($src2)) {
    die("ERROR: PositionBlockDetector.php not found next to this script.\n");
}
if (!file_exists($dest2)) {
    die("ERROR: app/Services/PositionBlockDetector.php doesn't exist — expected it already there.\n");
}
do_backup($dest2);
copy($src2, $dest2) or die("ERROR: Could not write $dest2\n");
echo "  Replaced: app/Services/PositionBlockDetector.php\n";

// ═════════════════════════════════════════════════════════════════════════════
// PART 4 — Install RequirementsExtractor.php
// ═════════════════════════════════════════════════════════════════════════════
echo "\n[4/6] Installing RequirementsExtractor.php...\n";

$src3  = "$root/RequirementsExtractor.php";
$dest3 = "$root/app/Services/RequirementsExtractor.php";

if (!file_exists($src3)) {
    die("ERROR: RequirementsExtractor.php not found next to this script.\n");
}
do_backup($dest3);
copy($src3, $dest3) or die("ERROR: Could not write $dest3\n");
echo "  Installed: app/Services/RequirementsExtractor.php\n";

// ═════════════════════════════════════════════════════════════════════════════
// PART 5 — Patch JobPostingImportController.php
// ═════════════════════════════════════════════════════════════════════════════
echo "\n[5/6] Patching JobPostingImportController.php...\n";

$ctrlPath = "$root/app/Http/Controllers/JobPostingImportController.php";
if (!file_exists($ctrlPath)) { die("ERROR: Cannot find JobPostingImportController.php\n"); }
do_backup($ctrlPath);

$ctrl = file_get_contents($ctrlPath);

apply_patch(
    $ctrl,
    "use App\Services\PositionBlockExpander;",
    "use App\Services\PositionBlockExpander;\nuse App\Services\RequirementsExtractor;",
    'add RequirementsExtractor use statement'
);

apply_patch(
    $ctrl,
    <<<'OLD'
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
OLD,
    <<<'NEW'
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

        // ── 5b. Track any newly-registered Secondary/Elementary title
        //        variants so the review screen can surface a notice —
        //        these are now PERMANENT entries in config/job_titles.php,
        //        not silently merged into a generic title as before.
        $newlyRegisteredTitles = collect($blocks)
            ->filter(fn ($b) => !empty($b['was_registered']))
            ->pluck('canonical_title')
            ->unique()
            ->values()
            ->all();

        // ── 6. Expand each block into flat per-row candidates ─────────────────
        $expander = new PositionBlockExpander();
        $candidates = $expander->expand($blocks);

        // ── 6b. Extract the REAL Mandatory/Additional Requirements from
        //        this document's cover-memo pages, instead of always
        //        falling back to the hardcoded standard A-J default.
        $requirementsExtractor = new RequirementsExtractor();
        $requirements = $requirementsExtractor->extract($pageTexts);

        // ── 7. Store as a temporary batch for the review screen ───────────────
        $batch = PdfImportBatch::create([
            'original_filename' => $originalName,
            'candidates' => $candidates,
            'requirements' => $requirements,
            'newly_registered_titles' => $newlyRegisteredTitles,
            'expires_at' => now()->addDay(),
        ]);

        return redirect()->route('job-postings.import.review', $batch->id);
NEW,
    'add requirements extraction + title-registration tracking to extract()'
);

apply_patch(
    $ctrl,
    <<<'OLD'
        return view('job-postings.import.review', [
            'batch' => $batch,
            'grouped' => $grouped,
        ]);
OLD,
    <<<'NEW'
        return view('job-postings.import.review', [
            'batch' => $batch,
            'grouped' => $grouped,
            'requirements' => $batch->requirements ?? ['mandatory' => [], 'additional' => ''],
            'newlyRegisteredTitles' => $batch->newly_registered_titles ?? [],
        ]);
NEW,
    'pass requirements + newly registered titles to review view'
);

apply_patch(
    $ctrl,
    <<<'OLD'
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
OLD,
    <<<'NEW'
        $selectedIndexes = array_flip($validated['selected'] ?? []);
        $editedRows = $validated['rows'];

        // Real requirements extracted from THIS document's cover memo
        // (Fix 2) — applied to every posting created from this import.
        // No more silent fallback to the old hardcoded standard A-J
        // default: if extraction found nothing for this particular PDF,
        // these fields are simply left null, same as any manually
        // created posting where HR hasn't filled them in yet.
        $extractedRequirements = $batch->requirements ?? ['mandatory' => [], 'additional' => ''];
        $mandatoryText = !empty($extractedRequirements['mandatory'])
            ? implode("\n", $extractedRequirements['mandatory'])
            : null;
        $additionalText = !empty($extractedRequirements['additional'])
            ? $extractedRequirements['additional']
            : null;

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
                'mandatory_requirements' => $mandatoryText,
                'additional_requirements' => $additionalText,
                'vacancies' => max(1, (int) ($rowData['vacancies'] ?? 1)),
                'employment_type' => 'Regular',
                'status' => 'draft',
            ]);

            $created++;
        }
NEW,
    'apply real extracted requirements to created job_postings in confirm()'
);

file_put_contents($ctrlPath, $ctrl);
echo "  Patched: JobPostingImportController.php\n";

// ═════════════════════════════════════════════════════════════════════════════
// PART 6 — Patch PdfImportBatch.php ($fillable + $casts)
// ═════════════════════════════════════════════════════════════════════════════
echo "\n[6/6] Patching PdfImportBatch.php...\n";

$batchPath = "$root/app/Models/PdfImportBatch.php";
if (!file_exists($batchPath)) { die("ERROR: Cannot find app/Models/PdfImportBatch.php\n"); }
do_backup($batchPath);

$batchModel = file_get_contents($batchPath);

apply_patch(
    $batchModel,
    <<<'OLD'
    protected $fillable = [
        'original_filename',
        'candidates',
        'expires_at',
    ];

    protected $casts = [
        'candidates' => 'array',
        'expires_at' => 'datetime',
    ];
OLD,
    <<<'NEW'
    protected $fillable = [
        'original_filename',
        'candidates',
        'requirements',
        'newly_registered_titles',
        'expires_at',
    ];

    protected $casts = [
        'candidates' => 'array',
        'requirements' => 'array',
        'newly_registered_titles' => 'array',
        'expires_at' => 'datetime',
    ];
NEW,
    'add requirements + newly_registered_titles to $fillable and $casts'
);

file_put_contents($batchPath, $batchModel);
echo "  Patched: app/Models/PdfImportBatch.php\n";

// ═════════════════════════════════════════════════════════════════════════════
echo "\n✓ Both fixes installed.\n";
echo "\nNext steps:\n";
echo "  1. php artisan migrate\n";
echo "       → adds 'requirements' and 'newly_registered_titles' columns to pdf_import_batches\n";
echo "  2. Upload a sample PDF via /job-postings/import and confirm:\n";
echo "       - Secondary/Elementary variants (if present) get registered as real\n";
echo "         dropdown options, not silently merged\n";
echo "       - The real Mandatory Requirements (A-J) from the memo appear on each\n";
echo "         created posting, not the old hardcoded default\n";
echo "       - The Additional Requirements text block (if present) is also populated\n";
echo "  3. Try editing one of the imported postings afterward — title save should\n";
echo "     no longer fail validation, since the variant now exists in config/job_titles.php\n";
echo "\n  Delete this script + the 3 companion .php files when confirmed working.\n";
