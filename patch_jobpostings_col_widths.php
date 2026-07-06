<?php

/**
 * patch_jobpostings_col_widths.php
 *
 * WHAT THIS DOES:
 *   Fixes column width distribution in the job postings index table.
 *   - Title: capped so it doesn't eat the whole left side
 *   - Place of assignment: given more room so locations don't wrap
 *   - Remaining columns (Employment type, SG, Posted, Closes, Status, Actions)
 *     set to shrink-wrap their content with nowrap
 *
 * HOW TO RUN:
 *   php patch_jobpostings_col_widths.php    (from project root)
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

echo "\n=== patch_jobpostings_col_widths.php ===\n\n";

$bladePath = ROOT . '/resources/views/job-postings/index.blade.php';

// 1. Add a <colgroup> right after <table ...> to set explicit column widths
apply_patch(
    $bladePath,
    '<table class="table align-top mb-0" style="vertical-align: top;">
            <thead>',
    '<table class="table align-top mb-0" style="vertical-align: top; table-layout: fixed; width: 100%;">
            <colgroup>
                <col style="width: 22%;">  {{-- Title --}}
                <col style="width: 28%;">  {{-- Place of assignment --}}
                <col style="width: 10%;">  {{-- Employment type --}}
                <col style="width: 6%;">   {{-- SG --}}
                <col style="width: 9%;">   {{-- Posted --}}
                <col style="width: 9%;">   {{-- Closes --}}
                <col style="width: 9%;">   {{-- Status --}}
                <col style="width: 7%;">   {{-- Actions --}}
            </colgroup>
            <thead>',
    'table: add colgroup with explicit width distribution'
);

// 2. thead cells — add nowrap to the narrow ones so headers don't wrap awkwardly
apply_patch(
    $bladePath,
    '                    <th>Title</th>
                    <th>Place of assignment</th>
                    <th>Employment type</th>
                    <th class="text-nowrap">SG</th>
                    {{-- Vacancies now shown per-location in the Places column --}}
                    <th>Posted</th>
                    <th>Closes</th>
                    <th>Status</th>
                    <th></th>',
    '                    <th>Title</th>
                    <th>Place of assignment</th>
                    <th class="text-nowrap">Employment type</th>
                    <th class="text-nowrap">SG</th>
                    {{-- Vacancies now shown per-location in the Places column --}}
                    <th class="text-nowrap">Posted</th>
                    <th class="text-nowrap">Closes</th>
                    <th>Status</th>
                    <th></th>',
    'thead: nowrap on narrow columns'
);

// 3. Title cell — allow wrapping but cap with overflow ellipsis as a last resort
apply_patch(
    $bladePath,
    '                    <td class="fw-medium">{{ $posting->title }}</td>',
    '                    <td class="fw-medium" style="word-break: break-word;">{{ $posting->title }}</td>',
    'title cell: word-break so long titles wrap cleanly within their column'
);

echo <<<TEXT

✅ Done. Hard-refresh the page (Ctrl+Shift+R).

WHAT CHANGED:
  - table-layout: fixed forces the browser to respect the colgroup widths
    instead of letting Title stretch to fill available space.
  - Title gets 22%, Place of assignment gets 28% — enough room for 2-location
    names to sit on one line each without wrapping.
  - Employment type, SG, Posted, Closes are text-nowrap so they stay on one line.
  - If you want to tweak the balance, adjust the <col style="width: ..."> values
    in the colgroup — they just need to add up to 100%.

DELETE this script after running.

TEXT;
