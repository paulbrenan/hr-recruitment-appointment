<?php
/**
 * apply_landing_page_colors.php
 *
 * One-shot script: patches resources/views/layouts/app.blade.php.
 *
 * The entire admin theme (header, sidebar, active nav-item border, and any
 * button using `style="background-color: var(--hr-primary)"`) is driven by
 * 3 CSS custom properties in :root. This script only changes those 3 values
 * to match the public landing page's teal/green DepEd Cavite palette --
 * no structural changes, no other files touched.
 *
 * New palette (estimated from the landing page screenshot):
 * - --hr-primary:      #1a5f4f  (dark teal -- header/sidebar background,
 *                                 matches the "Admin Login" button)
 * - --hr-primary-dark: #134539  (darker teal -- hover/active backgrounds,
 *                                 active nav-item background)
 * - --hr-accent:       #2fae57  (forest green -- matches "TRACKING SYSTEM"
 *                                 text and the "TRACK" button; used for the
 *                                 active nav-item's left border accent)
 *
 * Usage: place this file in the project root (same folder as artisan) and run:
 *   php apply_landing_page_colors.php
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
        :root {
            --hr-primary: #2f4858;
            --hr-primary-dark: #233843;
            --hr-accent: #3f7d8c;
            --hr-bg: #f4f6f7;
            --hr-header-h: 56px;
        }
OLD_EOF;

$new = <<<'NEW_EOF'
        :root {
            --hr-primary: #1a5f4f;
            --hr-primary-dark: #134539;
            --hr-accent: #2fae57;
            --hr-bg: #f4f6f7;
            --hr-header-h: 56px;
        }
NEW_EOF;

$newContent = apply_patch($content, $old, $new, 'root-color-variables');

backup_file($layoutPath);
if (file_put_contents($layoutPath, $newContent) === false) {
    die_loud("Could not write $layoutPath");
}
echo "Updated resources/views/layouts/app.blade.php\n";

echo "\nDone.\n";
echo "Next steps:\n";
echo "  1. Refresh any admin page -- confirm the header/sidebar are now dark teal\n";
echo "     and the active nav item's left border accent is green.\n";
echo "  2. Check Job Postings 'Save posting' and similar buttons that use\n";
echo "     var(--hr-primary) inline -- they should now be teal too.\n";
echo "  3. If the exact shades don't match closely enough, tell me which one\n";
echo "     looks off and I can adjust just that hex value.\n";
echo "  4. Delete this script once you've confirmed it looks right.\n";
