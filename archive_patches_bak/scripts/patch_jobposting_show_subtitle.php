<?php
/**
 * patch_jobposting_show_subtitle.php
 *
 * Moves the "Job postings > {title}" breadcrumb out of the content area
 * and into the shared @section('page-subtitle') slot, so it renders
 * centered on the same line as the "Job posting pipeline" title (same
 * pattern as Dashboard / Job Vacancy).
 *
 * Depends on patch_dashboard_header_beige.php having already been applied
 * (adds the @yield('page-subtitle') slot to the layout).
 *
 * Usage: php patch_jobposting_show_subtitle.php
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
$view = __DIR__ . '/resources/views/job-postings/show.blade.php';

// ---------------------------------------------------------------------
// 1. Add the page-subtitle section right after page-title, with the
//    breadcrumb markup (works fine here since $posting is already bound
//    to the view at render time).
// ---------------------------------------------------------------------
apply_patch(
    $view,
    "@section('title', \$posting->title . ' — Pipeline')\n@section('page-title', 'Job posting pipeline')\n\n@section('content')",
    "@section('title', \$posting->title . ' — Pipeline')\n@section('page-title', 'Job posting pipeline')\n\n@section('page-subtitle')\n<div class=\"d-flex gap-1 align-items-center small text-muted\">\n    <a href=\"{{ route('job-postings.index') }}\" class=\"text-decoration-none text-muted\">Job postings</a>\n    <i class=\"bi bi-chevron-right\" style=\"font-size:0.7rem;\"></i>\n    <span>{{ \$posting->title }}</span>\n</div>\n@endsection\n\n@section('content')",
    'Add page-subtitle section with breadcrumb'
);

// ---------------------------------------------------------------------
// 2. Remove the now-duplicate breadcrumb from the content area.
// ---------------------------------------------------------------------
apply_patch(
    $view,
    '{{-- Breadcrumb --}}
<div class="d-flex gap-1 align-items-center mb-3 small text-muted">
    <a href="{{ route(\'job-postings.index\') }}" class="text-decoration-none text-muted">Job postings</a>
    <i class="bi bi-chevron-right" style="font-size:0.7rem;"></i>
    <span>{{ $posting->title }}</span>
</div>

',
    '',
    'Remove breadcrumb from content area'
);

echo "\nDone. Reload a Job posting pipeline page to verify:\n";
echo " - 'Job postings > {title}' is centered on the same line as 'Job posting pipeline'.\n";
echo " - It no longer appears above the step tracker / schedules panel.\n";
