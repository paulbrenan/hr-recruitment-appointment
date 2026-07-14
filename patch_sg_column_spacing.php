<?php
/**
 * patch_sg_column_spacing.php
 *
 * Adds a little left padding to the SG column (header + data cell) so
 * there's visible breathing room between it and Employment type, instead
 * of the two sitting flush against each other.
 *
 * HOW TO RUN:
 *   php patch_sg_column_spacing.php    (from project root)
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

echo "\n=== patch_sg_column_spacing.php ===\n\n";

$indexPath = ROOT . '/resources/views/job-postings/index.blade.php';

echo "[1] Adding left padding to the SG header cell...\n";

apply_patch(
    $indexPath,
    '                    <th class="text-nowrap">SG</th>',
    '                    <th class="text-nowrap ps-4">SG</th>',
    'index.blade.php: add left padding to SG header'
);

echo "\n[2] Adding left padding to the SG data cell...\n";

apply_patch(
    $indexPath,
    '                    <td class="text-nowrap">
                        @if ($posting->salary_grade)
                            {{ Str::startsWith($posting->salary_grade, \'SG-\') ? $posting->salary_grade : \'SG-\' . $posting->salary_grade }}
                        @else
                            —',
    '                    <td class="text-nowrap ps-4">
                        @if ($posting->salary_grade)
                            {{ Str::startsWith($posting->salary_grade, \'SG-\') ? $posting->salary_grade : \'SG-\' . $posting->salary_grade }}
                        @else
                            —',
    'index.blade.php: add left padding to SG data cell'
);

echo <<<TEXT

✅ Patch applied.

WHAT CHANGED:
  The SG column (header and data cell) now has extra left padding
  (Bootstrap's ps-4), creating visible spacing between it and the
  Employment type column.

DELETE this script after running.

TEXT;
