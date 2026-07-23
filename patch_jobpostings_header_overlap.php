<?php
/**
 * Patch: Fix "Employment type" header overflowing into "SG" header
 * on the Job Postings index table (table-layout: fixed + text-nowrap
 * on the Employment type <th> was letting its text spill into the
 * next column instead of wrapping/clipping).
 *
 * Run once from the project root:
 *   php patch_jobpostings_header_overlap.php
 * Then delete this file.
 */

$target = __DIR__ . '/resources/views/job-postings/index.blade.php';

if (!file_exists($target)) {
    fwrite(STDERR, "ABORT: File not found at expected path:\n  $target\n");
    fwrite(STDERR, "Edit \$target at the top of this script to point at the correct view file, then re-run.\n");
    exit(1);
}

$original = file_get_contents($target);

$search = <<<'BLADE'
                    <th class="text-nowrap">Employment type</th>
                    <th class="text-nowrap">SG</th>
BLADE;

$replace = <<<'BLADE'
                    <th style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">Employment type</th>
                    <th class="text-nowrap">SG</th>
BLADE;

if (strpos($original, $search) === false) {
    fwrite(STDERR, "ABORT: Expected header markup not found — file content has drifted from what this patch expects.\n");
    fwrite(STDERR, "No changes were made. Please share the current file content so the patch can be regenerated.\n");
    exit(1);
}

if (substr_count($original, $search) > 1) {
    fwrite(STDERR, "ABORT: Expected header markup found more than once — refusing to guess which to patch.\n");
    exit(1);
}

// Backup before writing
$backup = $target . '.bak.' . date('Ymd_His');
if (!copy($target, $backup)) {
    fwrite(STDERR, "ABORT: Could not create backup at $backup — no changes made.\n");
    exit(1);
}

$patched = str_replace($search, $replace, $original);

if (file_put_contents($target, $patched) === false) {
    fwrite(STDERR, "ABORT: Failed to write patched file. Restoring from backup.\n");
    copy($backup, $target);
    exit(1);
}

echo "OK: Patched $target\n";
echo "Backup saved at: $backup\n";
