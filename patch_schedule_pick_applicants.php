<?php
/**
 * patch_schedule_pick_applicants.php
 *
 * Adds the ability to schedule a specific SUBSET of qualified applicants
 * (instead of always all of them at once), capped at 150, via a new
 * "Pick applicants" modal launched from the existing "New schedule"
 * modal in the job posting pipeline's "Open Ranking & Scheduling" step.
 *
 * NOTE ON "multiple schedules": this already works today without any
 * changes -- InterviewScheduleController::storeForPosting() creates one
 * schedule per (applicant, type) pair and skips anyone who already has
 * an active schedule of that type, so clicking "New schedule" a second
 * time naturally creates a second batch for whoever's left unscheduled.
 * This patch is purely about letting you choose WHICH applicants go into
 * a given batch, instead of it always being "everyone qualified."
 *
 * Changes:
 *  1. Controller (InterviewScheduleController::storeForPosting):
 *     accepts an optional `application_ids[]` array, validated server-
 *     side (max 150 -- not just trusting client-side JS). If provided,
 *     only those applicants are considered for this batch. If omitted,
 *     falls back to the original "all qualified" behavior -- so small
 *     postings where picking isn't worth the click still work exactly
 *     as before.
 *  2. View (job-postings/show.blade.php):
 *     - "Pick applicants" button inside the New Schedule modal.
 *     - New "Pick applicants" modal: checkbox list of qualified
 *       applicants, a running "X / 150 selected" counter, Select 100 /
 *       Select 150 quick buttons, a "Select first N" number input, and
 *       a "Clear" button.
 *     - Selecting and confirming writes hidden application_ids[] inputs
 *       into the New Schedule form and updates its info banner to show
 *       how many applicants were picked (vs. the original "all
 *       qualified" count).
 *
 * Usage: php patch_schedule_pick_applicants.php
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

// Adjust these if the live paths differ from the Laravel defaults.
$controller = __DIR__ . '/app/Http/Controllers/InterviewScheduleController.php';
$view       = __DIR__ . '/resources/views/job-postings/show.blade.php';

// =========================================================================
// CONTROLLER
// =========================================================================

// ---------------------------------------------------------------------
// 1. Add application_ids validation to storeForPosting's rules.
// ---------------------------------------------------------------------
apply_patch(
    $controller,
    "        \$validated = \$request->validate([
            'job_posting_id'          => ['required', 'exists:job_postings,id'],
            'job_posting_location_id' => ['nullable', 'exists:job_posting_locations,id'],
            'type'                    => ['required', 'array', 'min:1'],
            'type.*'                  => ['in:open_ranking,interview,exam'],
            'scheduled_at'            => ['required', 'date', 'after_or_equal:now'],
            'location'                => ['nullable', 'string', 'max:255'],
            'panelist_ids'            => ['nullable', 'array'],
            'panelist_ids.*'          => ['exists:panelists,id'],
        ]);",
    "        \$validated = \$request->validate([
            'job_posting_id'          => ['required', 'exists:job_postings,id'],
            'job_posting_location_id' => ['nullable', 'exists:job_posting_locations,id'],
            'type'                    => ['required', 'array', 'min:1'],
            'type.*'                  => ['in:open_ranking,interview,exam'],
            'scheduled_at'            => ['required', 'date', 'after_or_equal:now'],
            'location'                => ['nullable', 'string', 'max:255'],
            'panelist_ids'            => ['nullable', 'array'],
            'panelist_ids.*'          => ['exists:panelists,id'],
            // Optional: schedule only this specific subset of qualified
            // applicants (capped at 150) instead of all of them at once.
            // Validated server-side too -- the 150 cap in the modal's JS
            // is a UX convenience, not the actual guarantee.
            'application_ids'         => ['nullable', 'array', 'max:150'],
            'application_ids.*'       => ['integer', 'exists:applications,id'],
        ]);",
    'Add application_ids validation to storeForPosting'
);

// ---------------------------------------------------------------------
// 2. Filter the eligible-applicants query to the picked subset, if any
//    was provided.
// ---------------------------------------------------------------------
apply_patch(
    $controller,
    "        if (!empty(\$validated['job_posting_location_id'])) {
            \$query->where('job_posting_location_id', \$validated['job_posting_location_id']);
        }

        \$applications = \$query->with(['candidate', 'jobPosting'])->get();",
    "        if (!empty(\$validated['job_posting_location_id'])) {
            \$query->where('job_posting_location_id', \$validated['job_posting_location_id']);
        }

        // If specific applicants were picked (via the \"Pick applicants\"
        // modal), narrow to just that subset. Otherwise, fall back to the
        // original behavior of scheduling everyone qualified at once.
        if (!empty(\$validated['application_ids'])) {
            \$query->whereIn('id', \$validated['application_ids']);
        }

        \$applications = \$query->with(['candidate', 'jobPosting'])->get();",
    'Filter eligible applicants to the picked subset when provided'
);

// =========================================================================
// VIEW
// =========================================================================

// ---------------------------------------------------------------------
// 3. Add a "Pick applicants" button + live selection summary into the
//    New Schedule modal, replacing the static "all qualified" banner.
// ---------------------------------------------------------------------
apply_patch(
    $view,
    '                <div class="modal-body">
                    <div class="alert alert-info small py-2 mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        This will schedule <strong>all qualified applicants</strong> on this posting at once.
                        {{ $applications->where(\'qualification_result\', \'qualified\')->count() }} applicant(s) will be scheduled.
                    </div>

                    <div class="mb-2">
                        <label class="form-label small d-flex justify-content-between align-items-center mb-1">
                            <span>Type</span>',
    '                <div class="modal-body">
                    <div class="alert alert-info small py-2 mb-3" id="newScheduleInfoBanner">
                        <i class="bi bi-info-circle me-1"></i>
                        This will schedule <strong>all qualified applicants</strong> on this posting at once.
                        {{ $applications->where(\'qualification_result\', \'qualified\')->count() }} applicant(s) will be scheduled.
                    </div>

                    <div id="newScheduleApplicantIdInputs"></div>

                    <div class="mb-3 d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="pickApplicantsBtn">
                            <i class="bi bi-people me-1"></i> Pick applicants
                        </button>
                        <span class="small text-muted" id="pickApplicantsSummary">All qualified applicants (default)</span>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small d-flex justify-content-between align-items-center mb-1">
                            <span>Type</span>',
    'Add Pick applicants button + selection summary to New Schedule modal'
);

// ---------------------------------------------------------------------
// 4. Insert the "Pick applicants" modal right after the New Schedule
//    modal closes.
// ---------------------------------------------------------------------
apply_patch(
    $view,
    '                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background-color:var(--hr-primary);color:#fff;">
                        <i class="bi bi-calendar-check me-1"></i> Schedule &amp; send invitations
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Add Criterion --}}',
    '                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background-color:var(--hr-primary);color:#fff;">
                        <i class="bi bi-calendar-check me-1"></i> Schedule &amp; send invitations
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Pick Applicants (subset of qualified applicants for the New Schedule
     modal above, capped at 150) --}}
<div class="modal fade" id="pickApplicantsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Pick applicants (max 150)</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="pickSelect100">Select 100</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="pickSelect150">Select 150</button>
                    <div class="d-flex align-items-center gap-1">
                        <input type="number" min="1" max="150" class="form-control form-control-sm" style="width:80px;" id="pickSelectN" placeholder="N">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="pickSelectNBtn">Select first N</button>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger ms-auto" id="pickClearBtn">Clear</button>
                </div>
                <div class="small mb-2">
                    <span id="pickCountLabel">0</span> / 150 selected
                </div>
                <div class="border rounded" style="max-height:360px; overflow-y:auto;">
                    <table class="table table-sm mb-0">
                        <thead class="sticky-top bg-white">
                            <tr>
                                <th style="width:36px;"></th>
                                <th>Candidate</th>
                                <th>Already scheduled</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($applications->where(\'qualification_result\', \'qualified\') as $qa)
                            <tr>
                                <td>
                                    <input type="checkbox" class="form-check-input pick-applicant-checkbox"
                                           value="{{ $qa->id }}" data-name="{{ $qa->candidate->full_name ?? (\'Applicant #\' . $qa->id) }}">
                                </td>
                                <td class="small">{{ $qa->candidate->full_name ?? (\'Applicant #\' . $qa->id) }}</td>
                                <td class="small text-muted">
                                    @if ($qa->interviewSchedules->where(\'status\', \'!=\', \'cancelled\')->isNotEmpty())
                                        <i class="bi bi-check2-circle text-success"></i> Yes
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">No qualified applicants yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm" style="background-color:var(--hr-primary);color:#fff;" id="pickUseSelectedBtn">
                    Use selected
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Add Criterion --}}',
    'Insert Pick Applicants modal (checkbox list, 150 cap, quick-select)'
);

// ---------------------------------------------------------------------
// 5. Wire it all up with JS: modal handoff, 150 cap enforcement, quick-
//    select buttons, and writing hidden application_ids[] inputs into
//    the New Schedule form.
// ---------------------------------------------------------------------
apply_patch(
    $view,
    "    const panelistsList = document.getElementById('si-panelists-list');",
    "    // ── Pick applicants modal (New Schedule -> subset of up to 150) ────────
    (function () {
        const MAX_PICK = 150;
        const newScheduleModalEl = document.getElementById('newScheduleModal');
        const pickModalEl = document.getElementById('pickApplicantsModal');
        if (!newScheduleModalEl || !pickModalEl) return;

        const newScheduleModal = bootstrap.Modal.getOrCreateInstance(newScheduleModalEl);
        const pickModal = bootstrap.Modal.getOrCreateInstance(pickModalEl);

        const pickBtn = document.getElementById('pickApplicantsBtn');
        const checkboxes = Array.from(pickModalEl.querySelectorAll('.pick-applicant-checkbox'));
        const countLabel = document.getElementById('pickCountLabel');
        const summarySpan = document.getElementById('pickApplicantsSummary');
        const infoBanner = document.getElementById('newScheduleInfoBanner');
        const hiddenInputsContainer = document.getElementById('newScheduleApplicantIdInputs');

        function checkedCount() {
            return checkboxes.filter(function (cb) { return cb.checked; }).length;
        }

        function updateCountLabel() {
            countLabel.textContent = checkedCount();
        }

        function enforceCap(justChecked) {
            if (checkedCount() > MAX_PICK) {
                justChecked.checked = false;
                alert('You can select at most ' + MAX_PICK + ' applicants at a time.');
            }
            updateCountLabel();
        }

        checkboxes.forEach(function (cb) {
            cb.addEventListener('change', function () {
                if (cb.checked) enforceCap(cb);
                else updateCountLabel();
            });
        });

        function selectFirstN(n) {
            checkboxes.forEach(function (cb, i) {
                cb.checked = i < n;
            });
            updateCountLabel();
        }

        const select100Btn = document.getElementById('pickSelect100');
        if (select100Btn) select100Btn.addEventListener('click', function () { selectFirstN(100); });

        const select150Btn = document.getElementById('pickSelect150');
        if (select150Btn) select150Btn.addEventListener('click', function () { selectFirstN(150); });

        const selectNInput = document.getElementById('pickSelectN');
        const selectNBtn = document.getElementById('pickSelectNBtn');
        if (selectNBtn && selectNInput) {
            selectNBtn.addEventListener('click', function () {
                const n = Math.max(0, Math.min(MAX_PICK, parseInt(selectNInput.value, 10) || 0));
                selectFirstN(n);
            });
        }

        const clearBtn = document.getElementById('pickClearBtn');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                checkboxes.forEach(function (cb) { cb.checked = false; });
                updateCountLabel();
            });
        }

        // Open: hide New Schedule, then show Pick Applicants once the
        // first one has fully hidden (avoids Bootstrap backdrop stacking
        // issues from having two modals open at once).
        if (pickBtn) {
            pickBtn.addEventListener('click', function () {
                newScheduleModalEl.addEventListener('hidden.bs.modal', function handler() {
                    newScheduleModalEl.removeEventListener('hidden.bs.modal', handler);
                    pickModal.show();
                });
                newScheduleModal.hide();
            });
        }

        // Confirm: write hidden application_ids[] inputs into the New
        // Schedule form, update its summary/banner text, then hand back.
        const useSelectedBtn = document.getElementById('pickUseSelectedBtn');
        if (useSelectedBtn) {
            useSelectedBtn.addEventListener('click', function () {
                const selected = checkboxes.filter(function (cb) { return cb.checked; });

                hiddenInputsContainer.innerHTML = '';
                selected.forEach(function (cb) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'application_ids[]';
                    input.value = cb.value;
                    hiddenInputsContainer.appendChild(input);
                });

                if (selected.length > 0) {
                    summarySpan.textContent = selected.length + ' applicant(s) picked';
                    infoBanner.innerHTML = '<i class=\"bi bi-info-circle me-1\"></i> This will schedule the <strong>' + selected.length + ' picked applicant(s)</strong> only.';
                } else {
                    summarySpan.textContent = 'All qualified applicants (default)';
                    infoBanner.innerHTML = '<i class=\"bi bi-info-circle me-1\"></i> This will schedule <strong>all qualified applicants</strong> on this posting at once.';
                }

                pickModalEl.addEventListener('hidden.bs.modal', function handler() {
                    pickModalEl.removeEventListener('hidden.bs.modal', handler);
                    newScheduleModal.show();
                });
                pickModal.hide();
            });
        }
    })();

    const panelistsList = document.getElementById('si-panelists-list');",
    'Add Pick Applicants modal JS (150 cap, quick-select, form wiring)'
);

echo "\nDone. Verify:\n";
echo " - Open a job posting's pipeline, Step 3 (Open Ranking & Scheduling), click 'New schedule'.\n";
echo " - Click 'Pick applicants' -- the New Schedule modal hides, Pick Applicants modal shows with a checkbox list.\n";
echo " - Try Select 100 / Select 150 / a custom N -- confirm checking beyond 150 total is blocked with an alert.\n";
echo " - Click 'Use selected' -- you're returned to New Schedule, its banner now says how many were picked.\n";
echo " - Submit -- only the picked applicants get scheduled (check the DB / confirmation emails).\n";
echo " - Skip picking entirely and submit directly -- confirm it still falls back to scheduling ALL qualified applicants (old behavior, unchanged).\n";
echo " - Run 'New schedule' a second time for whoever's left unscheduled -- confirm it creates an additional batch without duplicating anyone already scheduled for that type.\n";
