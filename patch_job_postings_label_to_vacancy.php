<?php
/**
 * Patch: rename the "Job postings" display label to "Job Vacancy" —
 * the sidebar hover tooltip, browser tab title, and pagebar heading.
 * Route names (job-postings.*), controllers, and URLs are untouched —
 * this is display text only.
 *
 * Run once from the project root:
 *   php patch_job_postings_label_to_vacancy.php
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

// ── Adjust these paths if your project layout differs ──────────────────
$layoutFile = __DIR__ . '/resources/views/layouts/app.blade.php';
$indexFile  = __DIR__ . '/resources/views/job-postings/index.blade.php';

apply_patch($layoutFile, [
    [
        <<<'OLD'
                <a href="{{ route('job-postings.index') }}" class="nav-link {{ request()->routeIs('job-postings.*') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Job postings">
OLD,
        <<<'NEW'
                <a href="{{ route('job-postings.index') }}" class="nav-link {{ request()->routeIs('job-postings.*') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Job Vacancy">
NEW,
        'sidebar hover tooltip: Job postings -> Job Vacancy',
    ],
]);

apply_patch($indexFile, [
    [
        "@section('title', 'Job postings')\n@section('page-title', 'Job postings')",
        "@section('title', 'Job Vacancy')\n@section('page-title', 'Job Vacancy')",
        "browser tab title + pagebar heading: Job postings -> Job Vacancy",
    ],
]);

echo "\nDone. Diff and check the sidebar tooltip + page title before deleting this script and its .bak backups.\n";
