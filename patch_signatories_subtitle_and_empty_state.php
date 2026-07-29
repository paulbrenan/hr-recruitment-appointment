<?php
/**
 * patch_signatories_subtitle_and_empty_state.php
 *
 * 1. Replaces the long description paragraph with a short subtitle and
 *    moves it into @section('page-subtitle'), centered on the same line
 *    as "Signatories" (same pattern as Dashboard / Job Vacancy /
 *    Job posting pipeline / Applications).
 * 2. Shortens the IER empty-state message to just "None yet." to match
 *    the Qualification Notice table's empty state.
 *
 * Depends on patch_dashboard_header_beige.php having already been applied
 * (adds the @yield('page-subtitle') slot to the layout).
 *
 * Usage: php patch_signatories_subtitle_and_empty_state.php
 */

function apply_patch(string $file, string $search, string $replace, string $label): void
{
    if (!file_exists($file)) {
        echo "[ABORT] File not found: {$file}\n";
        exit(1);
    }

    $contents = file_get_contents($file);

    if (strpos($contents, $search) === false) {
        echo "[ABORT] {$label}: expected text not found in {$file}. File may have drifted — aborting without changes.\n";
        exit(1);
    }

    if (substr_count($contents, $search) > 1) {
        echo "[ABORT] {$label}: expected text is not unique in {$file}. Aborting to avoid ambiguous edit.\n";
        exit(1);
    }

    $backup = $file . '.bak';
    if (!file_exists($backup)) {
        copy($file, $backup);
    }

    $new_contents = str_replace($search, $replace, $contents);
    file_put_contents($file, $new_contents);

    echo "[OK] {$label} applied to {$file}\n";
}

// Adjust this if the live path differs from the Laravel default.
$view = __DIR__ . '/resources/views/signatories/index.blade.php';

// ---------------------------------------------------------------------
// 1. Add a short page-subtitle section right after page-title.
// ---------------------------------------------------------------------
apply_patch(
    $view,
    "@section('title', 'Signatories')\n@section('page-title', 'Signatories')\n\n@section('content')",
    "@section('title', 'Signatories')\n@section('page-title', 'Signatories')\n\n@section('page-subtitle')\nAdd and manage signatories for each document type\n@endsection\n\n@section('content')",
    'Add short page-subtitle section'
);

// ---------------------------------------------------------------------
// 2. Remove the old long description paragraph from the content area.
// ---------------------------------------------------------------------
apply_patch(
    $view,
    '<p class="text-muted small mb-4">
    People whose name and title appear on generated documents and emails. Each document type has its own
    list below -- a document can have more than one signature block (e.g. "Prepared by" and "Approved by"),
    so add as many rows per section as that document actually needs.
</p>

',
    '',
    'Remove old long description paragraph'
);

// ---------------------------------------------------------------------
// 3. Shorten the IER empty-state message to match the QN table.
// ---------------------------------------------------------------------
apply_patch(
    $view,
    '<tr><td colspan="3" class="text-center text-muted py-3">No IER signatories yet -- exports fall back to a generic default.</td></tr>',
    '<tr><td colspan="3" class="text-center text-muted py-3">None yet.</td></tr>',
    'Shorten IER empty-state message'
);

echo "\nDone. Reload the Signatories page to verify:\n";
echo " - A short subtitle is centered on the same line as 'Signatories'.\n";
echo " - The IER table's empty state now just says 'None yet.'\n";
