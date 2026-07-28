<?php
/**
 * Patch: fix the Records search bar — it was filtering on
 * where('full_name', 'like', ...) inside a candidate whereHas(), but
 * full_name is a PHP accessor on the Candidate model (first_name +
 * middle_name + last_name concatenated at read time), not a real
 * database column. Typing anything into the search box threw:
 *   SQLSTATE[42S22]: Column not found: 1054 Unknown column 'full_name'
 *
 * Fix: filter on the actual columns (first_name / middle_name /
 * last_name) instead, matched against any of the three.
 *
 * Run once from the project root:
 *   php patch_records_search_column.php
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
$controllerFile = __DIR__ . '/app/Http/Controllers/RecordsController.php';

apply_patch($controllerFile, [
    [
        <<<'OLD'
        $pending = Application::with(['candidate', 'jobPosting'])
            ->whereNull('transaction_number')
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('candidate', function ($q) use ($search) {
                    $q->where('full_name', 'like', '%' . $search . '%');
                });
            })
OLD,
        <<<'NEW'
        $pending = Application::with(['candidate', 'jobPosting'])
            ->whereNull('transaction_number')
            ->when($search !== '', function ($query) use ($search) {
                // CONFIRMED REAL BUG: full_name is a PHP accessor on the
                // Candidate model (first_name/middle_name/last_name
                // concatenated at read time) -- it isn't a real database
                // column, so where('full_name', ...) blew up with
                // "Column not found" the moment anyone typed a letter.
                // Filter on the actual columns instead.
                $query->whereHas('candidate', function ($q) use ($search) {
                    $q->where(function ($nameQuery) use ($search) {
                        $nameQuery->where('first_name', 'like', '%' . $search . '%')
                            ->orWhere('middle_name', 'like', '%' . $search . '%')
                            ->orWhere('last_name', 'like', '%' . $search . '%');
                    });
                });
            })
NEW,
        'index(): fix $pending search to filter on real name columns',
    ],
    [
        <<<'OLD'
        $assigned = Application::with(['candidate', 'jobPosting'])
            ->whereNotNull('transaction_number')
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('candidate', function ($q) use ($search) {
                    $q->where('full_name', 'like', '%' . $search . '%');
                });
            })
OLD,
        <<<'NEW'
        $assigned = Application::with(['candidate', 'jobPosting'])
            ->whereNotNull('transaction_number')
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('candidate', function ($q) use ($search) {
                    $q->where(function ($nameQuery) use ($search) {
                        $nameQuery->where('first_name', 'like', '%' . $search . '%')
                            ->orWhere('middle_name', 'like', '%' . $search . '%')
                            ->orWhere('last_name', 'like', '%' . $search . '%');
                    });
                });
            })
NEW,
        'index(): same fix for $assigned search',
    ],
]);

echo "\nDone. Diff and test typing a partial name (e.g. \"bar\" -> \"Baron\") before deleting this script and its .bak backup.\n";
