<?php
/**
 * Patch: on the Job Postings index table, the Edit button should not be
 * clickable once a posting's status is no longer 'open' — mirrors the
 * existing disabled/lock pattern already used on the show page (there
 * it's gated by pipeline step; here it's gated directly by status per
 * request, since the index page doesn't have $currentStep available).
 *
 * Run once from the project root:
 *   php patch_job_postings_index_edit_gate.php
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
$file = __DIR__ . '/resources/views/job-postings/index.blade.php';

apply_patch($file, [
    [
        <<<'OLD'
                        <a href="{{ route('job-postings.edit', $posting->id) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil"></i>
                        </a>
OLD,
        <<<'NEW'
                        @if ($posting->status === 'open')
                        <a href="{{ route('job-postings.edit', $posting->id) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        @else
                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled
                                title="This posting can no longer be edited once it's no longer open.">
                            <i class="bi bi-lock"></i>
                        </button>
                        @endif
NEW,
        "gate index page's Edit button on status === 'open'",
    ],
]);

echo "\nDone. Diff and check the postings table before deleting this script and its .bak backup.\n";
