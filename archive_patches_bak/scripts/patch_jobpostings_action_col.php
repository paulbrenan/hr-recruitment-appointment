<?php

/**
 * patch_jobpostings_action_col.php
 *
 * WHAT THIS DOES:
 *   Fixes the hidden delete button in the job postings table caused by the
 *   actions column being too narrow (7%) after table-layout: fixed was applied.
 *
 *   - Widens the actions column to fit all three buttons (eye, pencil, trash)
 *   - Compensates by slightly narrowing title and place-of-assignment
 *
 * HOW TO RUN:
 *   php patch_jobpostings_action_col.php    (from project root)
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

echo "\n=== patch_jobpostings_action_col.php ===\n\n";

$bladePath = ROOT . '/resources/views/job-postings/index.blade.php';

apply_patch(
    $bladePath,
    '            <colgroup>
                <col style="width: 22%;">  {{-- Title --}}
                <col style="width: 28%;">  {{-- Place of assignment --}}
                <col style="width: 10%;">  {{-- Employment type --}}
                <col style="width: 6%;">   {{-- SG --}}
                <col style="width: 9%;">   {{-- Posted --}}
                <col style="width: 9%;">   {{-- Closes --}}
                <col style="width: 9%;">   {{-- Status --}}
                <col style="width: 7%;">   {{-- Actions --}}
            </colgroup>',
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
    'colgroup: widen actions column to 16%, rebalance others'
);

echo <<<TEXT

✅ Done. Hard-refresh the page (Ctrl+Shift+R).

Actions column is now 16% — enough to show all three buttons (eye, pencil,
trash) without clipping. Title and Place of assignment each gave up 2-3%.

DELETE this script after running.

TEXT;
