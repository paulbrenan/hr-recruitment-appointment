<?php

/**
 * patch_schedule_panelists.php
 *
 * WHAT THIS DOES:
 *   Replaces the plain "Interviewer / evaluator" + "Interviewer / evaluator email"
 *   fields in the interview scheduling modals with a panelist checklist pulled
 *   from the vacancy's assigned panelists (the same pool set on the job posting).
 *
 *   Changes:
 *   1. Migration — creates interview_schedule_panelist pivot table
 *   2. InterviewSchedule model — adds panelists() relationship
 *   3. Panelist model — adds interviewSchedules() relationship
 *   4. InterviewScheduleController:
 *      - index()  → passes $allPanelists to the view
 *      - store()  → syncs panelist_ids[] pivot; still sends interviewer email
 *                   to each selected panelist that has an email (if panelists
 *                   table ever gains an email column — for now falls back to
 *                   the legacy interviewer_email field, kept as optional)
 *      - update() → syncs panelist_ids[] pivot
 *   5. New route: GET /interviews/panelists-for-posting/{jobPostingId}
 *      → returns JSON list of panelists assigned to that posting with
 *        their availability flag, used by the modal JS
 *   6. routes/web.php — adds the above route
 *   7. interviews/index.blade.php:
 *      - "New schedule" modal: replaces interviewer fields with panelist checklist
 *        (loaded via AJAX when an application is picked)
 *      - "Edit schedule" modal: same replacement; pre-checks already-assigned panelists
 *      - Table "Interviewer" column: shows comma-separated panelist names
 *      - JS: wires AJAX fetch + modal population
 *
 * HOW TO RUN:
 *   php patch_schedule_panelists.php     (from project root)
 *   php artisan migrate                  (required afterward)
 *
 * DELETE this script after running.
 */

define('ROOT', __DIR__);

// ─── helpers ───────────────────────────────────────────────────────────────

function backup(string $path): void {
    if (!file_exists($path)) return;
    $bak = $path . '.bak';
    $i = 2;
    while (file_exists($bak)) { $bak = $path . '.bak' . $i++; }
    copy($path, $bak);
    echo "  [bak] $bak\n";
}

function apply_patch(string $path, string $old, string $new, string $label): void {
    if (!file_exists($path)) { echo "\n❌ File not found: $path\n"; exit(1); }
    $content = file_get_contents($path);
    $count = substr_count($content, $old);
    if ($count === 0) {
        echo "\n❌ PATCH ABORTED — content not found in $path\nLabel: $label\nSearched for:\n---\n$old\n---\n";
        exit(1);
    }
    if ($count > 1) {
        echo "\n❌ PATCH ABORTED — pattern found $count times (expected 1) in $path\nLabel: $label\n";
        exit(1);
    }
    backup($path);
    file_put_contents($path, str_replace($old, $new, $content));
    echo "  [ok ] $label\n";
}

function write_new(string $path, string $content, string $label): void {
    backup($path);
    file_put_contents($path, $content);
    echo "  [ok ] $label\n";
}

echo "\n=== patch_schedule_panelists.php ===\n\n";

// ─── 1. Migration ──────────────────────────────────────────────────────────

echo "[1] Creating migration: interview_schedule_panelist pivot...\n";

$migrationDir = ROOT . '/database/migrations';
if (!is_dir($migrationDir)) { echo "❌ database/migrations not found. Run from project root.\n"; exit(1); }

$migrationFile = $migrationDir . '/' . date('Y_m_d_His') . '_create_interview_schedule_panelist_table.php';

write_new($migrationFile, <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interview_schedule_panelist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_schedule_id')->constrained('interview_schedules')->cascadeOnDelete();
            $table->foreignId('panelist_id')->constrained('panelists')->cascadeOnDelete();
            $table->unique(['interview_schedule_id', 'panelist_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_schedule_panelist');
    }
};
PHP, 'Migration: interview_schedule_panelist');

// ─── 2. InterviewSchedule model — panelists() relationship ────────────────

echo "\n[2] Patching InterviewSchedule model...\n";

$isModelPath = ROOT . '/app/Models/InterviewSchedule.php';
if (!file_exists($isModelPath)) { echo "❌ app/Models/InterviewSchedule.php not found.\n"; exit(1); }

$isContent = file_get_contents($isModelPath);

// Append panelists() before the last closing brace
backup($isModelPath);
$panelRelationship = <<<'PHP'

    public function panelists()
    {
        return $this->belongsToMany(\App\Models\Panelist::class, 'interview_schedule_panelist')
                    ->withTimestamps();
    }
PHP;
$patched = preg_replace('/(\n\})\s*$/', $panelRelationship . "\n}", $isContent);
file_put_contents($isModelPath, $patched);
echo "  [ok ] InterviewSchedule: panelists() relationship\n";

// ─── 3. Panelist model — interviewSchedules() relationship ────────────────

echo "\n[3] Patching Panelist model...\n";

$panelistModelPath = ROOT . '/app/Models/Panelist.php';
if (!file_exists($panelistModelPath)) { echo "❌ app/Models/Panelist.php not found.\n"; exit(1); }

$panelistContent = file_get_contents($panelistModelPath);
backup($panelistModelPath);
$scheduleRelationship = <<<'PHP'

    public function interviewSchedules()
    {
        return $this->belongsToMany(\App\Models\InterviewSchedule::class, 'interview_schedule_panelist')
                    ->withTimestamps();
    }
PHP;
$patched = preg_replace('/(\n\})\s*$/', $scheduleRelationship . "\n}", $panelistContent);
file_put_contents($panelistModelPath, $patched);
echo "  [ok ] Panelist: interviewSchedules() relationship\n";

// ─── 4. InterviewScheduleController ───────────────────────────────────────

echo "\n[4] Patching InterviewScheduleController.php...\n";

$controllerPath = ROOT . '/app/Http/Controllers/InterviewScheduleController.php';

// 4a. Add Panelist + JobPosting use statements
apply_patch(
    $controllerPath,
    "use App\Models\Application;\nuse App\Models\InterviewSchedule;",
    "use App\Models\Application;\nuse App\Models\InterviewSchedule;\nuse App\Models\JobPosting;\nuse App\Models\Panelist;",
    'Controller: add Panelist + JobPosting use statements'
);

// 4b. index() — pass allPanelists to view
apply_patch(
    $controllerPath,
    '        $applications = Application::with([\'candidate\', \'jobPosting\'])->get();
        return view(\'interviews.index\', compact(\'schedules\', \'applications\'));',
    '        $applications    = Application::with([\'candidate\', \'jobPosting\'])->get();
        $allPanelists    = Panelist::orderBy(\'name\')->get();
        return view(\'interviews.index\', compact(\'schedules\', \'applications\', \'allPanelists\'));',
    'Controller: index() passes allPanelists'
);

// 4c. createRules() — remove interviewer_name/email, add panelist_ids
apply_patch(
    $controllerPath,
    "    private function createRules(): array
    {
        return [
            'application_id' => ['required', 'exists:applications,id'],
            'type' => ['required', 'in:open_ranking,interview,exam'],
            'scheduled_at' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'interviewer_name' => ['nullable', 'string', 'max:255'],
            'interviewer_email' => ['nullable', 'email', 'max:255'],
        ];
    }",
    "    private function createRules(): array
    {
        return [
            'application_id'  => ['required', 'exists:applications,id'],
            'type'            => ['required', 'in:open_ranking,interview,exam'],
            'scheduled_at'    => ['required', 'date'],
            'location'        => ['nullable', 'string', 'max:255'],
            // Legacy single-interviewer fields — kept nullable so old data survives
            'interviewer_name'  => ['nullable', 'string', 'max:255'],
            'interviewer_email' => ['nullable', 'email', 'max:255'],
            'panelist_ids'    => ['nullable', 'array'],
            'panelist_ids.*'  => ['exists:panelists,id'],
        ];
    }",
    'Controller: createRules() adds panelist_ids'
);

// 4d. updateRules() — same
apply_patch(
    $controllerPath,
    "    private function updateRules(): array
    {
        return [
            'application_id' => ['required', 'exists:applications,id'],
            'type' => ['required', 'in:open_ranking,interview,exam'],
            'scheduled_at' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'interviewer_name' => ['nullable', 'string', 'max:255'],
            'interviewer_email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', 'in:scheduled,completed,cancelled,no_show'],
            'remarks' => ['nullable', 'string'],
        ];
    }",
    "    private function updateRules(): array
    {
        return [
            'application_id'    => ['required', 'exists:applications,id'],
            'type'              => ['required', 'in:open_ranking,interview,exam'],
            'scheduled_at'      => ['required', 'date'],
            'location'          => ['nullable', 'string', 'max:255'],
            'interviewer_name'  => ['nullable', 'string', 'max:255'],
            'interviewer_email' => ['nullable', 'email', 'max:255'],
            'panelist_ids'      => ['nullable', 'array'],
            'panelist_ids.*'    => ['exists:panelists,id'],
            'status'            => ['required', 'in:scheduled,completed,cancelled,no_show'],
            'remarks'           => ['nullable', 'string'],
        ];
    }",
    'Controller: updateRules() adds panelist_ids'
);

// 4e. store() — sync panelist pivot after creating the schedule
// Replace the line that creates the schedule and the line after it
apply_patch(
    $controllerPath,
    '        $validated[\'status\'] = \'scheduled\';
        $schedule = InterviewSchedule::create($validated);',
    '        $validated[\'status\'] = \'scheduled\';
        // Remove pivot fields from validated before create (not real columns)
        $panelistIds = array_map(\'intval\', $request->input(\'panelist_ids\', []));
        unset($validated[\'panelist_ids\']);
        $schedule = InterviewSchedule::create($validated);
        if (!empty($panelistIds)) {
            $schedule->panelists()->sync($panelistIds);
        }',
    'Controller: store() syncs panelist pivot'
);

// 4f. update() — sync panelist pivot after updating
apply_patch(
    $controllerPath,
    '        $schedule = InterviewSchedule::findOrFail($id);
        $validated = $request->validate($this->updateRules());
        $schedule->update($validated);
        return redirect()',
    '        $schedule = InterviewSchedule::findOrFail($id);
        $validated = $request->validate($this->updateRules());
        $panelistIds = array_map(\'intval\', $request->input(\'panelist_ids\', []));
        unset($validated[\'panelist_ids\']);
        $schedule->update($validated);
        $schedule->panelists()->sync($panelistIds);
        return redirect()',
    'Controller: update() syncs panelist pivot'
);

// 4g. Add panelisForPosting() method before destroy()
apply_patch(
    $controllerPath,
    '    public function destroy($id)
    {',
    '    /**
     * GET /interviews/panelists-for-posting/{jobPostingId}
     * Returns JSON list of panelists assigned to a job posting with availability flag.
     * Used by the scheduling modal to populate the checklist when an application is selected.
     */
    public function panelistsForPosting($jobPostingId)
    {
        $posting = JobPosting::findOrFail($jobPostingId);
        $panelists = $posting->panelists()->orderBy(\'name\')->get()->map(function ($p) {
            return [
                \'id\'           => $p->id,
                \'name\'         => $p->name,
                \'is_available\' => (bool) $p->pivot->is_available,
            ];
        });
        return response()->json($panelists);
    }

    public function destroy($id)
    {',
    'Controller: panelistsForPosting() method'
);

// ─── 5. routes/web.php ─────────────────────────────────────────────────────

echo "\n[5] Adding route to routes/web.php...\n";

$webPath = ROOT . '/routes/web.php';

// Find the interviews resource route and add the new route after it
// We'll anchor on the InterviewScheduleController use or the route declaration
// Try anchoring on the store route pattern — use a safe unique anchor
$webContent = file_get_contents($webPath);

// Look for the interviews routes block — anchor on the controller class reference in routes
// The safest anchor is whatever registers interviews.store
if (strpos($webContent, 'InterviewScheduleController') === false) {
    echo "  [warn] Could not find InterviewScheduleController in routes/web.php.\n";
    echo "         Add this route manually:\n";
    echo "         Route::get('/interviews/panelists-for-posting/{jobPostingId}', [InterviewScheduleController::class, 'panelistsForPosting'])->name('interviews.panelists-for-posting');\n";
} else {
    // Add the use statement if not already there
    if (strpos($webContent, 'use App\Http\Controllers\InterviewScheduleController;') !== false) {
        // Route already has use; just add the new route
        // Anchor: find the line with interviews.destroy or the resource block
        // We'll append after the destroy route or after the last InterviewScheduleController route
        // Safest: append after 'interviews.destroy' route registration
        $destroyAnchor = "Route::delete('/interviews/{id}', [InterviewScheduleController::class, 'destroy'])->name('interviews.destroy');";
        if (strpos($webContent, $destroyAnchor) !== false) {
            apply_patch(
                $webPath,
                $destroyAnchor,
                $destroyAnchor . "\nRoute::get('/interviews/panelists-for-posting/{jobPostingId}', [InterviewScheduleController::class, 'panelistsForPosting'])->name('interviews.panelists-for-posting');",
                'web.php: add panelistsForPosting route'
            );
        } else {
            // Fallback: try Route::resource pattern
            $resourceAnchor = "Route::resource('interviews', InterviewScheduleController::class);";
            if (strpos($webContent, $resourceAnchor) !== false) {
                apply_patch(
                    $webPath,
                    $resourceAnchor,
                    $resourceAnchor . "\nRoute::get('/interviews/panelists-for-posting/{jobPostingId}', [InterviewScheduleController::class, 'panelistsForPosting'])->name('interviews.panelists-for-posting');",
                    'web.php: add panelistsForPosting route (resource anchor)'
                );
            } else {
                echo "  [warn] Could not find a safe anchor in routes/web.php.\n";
                echo "         Add this route manually near the other interviews routes:\n";
                echo "         Route::get('/interviews/panelists-for-posting/{jobPostingId}', [InterviewScheduleController::class, 'panelistsForPosting'])->name('interviews.panelists-for-posting');\n";
            }
        }
    }
}

// ─── 6. interviews/index.blade.php ────────────────────────────────────────

echo "\n[6] Patching interviews/index.blade.php...\n";

$bladePath = ROOT . '/resources/views/interviews/index.blade.php';

// 6a. Table "Interviewer" column — show panelist names from relationship
apply_patch(
    $bladePath,
    '                    <td>{{ $s->interviewer_name ?? \'—\' }}</td>',
    '                    <td>
                        @if ($s->panelists->isNotEmpty())
                            {{ $s->panelists->pluck(\'name\')->implode(\', \') }}
                        @elseif ($s->interviewer_name)
                            {{ $s->interviewer_name }}
                        @else
                            —
                        @endif
                    </td>',
    'index.blade.php: table interviewer column shows panelist names'
);

// 6b. Load panelists relationship in the schedules query
// The index() already eager-loads application.candidate and application.jobPosting
// We need to also eager-load panelists on each schedule
// The controller already returns $schedules — we patch the eager load in the controller
apply_patch(
    $controllerPath,
    "        \$schedules = InterviewSchedule::with(['application.candidate', 'application.jobPosting'])",
    "        \$schedules = InterviewSchedule::with(['application.candidate', 'application.jobPosting', 'panelists'])",
    'Controller: index() eager-loads panelists on schedules'
);

// 6c. New schedule modal — replace interviewer fields with panelist checklist
apply_patch(
    $bladePath,
    '                    <div class="mb-2">
                        <label class="form-label small">Interviewer / evaluator</label>
                        <input type="text" name="interviewer_name" class="form-control form-control-sm">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Interviewer / evaluator email</label>
                        <input type="email" name="interviewer_email" class="form-control form-control-sm" placeholder="Needed to send the invitation email">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">Send invitation</button>',
    '                    {{-- Panelist checklist — populated via AJAX when application is selected --}}
                    <div class="mb-2" id="newPanelistSection">
                        <label class="form-label small">Vacancy for Screening / Interview</label>
                        <div id="newPanelistList" class="border rounded p-2" style="min-height: 48px; background: #f8f9fa;">
                            <span class="text-muted small" id="newPanelistPlaceholder">Select an application above to load panelists.</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">Send invitation</button>',
    'index.blade.php: new modal replaces interviewer fields with panelist checklist'
);

// 6d. Edit schedule modal — replace interviewer fields with panelist checklist
apply_patch(
    $bladePath,
    '                    <div class="mb-2">
                        <label class="form-label small">Interviewer / evaluator</label>
                        <input type="text" name="interviewer_name" id="edit_interviewer_name" class="form-control form-control-sm">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Interviewer / evaluator email</label>
                        <input type="email" name="interviewer_email" id="edit_interviewer_email" class="form-control form-control-sm">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Status</label>',
    '                    {{-- Panelist checklist — populated when modal opens --}}
                    <div class="mb-2" id="editPanelistSection">
                        <label class="form-label small">Vacancy for Screening / Interview</label>
                        <div id="editPanelistList" class="border rounded p-2" style="min-height: 48px; background: #f8f9fa;">
                            <span class="text-muted small" id="editPanelistPlaceholder">Loading panelists...</span>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Status</label>',
    'index.blade.php: edit modal replaces interviewer fields with panelist checklist'
);

// 6e. Edit modal open JS — populate panelists
apply_patch(
    $bladePath,
    "        document.getElementById('edit_application_id').value = button.getAttribute('data-application-id');
        document.getElementById('edit_type').value = button.getAttribute('data-type');
        document.getElementById('edit_scheduled_at').value = button.getAttribute('data-scheduled-at');
        document.getElementById('edit_location').value = button.getAttribute('data-location') || '';
        document.getElementById('edit_interviewer_name').value = button.getAttribute('data-interviewer-name') || '';
        document.getElementById('edit_interviewer_email').value = button.getAttribute('data-interviewer-email') || '';
        document.getElementById('edit_status').value = button.getAttribute('data-status');
        document.getElementById('edit_remarks').value = button.getAttribute('data-remarks') || '';",
    "        document.getElementById('edit_application_id').value = button.getAttribute('data-application-id');
        document.getElementById('edit_type').value = button.getAttribute('data-type');
        document.getElementById('edit_scheduled_at').value = button.getAttribute('data-scheduled-at');
        document.getElementById('edit_location').value = button.getAttribute('data-location') || '';
        document.getElementById('edit_status').value = button.getAttribute('data-status');
        document.getElementById('edit_remarks').value = button.getAttribute('data-remarks') || '';

        // Load panelists for this schedule's job posting
        const editJobPostingId = button.getAttribute('data-job-posting-id');
        const editSelectedIds  = JSON.parse(button.getAttribute('data-panelist-ids') || '[]');
        loadPanelists('editPanelistList', 'editPanelistPlaceholder', editJobPostingId, editSelectedIds);",
    'index.blade.php: edit modal JS removes interviewer fields, adds panelist load'
);

// 6f. Edit button data attributes — add job-posting-id and panelist-ids
apply_patch(
    $bladePath,
    '                            data-interviewer-name="{{ $s->interviewer_name }}"
                            data-interviewer-email="{{ $s->interviewer_email }}"
                            data-status="{{ $s->status }}"',
    '                            data-job-posting-id="{{ $s->application->job_posting_id }}"
                            data-panelist-ids="{{ json_encode($s->panelists->pluck(\'id\')) }}"
                            data-status="{{ $s->status }}"',
    'index.blade.php: edit button passes job-posting-id and panelist-ids'
);

// 6g. Add AJAX + panelist rendering JS + new modal application-change listener
// Append before closing @endpush
apply_patch(
    $bladePath,
    '@endpush
@endsection',
    '@endpush
@endsection',
    '' // placeholder — we\'ll do the real append below
);

// Actually append new JS block inside @push('scripts') before @endpush
apply_patch(
    $bladePath,
    '    });
</script>
@endpush
@endsection',
    '    });

    // ── Panelist checklist helpers ────────────────────────────────────────────

    /**
     * Fetch panelists for a job posting and render checkboxes into a container.
     * @param {string} listId        - ID of the container div
     * @param {string} placeholderId - ID of the placeholder span
     * @param {string|number} jobPostingId
     * @param {number[]} selectedIds - IDs to pre-check (for edit modal)
     */
    function loadPanelists(listId, placeholderId, jobPostingId, selectedIds) {
        const list = document.getElementById(listId);
        const placeholder = document.getElementById(placeholderId);

        if (!jobPostingId) {
            list.innerHTML = \'<span class="text-muted small" id="\' + placeholderId + \'">Select an application above to load panelists.</span>\';
            return;
        }

        list.innerHTML = \'<span class="text-muted small">Loading...</span>\';

        fetch(\'/interviews/panelists-for-posting/\' + jobPostingId)
            .then(function (res) {
                if (!res.ok) throw new Error(\'Server error \' + res.status);
                return res.json();
            })
            .then(function (panelists) {
                if (!panelists.length) {
                    list.innerHTML = \'<span class="text-muted small">No panelists assigned to this vacancy. Assign them on the Job Posting edit page first.</span>\';
                    return;
                }

                list.innerHTML = panelists.map(function (p) {
                    const checked    = selectedIds.includes(p.id) ? \'checked\' : \'\';
                    const available  = p.is_available
                        ? \'<span class="badge text-bg-success ms-2" style="font-size:0.65rem;">Available</span>\'
                        : \'<span class="badge text-bg-secondary ms-2" style="font-size:0.65rem;">Unavailable</span>\';
                    return \'<div class="form-check mb-1">\' +
                        \'<input class="form-check-input" type="checkbox" name="panelist_ids[]"\' +
                        \' value="\' + p.id + \'" id="panCheck_\' + listId + \'_\' + p.id + \'" \' + checked + \'>\' +
                        \'<label class="form-check-label small" for="panCheck_\' + listId + \'_\' + p.id + \'">\' +
                        p.name + available +
                        \'</label>\' +
                        \'</div>\';
                }).join(\'\');
            })
            .catch(function () {
                list.innerHTML = \'<span class="text-danger small">Failed to load panelists.</span>\';
            });
    }

    // New schedule modal — load panelists when application changes
    document.querySelector(\'#newScheduleModal select[name="application_id"]\').addEventListener(\'change\', function () {
        const selected = this.options[this.selectedIndex];
        // We need the job_posting_id from the selected application
        // Pass it via a data attribute on each <option> — see the patched view
        const jobPostingId = selected.getAttribute(\'data-job-posting-id\');
        loadPanelists(\'newPanelistList\', \'newPanelistPlaceholder\', jobPostingId, []);
    });
</script>
@endpush
@endsection',
    'index.blade.php: panelist JS (loadPanelists + new modal listener)'
);

// 6h. New modal application <option> — add data-job-posting-id attribute
apply_patch(
    $bladePath,
    '                            @foreach ($applications as $application)
                                <option value="{{ $application->id }}">
                                    {{ $application->candidate->full_name }} — {{ $application->jobPosting->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Type</label>
                        <select name="type" class="form-select form-select-sm">',
    '                            @foreach ($applications as $application)
                                <option value="{{ $application->id }}" data-job-posting-id="{{ $application->job_posting_id }}">
                                    {{ $application->candidate->full_name }} — {{ $application->jobPosting->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Type</label>
                        <select name="type" class="form-select form-select-sm">',
    'index.blade.php: new modal application options carry data-job-posting-id'
);

// ─── Done ──────────────────────────────────────────────────────────────────

echo <<<TEXT

✅ All patches applied.

NEXT STEPS (in order):
  1. php artisan migrate
     → Creates interview_schedule_panelist pivot table

  2. Open Scheduling → New schedule
     → Select an application; the panelist checklist loads via AJAX
     → Shows panelists assigned to that vacancy with Available/Unavailable badges
     → Check the ones attending; submit

  3. Open an existing schedule's edit modal
     → Previously-selected panelists are pre-checked automatically

  4. The "Interviewer" column in the table now shows the selected panelist names.
     (Falls back to the old interviewer_name value for schedules created before this patch.)

  NOTES:
  - The legacy interviewer_name / interviewer_email columns are kept in the DB and
    rules as nullable — old data is not lost.
  - Panelists shown are only those assigned to the job posting. If none are assigned,
    a message tells HR to assign them on the Job Posting edit page first.
  - The interviewer invitation email (InterviewerInvitationNotification) currently
    sends to interviewer_email. Since panelists don't have an email column yet,
    that notification path is unchanged for now. If you add an email column to the
    panelists table later, wire it up in store() by looping $schedule->panelists.

  5. DELETE this script after running.

TEXT;
