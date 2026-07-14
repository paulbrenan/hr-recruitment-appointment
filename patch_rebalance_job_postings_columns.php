<?php
/**
 * patch_rebalance_job_postings_columns.php
 *
 * Rebalances the job postings table column widths. The Actions column
 * was allocated 16% but only needs ~11% for its 3 icon buttons (right-
 * aligned), leaving visible dead space between the Status badges and the
 * action icons. Reclaims that space for Title and Place of assignment,
 * which currently wrap awkwardly (e.g. "School Sports Program Focal
 * Person (Contract of Service)", "On-the-Job Trainee").
 *
 * Before -> After:
 *   Title:              20% -> 22%
 *   Place of assignment 25% -> 28%
 *   Employment type:     10% -> 10% (unchanged, needed for "On-the-Job Trainee")
 *   SG:                   5% ->  5% (unchanged, "SG-33" fits easily)
 *   Posted:               8% ->  8% (unchanged)
 *   Closes:               8% ->  8% (unchanged)
 *   Status:               8% ->  8% (unchanged)
 *   Actions:             16% -> 11% (reclaimed 5%)
 *
 * HOW TO RUN:
 *   php patch_rebalance_job_postings_columns.php    (from project root)
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
    if ($count === 0) { echo "\n❌ PATCH ABORTED — not found in:\n  $path\nLabel: $label\n"; exit(1); }
    if ($count > 1)   { echo "\n❌ PATCH ABORTED — found $count times in:\n  $path\nLabel: $label\n"; exit(1); }
    backup($path);
    file_put_contents($path, str_replace($old, $new, $content));
    echo "  [ok ] $label\n";
}

echo "\n=== patch_rebalance_job_postings_columns.php ===\n\n";

$indexPath = ROOT . '/resources/views/job-postings/index.blade.php';

apply_patch(
    $indexPath,
    '            <colgroup>
                <col style="width: 20%;">  {{-- Title --}}
                <col style="width: 25%;">  {{-- Place of assignment --}}
                <col style="width: 10%;">  {{-- Employment type --}}
                <col style="width: 5%;">   {{-- SG --}}
                <col style="width: 8%;">   {{-- Posted --}}
                <col style="width: 8%;">   {{-- Closes --}}
                <col style="width: 8%;">   {{-- Status --}}
                <col style="width: 16%;">  {{-- Actions — wide enough for 3 buttons --}}
            </colgroup>',
    '            <colgroup>
                <col style="width: 22%;">  {{-- Title --}}
                <col style="width: 28%;">  {{-- Place of assignment --}}
                <col style="width: 10%;">  {{-- Employment type --}}
                <col style="width: 5%;">   {{-- SG --}}
                <col style="width: 8%;">   {{-- Posted --}}
                <col style="width: 8%;">   {{-- Closes --}}
                <col style="width: 8%;">   {{-- Status --}}
                <col style="width: 11%;">  {{-- Actions — tightened to fit 3 buttons without excess dead space --}}
            </colgroup>',
    'index.blade.php: rebalance column widths — Actions 16%→11%, Title 20%→22%, Place of assignment 25%→28%'
);

echo <<<TEXT

✅ Patch applied.

WHAT CHANGED:
  Actions column narrowed from 16% to 11% (was leaving visible dead space
  before the icon buttons). That reclaimed 5% is split between Title
  (+2%) and Place of assignment (+3%), which should reduce awkward
  wrapping on longer titles/place names.

DELETE this script after running.

TEXT;
