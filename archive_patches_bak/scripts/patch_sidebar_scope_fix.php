<?php
/**
 * patch_sidebar_scope_fix.php
 *
 * Follow-up to patch_sidebar_width_and_color.php:
 *  1. Reverts --hr-primary / --hr-primary-dark back to their original
 *     values, since those two vars are shared by the header bar too --
 *     darkening them darkened the header as a side effect.
 *  2. Introduces two sidebar-only vars (--hr-sidebar-bg,
 *     --hr-sidebar-active-bg) with the darker navy, used only by
 *     .hr-sidebar and .hr-sidebar .nav-link.active. The header keeps the
 *     original --hr-primary color, untouched.
 *  3. Shrinks the expanded sidebar further, from 200px down to 170px.
 *
 * Safe to run whether or not patch_sidebar_width_and_color.php was
 * applied first -- it checks for either the darkened or the original
 * --hr-primary values before touching the file.
 *
 * Usage: php patch_sidebar_scope_fix.php
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

$contents = file_get_contents($layout);
if ($contents === false) {
    echo "[ABORT] File not found: {$layout}\n";
    exit(1);
}

// ---------------------------------------------------------------------
// 1. Revert --hr-primary / --hr-primary-dark to the original values (if
//    they were darkened by the previous patch) and add the new
//    sidebar-only vars, in one go.
// ---------------------------------------------------------------------
if (strpos($contents, '--hr-primary: #081a3d;') !== false) {
    apply_patch(
        $layout,
        "            --hr-primary: #081a3d;\n            --hr-primary-dark: #040d1f;",
        "            --hr-primary: #0c2f6b;\n            --hr-primary-dark: #071c46;\n            --hr-sidebar-bg: #081a3d;\n            --hr-sidebar-active-bg: #040d1f;",
        'Revert header color, add sidebar-only color vars'
    );
} elseif (strpos($contents, '--hr-primary: #0c2f6b;') !== false) {
    apply_patch(
        $layout,
        "            --hr-primary: #0c2f6b;\n            --hr-primary-dark: #071c46;",
        "            --hr-primary: #0c2f6b;\n            --hr-primary-dark: #071c46;\n            --hr-sidebar-bg: #081a3d;\n            --hr-sidebar-active-bg: #040d1f;",
        'Add sidebar-only color vars'
    );
} else {
    echo "[ABORT] Could not find expected --hr-primary declaration in {$layout}. File may have drifted -- aborting without changes.\n";
    exit(1);
}

// ---------------------------------------------------------------------
// 2. Point the sidebar background at the new sidebar-only var.
// ---------------------------------------------------------------------
apply_patch(
    $layout,
    '.hr-sidebar {
            width: 200px;
            flex-shrink: 0;
            background-color: var(--hr-primary);',
    '.hr-sidebar {
            width: 170px;
            flex-shrink: 0;
            background-color: var(--hr-sidebar-bg);',
    'Shrink sidebar to 170px and use sidebar-only background color'
);

// ---------------------------------------------------------------------
// 3. Point the active nav-link background at the new sidebar-only var.
// ---------------------------------------------------------------------
apply_patch(
    $layout,
    '.hr-sidebar .nav-link.active {
            background-color: var(--hr-primary-dark);',
    '.hr-sidebar .nav-link.active {
            background-color: var(--hr-sidebar-active-bg);',
    'Use sidebar-only color for active nav-link background'
);

// ---------------------------------------------------------------------
// 4. Trim horizontal nav-link padding slightly so "Applications" (the
//    longest label) has room to fit on one line at the new 170px width.
// ---------------------------------------------------------------------
apply_patch(
    $layout,
    '.hr-sidebar .nav-link {
            color: #c9d4d9;
            padding: 0.7rem 1.25rem;',
    '.hr-sidebar .nav-link {
            color: #c9d4d9;
            padding: 0.7rem 1rem;',
    'Trim nav-link horizontal padding to fit the narrower sidebar'
);

echo "\nDone. Reload any page to verify:\n";
echo " - Sidebar is narrower still (170px expanded) and a darker navy than the header.\n";
echo " - Header bar is back to the original navy, unaffected by the sidebar color change.\n";
