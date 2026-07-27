<?php
/**
 * Patch: add a search bar to the Job Postings index page that filters
 * the table by title, client-side (no route/controller changes needed —
 * all postings are already rendered in the table).
 *
 * Run once from the project root:
 *   php patch_job_postings_search_bar.php
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
    // 1. Add the search input, right above the postings table card.
    [
        <<<'OLD'
<div class="card">
    <div class="table-responsive">
        <table class="table align-top mb-0" style="vertical-align: top; table-layout: fixed; width: 100%;">
OLD,
        <<<'NEW'
<div class="mb-3">
    <div class="input-group input-group-sm" style="max-width: 320px;">
        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
        <input
            type="text"
            id="jobTitleSearch"
            class="form-control"
            placeholder="Search by job title..."
            autocomplete="off"
        >
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-top mb-0" id="jobPostingsTable" style="vertical-align: top; table-layout: fixed; width: 100%;">
NEW,
        'insert search input above table',
    ],

    // 2. Give each row a data-title attribute the JS can filter on
    //    (avoids re-parsing the rendered title cell's HTML/markup).
    [
        <<<'OLD'
                <tr class="posting-row" style="cursor: pointer; vertical-align: top;" data-href="{{ route('job-postings.show', $posting->id) }}">
OLD,
        <<<'NEW'
                <tr class="posting-row" style="cursor: pointer; vertical-align: top;" data-href="{{ route('job-postings.show', $posting->id) }}" data-title="{{ strtolower($posting->title) }}">
NEW,
        'add data-title attribute to each row',
    ],

    // 3. Add the filter script, alongside the existing row-click script.
    [
        <<<'OLD'
<script>
    // Clickable rows
    document.querySelectorAll('.posting-row').forEach(function (row) {
        row.addEventListener('click', function () {
            window.location = this.dataset.href;
        });
    });
</script>
OLD,
        <<<'NEW'
<script>
    // Clickable rows
    document.querySelectorAll('.posting-row').forEach(function (row) {
        row.addEventListener('click', function () {
            window.location = this.dataset.href;
        });
    });

    // Job title search — client-side filter, all postings are already
    // rendered in the table so no extra request is needed.
    (function () {
        var searchInput = document.getElementById('jobTitleSearch');
        var table = document.getElementById('jobPostingsTable');
        if (!searchInput || !table) return;

        var rows = table.querySelectorAll('tbody tr.posting-row');

        searchInput.addEventListener('input', function () {
            var query = this.value.trim().toLowerCase();
            rows.forEach(function (row) {
                var title = row.dataset.title || '';
                row.style.display = title.indexOf(query) !== -1 ? '' : 'none';
            });
        });
    })();
</script>
NEW,
        'add search-filter script',
    ],
]);

echo "\nDone. Diff and check the page before deleting this script and its .bak backup.\n";
