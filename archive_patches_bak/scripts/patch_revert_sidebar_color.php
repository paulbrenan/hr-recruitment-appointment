<?php
/**
 * patch_revert_sidebar_color.php
 *
 * Reverts the sidebar/header color darkening from
 * patch_sidebar_width_and_color.php back to the original navy. Leaves
 * the sidebar width change alone (that patch is being kept).
 *
 * Handles either starting state:
 *  - Only patch_sidebar_width_and_color.php applied (--hr-primary
 *    darkened, no sidebar-only vars).
 *  - Both that patch and patch_sidebar_scope_fix.php applied
 *    (--hr-primary already reverted, but --hr-sidebar-bg /
 *    --hr-sidebar-active-bg vars added and in use) -- reverts those too,
 *    dropping back to plain var(--hr-primary) / var(--hr-primary-dark).
 *
 * Usage: php patch_revert_sidebar_color.php
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
// Case A: patch_sidebar_scope_fix.php was applied -- sidebar-only vars
// exist and are wired into .hr-sidebar / .nav-link.active. Remove the
// vars and point those rules back at the plain --hr-primary vars.
// ---------------------------------------------------------------------
if (strpos($contents, '--hr-sidebar-bg: #081a3d;') !== false) {
    apply_patch(
        $layout,
        "            --hr-primary: #0c2f6b;\n            --hr-primary-dark: #071c46;\n            --hr-sidebar-bg: #081a3d;\n            --hr-sidebar-active-bg: #040d1f;",
        "            --hr-primary: #0c2f6b;\n            --hr-primary-dark: #071c46;",
        'Remove sidebar-only color vars'
    );
    apply_patch(
        $layout,
        'background-color: var(--hr-sidebar-bg);',
        'background-color: var(--hr-primary);',
        'Point sidebar background back at --hr-primary'
    );
    apply_patch(
        $layout,
        'background-color: var(--hr-sidebar-active-bg);',
        'background-color: var(--hr-primary-dark);',
        'Point active nav-link background back at --hr-primary-dark'
    );
}
// ---------------------------------------------------------------------
// Case B: only patch_sidebar_width_and_color.php was applied -- just the
// darkened --hr-primary / --hr-primary-dark values, no sidebar-only vars.
// ---------------------------------------------------------------------
elseif (strpos($contents, '--hr-primary: #081a3d;') !== false) {
    apply_patch(
        $layout,
        "            --hr-primary: #081a3d;\n            --hr-primary-dark: #040d1f;",
        "            --hr-primary: #0c2f6b;\n            --hr-primary-dark: #071c46;",
        'Revert --hr-primary / --hr-primary-dark to original navy'
    );
} else {
    echo "[OK] Colors already at the original navy -- nothing to revert.\n";
}

echo "\nDone. Reload any page to verify:\n";
echo " - Sidebar and header are back to the original navy (#0c2f6b / #071c46).\n";
echo " - The sidebar width change (narrower expanded width) is unaffected.\n";
