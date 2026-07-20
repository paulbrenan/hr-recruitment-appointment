<?php
/**
 * fix_import_requirements_not_saved.php
 *
 * Root cause: JobPostingImportController::confirm() deliberately never
 * copies mandatory/additional requirements onto the created JobPosting
 * rows. The comment left in that method reasoned this was fine because
 * "JobPosting::mandatoryRequirementsList()/additionalRequirementsList()
 * fall back to the same static defaults at display time" -- but no such
 * accessor methods exist anywhere in this codebase. The MANUAL posting
 * form (JobPostingController::create(), a few lines away in the sibling
 * controller) proves the actual pattern this app uses: the raw text is
 * stored directly on the mandatory_requirements/additional_requirements
 * columns, e.g.:
 *
 *   $posting->mandatory_requirements = implode("\n", self::DEFAULT_MANDATORY_REQUIREMENTS);
 *
 * So imported postings were just left with null requirements columns,
 * with nothing anywhere actually falling back to defaults for them --
 * hence "requirements not added automatically" after import.
 *
 * Fix: use $batch->requirements (already correctly extracted by
 * RequirementsExtractor and wired through ProcessPdfImportJob) to
 * populate both columns on each created posting, same as the manual
 * form does.
 *
 * Run once from the project root:
 *   php fix_import_requirements_not_saved.php
 * Then delete this file.
 */

function apply_patch($path, $old, $new, $label) {
    if (!file_exists($path)) {
        fwrite(STDERR, "[ABORT] File not found: $path ($label)\n");
        exit(1);
    }
    $contents = file_get_contents($path);
    if (strpos($contents, $old) === false) {
        fwrite(STDERR, "[ABORT] Expected content not found for: $label\n");
        fwrite(STDERR, "        File may already be patched or is a different version. No changes made.\n");
        exit(1);
    }
    copy($path, $path . '.bak');
    $updated = str_replace($old, $new, $contents, $count);
    if ($count !== 1) {
        fwrite(STDERR, "[ABORT] Expected exactly 1 match for '$label', found $count. Restoring backup.\n");
        copy($path . '.bak', $path);
        exit(1);
    }
    file_put_contents($path, $updated);
    echo "[OK] $label\n";
}

$importCtrl = __DIR__ . '/app/Http/Controllers/JobPostingImportController.php';

// ── 1. Remove the stale "not copied here anymore" comment, replace with
//       an actual extraction of the batch's parsed requirements ────────

$old1 = <<<'OLD'
        // Mandatory/additional requirements are NOT copied onto each
        // created posting here anymore. RequirementsExtractor always
        // returns the same static DepEd-standard text regardless of which
        // PDF was uploaded, so duplicating that block into every single
        // imported JobPosting row's mandatory_requirements/
        // additional_requirements columns just bloats storage for no
        // benefit. These columns stay null on import (same as a manual
        // posting HR hasn't filled them in for yet) — JobPosting::
        // mandatoryRequirementsList()/additionalRequirementsList() fall
        // back to the same static defaults at display time instead, from
        // one source of truth.
OLD;

$new1 = <<<'NEW'
        // Mandatory/additional requirements, parsed by RequirementsExtractor
        // during OCR (see ProcessPdfImportJob) and stored on the batch.
        // No model-level fallback exists for these columns anywhere in
        // this app -- the manual posting form
        // (JobPostingController::create()) stores this same text
        // directly on the column, so imported postings need to do the
        // same or they're left with nothing.
        $mandatoryRequirementsText = implode("\n", $batch->requirements['mandatory'] ?? []);
        $additionalRequirementsText = $batch->requirements['additional'] ?? '';
NEW;

apply_patch($importCtrl, $old1, $new1, 'confirm(): extract mandatory/additional requirements text from $batch->requirements');

// ── 2. Actually save them on each created posting ────────────────────────

$old2 = <<<'OLD'
            $posting = JobPosting::create([
                'title' => $title,
                'salary_grade' => $rowData['salary_grade'] ?? null,
                'qualification_education' => $rowData['qualification_education'] ?? null,
                'qualification_training' => $rowData['qualification_training'] ?? null,
                'qualification_experience' => $rowData['qualification_experience'] ?? null,
                'qualification_eligibility' => $rowData['qualification_eligibility'] ?? null,
                'duties_responsibilities' => $rowData['duties_responsibilities'] ?? null,
                // Legacy single-location columns, kept in sync from the
                // first entered location -- same convention as
                // syncLocations() uses on the manual job posting form.
                'place_of_assignment' => $firstPlace,
                'memo_pdf_path' => $memoPdfPath,
                'vacancies' => $totalVacancies,
                'employment_type' => 'Regular',
                'status' => 'open',
            ]);
OLD;

$new2 = <<<'NEW'
            $posting = JobPosting::create([
                'title' => $title,
                'salary_grade' => $rowData['salary_grade'] ?? null,
                'qualification_education' => $rowData['qualification_education'] ?? null,
                'qualification_training' => $rowData['qualification_training'] ?? null,
                'qualification_experience' => $rowData['qualification_experience'] ?? null,
                'qualification_eligibility' => $rowData['qualification_eligibility'] ?? null,
                'duties_responsibilities' => $rowData['duties_responsibilities'] ?? null,
                'mandatory_requirements' => $mandatoryRequirementsText,
                'additional_requirements' => $additionalRequirementsText,
                // Legacy single-location columns, kept in sync from the
                // first entered location -- same convention as
                // syncLocations() uses on the manual job posting form.
                'place_of_assignment' => $firstPlace,
                'memo_pdf_path' => $memoPdfPath,
                'vacancies' => $totalVacancies,
                'employment_type' => 'Regular',
                'status' => 'open',
            ]);
NEW;

apply_patch($importCtrl, $old2, $new2, "confirm(): save mandatory_requirements/additional_requirements on each created posting");

echo "\nDone. Imported postings now get mandatory_requirements/additional_requirements\n";
echo "populated from the scanned PDF's parsed data, same as the manual posting form.\n";
