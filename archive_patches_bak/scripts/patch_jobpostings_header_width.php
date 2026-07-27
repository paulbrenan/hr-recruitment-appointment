<?php
/**
 * Patch: Employment type header still shows "Employment ..." because
 * the column is too narrow (10%) even though the previous patch fixed
 * the overlap. This patch:
 *   1. Widens Employment type (10% -> 12%) and SG (5% -> 7%), taking
 *      the slack from Title (35% -> 32%, which has room to spare).
 *   2. Lets the "Employment type" header wrap onto two lines instead
 *      of truncating with an ellipsis.
 *
 * Run once from the project root:
 *   php patch_jobpostings_header_width.php
 * Then delete this file.
 *
 * Requires patch_jobpostings_header_overlap.php to have been applied first.
 */

$target = __DIR__ . '/resources/views/job-postings/index.blade.php';

if (!file_exists($target)) {
    fwrite(STDERR, "ABORT: File not found at expected path:\n  $target\n");
    fwrite(STDERR, "Edit \$target at the top of this script to point at the correct view file, then re-run.\n");
    exit(1);
}

$original = file_get_contents($target);

$edits = [
    // 1. Widen colgroup columns
    [
        'search' => <<<'BLADE'
                <col style="width: 35%;">  {{-- Title --}}
                <col style="width: 10%;">  {{-- Vacancies --}}
                <col style="width: 10%;">  {{-- Employment type --}}
                <col style="width: 5%;">   {{-- SG --}}
BLADE,
        'replace' => <<<'BLADE'
                <col style="width: 32%;">  {{-- Title --}}
                <col style="width: 9%;">   {{-- Vacancies --}}
                <col style="width: 12%;">  {{-- Employment type --}}
                <col style="width: 7%;">   {{-- SG --}}
BLADE,
    ],
    // 2. Let the header wrap instead of truncating with an ellipsis
    [
        'search' => <<<'BLADE'
                    <th style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">Employment type</th>
                    <th class="text-nowrap">SG</th>
BLADE,
        'replace' => <<<'BLADE'
                    <th style="white-space: normal; word-break: break-word;">Employment type</th>
                    <th class="text-nowrap">SG</th>
BLADE,
    ],
];

$missing = [];
foreach ($edits as $i => $edit) {
    $count = substr_count($original, $edit['search']);
    if ($count === 0) {
        $missing[] = $i;
    } elseif ($count > 1) {
        fwrite(STDERR, "ABORT: Edit #" . ($i + 1) . " target found more than once — refusing to guess which to patch.\n");
        exit(1);
    }
}

if (!empty($missing)) {
    fwrite(STDERR, "ABORT: The following expected snippets were not found (content has drifted from what this patch expects):\n");
    foreach ($missing as $i) {
        fwrite(STDERR, "  - Edit #" . ($i + 1) . "\n");
    }
    fwrite(STDERR, "No changes were made. Please share the current file content so the patch can be regenerated.\n");
    exit(1);
}

// Backup before writing
$backup = $target . '.bak.' . date('Ymd_His');
if (!copy($target, $backup)) {
    fwrite(STDERR, "ABORT: Could not create backup at $backup — no changes made.\n");
    exit(1);
}

$patched = $original;
foreach ($edits as $edit) {
    $patched = str_replace($edit['search'], $edit['replace'], $patched);
}

if (file_put_contents($target, $patched) === false) {
    fwrite(STDERR, "ABORT: Failed to write patched file. Restoring from backup.\n");
    copy($backup, $target);
    exit(1);
}

echo "OK: Patched $target\n";
echo "Backup saved at: $backup\n";
