<?php
/**
 * separate_header_sidebar.php
 *
 * One-shot script: patches resources/views/layouts/app.blade.php.
 *
 * Problem: .hr-header has border-bottom: 1px solid var(--hr-primary-dark),
 * but the sidebar sitting directly below it is var(--hr-primary) -- nearly
 * the same shade -- so the two visually merge into one teal mass with no
 * clear dividing line, unlike the white-header/black-sidebar reference
 * image where the contrast does this for free.
 *
 * Fix: add a box-shadow under .hr-header so there's a visible horizontal
 * line across the FULL width (over both the sidebar and the content area),
 * regardless of how close the sidebar's color is to the header's. No
 * structural or color-palette changes -- the sidebar was already a
 * separate column in the markup, it just wasn't visually distinguished.
 *
 * Usage: place this file in the project root (same folder as artisan) and run:
 *   php separate_header_sidebar.php
 * Then delete this script. No migration needed.
 *
 * Backs up the file before overwriting (.bak, .bak2, ... if needed).
 */

function die_loud($msg) {
    fwrite(STDERR, "\n[ABORTED] $msg\n\n");
    exit(1);
}

function backup_file($path) {
    if (!file_exists($path)) {
        die_loud("Expected file not found: $path");
    }
    $backupPath = $path . '.bak';
    $n = 1;
    while (file_exists($backupPath)) {
        $n++;
        $backupPath = $path . '.bak' . $n;
    }
    if (!copy($path, $backupPath)) {
        die_loud("Could not create backup at $backupPath");
    }
    echo "Backed up " . $path . " -> " . basename($backupPath) . "\n";
}

function apply_patch($content, $old, $new, $label) {
    $count = substr_count($content, $old);
    if ($count !== 1) {
        die_loud("Patch '$label' expected exactly 1 match but found $count.\nThe file may have drifted -- please re-paste it so the patch can be updated.");
    }
    return str_replace($old, $new, $content);
}

$root = __DIR__;
$layoutPath = $root . '/resources/views/layouts/app.blade.php';

$content = file_get_contents($layoutPath);
if ($content === false) {
    die_loud("Could not read $layoutPath");
}

$old = <<<'OLD_EOF'
        .hr-header {
            height: var(--hr-header-h);
            background-color: var(--hr-primary);
            border-bottom: 1px solid var(--hr-primary-dark);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1rem;
            position: sticky;
            top: 0;
            z-index: 1030;
        }
OLD_EOF;

$new = <<<'NEW_EOF'
        .hr-header {
            height: var(--hr-header-h);
            background-color: var(--hr-primary);
            border-bottom: 1px solid var(--hr-primary-dark);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1rem;
            position: sticky;
            top: 0;
            z-index: 1030;
        }
NEW_EOF;

$newContent = apply_patch($content, $old, $new, 'header-separation-shadow');

backup_file($layoutPath);
if (file_put_contents($layoutPath, $newContent) === false) {
    die_loud("Could not write $layoutPath");
}
echo "Updated resources/views/layouts/app.blade.php\n";

echo "\nDone.\n";
echo "Next steps:\n";
echo "  1. Refresh any admin page -- confirm there's now a visible shadow line separating\n";
echo "     the header from the sidebar/content below it.\n";
echo "  2. If you want a stronger or more subtle effect, the box-shadow value\n";
echo "     '0 2px 6px rgba(0, 0, 0, 0.25)' is the one knob to adjust.\n";
echo "  3. Delete this script once you've confirmed it looks right.\n";
