<?php
/**
 * patch_salary_grade_review_layout.php
 *
 * On the Salary Grade "Review Import" page:
 *  1. Moves the "Status: ... source: ..." line into @section('page-subtitle'),
 *     centered on the same line as "Review Salary Grade Import" (same
 *     pattern as the other pages). The "Back to Salary Grade" link stays,
 *     now alone and flush right.
 *  2. Puts "Save corrections" and "Confirm as active schedule" side by
 *     side on the same row, far right. They're normally in two separate
 *     <form> elements (Save corrections' button lives inside the big
 *     table-editing form) so this uses the HTML5 form="..." attribute to
 *     detach the Save button and place it next to the Confirm button in
 *     a shared flex row, without moving the big form itself.
 *  3. Recolors: Save corrections -> blue (btn-primary),
 *     Confirm as active schedule -> green (btn-success).
 *
 * Depends on patch_dashboard_header_beige.php having already been applied
 * (adds the @yield('page-subtitle') slot to the layout).
 *
 * Usage: php patch_salary_grade_review_layout.php
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
$view = __DIR__ . '/resources/views/salary-grades/review.blade.php';

// ---------------------------------------------------------------------
// 1a. Add page-subtitle section with the Status/source line.
// ---------------------------------------------------------------------
apply_patch(
    $view,
    "@section('title', 'Review Salary Grade Import')\n@section('page-title', 'Review Salary Grade Import')\n\n@php",
    "@section('title', 'Review Salary Grade Import')\n@section('page-title', 'Review Salary Grade Import')\n\n@section('page-subtitle')\nStatus: <span class=\"badge sg-status-{{ \$circular->status }}\">{{ ucfirst(\$circular->status) }}</span>\n&middot; source: {{ \$circular->original_filename }}\n@endsection\n\n@php",
    'Add page-subtitle section with status/source line'
);

// ---------------------------------------------------------------------
// 1b. Remove the old <p> and leave "Back to Salary Grade" alone, flush right.
// ---------------------------------------------------------------------
apply_patch(
    $view,
    '<div class="d-flex justify-content-between align-items-start mb-3">
    <p class="text-muted small mb-0">
        Status: <span class="badge sg-status-{{ $circular->status }}">{{ ucfirst($circular->status) }}</span>
        &middot; source: {{ $circular->original_filename }}
    </p>
    <a href="{{ route(\'salary-grades.index\') }}" class="small">&larr; Back to Salary Grade</a>
</div>',
    '<div class="d-flex justify-content-end align-items-start mb-3">
    <a href="{{ route(\'salary-grades.index\') }}" class="small">&larr; Back to Salary Grade</a>
</div>',
    'Remove old status/source paragraph, keep Back link flush right'
);

// ---------------------------------------------------------------------
// 2a. Tag the big corrections form with an id so its submit button can
//     be detached and placed elsewhere via the form="..." attribute.
// ---------------------------------------------------------------------
apply_patch(
    $view,
    "<form method=\"POST\" action=\"{{ route('salary-grades.update', \$circular->id) }}\">\n    @csrf\n    @method('PUT')",
    "<form method=\"POST\" action=\"{{ route('salary-grades.update', \$circular->id) }}\" id=\"salaryCorrectionsForm\">\n    @csrf\n    @method('PUT')",
    'Add id to corrections form for detached submit button'
);

// ---------------------------------------------------------------------
// 2b/3. Move Save corrections button out of the form (via form="..."),
//        put it and Confirm as active schedule in one flex row on the
//        right, and recolor both.
// ---------------------------------------------------------------------
apply_patch(
    $view,
    '<button type="submit" class="btn btn-outline-secondary">Save corrections</button>
</form>

@if ($circular->status === \'ready\')
<form method="POST" action="{{ route(\'salary-grades.confirm\', $circular->id) }}" class="d-inline"
      onsubmit="return confirm(\'Make this the active salary schedule system-wide?\');">
    @csrf
    @method(\'PUT\')
    <button type="submit" class="btn" style="background-color: var(--hr-primary); color: #fff;">
        Confirm as active schedule
    </button>
</form>
@endif',
    '</form>

<div class="d-flex justify-content-end gap-2 mt-3">
    <button type="submit" form="salaryCorrectionsForm" class="btn btn-primary">Save corrections</button>

    @if ($circular->status === \'ready\')
    <form method="POST" action="{{ route(\'salary-grades.confirm\', $circular->id) }}" class="d-inline"
          onsubmit="return confirm(\'Make this the active salary schedule system-wide?\');">
        @csrf
        @method(\'PUT\')
        <button type="submit" class="btn btn-success">
            Confirm as active schedule
        </button>
    </form>
    @endif
</div>',
    'Move buttons into a shared right-aligned row and recolor them'
);

echo "\nDone. Reload a Review Salary Grade Import page to verify:\n";
echo " - Status/source line is centered on the same line as the title; 'Back to Salary Grade' sits alone, flush right.\n";
echo " - 'Save corrections' (blue) and 'Confirm as active schedule' (green) now sit side by side, far right.\n";
echo " - Clicking 'Save corrections' still submits the full table-editing form (via the form=\"salaryCorrectionsForm\" attribute).\n";
