<?php
/**
 * patch_register_form_card_white.php
 *
 * Reverts patch_register_white_background.php (that one targeted
 * html/body, but the beige box in the screenshot is actually the
 * .form-card container -- body wasn't the problem) and instead forces
 * .form-card itself to white, which is the element that actually needs it.
 *
 * Usage: php patch_register_form_card_white.php
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

$contents = file_get_contents($view);
if ($contents === false) {
    echo "[ABORT] File not found: {$view}\n";
    exit(1);
}

// ---------------------------------------------------------------------
// 1. Revert the html/body override from the previous patch, if present.
// ---------------------------------------------------------------------
if (strpos($contents, "html, body { background: #fff !important; }") !== false) {
    apply_patch(
        $view,
        '<style>
  /* Page-specific overrides only — shared theme lives in deped-theme.css */
  html, body { background: #fff !important; }
  .form-body { padding:32px 36px; }',
        '<style>
  /* Page-specific overrides only — shared theme lives in deped-theme.css */
  .form-body { padding:32px 36px; }',
        'Revert previous html/body background override'
    );
}

// ---------------------------------------------------------------------
// 2. Force the actual form container white instead.
// ---------------------------------------------------------------------
apply_patch(
    $view,
    '<style>
  /* Page-specific overrides only — shared theme lives in deped-theme.css */
  .form-body { padding:32px 36px; }',
    '<style>
  /* Page-specific overrides only — shared theme lives in deped-theme.css */
  .form-card { background: #fff !important; }
  .form-body { padding:32px 36px; }',
    'Force .form-card background to white'
);

echo "\nDone. Reload the registration page to verify:\n";
echo " - The form box (.form-card) is white, not beige.\n";
echo " - The page body outside the form is back to whatever deped-theme.css normally sets (the earlier override is undone).\n";
