<?php
/**
 * Patch: Records index page shows "Dashboard" in the pagebar heading
 * because it never sets @section('page-title', ...) — it only sets
 * @section('title', ...) which controls the <title> tag, not the
 * visible pagebar heading (@yield('page-title', 'Dashboard') in
 * layouts/app.blade.php). Without an override it silently falls back
 * to the layout's default of "Dashboard".
 *
 * Run once from the project root:
 *   php patch_records_page_title.php
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
$file = __DIR__ . '/resources/views/records/index.blade.php';

apply_patch($file, [
    [
        "@section('title', 'Records')\n\n@section('content')",
        "@section('title', 'Records')\n@section('page-title', 'Records')\n\n@section('content')",
        "add missing @section('page-title', 'Records')",
    ],
]);

echo "\nDone. Diff and check the page before deleting this script and its .bak backup.\n";
