<?php
/**
 * patch_applications_export_button.php
 *
 * Restyles the "Export to Excel" button on the Applications index page:
 *  - Wider (so the label stops wrapping to two lines, which is what was
 *    making it look tall).
 *  - Shorter height via tighter vertical padding + nowrap.
 *  - Solid green fill instead of the green outline style.
 *
 * Usage: php patch_applications_export_button.php
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

apply_patch(
    $view,
    '<a href="{{ route(\'applications.export\', request()->only([\'status\', \'job_posting\'])) }}"
           id="export-excel-btn"
           data-no-loader
           class="btn btn-sm btn-outline-success"
           title="{{ request(\'job_posting\') ? \'Includes this posting\\\'s scoring columns — ready to fill in and re-import on Assessment & ranking\' : \'Select a job posting above to include scoring columns for that posting\' }}">
            <i class="bi bi-file-earmark-excel"></i> Export to Excel
        </a>',
    '<a href="{{ route(\'applications.export\', request()->only([\'status\', \'job_posting\'])) }}"
           id="export-excel-btn"
           data-no-loader
           class="btn btn-sm btn-success d-inline-flex align-items-center justify-content-center"
           style="white-space: nowrap; padding: 0.3rem 1.1rem; min-width: 160px;"
           title="{{ request(\'job_posting\') ? \'Includes this posting\\\'s scoring columns — ready to fill in and re-import on Assessment & ranking\' : \'Select a job posting above to include scoring columns for that posting\' }}">
            <i class="bi bi-file-earmark-excel me-1"></i> Export to Excel
        </a>',
    'Restyle Export to Excel button (wider, shorter, solid green)'
);

echo "\nDone. Reload the Applications index page to verify:\n";
echo " - The button no longer wraps to two lines and looks shorter.\n";
echo " - It's wider (min-width 160px) and filled solid green instead of outlined.\n";
