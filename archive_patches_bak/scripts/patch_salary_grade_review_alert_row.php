<?php
/**
 * patch_salary_grade_review_alert_row.php
 *
 * On the Salary Grade "Review Import" page:
 *  - Puts the session-success alert on the same row as the
 *    "Back to Salary Grade" link instead of its own full-width block above it.
 *  - Fixes the close (X) button: the old markup used Bootstrap's
 *    `alert-dismissible`, which absolutely-positions the close button
 *    relative to the alert box. Once the alert sits in a flex row and
 *    auto-sizes to its content, that absolute positioning breaks (the X
 *    can end up misplaced/overlapping). This switches to a manual flex
 *    layout (text + button side by side, no absolute positioning) so the
 *    X always sits correctly next to the message, and adds the missing
 *    aria-label for accessibility.
 *
 * Depends on patch_salary_grade_review_layout.php having already been
 * applied (that patch left the Back link alone in its own flex row).
 *
 * Usage: php patch_salary_grade_review_alert_row.php
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

apply_patch(
    $view,
    '@if (session(\'success\'))
<div class="alert alert-success alert-dismissible fade show small py-2" role="alert">
    {{ session(\'success\') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="d-flex justify-content-end align-items-start mb-3">
    <a href="{{ route(\'salary-grades.index\') }}" class="small">&larr; Back to Salary Grade</a>
</div>',
    '<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    @if (session(\'success\'))
    <div class="alert alert-success fade show small py-2 px-3 mb-0 d-flex align-items-center gap-2" role="alert">
        <span>{{ session(\'success\') }}</span>
        <button type="button" class="btn-close" style="font-size: 0.65rem;" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif
    <a href="{{ route(\'salary-grades.index\') }}" class="small ms-auto">&larr; Back to Salary Grade</a>
</div>',
    'Combine success alert and Back link into one row, fix close button'
);

echo "\nDone. Reload a Review Salary Grade Import page (after a successful upload) to verify:\n";
echo " - The success message sits on the same row as 'Back to Salary Grade', message on the left, link on the right.\n";
echo " - The X button sits right next to the message text and dismisses the alert correctly.\n";
