<?php
/**
 * Patch: dashboard view has a stray leftover
 * @section('page-title', 'Records') line above its real
 * @section('title', 'Dashboard') / @section('page-title', 'Dashboard')
 * declarations. Blade's inline @section('name', 'value') overwrites the
 * section value each time it's called, so the LAST page-title call
 * ('Dashboard') is what actually wins today -- this line isn't currently
 * causing a live bug, but it's confusing dead cruft (likely a leftover
 * from whatever copy-paste caused the original title mixup) and removing
 * it eliminates any risk of it winning again if the lines get reordered.
 *
 * Run once from the project root:
 *   php patch_dashboard_stray_page_title.php
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
$file = __DIR__ . '/resources/views/dashboard/index.blade.php';

apply_patch($file, [
    [
        "@section('page-title', 'Records')\n@section('title', 'Dashboard')\n@section('page-title', 'Dashboard')",
        "@section('title', 'Dashboard')\n@section('page-title', 'Dashboard')",
        "remove stray leftover @section('page-title', 'Records') line",
    ],
]);

echo "\nDone. Diff and check both the Dashboard and Records pages before deleting this script and its .bak backup.\n";
