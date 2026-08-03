<?php
/**
 * patch_pick_applicants_select_n_label.php
 *
 * Follow-up to patch_schedule_pick_applicants.php: relabels the custom
 * count control from "[N input] [Select first N button]" to
 * "Select: [N input] [Select button]" -- a leading label instead of a
 * trailing "first N" phrase on the button itself.
 *
 * Usage: php patch_pick_applicants_select_n_label.php
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

apply_patch(
    $view,
    '<div class="d-flex align-items-center gap-1">
                        <input type="number" min="1" max="150" class="form-control form-control-sm" style="width:80px;" id="pickSelectN" placeholder="N">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="pickSelectNBtn">Select first N</button>
                    </div>',
    '<div class="d-flex align-items-center gap-1">
                        <span class="small text-muted">Select:</span>
                        <input type="number" min="1" max="150" class="form-control form-control-sm" style="width:80px;" id="pickSelectN" placeholder="N">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="pickSelectNBtn">Select</button>
                    </div>',
    'Relabel to "Select:" + number + Select button'
);

echo "\nDone. Reload the Pick Applicants modal to verify the control now reads 'Select: [N] [Select]'.\n";
echo "No JS changes needed -- #pickSelectN and #pickSelectNBtn element IDs are unchanged, so the existing logic still works.\n";
