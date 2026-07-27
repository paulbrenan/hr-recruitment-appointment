<?php

/**
 * patch_import_confirm.php
 *
 * WHAT THIS DOES:
 *   Fixes JobPostingImportController::confirm() so that importing a PDF
 *   actually creates job_posting_locations rows (place of assignment +
 *   vacancies per school) instead of only writing the legacy
 *   place_of_assignment column.
 *
 *   Root cause: confirm() never called JobPostingLocation::create() —
 *   it just dumped everything into the flat place_of_assignment column
 *   and dropped all the location_place[] data on the floor.
 *
 *   Also fixes:
 *   - 'status' => 'draft' replaced with 'open' (draft was removed from enum)
 *   - memo_pdf_path now copied from the batch to the posting
 *   - employment_type defaults to 'Regular' (kept)
 *   - Each unique school name in location_place[] becomes one
 *     job_posting_locations row; duplicate school names are counted
 *     as extra vacancies for that school (matching the review blade's
 *     "Duplicate rows = more vacancies for that school" note)
 *   - The top-level vacancies field from the review form is used as a
 *     fallback if no location rows were submitted
 *
 * HOW TO RUN:
 *   php patch_import_confirm.php    (from project root)
 *   No migration needed.
 *
 * DELETE this script after running.
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
    if ($count === 0) {
        echo "\n❌ PATCH ABORTED — content not found in $path\nLabel: $label\nSearched for:\n---\n$old\n---\n";
        exit(1);
    }
    if ($count > 1) {
        echo "\n❌ PATCH ABORTED — pattern found $count times (expected 1) in $path\nLabel: $label\n";
        exit(1);
    }
    backup($path);
    file_put_contents($path, str_replace($old, $new, $content));
    echo "  [ok ] $label\n";
}

echo "\n=== patch_import_confirm.php ===\n\n";

$controllerPath = ROOT . '/app/Http/Controllers/JobPostingImportController.php';

// 1. Add JobPostingLocation use statement
apply_patch(
    $controllerPath,
    "use App\Models\JobPosting;\nuse App\Models\PdfImportBatch;",
    "use App\Models\JobPosting;\nuse App\Models\JobPostingLocation;\nuse App\Models\PdfImportBatch;",
    'Controller: add JobPostingLocation use statement'
);

// 2. Replace the entire confirm() method
apply_patch(
    $controllerPath,
    '    // ── Confirm: bulk-create real job_postings from checked candidates ────────
    public function confirm(Request $request, $batchId)
    {
        $batch = PdfImportBatch::findOrFail($batchId);

        $validated = $request->validate([
            \'selected\' => [\'nullable\', \'array\'],
            \'selected.*\' => [\'integer\'],
            \'rows\' => [\'required\', \'array\'],
        ]);

        $selectedIndexes = array_flip($validated[\'selected\'] ?? []);
        $editedRows = $validated[\'rows\'];

        $created = 0;

        foreach ($editedRows as $index => $rowData) {
            if (!isset($selectedIndexes[$index])) {
                continue;
            }

            JobPosting::create([
                \'title\' => $rowData[\'title\'],
                \'salary_grade\' => $rowData[\'salary_grade\'] ?? null,
                \'qualification_education\' => $rowData[\'qualification_education\'] ?? null,
                \'qualification_training\' => $rowData[\'qualification_training\'] ?? null,
                \'qualification_experience\' => $rowData[\'qualification_experience\'] ?? null,
                \'qualification_eligibility\' => $rowData[\'qualification_eligibility\'] ?? null,
                \'duties_responsibilities\' => $rowData[\'duties_responsibilities\'] ?? null,
                \'place_of_assignment\' => $rowData[\'place_of_assignment\'] ?? null,
                \'vacancies\' => max(1, (int) ($rowData[\'vacancies\'] ?? 1)),
                \'employment_type\' => \'Regular\',
                \'status\' => \'draft\',
            ]);

            $created++;
        }

        $batch->delete();

        return redirect()
            ->route(\'job-postings.index\')
            ->with(\'success\', "Imported {$created} job posting(s) from PDF.");
    }',
    '    // ── Confirm: bulk-create real job_postings from checked candidates ────────
    public function confirm(Request $request, $batchId)
    {
        $batch = PdfImportBatch::findOrFail($batchId);

        $validated = $request->validate([
            \'selected\'    => [\'nullable\', \'array\'],
            \'selected.*\'  => [\'integer\'],
            \'rows\'        => [\'required\', \'array\'],
        ]);

        $selectedIndexes = array_flip($validated[\'selected\'] ?? []);
        $editedRows      = $validated[\'rows\'];
        $created         = 0;

        foreach ($editedRows as $index => $rowData) {
            if (!isset($selectedIndexes[$index])) {
                continue;
            }

            // ── Build location list from location_place[] ─────────────────────
            // The review blade submits rows[i][location_place][] — an array of
            // school name strings. Duplicate school names count as extra vacancies
            // for that school (matching the review blade note).
            $locationPlaces = array_filter(
                array_map(\'trim\', (array) ($rowData[\'location_place\'] ?? [])),
                fn($s) => $s !== \'\'
            );

            // Count occurrences: school name → vacancy count
            $locationVacancies = [];
            foreach ($locationPlaces as $school) {
                $locationVacancies[$school] = ($locationVacancies[$school] ?? 0) + 1;
            }

            // Total vacancies = sum of all location rows, or fallback to the
            // manual vacancies field if no location rows were submitted.
            $totalVacancies = !empty($locationVacancies)
                ? array_sum($locationVacancies)
                : max(1, (int) ($rowData[\'vacancies\'] ?? 1));

            // First location name for the legacy column (kept for backwards compat)
            $legacyPlace = array_key_first($locationVacancies) ?? ($rowData[\'place_of_assignment\'] ?? null);

            // ── Create the JobPosting ─────────────────────────────────────────
            $posting = JobPosting::create([
                \'title\'                    => trim($rowData[\'title\']),
                \'salary_grade\'             => $rowData[\'salary_grade\'] ?? null,
                \'qualification_education\'  => $rowData[\'qualification_education\'] ?? null,
                \'qualification_training\'   => $rowData[\'qualification_training\'] ?? null,
                \'qualification_experience\' => $rowData[\'qualification_experience\'] ?? null,
                \'qualification_eligibility\'=> $rowData[\'qualification_eligibility\'] ?? null,
                \'duties_responsibilities\'  => $rowData[\'duties_responsibilities\'] ?? null,
                \'place_of_assignment\'      => $legacyPlace,
                \'vacancies\'               => $totalVacancies,
                \'employment_type\'         => \'Regular\',
                \'status\'                  => \'open\',
                \'memo_pdf_path\'           => $batch->memo_pdf_path ?? null,
                \'posted_at\'              => now()->toDateString(),
            ]);

            // ── Create one JobPostingLocation row per unique school ───────────
            foreach ($locationVacancies as $school => $vacCount) {
                JobPostingLocation::create([
                    \'job_posting_id\'      => $posting->id,
                    \'place_of_assignment\' => $school,
                    \'vacancies\'           => $vacCount,
                ]);
            }

            // If no location rows submitted at all, create one fallback row
            // using the legacy place_of_assignment so the posting still shows
            // something in the locations table.
            if (empty($locationVacancies) && $legacyPlace) {
                JobPostingLocation::create([
                    \'job_posting_id\'      => $posting->id,
                    \'place_of_assignment\' => $legacyPlace,
                    \'vacancies\'           => $totalVacancies,
                ]);
            }

            $created++;
        }

        $batch->delete();

        return redirect()
            ->route(\'job-postings.index\')
            ->with(\'success\', "Imported {$created} job posting(s) from PDF.");
    }',
    'Controller: confirm() creates JobPostingLocation rows + fixes status + memo_pdf_path'
);

echo <<<TEXT

✅ Done. No migration needed.

WHAT CHANGED IN confirm():
  - Reads location_place[] array from each submitted row
  - Counts duplicates → vacancy count per school
  - Creates one job_posting_locations row per unique school name
  - Total vacancies = sum of all location rows (not the manual field)
  - status: 'draft' → 'open'
  - memo_pdf_path copied from the batch to the posting
  - posted_at set to today automatically on import
  - Falls back gracefully if no location rows were submitted

FLOW AFTER THIS PATCH:
  Import PDF → review screen (edit fields) → confirm
  → job_postings row created (open, with title/SG/qualifications/duties)
  → job_posting_locations rows created (one per school with vacancy count)
  → posting appears correctly in index with all columns populated

DELETE this script after running.

TEXT;
