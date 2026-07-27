<?php
/**
 * cleanup_patch_clutter.php
 *
 * NOT a patch script -- doesn't touch any application code. Just tidies
 * up the project root by moving:
 *   1. Every already-run patch_*.php / fix_*.php script in the project
 *      root into  _patch-archive/scripts/
 *   2. Every scattered *.bak backup file (created next to whatever it
 *      backed up, e.g. resources/views/.../show.blade.php.bak) into
 *      _patch-archive/backups/, preserving the original relative folder
 *      structure so files with the same name from different folders
 *      (e.g. two different index.blade.php.bak) don't collide/overwrite
 *      each other.
 *
 * Safe to run any time. Nothing is deleted -- only moved. Skips
 * vendor/, node_modules/, and this archive folder itself.
 *
 * Usage from the project root:
 *   php cleanup_patch_clutter.php
 *
 * This script deletes itself at the end (moves into the archive too), so
 * you don't have to remember to clean it up separately.
 */

$root = __DIR__;
$archiveRoot   = $root . '/_patch-archive';
$scriptsDir    = $archiveRoot . '/scripts';
$backupsDir    = $archiveRoot . '/backups';

$skipDirs = ['vendor', 'node_modules', '.git', '_patch-archive', 'storage'];

@mkdir($scriptsDir, 0777, true);
@mkdir($backupsDir, 0777, true);

// ── 1. Move spent patch_*.php / fix_*.php scripts sitting in the project
//       root (that's the convention this project's patches follow --
//       one-shot installers run from the root, meant to be deleted after). ──

$movedScripts = 0;
foreach (glob($root . '/{patch_,fix_}*.php', GLOB_BRACE) as $scriptFile) {
    $name = basename($scriptFile);
    if ($name === basename(__FILE__)) {
        continue; // don't move ourselves yet
    }
    $dest = $scriptsDir . '/' . $name;
    if (file_exists($dest)) {
        $dest = $scriptsDir . '/' . date('Ymd_His') . '_' . $name;
    }
    rename($scriptFile, $dest);
    echo "[moved script] $name\n";
    $movedScripts++;
}

// ── 2. Recursively find *.bak files and move them into backups/,
//       preserving their relative path so nothing collides. ─────────────

$movedBackups = 0;

function scanForBakFiles($dir, $root, $backupsDir, $skipDirs, &$movedBackups) {
    $items = @scandir($dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $fullPath = $dir . '/' . $item;

        if (is_dir($fullPath)) {
            if (in_array($item, $skipDirs, true)) {
                continue;
            }
            scanForBakFiles($fullPath, $root, $backupsDir, $skipDirs, $movedBackups);
            continue;
        }

        if (substr($item, -4) === '.bak') {
            $relativePath = ltrim(str_replace($root, '', $dir), '/\\');
            $destDir = $backupsDir . ($relativePath ? '/' . $relativePath : '');
            if (!is_dir($destDir)) {
                mkdir($destDir, 0777, true);
            }
            $dest = $destDir . '/' . $item;
            rename($fullPath, $dest);
            echo "[moved backup] " . ($relativePath ? $relativePath . '/' : '') . "$item\n";
            $movedBackups++;
        }
    }
}

scanForBakFiles($root, $root, $backupsDir, $skipDirs, $movedBackups);

echo "\nDone. Moved $movedScripts patch script(s) and $movedBackups .bak file(s) into:\n";
echo "  $scriptsDir\n";
echo "  $backupsDir\n";
echo "\nNothing was deleted -- everything is still there if you need to restore a .bak\n";
echo "file, just move it back to its original location.\n";

// Move this script itself into the archive last, so it's not left behind
// cluttering the root either.
$self = __FILE__;
$selfDest = $scriptsDir . '/' . basename($self);
if (file_exists($selfDest)) {
    $selfDest = $scriptsDir . '/' . date('Ymd_His') . '_' . basename($self);
}
register_shutdown_function(function () use ($self, $selfDest) {
    @rename($self, $selfDest);
});
