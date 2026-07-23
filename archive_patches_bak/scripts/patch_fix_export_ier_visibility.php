<?php
/**
 * patch_fix_export_ier_visibility.php
 *
 * Fixes: the "Export IER" button (added by patch_add_export_ier.php)
 * disappeared once a posting advanced past Step 3 or was closed.
 *
 * Root cause: it was inserted directly after the "New schedule" button,
 * which sits inside an existing `@if ($currentStep < 4)` guard -- correct
 * for "New schedule" (shouldn't create new schedules once you've moved
 * on), but wrong for "Export IER", which should stay available as a
 * record any time, including after the posting closes.
 *
 * Fix: moves the button to right after that @endif, so it always shows
 * in Step 3 regardless of how far the posting has progressed.
 *
 * IMPORTANT: run patch_add_export_ier.php first if you haven't already --
 * this patch only relocates the button, it doesn't add it from scratch.
 *
 * Run once from the project root:
 *   php patch_fix_export_ier_visibility.php
 * Then delete this file — it is a one-shot installer, not idempotent.
 */

function apply_patch($path, $old, $new, $label) {
    if (!file_exists($path)) {
        fwrite(STDERR, "[ABORT] File not found: $path ($label)\n");
        exit(1);
    }
    $contents = file_get_contents($path);
    if (strpos($contents, $old) === false) {
        fwrite(STDERR, "[ABORT] Expected content not found for: $label\n");
        fwrite(STDERR, "        File may already be patched, or patch_add_export_ier.php hasn't\n");
        fwrite(STDERR, "        been run yet. No changes made.\n");
        exit(1);
    }
    copy($path, $path . '.bak');
    $updated = str_replace($old, $new, $contents, $count);
    if ($count !== 1) {
        fwrite(STDERR, "[ABORT] Expected exactly 1 match for '$label', found $count. Restoring backup.\n");
        copy($path . '.bak', $path);
        exit(1);
    }
    file_put_contents($path, $updated);
    echo "[OK] $label\n";
}

$showView = __DIR__ . '/resources/views/job-postings/show.blade.php';

$old = <<<'OLD'
                            @if ($currentStep < 4)
                            <button class="btn btn-sm" style="background-color:var(--hr-primary);color:#fff;"
                                    data-bs-toggle="modal" data-bs-target="#newScheduleModal">
                                <i class="bi bi-plus-lg me-1"></i> New schedule
                            </button>
                            <a href="{{ route('job-postings.export-ier', $posting->id) }}" class="btn btn-sm btn-outline-secondary ms-2">
                                <i class="bi bi-file-earmark-excel me-1"></i> Export IER
                            </a>
                            @endif
OLD;

$new = <<<'NEW'
                            @if ($currentStep < 4)
                            <button class="btn btn-sm" style="background-color:var(--hr-primary);color:#fff;"
                                    data-bs-toggle="modal" data-bs-target="#newScheduleModal">
                                <i class="bi bi-plus-lg me-1"></i> New schedule
                            </button>
                            @endif
                            <a href="{{ route('job-postings.export-ier', $posting->id) }}" class="btn btn-sm btn-outline-secondary ms-2">
                                <i class="bi bi-file-earmark-excel me-1"></i> Export IER
                            </a>
NEW;

apply_patch($showView, $old, $new, 'Move Export IER button outside the currentStep<4 guard so it always shows');

echo "\nDone. 'Export IER' now stays visible in Step 3 regardless of how far the posting\n";
echo "has progressed, including after it's closed.\n";
