<?php
/**
 * Patch: Records page search/position filter becomes live -- typing in
 * the search box (debounced) or changing the position dropdown
 * auto-submits the form instead of requiring a manual "Filter" click.
 * Matches the existing onchange="this.form.submit()" pattern already
 * used for the Applications page's status/posting filters.
 *
 * The "Filter" button is removed per request. "Clear" stays -- it's the
 * only way to blank both fields at once.
 *
 * Debounce (350ms) rather than firing on every keystroke: this reloads
 * the whole page (server-side search, not a client-side row filter --
 * the table is paginated, so a client-only filter would only ever see
 * the current page's rows). Firing on every keystroke would reload the
 * page mid-word; debouncing waits for a short pause in typing first,
 * which is what "filters before you finish typing" means in practice
 * for a full-page-reload search rather than an AJAX one.
 *
 * Run once from the project root:
 *   php patch_records_live_filter.php
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
        <<<'OLD'
<form action="{{ route('records.index') }}" method="GET" class="row g-2 mb-3 align-items-center">
    <div class="col-auto">
        <input type="text" name="search" value="{{ $search ?? '' }}"
               class="form-control" placeholder="Search applicant name..." style="min-width: 250px;">
    </div>
    <div class="col-auto">
        <select name="position" class="form-select" style="min-width: 220px;">
            <option value="">All positions</option>
            @foreach ($positions as $title)
                <option value="{{ $title }}" @selected(($position ?? '') === $title)>
                    {{ $title }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-outline-secondary">Filter</button>
        @if (!empty($search) || !empty($position))
            <a href="{{ route('records.index') }}" class="btn btn-outline-danger">Clear</a>
        @endif
    </div>
</form>
OLD,
        <<<'NEW'
<form action="{{ route('records.index') }}" method="GET" class="row g-2 mb-3 align-items-center" id="recordsFilterForm">
    <div class="col-auto">
        <input type="text" name="search" value="{{ $search ?? '' }}" id="recordsSearchInput"
               class="form-control" placeholder="Search applicant name..." style="min-width: 250px;">
    </div>
    <div class="col-auto">
        <select name="position" class="form-select" onchange="this.form.submit()">
            <option value="">All positions</option>
            @foreach ($positions as $title)
                <option value="{{ $title }}" @selected(($position ?? '') === $title)>
                    {{ $title }}
                </option>
            @endforeach
        </select>
    </div>
    @if (!empty($search) || !empty($position))
    <div class="col-auto">
        <a href="{{ route('records.index') }}" class="btn btn-outline-danger">Clear</a>
    </div>
    @endif
</form>
<script>
    // Live search: auto-submit a short pause after typing stops,
    // instead of requiring a manual "Filter" click.
    (function () {
        var input = document.getElementById('recordsSearchInput');
        var form = document.getElementById('recordsFilterForm');
        if (!input || !form) return;

        var debounceTimer = null;
        input.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                form.submit();
            }, 350);
        });
    })();
</script>
NEW,
        'live-filter search: remove Filter button, auto-submit on typing (debounced) and on position change',
    ],
]);

echo "\nDone. Diff and check the Records page before deleting this script\n";
echo "and its .bak backup. If 350ms feels too slow/fast, tweak the number\n";
echo "in the setTimeout call.\n";
