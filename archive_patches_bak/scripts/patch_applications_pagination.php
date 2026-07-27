<?php
/**
 * Patch: applications list pagination.
 *   1. ApplicationController@index already paginates — just drop the page
 *      size from 25 to 10 to match the job postings / candidate ranking
 *      tables.
 *   2. applications/index.blade.php gets the {{ $applications->links() }}
 *      output added below the table (the paginator was already being
 *      built in the controller, it just wasn't rendered anywhere).
 *
 * Run once from the project root:
 *   php patch_applications_pagination.php
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
$controllerFile = __DIR__ . '/app/Http/Controllers/ApplicationController.php';
$indexBladeFile = __DIR__ . '/resources/views/applications/index.blade.php';

apply_patch($controllerFile, [
    [
        "        \$applications = \$query->paginate(25)->withQueryString();",
        "        \$applications = \$query->paginate(10)->withQueryString();",
        'index(): drop page size from 25 to 10',
    ],
]);

apply_patch($indexBladeFile, [
    [
        <<<'OLD'
        </table>
    </div>
</div>

@push('styles')
OLD,
        <<<'NEW'
        </table>
    </div>
</div>
<div class="mt-3">
    {{ $applications->onEachSide(1)->links() }}
</div>

@push('styles')
NEW,
        'index blade: render pagination links below the table',
    ],
]);

echo "\nDone. Diff and reload the Applications page before deleting this script and its .bak backups.\n";
