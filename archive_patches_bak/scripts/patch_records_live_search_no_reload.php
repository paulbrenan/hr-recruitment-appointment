<?php
/**
 * Patch: stop the Records search box from triggering a full page reload
 * (and the global page-loader overlay flashing) on every keystroke.
 *
 * The debounced auto-submit was still a real form submission -> full page
 * navigation each time the debounce timer fired, which is exactly what
 * triggers the app's global page-loader. Switching to a background fetch()
 * that swaps just the two tables' <tbody> and pagination in place avoids
 * navigating at all, so the loader never fires. Falls back to a real
 * form.submit() if the fetch itself fails for some reason.
 *
 * Run once from the project root:
 *   php patch_records_live_search_no_reload.php
 * Then delete this file.
 *
 * This does NOT require any controller change -- it fetches the full page
 * HTML for the filtered URL (same route, same view) and just pulls the
 * pieces it needs out of it client-side.
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
        <<<'OLD'
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
OLD,
        <<<'NEW'
<script>
    // Live search: fetch results in the background and swap just the two
    // tables' contents in place, instead of a full form submit. A full
    // submit was a real page navigation on every debounced keystroke,
    // which is what triggered the global page-loader overlay to flash on
    // every letter typed -- fetching avoids navigation entirely, so the
    // loader never fires.
    (function () {
        var input = document.getElementById('recordsSearchInput');
        var form = document.getElementById('recordsFilterForm');
        if (!input || !form) return;

        var pendingTbody  = document.querySelector('#pendingTable tbody');
        var assignedTbody = document.querySelector('#assignedTable tbody');
        var pendingPagination  = document.getElementById('pendingPagination');
        var assignedPagination = document.getElementById('assignedPagination');

        function applyFilter(url) {
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (response) { return response.text(); })
                .then(function (html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');

                    var newPendingTbody = doc.querySelector('#pendingTable tbody');
                    var newAssignedTbody = doc.querySelector('#assignedTable tbody');
                    var newPendingPagination = doc.getElementById('pendingPagination');
                    var newAssignedPagination = doc.getElementById('assignedPagination');

                    if (pendingTbody && newPendingTbody) pendingTbody.innerHTML = newPendingTbody.innerHTML;
                    if (assignedTbody && newAssignedTbody) assignedTbody.innerHTML = newAssignedTbody.innerHTML;
                    if (pendingPagination && newPendingPagination) pendingPagination.innerHTML = newPendingPagination.innerHTML;
                    if (assignedPagination && newAssignedPagination) assignedPagination.innerHTML = newAssignedPagination.innerHTML;

                    // Keep the address bar / back button in sync without
                    // triggering a real navigation.
                    window.history.replaceState(null, '', url);
                })
                .catch(function () {
                    // Fetch failed for some reason (network hiccup, etc.) --
                    // fall back to a real submit so search still works.
                    form.submit();
                });
        }

        var debounceTimer = null;
        input.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                var params = new URLSearchParams(new FormData(form));
                applyFilter(form.action + '?' + params.toString());
            }, 350);
        });

        // Intercept pagination link clicks too, so paging through results
        // stays on the same no-reload behavior once a search is active.
        document.addEventListener('click', function (e) {
            var link = e.target.closest('#pendingPagination a, #assignedPagination a');
            if (!link) return;
            e.preventDefault();
            applyFilter(link.href);
        });
    })();
</script>
NEW,
        'Replace debounced full-submit search with background-fetch live search',
    ],
    [
        <<<'OLD'
<table class="table table-bordered align-middle">
    <thead>
        <tr>
            <th>Applicant</th>
            <th>Position</th>
            <th>Submitted</th>
            <th class="text-end">Action</th>
OLD,
        <<<'NEW'
<table class="table table-bordered align-middle" id="pendingTable">
    <thead>
        <tr>
            <th>Applicant</th>
            <th>Position</th>
            <th>Submitted</th>
            <th class="text-end">Action</th>
NEW,
        'Tag the pending table so the fetch script can find its <tbody>',
    ],
    [
        <<<'OLD'
<table class="table table-bordered align-middle">
    <thead>
        <tr>
            <th>Applicant</th>
            <th>Position</th>
            <th style="min-width: 220px;">Application Code</th>
OLD,
        <<<'NEW'
<table class="table table-bordered align-middle" id="assignedTable">
    <thead>
        <tr>
            <th>Applicant</th>
            <th>Position</th>
            <th style="min-width: 220px;">Application Code</th>
NEW,
        'Tag the assigned table so the fetch script can find its <tbody>',
    ],
    [
        "{{ \$pending->links() }}",
        "<div id=\"pendingPagination\">\n{{ \$pending->links() }}\n</div>",
        'Wrap pending pagination in an id\'d container',
    ],
    [
        "{{ \$assigned->links() }}",
        "<div id=\"assignedPagination\">\n{{ \$assigned->links() }}\n</div>",
        'Wrap assigned pagination in an id\'d container',
    ],
]);

echo "\nDone. Diff and test typing in the search box (watch that no page loader appears) before deleting this script and its .bak backup.\n";
echo "Note: if this project's global page-loader script also fires on fetch()/XHR requests (not just navigations), it may still\n";
echo "appear here. If so, tell me and I'll look for a data-no-loader-style opt-out on the fetch call instead.\n";
