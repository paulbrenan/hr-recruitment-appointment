<?php
/**
 * patch_prevent_past_schedule_dates.php
 *
 * Fixes: HR can currently pick a date/time that has already passed when
 * creating a new interview/exam/open-ranking schedule.
 *
 * Adds server-side validation (after_or_equal:now) to the two "create a
 * new schedule" paths:
 *   - store()           (single schedule, from the standalone /interviews page)
 *   - storeForPosting() (bulk schedule, from the pipeline's Step 3 "New schedule" modal)
 *
 * update() is deliberately left untouched: it's also used to record the
 * outcome of a schedule that already happened (mark it completed /
 * no_show / cancelled), which requires the date to be allowed to stay in
 * the past.
 *
 * Note: this is server-side only. If the scheduling form's date/time
 * picker should also visually grey out past dates, that needs a small
 * client-side change (e.g. a `min` attribute) in the blade file itself --
 * send that file over and it can be added too.
 *
 * Run once from the project root:
 *   php patch_prevent_past_schedule_dates.php
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

$file = __DIR__ . '/app/Http/Controllers/InterviewScheduleController.php';

// 1. createRules() — used by store()
apply_patch(
    $file,
    <<<'OLD'
    private function createRules(): array
    {
        return [
            'application_id'  => ['required', 'exists:applications,id'],
            'type'            => ['required', 'in:open_ranking,interview,exam'],
            'scheduled_at'    => ['required', 'date'],
OLD,
    <<<'NEW'
    private function createRules(): array
    {
        return [
            'application_id'  => ['required', 'exists:applications,id'],
            'type'            => ['required', 'in:open_ranking,interview,exam'],
            'scheduled_at'    => ['required', 'date', 'after_or_equal:now'],
NEW,
    'createRules(): reject past dates when creating a schedule via store()'
);

// 2. storeForPosting()'s inline validation — the pipeline's bulk-schedule modal
apply_patch(
    $file,
    <<<'OLD'
            'job_posting_id'          => ['required', 'exists:job_postings,id'],
            'job_posting_location_id' => ['nullable', 'exists:job_posting_locations,id'],
            'type'                    => ['required', 'array', 'min:1'],
            'type.*'                  => ['in:open_ranking,interview,exam'],
            'scheduled_at'            => ['required', 'date'],
OLD,
    <<<'NEW'
            'job_posting_id'          => ['required', 'exists:job_postings,id'],
            'job_posting_location_id' => ['nullable', 'exists:job_posting_locations,id'],
            'type'                    => ['required', 'array', 'min:1'],
            'type.*'                  => ['in:open_ranking,interview,exam'],
            'scheduled_at'            => ['required', 'date', 'after_or_equal:now'],
NEW,
    'storeForPosting(): reject past dates when bulk-scheduling from the pipeline'
);

echo "\nDone. Attempting to create a new schedule with a date/time in the past will now\n";
echo "fail validation with a standard 'scheduled at must be a date after or equal to now' error.\n";
