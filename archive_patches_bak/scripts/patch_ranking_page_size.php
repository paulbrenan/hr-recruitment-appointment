<?php
/**
 * Patch: the candidate ranking table already has real server-side
 * pagination (via ?pg_rank= query param, forPage(), Prev/Next links) --
 * it just defaults to 50 rows/page. Drop it to 10, matching the
 * Applications list and Job postings list page sizes.
 *
 * Run once from the project root:
 *   php patch_ranking_page_size.php
 * Then delete this file.
 *
 * Note: this file's ranking table also already filters to actually-
 * qualified applicants via $rankableApplications->filter(fn ($app) =>
 * $app->qualification_result === 'qualified') in JobPostingController@show
 * -- that part needed no further change.
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
$showBladeFile = __DIR__ . '/resources/views/job-postings/show.blade.php';

apply_patch($showBladeFile, [
    [
        '                        $rankPerPage = 50;',
        '                        $rankPerPage = 10;',
        'ranking table: drop page size from 50 to 10',
    ],
]);

echo "\nDone. Diff and test the ranking table (with 11+ qualified candidates on one posting) before deleting this script and its .bak backup.\n";
