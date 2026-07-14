<?php
/**
 * patch_posted_column_spacing.php
 *
 * Adds left padding to the Posted column (header + data cell) so there's
 * visible breathing room between it and SG, same approach as the earlier
 * SG-vs-Employment-type spacing fix.
 *
 * HOW TO RUN:
 *   php patch_posted_column_spacing.php    (from project root)
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

echo "\n=== patch_posted_column_spacing.php ===\n\n";

$indexPath = ROOT . '/resources/views/job-postings/index.blade.php';

echo "[1] Adding left padding to the Posted header cell...\n";

apply_patch(
    $indexPath,
    '                    <th class="text-nowrap">Posted</th>',
    '                    <th class="text-nowrap ps-4">Posted</th>',
    'index.blade.php: add left padding to Posted header'
);

echo "\n[2] Adding left padding to the Posted data cell...\n";

apply_patch(
    $indexPath,
    '                    <td>{{ $posting->posted_at ? \Carbon\Carbon::parse($posting->posted_at)->format(\'M d, Y\') : \'—\' }}</td>',
    '                    <td class="ps-4">{{ $posting->posted_at ? \Carbon\Carbon::parse($posting->posted_at)->format(\'M d, Y\') : \'—\' }}</td>',
    'index.blade.php: add left padding to Posted data cell'
);

echo <<<TEXT

✅ Patch applied.

WHAT CHANGED:
  The Posted column (header and data cell) now has extra left padding
  (Bootstrap's ps-4), creating visible spacing between it and SG. Closes
  and Status were left untouched since they weren't part of this request
  — let me know if you also want gaps added before those.

DELETE this script after running.

TEXT;
