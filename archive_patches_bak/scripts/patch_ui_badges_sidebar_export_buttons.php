<?php
/**
 * Patch: three UI fixes.
 *
 * 1. hr-admin-theme.css — the "stamped-ink" badge treatment set
 *    background-color with !important but NOT color. Bootstrap 5.3's
 *    own .text-bg-* utilities set color:#fff !important, and any
 *    !important declaration beats any non-!important one regardless of
 *    selector specificity -- so the background successfully became a
 *    pale tint, but the text stayed forced-white by Bootstrap,
 *    producing pale-background / white-text / colored-border badges
 *    (confirmed from the screenshot). Since the desired look (per the
 *    reference image) is the plain solid Bootstrap style anyway, this
 *    removes the stamped-ink override block entirely rather than fixing
 *    its missing !important -- reverts every status badge on every
 *    page back to a solid color fill with white text.
 *
 * 2. app_blade.php — sidebar active nav item's left-border and icon
 *    color switch from the gold/orange accent to white.
 *
 * 3. Two export buttons switch from outline-primary/outline-secondary
 *    to outline-success (green), matching export-qualifications-btn
 *    which was already green. export-qualifications-btn itself needs
 *    no change.
 *
 * Run once from the project root:
 *   php patch_ui_badges_sidebar_export_buttons.php
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
$themeFile = __DIR__ . '/public/css/hr-admin-theme.css';
$layoutFile = __DIR__ . '/resources/views/layouts/app.blade.php';
$appsIndexFile = __DIR__ . '/resources/views/applications/index.blade.php';
$showFile = __DIR__ . '/resources/views/job-postings/show.blade.php';

// 1. Remove the stamped-ink badge override block.
apply_patch($themeFile, [
    [
        <<<'OLD'
/* Bootstrap's solid text-bg-* utilities (used on Applications /
   Job Postings status badges) — swap the flat fill for an
   outline-on-paper stamp: white background, colored ring, colored
   ink. Two-class selector out-specifies the single-class Bootstrap
   utility, so no !important needed. */
.badge-status.text-bg-secondary { background-color: rgba(73, 80, 87, 0.06) !important; color: #495057; border: 1px solid #c3c9d1; }
.badge-status.text-bg-info      { background-color: rgba(10, 162, 192, 0.08) !important; color: #0aa2c0; border: 1px solid #0aa2c0; }
.badge-status.text-bg-primary   { background-color: rgba(12, 47, 107, 0.08) !important; color: var(--hr-primary); border: 1px solid var(--hr-primary); }
.badge-status.text-bg-success   { background-color: rgba(23, 138, 108, 0.08) !important; color: var(--hr-good); border: 1px solid var(--hr-good); }
.badge-status.text-bg-warning   { background-color: rgba(185, 146, 47, 0.12) !important; color: #8a6500; border: 1px solid var(--hr-gold); }
.badge-status.text-bg-danger    { background-color: rgba(163, 32, 47, 0.08) !important; color: var(--hr-red); border: 1px solid var(--hr-red); }
.badge-status.text-bg-dark      { background-color: rgba(10, 26, 51, 0.06) !important; color: #0a1a33; border: 1px solid #0a1a33; }
OLD,
        <<<'NEW'
/* Stamped-ink outline badge treatment removed (2026-07) -- it set
   background-color with !important but not color, and Bootstrap
   5.3's own .text-bg-* utilities force color:#fff !important, which
   always wins over a non-!important declaration regardless of
   selector specificity. Net effect: pale background, colored
   border, but text stayed forced-white -- unreadable. Reverted to
   Bootstrap's plain solid-fill badges (.badge-status above only
   controls sizing now, same as before this block existed). */
NEW,
        'remove stamped-ink badge override block',
    ],
]);

// 2. Sidebar active highlight: gold/orange -> white.
apply_patch($layoutFile, [
    [
        <<<'OLD'
        .hr-sidebar .nav-link.active {
            background-color: var(--hr-primary-dark);
            color: #fff;
            font-weight: 600;
            border-left-color: var(--hr-accent);
        }
        .hr-sidebar .nav-link.active i {
            opacity: 1;
            color: var(--hr-accent);
        }
OLD,
        <<<'NEW'
        .hr-sidebar .nav-link.active {
            background-color: var(--hr-primary-dark);
            color: #fff;
            font-weight: 600;
            border-left-color: #fff;
        }
        .hr-sidebar .nav-link.active i {
            opacity: 1;
            color: #fff;
        }
NEW,
        'sidebar active highlight: gold -> white',
    ],
]);

// 3. Job posting show "Export IER" button: outline-secondary -> outline-success.
apply_patch($showFile, [
    [
        '<a href="{{ route(\'job-postings.export-ier\', $posting->id) }}" id="export-ier-btn" data-no-loader class="btn btn-sm btn-outline-secondary ms-2">',
        '<a href="{{ route(\'job-postings.export-ier\', $posting->id) }}" id="export-ier-btn" data-no-loader class="btn btn-sm btn-outline-success ms-2">',
        'Export IER button: outline-secondary -> outline-success',
    ],
]);

// 4. Applications index "Export to Excel" button: outline-primary -> outline-success.
// Run LAST and separately -- this one file I don't have a freshly
// confirmed copy of in this session, so if your live file has drifted
// this specific edit may abort. Everything above it still gets applied
// either way since each apply_patch() call runs independently.
apply_patch($appsIndexFile, [
    [
        <<<'OLD'
           class="btn btn-sm btn-outline-primary"
           title="{{ request('job_posting') ? 'Includes this posting\'s scoring columns — ready to fill in and re-import on Assessment & ranking' : 'Select a job posting above to include scoring columns for that posting' }}">
OLD,
        <<<'NEW'
           class="btn btn-sm btn-outline-success"
           title="{{ request('job_posting') ? 'Includes this posting\'s scoring columns — ready to fill in and re-import on Assessment & ranking' : 'Select a job posting above to include scoring columns for that posting' }}">
NEW,
        'Export to Excel button: outline-primary -> outline-success',
    ],
]);

echo "\nDone. If the Applications-index edit aborted, that file may have\n";
echo "drifted from what I have on record -- send me the current live\n";
echo "resources/views/applications/index.blade.php and I'll regenerate\n";
echo "just that piece. Diff and check all pages before deleting this\n";
echo "script and its .bak backups.\n";
