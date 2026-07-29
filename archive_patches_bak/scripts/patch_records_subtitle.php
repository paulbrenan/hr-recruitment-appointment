<?php
/**
 * patch_records_subtitle.php
 *
 * Removes the "Records — Pending Application Codes" <h4> block and adds
 * "Pending Application Codes" (the "Records —" prefix dropped, since it's
 * redundant with the page title) as @section('page-subtitle'), so it
 * renders centered on the same line as the "Records" title (same pattern
 * as the other pages already patched).
 *
 * Depends on patch_dashboard_header_beige.php having already been applied
 * (adds the @yield('page-subtitle') slot to the layout).
 *
 * Usage: php patch_records_subtitle.php
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

// TODO: this view's actual path wasn't confirmed (the file was uploaded
// with a note that 'layouts.app' might not be the real layout). Adjust
// $view below to match the live path before running.
$view = __DIR__ . '/resources/views/records/index.blade.php';

// ---------------------------------------------------------------------
// 1. Add page-subtitle section right after page-title.
// ---------------------------------------------------------------------
apply_patch(
    $view,
    "@section('title', 'Records')\n@section('page-title', 'Records')\n\n@section('content')",
    "@section('title', 'Records')\n@section('page-title', 'Records')\n\n@section('page-subtitle')\nPending Application Codes\n@endsection\n\n@section('content')",
    'Add page-subtitle section'
);

// ---------------------------------------------------------------------
// 2. Remove the old h4 heading block from the content area.
// ---------------------------------------------------------------------
apply_patch(
    $view,
    '<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Records — Pending Application Codes</h4>
</div>

',
    '',
    'Remove old h4 heading block'
);

echo "\nDone. Reload the Records page to verify:\n";
echo " - 'Pending Application Codes' is centered on the same line as 'Records'.\n";
echo " - The old 'Records — Pending Application Codes' heading no longer appears in the content area.\n";
