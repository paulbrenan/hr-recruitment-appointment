<?php
/**
 * patch_applications_fix_search_selector.php
 *
 * Fixes a regression from patch_applications_subtitle.php: that patch
 * changed the top action row's class from justify-content-between to
 * justify-content-end. applications-index-polish.js selects the filter
 * bar using:
 *   .d-flex.justify-content-between.align-items-center.mb-3 .d-flex.gap-2
 * so the class change silently broke the quick-search box injection.
 *
 * This just restores justify-content-between (harmless now that the row
 * has a single child — justify-content-end/between look identical here,
 * but between keeps the JS selector working).
 *
 * Usage: php patch_applications_fix_search_selector.php
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
$view = __DIR__ . '/resources/views/applications/index.blade.php';

apply_patch(
    $view,
    '<div class="d-flex justify-content-end align-items-center mb-3">
    <div class="d-flex gap-2 align-items-center">',
    '<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex gap-2 align-items-center">',
    'Restore justify-content-between so JS search-bar selector matches again'
);

echo "\nDone. Reload the Applications index page to verify:\n";
echo " - The 'Quick search by name or email...' box is back next to the filters.\n";
