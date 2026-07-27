<?php
/**
 * patch_disable_past_dates_and_hide_availability.php
 *
 * Two small UI changes to the pipeline's "New schedule" modal
 * (Step 3 -- Open Ranking & Scheduling):
 *
 *   1. The date & time picker now has a `min` attribute set to right now,
 *      so the browser itself greys out/blocks picking a past date. This
 *      is on top of the server-side `after_or_equal:now` validation
 *      already added by patch_prevent_past_schedule_dates.php -- that
 *      one is the real enforcement, this one is just the UI affordance.
 *   2. The Available/Unavailable badge next to each panelist's name in
 *      the panel-members checklist is removed.
 *
 * Run once from the project root:
 *   php patch_disable_past_dates_and_hide_availability.php
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
        fwrite(STDERR, "        File may already be patched or is a different version. No changes made.\n");
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

$file = __DIR__ . '/resources/views/job-postings/show.blade.php';

// ── 1. Block past dates in the date/time picker ─────────────────────────

$old1 = <<<'OLD'
                    <div class="mb-2">
                        <label class="form-label small">Date &amp; time</label>
                        <input type="datetime-local" name="scheduled_at" class="form-control form-control-sm" required>
                    </div>
OLD;

$new1 = <<<'NEW'
                    <div class="mb-2">
                        <label class="form-label small">Date &amp; time</label>
                        <input type="datetime-local" name="scheduled_at" class="form-control form-control-sm"
                               min="{{ now()->format('Y-m-d\TH:i') }}" required>
                    </div>
NEW;

apply_patch($file, $old1, $new1, 'New Schedule modal: add min="now" to the date/time picker so past dates are unclickable');

// ── 2. Remove the Available/Unavailable badge from the panelist checklist ──

$old2 = <<<'OLD'
                                    <label class="form-check-label small" for="sp{{ $p->id }}">
                                        {{ $p->name }}
                                        <span class="badge ms-1 {{ $p->pivot->is_available ? 'text-bg-success' : 'text-bg-secondary' }}" style="font-size:.65rem;">
                                            {{ $p->pivot->is_available ? 'Available' : 'Unavailable' }}
                                        </span>
                                    </label>
OLD;

$new2 = <<<'NEW'
                                    <label class="form-check-label small" for="sp{{ $p->id }}">
                                        {{ $p->name }}
                                    </label>
NEW;

apply_patch($file, $old2, $new2, 'Panel members checklist: remove Available/Unavailable badge next to each name');

echo "\nDone. Reload the pipeline's Open Ranking & Scheduling step -- the date/time picker\n";
echo "will now block past dates, and panelist names no longer show an availability badge.\n";
