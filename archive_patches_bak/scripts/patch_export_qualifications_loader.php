<?php
/**
 * Patch: fix "Export qualifications" button on job-postings show view
 * getting stuck in a loading state.
 *
 * Root cause: the button was a plain <a href="..."> link. Clicking it
 * starts a native browser file download, but the page never navigates
 * away -- so the global page-loader overlay (which shows on link click
 * expecting a page navigation to reset it) never gets turned back off.
 *
 * Fix: give the anchor an id + data-no-loader flag, and intercept the
 * click with fetch() -> blob() -> forced download, exactly like the
 * Applications index export button. The button shows "Exporting..."
 * only while the request is in flight, then resets immediately once
 * the file is handed to the browser -- no dependency on page navigation.
 *
 * Run: php patch_export_qualifications_loader.php
 */

$file = __DIR__ . '/resources/views/job-postings/show.blade.php';

if (!file_exists($file)) {
    fwrite(STDERR, "File not found: $file\n");
    fwrite(STDERR, "Edit \$file at the top of this script to point to the correct path.\n");
    exit(1);
}

$backup = $file . '.bak';
if (!copy($file, $backup)) {
    fwrite(STDERR, "Failed to create backup at $backup\n");
    exit(1);
}

$content = file_get_contents($file);

// ── 1. Update the anchor markup ─────────────────────────────────────
$oldAnchor = <<<'HTML'
                            <a href="{{ route('job-postings.export-qualifications', $posting->id) }}"
                               class="btn btn-sm btn-outline-success">
                                <i class="bi bi-file-earmark-excel me-1"></i> Export qualifications
                            </a>
HTML;

$newAnchor = <<<'HTML'
                            <a href="{{ route('job-postings.export-qualifications', $posting->id) }}"
                               id="export-qualifications-btn"
                               data-no-loader
                               class="btn btn-sm btn-outline-success">
                                <i class="bi bi-file-earmark-excel me-1"></i> Export qualifications
                            </a>
HTML;

if (strpos($content, $oldAnchor) === false) {
    fwrite(STDERR, "ABORT: anchor markup anchor-string not found. File may have changed -- no changes written.\n");
    exit(1);
}
if (substr_count($content, $oldAnchor) > 1) {
    fwrite(STDERR, "ABORT: anchor markup found more than once -- refusing to guess which one. No changes written.\n");
    exit(1);
}
$content = str_replace($oldAnchor, $newAnchor, $content);

// ── 2. Append the click-intercept JS just before @endpush ──────────
$scriptAnchor = <<<'HTML'
});
</script>
@endpush
@endsection
HTML;

$scriptInsert = <<<'HTML'
});

// Export qualifications: fetch as blob so the button never stays stuck
// in "Exporting..." -- no dependency on a page navigation event to
// reset it (a plain <a> download never actually navigates the page).
(function () {
    var btn = document.getElementById('export-qualifications-btn');
    if (!btn) return;

    btn.addEventListener('click', function (e) {
        e.preventDefault();

        // Safety net: in case the global page-loader overlay was
        // triggered for this click, force it closed immediately.
        var loaderOverlay = document.getElementById('deped-page-loader');
        if (loaderOverlay) {
            loaderOverlay.classList.remove('is-active');
        }

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
                var filename = match ? match[1] : 'qualifications.xlsx';
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
</script>
@endpush
@endsection
HTML;

if (strpos($content, $scriptAnchor) === false) {
    fwrite(STDERR, "ABORT: end-of-scripts anchor not found. No changes written (anchor changes were rolled back).\n");
    copy($backup, $file);
    exit(1);
}
$content = str_replace($scriptAnchor, $scriptInsert, $content);

file_put_contents($file, $content);

echo "Patched successfully: $file\n";
echo "Backup saved at: $backup\n";
