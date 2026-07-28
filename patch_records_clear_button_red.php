<?php
/**
 * Patch: Clear filters button — outline red -> solid red.
 *
 * Run once from the project root:
 *   php patch_records_clear_button_red.php
 * Then delete this file.
 */

function apply_patch(string $path, array $edits): void
{
    if (!file_exists($path)) {
        fwrite(STDERR, "ABORT: file not found: $path\n");
        exit(1);
    }

    $original = file_get_contents($path);
    $working = $original;

    foreach ($edits as $i => [$search, $replace, $label]) {
        $count = substr_count($working, $search);
        if ($count !== 1) {
            fwrite(STDERR, "ABORT: edit #$i ($label) matched $count times (expected exactly 1) in $path\n");
            fwrite(STDERR, "No changes were written.\n");
            exit(1);
        }
        $working = str_replace($search, $replace, $working);
    }

    $backup = $path . '.bak';
    if (!copy($path, $backup)) {
        fwrite(STDERR, "ABORT: could not create backup at $backup\n");
        exit(1);
    }

    file_put_contents($path, $working);
    echo "Patched: $path\n";
    echo "Backup:  $backup\n";
}

// ── Adjust this path if your project layout differs ────────────────────
$indexBladeFile = __DIR__ . '/resources/views/records/index.blade.php';

apply_patch($indexBladeFile, [
    [
        '<a href="{{ route(\'records.index\') }}" class="btn btn-outline-danger">Clear</a>',
        '<a href="{{ route(\'records.index\') }}" class="btn btn-danger">Clear</a>',
        'Clear button: solid red',
    ],
]);

echo "\nDone. Diff and reload the Records page before deleting this script and its .bak backup.\n";
