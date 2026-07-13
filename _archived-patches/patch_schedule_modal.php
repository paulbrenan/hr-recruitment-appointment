<?php
/**
 * patch_schedule_modal.php
 *
 * Run from the project root:
 *   php patch_schedule_modal.php
 *
 * What it does:
 *  1. resources/views/job-postings/show.blade.php
 *     - removes the "Place of assignment" filter from the New Schedule modal
 *     - replaces the "Type" <select> with checkboxes + "Select all"
 *     - updates the JS to drive the checkboxes and guard against 0 selected
 *  2. app/Http/Controllers/InterviewScheduleController.php
 *     - storeForPosting(): 'type' becomes an array, validated per-value
 *     - loops per applicant x per selected type (so picking Interview +
 *       Exam creates 2 schedule rows + 2 invitation emails per applicant)
 *
 * Safe to run multiple times: each target string is checked for existence
 * first. If a file doesn't match exactly (e.g. already patched, or edited
 * since), that file is skipped with a message and nothing is written.
 * A .bak copy of each file is made before any write.
 */

$root = __DIR__;

function patchFile(string $path, array $replacements, string $label): void
{
    if (!file_exists($path)) {
        echo "[SKIP] $label — file not found: $path\n";
        return;
    }

    $content = file_get_contents($path);
    $original = $content;

    foreach ($replacements as $i => [$search, $replace]) {
        if (strpos($content, $search) === false) {
            echo "[ABORT] $label — expected block #$i not found (file may already be patched, or has changed). No changes written.\n";
            return;
        }
    }

    foreach ($replacements as [$search, $replace]) {
        $content = substr_replace($content, $replace, strpos($content, $search), strlen($search));
    }

    if ($content === $original) {
        echo "[SKIP] $label — no changes needed.\n";
        return;
    }

    $backup = $path . '.bak';
    if (!file_exists($backup)) {
        copy($path, $backup);
    } else {
        copy($path, $path . '.bak.' . date('Ymd_His'));
    }

    file_put_contents($path, $content);
    echo "[OK] $label — patched. Backup at: $backup\n";
}

// ── 1. Blade view ────────────────────────────────────────────────────────
$bladePath = $root . '/resources/views/job-postings/show.blade.php';

$bladeOldModalFields = <<<'OLD'
                    @if ($locations->count() > 1)
                    <div class="mb-2">
                        <label class="form-label small">Place of assignment <span class="text-muted">(optional — filter applicants by location)</span></label>
                        <select name="job_posting_location_id" id="schedLocationSelect" class="form-select form-select-sm">
                            <option value="">All locations</option>
                            @foreach ($locations as $loc)
                            <option value="{{ $loc->id }}">
                                {{ $loc->place_of_assignment }} ({{ $applications->where('job_posting_location_id', $loc->id)->count() }} applicants)
                            </option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    <div class="mb-2">
                        <label class="form-label small">Type</label>
                        <select name="type" class="form-select form-select-sm">
                            <option value="open_ranking">Open ranking</option>
                            <option value="interview">Interview</option>
                            <option value="exam">Exam</option>
                        </select>
                    </div>
OLD;

$bladeNewModalFields = <<<'NEW'
                    <div class="mb-2">
                        <label class="form-label small d-flex justify-content-between align-items-center mb-1">
                            <span>Type</span>
                            <span class="form-check form-check-inline m-0">
                                <input class="form-check-input" type="checkbox" id="schedTypeSelectAll">
                                <label class="form-check-label small" for="schedTypeSelectAll">Select all</label>
                            </span>
                        </label>
                        <div class="border rounded p-2">
                            <div class="form-check">
                                <input class="form-check-input sched-type-checkbox" type="checkbox" name="type[]" value="open_ranking" id="schedTypeOpenRanking" checked>
                                <label class="form-check-label small" for="schedTypeOpenRanking">Open ranking</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input sched-type-checkbox" type="checkbox" name="type[]" value="interview" id="schedTypeInterview">
                                <label class="form-check-label small" for="schedTypeInterview">Interview</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input sched-type-checkbox" type="checkbox" name="type[]" value="exam" id="schedTypeExam">
                                <label class="form-check-label small" for="schedTypeExam">Exam</label>
                            </div>
                        </div>
                    </div>
NEW;

$bladeOldJs = <<<'OLD'
// ── Schedule modal: update applicant count when location filter changes ────
document.getElementById('schedLocationSelect')?.addEventListener('change', function () {
    // Nothing needed — the server handles filtering on submit.
    // Could show a live count here in future.
});
OLD;

$bladeNewJs = <<<'NEW'
// ── Schedule modal: type checkboxes "select all" + at-least-one guard ──────
document.getElementById('schedTypeSelectAll')?.addEventListener('change', function () {
    document.querySelectorAll('.sched-type-checkbox').forEach(cb => cb.checked = this.checked);
});
document.querySelectorAll('.sched-type-checkbox').forEach(function (cb) {
    cb.addEventListener('change', function () {
        const boxes = document.querySelectorAll('.sched-type-checkbox');
        const checkedCount = document.querySelectorAll('.sched-type-checkbox:checked').length;
        const selectAll = document.getElementById('schedTypeSelectAll');
        if (selectAll) selectAll.checked = checkedCount === boxes.length;
    });
});
document.querySelector('#newScheduleModal form')?.addEventListener('submit', function (e) {
    const checkedCount = document.querySelectorAll('.sched-type-checkbox:checked').length;
    if (checkedCount === 0) {
        e.preventDefault();
        alert('Please select at least one schedule type.');
    }
});
NEW;

patchFile($bladePath, [
    [$bladeOldModalFields, $bladeNewModalFields],
    [$bladeOldJs, $bladeNewJs],
], 'show.blade.php');


// ── 2. Controller ────────────────────────────────────────────────────────
$controllerPath = $root . '/app/Http/Controllers/InterviewScheduleController.php';

$ctrlOldValidate = <<<'OLD'
        $validated = $request->validate([
            'job_posting_id'          => ['required', 'exists:job_postings,id'],
            'job_posting_location_id' => ['nullable', 'exists:job_posting_locations,id'],
            'type'                    => ['required', 'in:open_ranking,interview,exam'],
            'scheduled_at'            => ['required', 'date'],
            'location'                => ['nullable', 'string', 'max:255'],
            'panelist_ids'            => ['nullable', 'array'],
            'panelist_ids.*'          => ['exists:panelists,id'],
        ]);
OLD;

$ctrlNewValidate = <<<'NEW'
        $validated = $request->validate([
            'job_posting_id'          => ['required', 'exists:job_postings,id'],
            'job_posting_location_id' => ['nullable', 'exists:job_posting_locations,id'],
            'type'                    => ['required', 'array', 'min:1'],
            'type.*'                  => ['in:open_ranking,interview,exam'],
            'scheduled_at'            => ['required', 'date'],
            'location'                => ['nullable', 'string', 'max:255'],
            'panelist_ids'            => ['nullable', 'array'],
            'panelist_ids.*'          => ['exists:panelists,id'],
        ]);
NEW;

$ctrlOldLoop = <<<'OLD'
        $created = 0;
        foreach ($applications as $application) {
            $schedule = InterviewSchedule::create([
                'application_id' => $application->id,
                'type'           => $validated['type'],
                'scheduled_at'   => $validated['scheduled_at'],
                'location'       => $validated['location'] ?? null,
                'status'         => 'scheduled',
            ]);

            if (!empty($panelistIds)) {
                $schedule->panelists()->sync($panelistIds);
            }

            // Send invitation to candidate
            try {
                $application->candidate->notify(new \App\Notifications\ScheduleInvitationNotification($schedule));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to send schedule invitation: ' . $e->getMessage());
            }

            $created++;
        }
OLD;

$ctrlNewLoop = <<<'NEW'
        $created = 0;
        foreach ($applications as $application) {
            foreach ($validated['type'] as $type) {
                $schedule = InterviewSchedule::create([
                    'application_id' => $application->id,
                    'type'           => $type,
                    'scheduled_at'   => $validated['scheduled_at'],
                    'location'       => $validated['location'] ?? null,
                    'status'         => 'scheduled',
                ]);

                if (!empty($panelistIds)) {
                    $schedule->panelists()->sync($panelistIds);
                }

                // Send invitation to candidate (one per selected type)
                try {
                    $application->candidate->notify(new \App\Notifications\ScheduleInvitationNotification($schedule));
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed to send schedule invitation: ' . $e->getMessage());
                }

                $created++;
            }
        }
NEW;

patchFile($controllerPath, [
    [$ctrlOldValidate, $ctrlNewValidate],
    [$ctrlOldLoop, $ctrlNewLoop],
], 'InterviewScheduleController.php');

echo "\nDone.\n";
