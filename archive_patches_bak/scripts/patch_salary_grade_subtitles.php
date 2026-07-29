<?php
/**
 * patch_salary_grade_subtitles.php
 *
 * Moves the descriptive text on both Salary Grade pages into the shared
 * @section('page-subtitle') slot so it renders centered on the same line
 * as the page title (same pattern as the other pages already patched):
 *
 *  - salary-grades/index.blade.php: the "Active schedule: ..." /
 *    "No salary schedule has been imported yet ..." line.
 *  - salary-grades/upload.blade.php: "Upload the DBM Budget Circular ..."
 *
 * Depends on patch_dashboard_header_beige.php having already been applied
 * (adds the @yield('page-subtitle') slot to the layout).
 *
 * Usage: php patch_salary_grade_subtitles.php
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

// Adjust these if the live paths differ from the Laravel defaults.
$indexView  = __DIR__ . '/resources/views/salary-grades/index.blade.php';
$uploadView = __DIR__ . '/resources/views/salary-grades/upload.blade.php';

// ---------------------------------------------------------------------
// INDEX PAGE
// ---------------------------------------------------------------------

// 1. Add page-subtitle section with the dynamic "Active schedule / No
//    schedule imported yet" text.
apply_patch(
    $indexView,
    "@section('title', 'Salary Grade')\n@section('page-title', 'Salary Grade')\n\n@section('content')",
    "@section('title', 'Salary Grade')\n@section('page-title', 'Salary Grade')\n\n@section('page-subtitle')\n@if (\$currentCircular)\n    Active schedule: <strong>Budget Circular No. {{ \$currentCircular->circular_no ?? '—' }}</strong>\n    @if (\$currentCircular->effective_date)\n        &middot; effective {{ \$currentCircular->effective_date->format('M d, Y') }}\n    @endif\n@else\n    No salary schedule has been imported yet -- using the built-in default table.\n@endif\n@endsection\n\n@section('content')",
    'Add page-subtitle section (index)'
);

// 2. Remove the old <p> and unwrap the button row (drop the now-empty
//    justify-content-between flex wrapper).
apply_patch(
    $indexView,
    '<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted small mb-0">
        @if ($currentCircular)
            Active schedule: <strong>Budget Circular No. {{ $currentCircular->circular_no ?? \'—\' }}</strong>
            @if ($currentCircular->effective_date)
                &middot; effective {{ $currentCircular->effective_date->format(\'M d, Y\') }}
            @endif
        @else
            No salary schedule has been imported yet -- using the built-in default table.
        @endif
    </p>
    <a href="{{ route(\'salary-grades.create\') }}" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">
        <i class="bi bi-upload"></i> Import new circular
    </a>
</div>',
    '<div class="d-flex justify-content-end align-items-center mb-3">
    <a href="{{ route(\'salary-grades.create\') }}" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">
        <i class="bi bi-upload"></i> Import new circular
    </a>
</div>',
    'Remove old subtitle text, keep Import button flush right (index)'
);

// ---------------------------------------------------------------------
// UPLOAD PAGE
// ---------------------------------------------------------------------

apply_patch(
    $uploadView,
    "@section('title', 'Import Salary Grade Schedule')\n@section('page-title', 'Import Salary Grade Schedule')\n\n@section('content')\n<p class=\"text-muted small mb-3\">Upload the DBM Budget Circular (PDF) or an Excel/CSV export of the Annex A salary schedule.</p>",
    "@section('title', 'Import Salary Grade Schedule')\n@section('page-title', 'Import Salary Grade Schedule')\n\n@section('page-subtitle')\nUpload the DBM Budget Circular (PDF) or an Excel/CSV export of the Annex A salary schedule.\n@endsection\n\n@section('content')",
    'Move subtitle into page-subtitle section (upload)'
);

echo "\nDone. Reload both Salary Grade pages to verify:\n";
echo " - Index: 'Active schedule / No schedule imported yet' is centered on the same line as 'Salary Grade'; 'Import new circular' button now sits alone, flush right.\n";
echo " - Upload: the upload instructions line is centered on the same line as 'Import Salary Grade Schedule'.\n";
