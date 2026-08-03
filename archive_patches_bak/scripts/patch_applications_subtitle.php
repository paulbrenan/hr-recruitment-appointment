<?php
/**
 * patch_applications_subtitle.php
 *
 * Moves "Track candidate applications from submission through hiring"
 * out of the content area and into the shared @section('page-subtitle')
 * slot, so it renders centered on the same line as the "Candidate
 * applications" title (same pattern as Dashboard / Job Vacancy / Job
 * posting pipeline).
 *
 * Depends on patch_dashboard_header_beige.php having already been applied
 * (adds the @yield('page-subtitle') slot to the layout).
 *
 * Usage: php patch_applications_subtitle.php
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
$view = __DIR__ . '/resources/views/applications/index.blade.php';

// ---------------------------------------------------------------------
// 1. Add the page-subtitle section right after page-title.
// ---------------------------------------------------------------------
apply_patch(
    $view,
    "@section('title', 'Applications')\n@section('page-title', 'Candidate applications')\n\n@section('content')",
    "@section('title', 'Applications')\n@section('page-title', 'Candidate applications')\n\n@section('page-subtitle')\nTrack candidate applications from submission through hiring\n@endsection\n\n@section('content')",
    'Add page-subtitle section'
);

// ---------------------------------------------------------------------
// 2. Remove the description <p> from its old spot in the top action row,
//    leaving the filters/export button flush left.
// ---------------------------------------------------------------------
apply_patch(
    $view,
    '<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0 small">Track candidate applications from submission through hiring</p>
    <div class="d-flex gap-2 align-items-center">',
    '<div class="d-flex justify-content-end align-items-center mb-3">
    <div class="d-flex gap-2 align-items-center">',
    'Remove description text from top action row'
);

echo "\nDone. Reload the Applications index page to verify:\n";
echo " - 'Track candidate applications from submission through hiring' is centered on the same line as 'Candidate applications'.\n";
echo " - The filters/export button row now sits flush right where the description used to be.\n";
