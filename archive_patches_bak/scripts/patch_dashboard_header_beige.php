<?php
/**
 * patch_dashboard_header_beige.php
 *
 * 1. Makes the .hr-pagebar background match the page's beige background
 *    instead of solid white (removes the visible white bar behind the
 *    "Dashboard" title).
 * 2. Adds a @yield('page-subtitle') slot in the pagebar, centered between
 *    the page title and the "HR Staff" pill.
 * 3. Updates the Dashboard view (index.blade.php) to push its
 *    "Recruitment pipeline overview as of ..." line into that new
 *    page-subtitle section instead of rendering it as a <p> inside the
 *    content area.
 *
 * Usage: php patch_dashboard_header_beige.php
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

$layout = __DIR__ . '/resources/views/layouts/app.blade.php';
$dashboard = __DIR__ . '/resources/views/dashboard/index.blade.php';

// ---------------------------------------------------------------------
// 1. Pagebar background: white -> beige (matches --hr-bg)
// ---------------------------------------------------------------------
apply_patch(
    $layout,
    ".hr-pagebar {\n            background-color: #fff;\n            border-bottom: 1px solid #e2e6e8;\n            padding: 0.85rem 1.5rem;\n        }",
    ".hr-pagebar {\n            background-color: var(--hr-bg);\n            border-bottom: 1px solid #e9e6df;\n            padding: 0.85rem 1.5rem;\n        }",
    'Pagebar background -> beige'
);

// ---------------------------------------------------------------------
// 2. Add centered page-subtitle slot between title and "HR Staff" pill
// ---------------------------------------------------------------------
apply_patch(
    $layout,
    '<div class="hr-pagebar d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">@yield(\'page-title\', \'Dashboard\')</h5>
                    <div class="text-muted small">
                        <i class="bi bi-person-circle me-1"></i> HR Staff
                    </div>
                </div>',
    '<div class="hr-pagebar d-flex justify-content-between align-items-center position-relative">
                    <h5 class="mb-0">@yield(\'page-title\', \'Dashboard\')</h5>
                    <div class="text-muted small hr-pagebar-subtitle"
                         style="position:absolute;left:50%;transform:translateX(-50%);">
                        @yield(\'page-subtitle\')
                    </div>
                    <div class="text-muted small">
                        <i class="bi bi-person-circle me-1"></i> HR Staff
                    </div>
                </div>',
    'Centered page-subtitle slot'
);

// ---------------------------------------------------------------------
// 3. Dashboard view: move subtitle text into the new @section('page-subtitle')
// ---------------------------------------------------------------------
apply_patch(
    $dashboard,
    "@section('page-title', 'Dashboard')\n\n@section('content')\n<p class=\"text-muted small mb-3\">Recruitment pipeline overview as of {{ \\Carbon\\Carbon::now()->format('M d, Y') }}</p>",
    "@section('page-title', 'Dashboard')\n\n@section('page-subtitle')\nRecruitment pipeline overview as of {{ \\Carbon\\Carbon::now()->format('M d, Y') }}\n@endsection\n\n@section('content')",
    'Move subtitle into page-subtitle section'
);

echo "\nDone. Reload the dashboard to verify:\n";
echo " - Pagebar now blends into the beige page background.\n";
echo " - Subtitle 'Recruitment pipeline overview as of ...' is centered on the same line as 'Dashboard'.\n";
