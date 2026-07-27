<?php
/**
 * patch_add_location_id_attribute.php
 *
 * Root cause of "location filter dropdown does nothing": the qualLocationFilter
 * JS in show.blade.php queries `#panel-2 [data-location-id]` — but the
 * applicant row markup never actually had a data-location-id attribute on
 * it. The filter logic was fine; it just had nothing to filter.
 *
 * Fix: add data-location-id="{{ $app->job_posting_location_id }}" to the
 * row wrapper div. Rows with a null location render data-location-id=""
 * which the existing JS already treats as "match every filter" (see the
 * `!rowLoc || rowLoc === 'null'` check from the earlier location-filter fix).
 *
 * HOW TO RUN:
 *   php patch_add_location_id_attribute.php    (from project root)
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

echo "\n=== patch_add_location_id_attribute.php ===\n\n";

$showPath = ROOT . '/resources/views/job-postings/show.blade.php';

apply_patch(
    $showPath,
    '                        <div class="border rounded p-3 mb-2 d-flex justify-content-between align-items-center flex-wrap gap-2" style="font-size:0.875rem;">',
    '                        <div class="border rounded p-3 mb-2 d-flex justify-content-between align-items-center flex-wrap gap-2" style="font-size:0.875rem;" data-location-id="{{ $app->job_posting_location_id }}">',
    'show.blade.php: add data-location-id to applicant row for the filter to match against'
);

echo <<<TEXT

✅ Patch applied.

WHAT CHANGED:
  Each applicant row in the Qualification Checking step now carries
  data-location-id="{{ \$app->job_posting_location_id }}" — the attribute
  the filter dropdown's JS was already looking for but never found.

DELETE this script after running.

TEXT;
