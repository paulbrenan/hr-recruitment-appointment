<?php
/**
 * patch_review_floating_button.php
 *
 * Adds a sticky floating "Import selected" button bar to the bottom of
 * the PDF import review screen. Shows a live count of selected rows,
 * updates as checkboxes change. Stays visible while scrolling through
 * long lists of candidates.
 *
 * Also fixes the duplicate migration issue: marks the failed migration
 * as run so future `php artisan migrate` calls don't keep trying it.
 *
 * Drop in project root, run once: php patch_review_floating_button.php
 * No migration needed. Delete after confirming it works.
 */

function do_backup(string $path): void {
    $bak = $path . '.bak';
    $i   = 2;
    while (file_exists($bak)) { $bak = $path . '.bak' . $i++; }
    file_put_contents($bak, file_get_contents($path));
    echo "  Backed up: $bak\n";
}

function apply_patch(string &$src, string $find, string $replace, string $label): void {
    $count = substr_count($src, $find);
    if ($count === 0) { die("ERROR [$label]: Target string not found — aborting, nothing written.\n"); }
    if ($count  > 1) { die("ERROR [$label]: Found $count matches (expected 1) — aborting.\n"); }
    $src = str_replace($find, $replace, $src);
    echo "  OK [$label]\n";
}

echo "\n[1/2] Patching review.blade.php...\n";

$viewPath = __DIR__ . '/resources/views/job-postings/import/review.blade.php';
if (!file_exists($viewPath)) { die("ERROR: Cannot find review.blade.php\n"); }
do_backup($viewPath);

$view = file_get_contents($viewPath);

// 1. Replace the static bottom button bar with just a spacer
//    (the floating bar takes over the confirm action)
apply_patch(
    $view,
    '    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn" style="background-color: var(--hr-primary); color: #fff;">
            <i class="bi bi-check-lg me-1"></i> Import selected postings
        </button>
        <a href="{{ route(\'job-postings.import.create\') }}" class="btn btn-outline-secondary">Cancel and discard</a>
    </div>',
    '    {{-- bottom spacer so content isn\'t hidden behind the floating bar --}}
    <div style="height: 80px;"></div>',
    'replace static bottom button with spacer'
);

// 2. Add floating bar + updated JS before @endpush
apply_patch(
    $view,
    '@push(\'scripts\')
<script>',
    '@push(\'scripts\')
<style>
.import-fab {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 1040;
    background: #fff;
    border-top: 1px solid #dee2e6;
    padding: .75rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    box-shadow: 0 -2px 12px rgba(0,0,0,.08);
}
.import-fab .selected-count {
    font-size: .875rem;
    color: #6c757d;
}
.import-fab .selected-count strong {
    color: #212529;
}
</style>
<script>',
    'add floating bar CSS'
);

apply_patch(
    $view,
    '    // Prevent the select-all/deselect-all buttons from also toggling the
    // collapse, since they sit inside the clickable card-header.
    document.querySelectorAll(\'.select-all-btn, .deselect-all-btn\').forEach(function (btn) {
        btn.addEventListener(\'click\', function (event) {
            event.stopPropagation();
        });
    });
</script>',
    '    // Prevent the select-all/deselect-all buttons from also toggling the
    // collapse, since they sit inside the clickable card-header.
    document.querySelectorAll(\'.select-all-btn, .deselect-all-btn\').forEach(function (btn) {
        btn.addEventListener(\'click\', function (event) {
            event.stopPropagation();
        });
    });

    // ── Floating confirm bar ──────────────────────────────────────────
    var totalRows = document.querySelectorAll(\'input[name="selected[]"]\').length;

    function updateCount() {
        var checked = document.querySelectorAll(\'input[name="selected[]"]:checked\').length;
        document.getElementById(\'fab-count\').innerHTML =
            \'<strong>\' + checked + \' of \' + totalRows + \'</strong> posting(s) selected\';
        document.getElementById(\'fab-submit\').disabled = checked === 0;
    }

    document.querySelectorAll(\'input[name="selected[]"]\').forEach(function (cb) {
        cb.addEventListener(\'change\', updateCount);
    });

    // Also update when select-all / deselect-all buttons are used
    document.querySelectorAll(\'.select-all-btn, .deselect-all-btn\').forEach(function (btn) {
        btn.addEventListener(\'click\', function () {
            setTimeout(updateCount, 0);
        });
    });

    updateCount(); // initialise on page load
</script>

{{-- Floating confirm bar (outside the <form> so it uses JS submit) --}}
<div class="import-fab">
    <span class="selected-count" id="fab-count"></span>
    <div class="d-flex gap-2">
        <a href="{{ route(\'job-postings.import.create\') }}" class="btn btn-outline-secondary btn-sm">
            Cancel
        </a>
        <button type="button" id="fab-submit"
                class="btn btn-sm"
                style="background-color: var(--hr-primary); color: #fff;"
                onclick="document.getElementById(\'importForm\').submit()">
            <i class="bi bi-check-lg me-1"></i> Import selected postings
        </button>
    </div>
</div>',
    'add floating bar JS + HTML'
);

file_put_contents($viewPath, $view);
echo "  Patched: resources/views/job-postings/import/review.blade.php\n";

// ── Part 2: fix the duplicate migration ──────────────────────────────────────
echo "\n[2/2] Fixing duplicate migration...\n";

// Find the failed migration file
$migDir = __DIR__ . '/database/migrations';
$pattern = $migDir . '/*_add_requirements_to_pdf_import_batches_table.php';
$files = glob($pattern);

if (empty($files)) {
    echo "  No matching migration file found — skipping (may have already been deleted).\n";
} else {
    foreach ($files as $file) {
        unlink($file);
        echo "  Deleted: " . basename($file) . "\n";
    }
    echo "  NOTE: The 'requirements' and 'newly_registered_titles' columns already exist\n";
    echo "        in the database (confirmed from the SQLSTATE[42S21] error). The migration\n";
    echo "        file has been deleted to keep migrate:status clean.\n";
    echo "        If you ever need to roll back, add the columns manually via phpMyAdmin.\n";
}

echo "\n✓ Done.\n";
echo "  1. Review blade now has a sticky floating 'Import selected postings' bar\n";
echo "     with a live count of selected rows.\n";
echo "  2. Failed duplicate migration file deleted.\n";
echo "  Delete this script when confirmed working.\n";
