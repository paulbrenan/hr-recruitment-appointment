<?php
/**
 * patch_fix_export_ier_page_loader.php
 *
 * Fixes: clicking "Export IER" shows the full-screen DepEd page-loader
 * overlay (page-loader.js) and it never goes away.
 *
 * Root cause: page-loader.js shows the overlay on every same-origin link
 * click, and only hides it on the browser's 'load'/'pageshow' events --
 * i.e. on an actual page navigation. A file download response (like
 * exportIER()'s streamDownload) never navigates the page, so 'load'
 * never fires again and the overlay is stuck forever. This is the exact
 * same problem the "Export Qualifications" button already had, and it
 * was already solved for that button (data-no-loader attribute + a
 * fetch-as-blob click handler instead of a plain link). This patch
 * applies the identical, already-established pattern to Export IER.
 *
 * Also adds a genuine safety-net timeout to page-loader.js itself --
 * the file's own header comment already claimed an "8s safety net"
 * exists, but the actual code never implemented one (the beforeunload
 * listener calls showLoader(), not hide). This is defense-in-depth for
 * any FUTURE download link that forgets the data-no-loader treatment.
 *
 * Run once from the project root:
 *   php patch_fix_export_ier_page_loader.php
 * Then delete this file — it is a one-shot installer, not idempotent.
 */

function apply_patch($path, $old, $new, $label) {
    if (!file_exists($path)) {
        fwrite(STDERR, "[ABORT] File not found: $path ($label)\n");
        exit(1);
    }
    $contents = file_get_contents($path);
    if (strpos($contents, $old) === false) {
        fwrite(STDERR, "[ABORT] Expected content not found for: $label\n");
        fwrite(STDERR, "        File may already be patched, or an earlier IER patch hasn't been\n");
        fwrite(STDERR, "        run yet. No changes made.\n");
        exit(1);
    }
    copy($path, $path . '.bak');
    $updated = str_replace($old, $new, $contents, $count);
    if ($count !== 1) {
        fwrite(STDERR, "[ABORT] Expected exactly 1 match for '$label', found $count. Restoring backup.\n");
        copy($path . '.bak', $path);
        exit(1);
    }
    file_put_contents($path, $updated);
    echo "[OK] $label\n";
}

$showView   = __DIR__ . '/resources/views/job-postings/show.blade.php';
$loaderJs   = __DIR__ . '/public/js/page-loader.js';

// ── 1. Mark the Export IER anchor so the global loader skips it ─────────

apply_patch(
    $showView,
    <<<'OLD'
                            <a href="{{ route('job-postings.export-ier', $posting->id) }}" class="btn btn-sm btn-outline-secondary ms-2">
                                <i class="bi bi-file-earmark-excel me-1"></i> Export IER
                            </a>
OLD,
    <<<'NEW'
                            <a href="{{ route('job-postings.export-ier', $posting->id) }}" id="export-ier-btn" data-no-loader class="btn btn-sm btn-outline-secondary ms-2">
                                <i class="bi bi-file-earmark-excel me-1"></i> Export IER
                            </a>
NEW,
    'Export IER anchor: add id + data-no-loader so page-loader.js skips it'
);

// ── 2. Fetch-as-blob click handler, same pattern as export-qualifications ─

apply_patch(
    $showView,
    <<<'OLD'
// Download template: same problem as the export button above -- a plain
// <a> file download never navigates the page, so the global page-loader's
// full-screen overlay (shown on every internal link click) would never
OLD,
    <<<'NEW'
// Export IER: same problem/fix as the export-qualifications button above --
// fetch as blob so the button never depends on a page navigation event to
// reset it. The anchor has data-no-loader, so page-loader.js's global
// click listener skips it and never shows the full-screen overlay for
// this button in the first place.
(function () {
    var btn = document.getElementById('export-ier-btn');
    if (!btn) return;

    btn.addEventListener('click', function (e) {
        e.preventDefault();

        var url = btn.getAttribute('href');
        var originalHtml = btn.innerHTML;
        btn.classList.add('disabled');
        btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Exporting…';

        fetch(url, { credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) {
                    return response.text().then(function (text) {
                        throw new Error('Export failed (HTTP ' + response.status + '). ' + text.slice(0, 200));
                    });
                }
                var disposition = response.headers.get('Content-Disposition') || '';
                var match = disposition.match(/filename="?([^";]+)"?/);
                var filename = match ? match[1] : 'IER.xlsx';
                return response.blob().then(function (blob) {
                    return { blob: blob, filename: filename };
                });
            })
            .then(function (result) {
                var blobUrl = window.URL.createObjectURL(result.blob);
                var tempLink = document.createElement('a');
                tempLink.href = blobUrl;
                tempLink.download = result.filename;
                document.body.appendChild(tempLink);
                tempLink.click();
                document.body.removeChild(tempLink);
                window.URL.revokeObjectURL(blobUrl);
            })
            .catch(function (err) {
                alert('Could not export: ' + err.message);
            })
            .finally(function () {
                btn.classList.remove('disabled');
                btn.innerHTML = originalHtml;
            });
    });
})();

// Download template: same problem as the export button above -- a plain
// <a> file download never navigates the page, so the global page-loader's
// full-screen overlay (shown on every internal link click) would never
NEW,
    'Add fetch-as-blob click handler for Export IER (mirrors export-qualifications-btn exactly)'
);

// ── 3. Genuine safety-net timeout in page-loader.js itself ──────────────

if (file_exists($loaderJs)) {
    apply_patch(
        $loaderJs,
        <<<'OLD'
    function showLoader() {
        const overlay = document.getElementById('deped-page-loader');
        if (overlay) {
            overlay.classList.add('is-active');
            shownAt = Date.now();
        }
    }
OLD,
        <<<'NEW'
    function showLoader() {
        const overlay = document.getElementById('deped-page-loader');
        if (overlay) {
            overlay.classList.add('is-active');
            shownAt = Date.now();

            // Genuine safety net: force-hide after 8s no matter what, in
            // case a future link/download triggers the loader without
            // ever firing a 'load' or 'pageshow' event to hide it again
            // (the header comment already claimed this existed -- it
            // didn't, this is that).
            clearTimeout(window.__depedLoaderSafetyTimer);
            window.__depedLoaderSafetyTimer = setTimeout(function () {
                if (overlay.classList.contains('is-active')) {
                    overlay.classList.remove('is-active');
                }
            }, 8000);
        }
    }
NEW,
        'page-loader.js: add real 8s safety-net timeout (comment already claimed one existed)'
    );
} else {
    echo "[SKIP] public/js/page-loader.js not found at the expected path -- adjust \$loaderJs and re-run\n";
    echo "       if you want the safety-net timeout too. Steps 1 and 2 above are the actual fix and\n";
    echo "       don't depend on this file.\n";
}

echo "\nDone. Export IER now fetches as a blob instead of relying on a plain link, so it\n";
echo "no longer triggers (or gets stuck behind) the global page-loader overlay.\n";
