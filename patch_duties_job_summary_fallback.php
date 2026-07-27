<?php
/**
 * Patch: make PositionBlockDetector::extractDuties() also recognize
 * "Job Summary:" as a duties label, not just "Duties and Responsibilities:".
 *
 * Run once from the project root:
 *   php patch_duties_job_summary_fallback.php
 * Then delete this file.
 *
 * Confirmed real case: OSDS-2025-0132 (DepEd Cavite "Call for Application
 * for Various Non-Teaching Positions"). Every position block in that memo
 * (Medical Officer II, Administrative Officer IV, Administrative
 * Assistant III, etc.) uses "Job Summary:" in place of "Duties and
 * Responsibilities:". extractLabeledField()'s stopLabels list already
 * anticipated "Job Summary" as a section heading (so Eligibility/other
 * fields correctly stop before it), but extractDuties() itself only ever
 * checked for the "Duties and Responsibilities" label. Result: every
 * position in this memo silently imported with duties_responsibilities
 * = null, with no error.
 *
 * Fix: extractDuties() tries "Duties and Responsibilities" first
 * (existing behavior, unchanged), then falls back to "Job Summary" if
 * that's not found, so both memo styles resolve correctly.
 */

function apply_patch(string $path, array $edits): void
{
    if (!file_exists($path)) {
        fwrite(STDERR, "ABORT: file not found: $path\n");
        exit(1);
    }

    $original = file_get_contents($path);
    $working = $original;

    foreach ($edits as $i => [$search, $replace, $label]) {
        $count = substr_count($working, $search);
        if ($count !== 1) {
            fwrite(STDERR, "ABORT: edit #$i ($label) matched $count times (expected exactly 1) in $path\n");
            fwrite(STDERR, "No changes were written.\n");
            exit(1);
        }
        $working = str_replace($search, $replace, $working);
    }

    $backup = $path . '.bak';
    if (!copy($path, $backup)) {
        fwrite(STDERR, "ABORT: could not create backup at $backup\n");
        exit(1);
    }

    file_put_contents($path, $working);
    echo "Patched: $path\n";
    echo "Backup:  $backup\n";
}

// ── Adjust this path if your project layout differs ────────────────────
$detectorFile = __DIR__ . '/app/Services/PositionBlockDetector.php';

apply_patch($detectorFile, [
    [
        <<<'OLD'
    private function extractDuties(string $blockText, string $canonicalTitle): ?string
    {
        if (preg_match('/Duties and Responsibilities(?: OF [A-Z\s]+)?:?\s*(.*)/is', $blockText, $m)) {
            return $this->cleanDutiesText($m[1]);
        }

        return null;
    }
OLD,
        <<<'NEW'
    private function extractDuties(string $blockText, string $canonicalTitle): ?string
    {
        // Confirmed real case (OSDS-2025-0132): this memo's positions use
        // "Job Summary:" in place of "Duties and Responsibilities:" —
        // extractLabeledField()'s stopLabels list already anticipated
        // "Job Summary" as a section heading, but duties extraction itself
        // never checked for it, so every block in this memo silently came
        // back with duties_responsibilities = null. Try the standard label
        // first (existing behavior unchanged), then fall back to "Job
        // Summary" so both memo styles work.
        if (preg_match('/Duties and Responsibilities(?: OF [A-Z\s]+)?:?\s*(.*)/is', $blockText, $m)) {
            return $this->cleanDutiesText($m[1]);
        }

        if (preg_match('/Job Summary:?\s*(.*)/is', $blockText, $m)) {
            return $this->cleanDutiesText($m[1]);
        }

        return null;
    }
NEW,
        'extractDuties(): add Job Summary fallback label',
    ],
]);

echo "\nDone. Diff and test an import (e.g. OSDS-2025-0132) before deleting this script and its .bak backups.\n";
