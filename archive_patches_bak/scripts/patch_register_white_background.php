<?php
/**
 * patch_register_white_background.php
 *
 * Forces a white page background on the registration form, overriding
 * whatever background css/deped-theme.css sets (that file wasn't
 * available to inspect directly, so this adds a targeted override in the
 * page's own <style> block rather than guessing at deped-theme.css's
 * internals).
 *
 * Usage: php patch_register_white_background.php
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
$view = __DIR__ . '/resources/views/portal/register.blade.php';

apply_patch(
    $view,
    '<style>
  /* Page-specific overrides only — shared theme lives in deped-theme.css */
  .form-body { padding:32px 36px; }',
    '<style>
  /* Page-specific overrides only — shared theme lives in deped-theme.css */
  html, body { background: #fff !important; }
  .form-body { padding:32px 36px; }',
    'Force white page background, overriding deped-theme.css'
);

echo "\nDone. Reload the registration page to verify the background is white.\n";
echo "Note: css/deped-theme.css (not available to inspect) is what was setting the previous background --\n";
echo "if you'd rather fix it at the source instead of overriding it here, upload that file and I'll patch it directly.\n";
