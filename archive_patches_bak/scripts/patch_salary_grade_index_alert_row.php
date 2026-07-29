<?php
/**
 * patch_salary_grade_index_alert_row.php
 *
 * On the Salary Grade index page:
 *  - Puts the session-success alert on the same row as the
 *    "Import new circular" button instead of its own full-width block above it.
 *  - Fixes the close (X) button the same way as the review page: drops
 *    `alert-dismissible` (which absolutely-positions the X relative to
 *    the alert box — breaks once the alert auto-sizes in a flex row) in
 *    favor of a manual flex layout (text + button side by side), and adds
 *    the missing aria-label.
 *
 * Depends on patch_salary_grade_subtitles.php having already been applied
 * (that patch left "Import new circular" alone in its own
 * justify-content-end row).
 *
 * Usage: php patch_salary_grade_index_alert_row.php
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
$view = __DIR__ . '/resources/views/salary-grades/index.blade.php';

apply_patch(
    $view,
    '@if (session(\'success\'))
<div class="alert alert-success alert-dismissible fade show small py-2" role="alert">
    {{ session(\'success\') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if (session(\'error\'))
<div class="alert alert-danger alert-dismissible fade show small py-2" role="alert">
    {{ session(\'error\') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="d-flex justify-content-end align-items-center mb-3">
    <a href="{{ route(\'salary-grades.create\') }}" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">
        <i class="bi bi-upload"></i> Import new circular
    </a>
</div>',
    '@if (session(\'error\'))
<div class="alert alert-danger alert-dismissible fade show small py-2" role="alert">
    {{ session(\'error\') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    @if (session(\'success\'))
    <div class="alert alert-success fade show small py-2 px-3 mb-0 d-flex align-items-center gap-2" role="alert">
        <span>{{ session(\'success\') }}</span>
        <button type="button" class="btn-close" style="font-size: 0.65rem;" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif
    <a href="{{ route(\'salary-grades.create\') }}" class="btn btn-sm ms-auto" style="background-color: var(--hr-primary); color: #fff;">
        <i class="bi bi-upload"></i> Import new circular
    </a>
</div>',
    'Combine success alert and Import button into one row, fix close button'
);

echo "\nDone. Reload the Salary Grade index page (after a successful import/confirm) to verify:\n";
echo " - The success message sits on the same row as 'Import new circular', message on the left, button on the right.\n";
echo " - The X button sits right next to the message text and dismisses the alert correctly.\n";
echo " - Error messages (if any) still show on their own row above, unaffected.\n";
