<?php
/**
 * patch_jobpostings_subtitle.php
 *
 * Moves "Manage open positions, qualifications, and assignment details"
 * out of the content area and into the shared @section('page-subtitle')
 * slot, so it renders centered on the same line as the "Job Vacancy"
 * title (same pattern as the Dashboard page).
 *
 * Depends on patch_dashboard_header_beige.php having already been applied
 * (adds the @yield('page-subtitle') slot to the layout).
 *
 * Usage: php patch_jobpostings_subtitle.php
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
$view = __DIR__ . '/resources/views/job-postings/index.blade.php';

// ---------------------------------------------------------------------
// 1. Add the page-subtitle section right after page-title.
// ---------------------------------------------------------------------
apply_patch(
    $view,
    "@section('title', 'Job Vacancy')\n@section('page-title', 'Job Vacancy')\n\n@section('content')",
    "@section('title', 'Job Vacancy')\n@section('page-title', 'Job Vacancy')\n\n@section('page-subtitle')\nManage open positions, qualifications, and assignment details\n@endsection\n\n@section('content')",
    'Add page-subtitle section'
);

// ---------------------------------------------------------------------
// 2. Remove the now-duplicate description text from the content area.
// ---------------------------------------------------------------------
apply_patch(
    $view,
    '<div class="mb-3">
    <p class="text-muted mb-0 small">Manage open positions, qualifications, and assignment details</p>
</div>
',
    '',
    'Remove description text from content area'
);

echo "\nDone. Reload the Job Vacancy index page to verify:\n";
echo " - 'Manage open positions, qualifications, and assignment details' is centered on the same line as the 'Job Vacancy' title.\n";
echo " - It no longer appears above the stat cards.\n";
