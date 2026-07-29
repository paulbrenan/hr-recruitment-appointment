<?php
/**
 * patch_sidebar_width_and_color.php
 *
 * 1. Shrinks the expanded sidebar from a fixed 240px down to 200px, which
 *    is enough to comfortably fit the longest nav label ("Applications")
 *    plus its icon and padding, without the large empty gutter seen at
 *    240px. (Collapsed width stays 64px, unaffected.)
 * 2. Darkens the sidebar/header navy from #0c2f6b / #071c46 to a deeper
 *    #081a3d / #040d1f, changing --hr-primary and --hr-primary-dark
 *    (these two CSS vars drive the sidebar, header bar, and active nav
 *    item background everywhere, so this is a single source-of-truth
 *    change).
 *
 * Usage: php patch_sidebar_width_and_color.php
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

$layout = __DIR__ . '/resources/views/layouts/app.blade.php';

// ---------------------------------------------------------------------
// 1. Darken the theme's navy.
// ---------------------------------------------------------------------
apply_patch(
    $layout,
    "            --hr-primary: #0c2f6b;\n            --hr-primary-dark: #071c46;",
    "            --hr-primary: #081a3d;\n            --hr-primary-dark: #040d1f;",
    'Darken --hr-primary / --hr-primary-dark'
);

// ---------------------------------------------------------------------
// 2. Shrink the expanded sidebar width to fit its content.
// ---------------------------------------------------------------------
apply_patch(
    $layout,
    '.hr-sidebar {
            width: 240px;',
    '.hr-sidebar {
            width: 200px;',
    'Shrink expanded sidebar width from 240px to 200px'
);

echo "\nDone. Reload any page to verify:\n";
echo " - Expanded sidebar is noticeably narrower, hugging the nav labels instead of leaving a big gutter.\n";
echo " - Sidebar and header are a darker navy.\n";
echo " - Collapsed (icon-only) sidebar width is unchanged at 64px.\n";
