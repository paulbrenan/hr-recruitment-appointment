<?php
/**
 * patch_remove_eligibility_picker_keep_ranking.php
 *
 * Step 5 currently has TWO ways to pick candidates for orientation:
 *   1. "Schedule orientation for new candidate(s)" -- a bulk checkbox
 *      table scoped to $eligibleOrientationApplications (only
 *      auto-eligible statuses), with its own date/time/place/submit form.
 *   2. "Candidate ranking" -- shows ALL $rankedCandidates, greys out
 *      already-scheduled ones, and opens a per-candidate modal
 *      (#scheduleOrientationModal) to schedule one at a time.
 *
 * This removes #1 entirely (heading, empty-state messages, bulk
 * checkbox table, its form, its pagination, its JS) and keeps #2 as the
 * only picker -- it's self-contained via its own modal, so nothing else
 * needs to change for scheduling to keep working.
 *
 * Usage: php patch_remove_eligibility_picker_keep_ranking.php
 */

function apply_patch(string $file, string $search, string $replace, string $label): void
{
    if (!file_exists($file)) {
        echo "[ABORT] File not found: {$file}\n";
        exit(1);
    }

    $contents = file_get_contents($file);

    if (strpos($contents, $search) === false) {
        echo "[ABORT] {$label}: expected text not found in {$file}. File may have drifted — aborting without changes.\n";
        exit(1);
    }

    if (substr_count($contents, $search) > 1) {
        echo "[ABORT] {$label}: expected text is not unique in {$file}. Aborting to avoid ambiguous edit.\n";
        exit(1);
    }

    $backup = $file . '.bak';
    if (!file_exists($backup)) {
        copy($file, $backup);
    }

    $new_contents = str_replace($search, $replace, $contents);
    file_put_contents($file, $new_contents);

    echo "[OK] {$label} applied to {$file}\n";
}

// Adjust this if the live path differs from the Laravel default.
$view = __DIR__ . '/resources/views/job-postings/show.blade.php';

$blockToRemove = file_get_contents(__DIR__ . '/patch_remove_eligibility_picker_block.txt');

if ($blockToRemove === false) {
    echo "[ABORT] Companion file patch_remove_eligibility_picker_block.txt not found next to this script.\n";
    echo "        It must be in the same directory as patch_remove_eligibility_picker_keep_ranking.php before running it.\n";
    exit(1);
}

apply_patch($view, $blockToRemove, '', 'Remove the eligibility-based bulk picker section');

echo "\nDone. Verify:\n";
echo " - Step 5 no longer shows 'Schedule orientation for new candidate(s)' or its empty-state messages.\n";
echo " - The 'Candidate ranking' table (with per-row 'Schedule' buttons opening a modal) still works exactly as before -- untouched.\n";
echo " - Existing orientation schedules table at the top of Step 5 is unaffected.\n";
