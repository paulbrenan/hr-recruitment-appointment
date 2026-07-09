<?php
/**
 * patch_scheduling_assessment_pipeline.php
 *
 * WHAT THIS DOES:
 *
 * 1. FIX: Location filter dropdown in qualification checking (Step 2)
 *    The filter was broken because applications created before the
 *    job_posting_location_id column existed have null there, so
 *    data-location-id="null" never matched any filter option.
 *    Fix: show ALL rows when no filter is selected; treat null location
 *    rows as matching every filter option (no location = no filter).
 *    ALSO adds a "Export qualifications" button that appears once
 *    ALL applicants have been checked (qualification_result is not null).
 *
 * 2. RESTRUCTURE: Scheduling by job not by candidate (Step 3)
 *    - Removes the candidate select from the schedule modal
 *    - Adds a location dropdown (only shown if posting has multiple locations)
 *    - On submit: creates one InterviewSchedule per qualified applicant
 *      (status: qualified or interview_scheduled) on that posting/location
 *    - InterviewScheduleController::storeForPosting() — new method
 *    - New route: POST /interviews/for-posting
 *
 * 3. COPY: Assessment import/export into Step 4 of pipeline
 *    - Import scores from Excel button (reuses AssessmentController routes)
 *    - Export template button
 *    - View/Print CAR button
 *    - Send all notifications button
 *    - All wired to the posting's id directly (no separate page needed)
 *
 * HOW TO RUN:
 *   php patch_scheduling_assessment_pipeline.php    (from project root)
 *   No migration needed.
 *
 * DELETE this script after running.
 */

define('ROOT', __DIR__);

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
    if ($count === 0) { echo "\n❌ PATCH ABORTED — not found in:\n  $path\nLabel: $label\n"; exit(1); }
    if ($count > 1)   { echo "\n❌ PATCH ABORTED — found $count times in:\n  $path\nLabel: $label\n"; exit(1); }
    backup($path);
    file_put_contents($path, str_replace($old, $new, $content));
    echo "  [ok ] $label\n";
}

function write_file(string $path, string $content, string $label): void {
    backup($path);
    file_put_contents($path, $content);
    echo "  [ok ] $label\n";
}

echo "\n=== patch_scheduling_assessment_pipeline.php ===\n\n";

$showPath       = ROOT . '/resources/views/job-postings/show.blade.php';
$schedCtrlPath  = ROOT . '/app/Http/Controllers/InterviewScheduleController.php';
$webPath        = ROOT . '/routes/web.php';

// ═══════════════════════════════════════════════════════════════════════════
// 1. FIX: Location filter + Export qualifications button (Step 2)
// ═══════════════════════════════════════════════════════════════════════════

echo "[1] Fixing location filter + adding export qualifications button...\n";

// Fix the JS filter to handle null location IDs
apply_patch(
    $showPath,
    "// ── Qualification checking: filter by place of assignment ──────────────────
document.getElementById('qualLocationFilter')?.addEventListener('change', function () {
    const val = this.value;
    document.querySelectorAll('#panel-2 [data-location-id]').forEach(row => {
        row.style.display = (!val || row.dataset.locationId === val) ? '' : 'none';
    });
});",
    "// ── Qualification checking: filter by place of assignment ──────────────────
document.getElementById('qualLocationFilter')?.addEventListener('change', function () {
    const val = this.value;
    document.querySelectorAll('#panel-2 [data-location-id]').forEach(row => {
        if (!val) {
            row.style.display = ''; // show all when no filter
        } else {
            // Rows with no location (null) match all filters — don't hide them
            const rowLoc = row.dataset.locationId;
            row.style.display = (!rowLoc || rowLoc === 'null' || rowLoc === val) ? '' : 'none';
        }
    });
});",
    'show.blade.php: fix location filter for null job_posting_location_id'
);

// Add export qualifications button — appears when ALL applicants have been checked
apply_patch(
    $showPath,
    "                    <div class=\"d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2\">
                        <h6 class=\"mb-0\">
                            Qualification checking
                            <span class=\"badge text-bg-light text-dark border ms-1\">{{ \$applications->count() }}</span>
                        </h6>
                        @if (\$locations->count() > 1)
                        <select id=\"qualLocationFilter\" class=\"form-select form-select-sm\" style=\"max-width:280px;\">
                            <option value=\"\">All places of assignment</option>
                            @foreach (\$locations as \$loc)
                            <option value=\"{{ \$loc->id }}\">{{ \$loc->place_of_assignment }} ({{ \$applications->where('job_posting_location_id', \$loc->id)->count() }})</option>
                            @endforeach
                        </select>
                        @endif
                    </div>",
    "                    @php
                        \$allChecked = \$applications->count() > 0
                            && \$applications->every(fn(\$a) => !empty(\$a->qualification_result));
                    @endphp
                    <div class=\"d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2\">
                        <h6 class=\"mb-0\">
                            Qualification checking
                            <span class=\"badge text-bg-light text-dark border ms-1\">{{ \$applications->count() }}</span>
                        </h6>
                        <div class=\"d-flex align-items-center gap-2 flex-wrap\">
                            @if (\$locations->count() > 1)
                            <select id=\"qualLocationFilter\" class=\"form-select form-select-sm\" style=\"max-width:280px;\">
                                <option value=\"\">All places of assignment</option>
                                @foreach (\$locations as \$loc)
                                <option value=\"{{ \$loc->id }}\">{{ \$loc->place_of_assignment }} ({{ \$applications->where('job_posting_location_id', \$loc->id)->count() }})</option>
                                @endforeach
                            </select>
                            @endif
                            @if (\$allChecked)
                            <a href=\"{{ route('job-postings.export-qualifications', \$posting->id) }}\"
                               class=\"btn btn-sm btn-outline-success\">
                                <i class=\"bi bi-file-earmark-excel me-1\"></i> Export qualifications
                            </a>
                            @else
                            <button type=\"button\" class=\"btn btn-sm btn-outline-secondary\" disabled
                                    title=\"Check all applicants before exporting\">
                                <i class=\"bi bi-file-earmark-excel me-1\"></i> Export qualifications
                            </button>
                            @endif
                        </div>
                    </div>",
    'show.blade.php: add export qualifications button (visible when all checked)'
);

// ═══════════════════════════════════════════════════════════════════════════
// 2. RESTRUCTURE: Schedule modal — by job not by candidate
// ═══════════════════════════════════════════════════════════════════════════

echo "\n[2] Restructuring schedule modal to be per-job...\n";

// Replace the entire newScheduleModal with a job-based version
apply_patch(
    $showPath,
    "{{-- New Schedule --}}
<div class=\"modal fade\" id=\"newScheduleModal\" tabindex=\"-1\">
    <div class=\"modal-dialog\">
        <div class=\"modal-content\">
            <form action=\"{{ route('interviews.store') }}\" method=\"POST\">
                @csrf
                <div class=\"modal-header\">
                    <h6 class=\"modal-title\">Schedule interview / exam</h6>
                    <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\"></button>
                </div>
                <div class=\"modal-body\">
                    <div class=\"mb-2\">
                        <label class=\"form-label small\">Candidate</label>
                        <select name=\"application_id\" id=\"schedAppSelect\" class=\"form-select form-select-sm\" required>
                            <option value=\"\" disabled selected>Select candidate</option>
                            @foreach (\$applications->whereIn('status', ['qualified','interview_scheduled','ranked']) as \$app)
                                <option value=\"{{ \$app->id }}\" data-job-posting-id=\"{{ \$posting->id }}\">
                                    {{ \$app->candidate->full_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class=\"mb-2\">
                        <label class=\"form-label small\">Type</label>
                        <select name=\"type\" class=\"form-select form-select-sm\">
                            <option value=\"open_ranking\">Open ranking</option>
                            <option value=\"interview\">Interview</option>
                            <option value=\"exam\">Exam</option>
                        </select>
                    </div>
                    <div class=\"mb-2\">
                        <label class=\"form-label small\">Date &amp; time</label>
                        <input type=\"datetime-local\" name=\"scheduled_at\" class=\"form-control form-control-sm\" required>
                    </div>
                    <div class=\"mb-2\">
                        <label class=\"form-label small\">Location</label>
                        <input type=\"text\" name=\"location\" class=\"form-control form-control-sm\">
                    </div>
                    <div class=\"mb-2\">
                        <label class=\"form-label small\">Vacancy for Screening / Interview</label>
                        <div id=\"schedPanelistBox\" class=\"border rounded p-2\" style=\"min-height:48px;background:#f8f9fa;\">
                            <span class=\"text-muted small\">Select a candidate above to load panelists.</span>
                        </div>
                    </div>
                </div>
                <div class=\"modal-footer\">
                    <button type=\"button\" class=\"btn btn-sm btn-outline-secondary\" data-bs-dismiss=\"modal\">Cancel</button>
                    <button type=\"submit\" class=\"btn btn-sm\" style=\"background-color:var(--hr-primary);color:#fff;\">Send invitation</button>
                </div>
            </form>
        </div>
    </div>
</div>",
    "{{-- New Schedule (per-job: schedules ALL qualified applicants at once) --}}
<div class=\"modal fade\" id=\"newScheduleModal\" tabindex=\"-1\">
    <div class=\"modal-dialog\">
        <div class=\"modal-content\">
            <form action=\"{{ route('interviews.store-for-posting') }}\" method=\"POST\">
                @csrf
                <input type=\"hidden\" name=\"job_posting_id\" value=\"{{ \$posting->id }}\">
                <div class=\"modal-header\">
                    <h6 class=\"modal-title\">Schedule interview / exam</h6>
                    <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\"></button>
                </div>
                <div class=\"modal-body\">
                    <div class=\"alert alert-info small py-2 mb-3\">
                        <i class=\"bi bi-info-circle me-1\"></i>
                        This will schedule <strong>all qualified applicants</strong> on this posting at once.
                        {{ \$applications->whereIn('status', ['qualified','interview_scheduled','ranked'])->count() }} applicant(s) will be scheduled.
                    </div>

                    @if (\$locations->count() > 1)
                    <div class=\"mb-2\">
                        <label class=\"form-label small\">Place of assignment <span class=\"text-muted\">(optional — filter applicants by location)</span></label>
                        <select name=\"job_posting_location_id\" id=\"schedLocationSelect\" class=\"form-select form-select-sm\">
                            <option value=\"\">All locations</option>
                            @foreach (\$locations as \$loc)
                            <option value=\"{{ \$loc->id }}\">
                                {{ \$loc->place_of_assignment }} ({{ \$applications->where('job_posting_location_id', \$loc->id)->count() }} applicants)
                            </option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    <div class=\"mb-2\">
                        <label class=\"form-label small\">Type</label>
                        <select name=\"type\" class=\"form-select form-select-sm\">
                            <option value=\"open_ranking\">Open ranking</option>
                            <option value=\"interview\">Interview</option>
                            <option value=\"exam\">Exam</option>
                        </select>
                    </div>
                    <div class=\"mb-2\">
                        <label class=\"form-label small\">Date &amp; time</label>
                        <input type=\"datetime-local\" name=\"scheduled_at\" class=\"form-control form-control-sm\" required>
                    </div>
                    <div class=\"mb-2\">
                        <label class=\"form-label small\">Venue</label>
                        <input type=\"text\" name=\"location\" class=\"form-control form-control-sm\" placeholder=\"e.g. SDO Conference Room\">
                    </div>
                    <div class=\"mb-2\">
                        <label class=\"form-label small\">Panel members</label>
                        <div id=\"schedPanelistBox\" class=\"border rounded p-2\" style=\"min-height:48px;background:#f8f9fa;\">
                            @if (\$panelists->isNotEmpty())
                                @foreach (\$panelists as \$p)
                                <div class=\"form-check mb-1\">
                                    <input class=\"form-check-input\" type=\"checkbox\" name=\"panelist_ids[]\"
                                           value=\"{{ \$p->id }}\" id=\"sp{{ \$p->id }}\">
                                    <label class=\"form-check-label small\" for=\"sp{{ \$p->id }}\">
                                        {{ \$p->name }}
                                        <span class=\"badge ms-1 {{ \$p->pivot->is_available ? 'text-bg-success' : 'text-bg-secondary' }}\" style=\"font-size:.65rem;\">
                                            {{ \$p->pivot->is_available ? 'Available' : 'Unavailable' }}
                                        </span>
                                    </label>
                                </div>
                                @endforeach
                            @else
                                <span class=\"text-muted small\">No panelists assigned to this posting.</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class=\"modal-footer\">
                    <button type=\"button\" class=\"btn btn-sm btn-outline-secondary\" data-bs-dismiss=\"modal\">Cancel</button>
                    <button type=\"submit\" class=\"btn btn-sm\" style=\"background-color:var(--hr-primary);color:#fff;\">
                        <i class=\"bi bi-calendar-check me-1\"></i> Schedule &amp; send invitations
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>",
    'show.blade.php: replace candidate-based schedule modal with job-based modal'
);

// Remove the old schedAppSelect JS (no longer needed)
apply_patch(
    $showPath,
    "// ── Panelist checklist for schedule modal ───────────────────────────────────
document.getElementById('schedAppSelect')?.addEventListener('change', function () {
    const postingId = this.selectedOptions[0]?.dataset.jobPostingId;
    if (!postingId) return;
    const box = document.getElementById('schedPanelistBox');
    box.innerHTML = '<span class=\"text-muted small\">Loading...</span>';
    fetch('/interviews/panelists-for-posting/' + postingId)
        .then(r => r.json())
        .then(list => {
            if (!list.length) { box.innerHTML = '<span class=\"text-muted small\">No panelists assigned to this vacancy.</span>'; return; }
            box.innerHTML = list.map(p =>
                `<div class=\"form-check mb-1\">
                    <input class=\"form-check-input\" type=\"checkbox\" name=\"panelist_ids[]\" value=\"${p.id}\" id=\"pc${p.id}\">
                    <label class=\"form-check-label small\" for=\"pc${p.id}\">${p.name}
                        <span class=\"badge ms-1 ${p.is_available ? 'text-bg-success' : 'text-bg-secondary'}\" style=\"font-size:0.65rem;\">
                            ${p.is_available ? 'Available' : 'Unavailable'}
                        </span>
                    </label>
                </div>`
            ).join('');
        })
        .catch(() => { box.innerHTML = '<span class=\"text-danger small\">Failed to load panelists.</span>'; });
});",
    "// ── Schedule modal: update applicant count when location filter changes ────
document.getElementById('schedLocationSelect')?.addEventListener('change', function () {
    // Nothing needed — the server handles filtering on submit.
    // Could show a live count here in future.
});",
    'show.blade.php: replace schedAppSelect JS with location select note'
);

// ═══════════════════════════════════════════════════════════════════════════
// 3. ADD: Assessment import/export/CAR/send-all to Step 4 panel
// ═══════════════════════════════════════════════════════════════════════════

echo "\n[3] Adding assessment import/export/CAR/send-all to Step 4...\n";

apply_patch(
    $showPath,
    "            {{-- Ranking --}}
            <div class=\"card mb-3\">
                <div class=\"card-body p-4\">
                    <h6 class=\"mb-3\">Candidate ranking</h6>",
    "            {{-- Assessment toolbar --}}
            <div class=\"d-flex flex-wrap gap-2 mb-3\">
                @if (\$rankedCandidates->isNotEmpty())
                <form method=\"POST\" action=\"{{ route('assessments.send-all') }}\" class=\"m-0\">
                    @csrf
                    <input type=\"hidden\" name=\"job_posting_id\" value=\"{{ \$posting->id }}\">
                    <button type=\"submit\" class=\"btn btn-sm btn-outline-primary\"
                            onclick=\"return confirm('Send ranking notifications to all {{ \$rankedCandidates->count() }} applicant(s)?')\">
                        <i class=\"bi bi-envelope me-1\"></i> Send all notifications
                    </button>
                </form>
                @endif
                @if (\$criteria->isNotEmpty())
                <button type=\"button\" class=\"btn btn-sm btn-outline-secondary\" data-bs-toggle=\"modal\" data-bs-target=\"#importScoresModal\">
                    <i class=\"bi bi-upload me-1\"></i> Import scores from Excel
                </button>
                <a href=\"{{ route('assessments.template') }}?job_posting_id={{ \$posting->id }}\"
                   class=\"btn btn-sm btn-outline-secondary\">
                    <i class=\"bi bi-download me-1\"></i> Download template
                </a>
                @endif
                @if (\$rankedCandidates->isNotEmpty())
                <button type=\"button\" class=\"btn btn-sm btn-outline-secondary\" data-bs-toggle=\"modal\" data-bs-target=\"#carDocumentModal\">
                    <i class=\"bi bi-file-earmark-text me-1\"></i> View / Print CAR
                </button>
                @endif
            </div>

            {{-- Ranking --}}
            <div class=\"card mb-3\">
                <div class=\"card-body p-4\">
                    <h6 class=\"mb-3\">Candidate ranking</h6>",
    'show.blade.php: add assessment toolbar (import/export/CAR/send-all) to Step 4'
);

// Add import scores modal and CAR modal before @push('scripts')
apply_patch(
    $showPath,
    "@push('scripts')",
    "{{-- Import Scores --}}
<div class=\"modal fade\" id=\"importScoresModal\" tabindex=\"-1\">
    <div class=\"modal-dialog\">
        <div class=\"modal-content\">
            <form method=\"POST\" action=\"{{ route('assessments.import') }}\" enctype=\"multipart/form-data\">
                @csrf
                <input type=\"hidden\" name=\"job_posting_id\" value=\"{{ \$posting->id }}\">
                <div class=\"modal-header\">
                    <h6 class=\"modal-title\">Import scores from Excel</h6>
                    <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\"></button>
                </div>
                <div class=\"modal-body\">
                    <p class=\"small text-muted\">Upload the filled-in Excel template. Application codes and criterion names must match exactly.</p>
                    <input type=\"file\" name=\"import_file\" class=\"form-control form-control-sm\" accept=\".xlsx,.xls\" required>
                </div>
                <div class=\"modal-footer\">
                    <button type=\"button\" class=\"btn btn-sm btn-outline-secondary\" data-bs-dismiss=\"modal\">Cancel</button>
                    <button type=\"submit\" class=\"btn btn-sm\" style=\"background-color:var(--hr-primary);color:#fff;\">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- CAR Document --}}
<div class=\"modal fade\" id=\"carDocumentModal\" tabindex=\"-1\">
    <div class=\"modal-dialog modal-xl modal-dialog-scrollable\">
        <div class=\"modal-content\">
            <div class=\"modal-header\">
                <h6 class=\"modal-title\"><i class=\"bi bi-file-earmark-text me-2\"></i>Comparative Assessment Result</h6>
                <div class=\"d-flex align-items-center gap-2 ms-auto me-2\">
                    <div class=\"form-check form-check-inline mb-0\" style=\"font-size:0.8rem;\">
                        <input type=\"checkbox\" class=\"form-check-input\" id=\"carPublicToggle\">
                        <label for=\"carPublicToggle\" class=\"form-check-label\">Public view (conceal names)</label>
                    </div>
                    <button type=\"button\" class=\"btn btn-sm btn-outline-secondary\" onclick=\"window.print()\">
                        <i class=\"bi bi-printer me-1\"></i> Print
                    </button>
                </div>
                <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\"></button>
            </div>
            <div class=\"modal-body\">
                <div id=\"carDocumentPrintArea\">
                    <div class=\"text-center fw-bold mb-1\">Comparative Assessment Result (CAR)</div>
                    <div class=\"text-center text-muted small mb-3\">{{ \$posting->title }}</div>
                    <div class=\"row mb-2\" style=\"font-size:0.8rem;\">
                        <div class=\"col-6\">Position: <strong>{{ \$posting->title }}</strong></div>
                        <div class=\"col-6\">Date: <strong>{{ now()->format('M d, Y') }}</strong></div>
                        <div class=\"col-6\">SG: <strong>{{ \$posting->salary_grade ?? '—' }}</strong></div>
                        <div class=\"col-6\">Office: <strong>DepEd Division of Cavite Province</strong></div>
                    </div>
                    <div class=\"table-responsive\">
                        <table class=\"table table-bordered\" style=\"font-size:0.78rem;\" id=\"carDocTable\">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th class=\"car-confidential\">Name</th>
                                    <th>App. Code</th>
                                    @foreach (\$criteria as \$c)
                                    <th>{{ \$c->name }} ({{ rtrim(rtrim(number_format(\$c->weight_percentage,2),'0'),'.') }}%)</th>
                                    @endforeach
                                    <th>Total</th>
                                    <th>Passed</th>
                                    <th class=\"car-doc-fillable\">Background Investigation</th>
                                    <th class=\"car-doc-fillable\">Appointment</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (\$rankedCandidates as \$i => \$cand)
                                <tr>
                                    <td class=\"text-center fw-bold\">#{{ \$i + 1 }}</td>
                                    <td class=\"car-confidential\">{{ \$cand->candidate_name }}</td>
                                    <td>{{ \$cand->application_code ?? '—' }}</td>
                                    @foreach (\$criteria as \$c)
                                    <td class=\"text-center\">{{ \$cand->scores[\$c->id] ?? '—' }}</td>
                                    @endforeach
                                    <td class=\"text-center fw-bold\">{{ \$cand->total_score }}</td>
                                    <td class=\"text-center\">
                                        @if (\$cand->passed ?? false)
                                            <span class=\"badge text-bg-success\">Passed</span>
                                        @else
                                            <span class=\"badge text-bg-secondary\">—</span>
                                        @endif
                                    </td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')",
    'show.blade.php: add importScoresModal and carDocumentModal'
);

// Add CAR public toggle JS + print CSS
apply_patch(
    $showPath,
    "// ── Edit scores modal ───────────────────────────────────────────────────────",
    "// ── CAR public toggle ───────────────────────────────────────────────────────
document.getElementById('carPublicToggle')?.addEventListener('change', function () {
    document.getElementById('carDocTable')?.classList.toggle('public-mode', this.checked);
});

// ── Print CAR ───────────────────────────────────────────────────────────────
// Scoped print CSS added inline so it works without a separate stylesheet
if (!document.getElementById('carPrintStyle')) {
    const s = document.createElement('style');
    s.id = 'carPrintStyle';
    s.textContent = `@media print {
        body * { visibility: hidden; }
        #carDocumentPrintArea, #carDocumentPrintArea * { visibility: visible; }
        #carDocumentPrintArea { position: absolute; top: 0; left: 0; width: 100%; }
        .car-confidential.public-mode { display: none !important; }
    }`;
    document.head.appendChild(s);
}

// ── Edit scores modal ───────────────────────────────────────────────────────",
    'show.blade.php: add CAR toggle + print JS'
);

// ═══════════════════════════════════════════════════════════════════════════
// 4. InterviewScheduleController: add storeForPosting()
// ═══════════════════════════════════════════════════════════════════════════

echo "\n[4] Adding storeForPosting() to InterviewScheduleController...\n";

apply_patch(
    $schedCtrlPath,
    "    public function destroy(\$id)
    {
        \$schedule = InterviewSchedule::findOrFail(\$id);
        \$schedule->delete();
        return redirect()
            ->route('interviews.index')
            ->with('success', 'Schedule deleted successfully.');
    }",
    "    /**
     * Create one InterviewSchedule per qualified applicant on a given posting.
     * Called from the pipeline dashboard's Step 3 'New schedule' modal.
     * If a job_posting_location_id is provided, only applicants assigned to
     * that location are scheduled; otherwise all qualified applicants on the
     * posting are scheduled.
     */
    public function storeForPosting(Request \$request)
    {
        \$validated = \$request->validate([
            'job_posting_id'          => ['required', 'exists:job_postings,id'],
            'job_posting_location_id' => ['nullable', 'exists:job_posting_locations,id'],
            'type'                    => ['required', 'in:open_ranking,interview,exam'],
            'scheduled_at'            => ['required', 'date'],
            'location'                => ['nullable', 'string', 'max:255'],
            'panelist_ids'            => ['nullable', 'array'],
            'panelist_ids.*'          => ['exists:panelists,id'],
        ]);

        \$panelistIds = array_map('intval', \$request->input('panelist_ids', []));

        \$query = Application::where('job_posting_id', \$validated['job_posting_id'])
            ->whereIn('status', ['qualified', 'interview_scheduled', 'ranked']);

        if (!empty(\$validated['job_posting_location_id'])) {
            \$query->where('job_posting_location_id', \$validated['job_posting_location_id']);
        }

        \$applications = \$query->with(['candidate', 'jobPosting'])->get();

        if (\$applications->isEmpty()) {
            return redirect()->back()->with('error', 'No qualified applicants found for this posting/location.');
        }

        \$created = 0;
        foreach (\$applications as \$application) {
            \$schedule = InterviewSchedule::create([
                'application_id' => \$application->id,
                'type'           => \$validated['type'],
                'scheduled_at'   => \$validated['scheduled_at'],
                'location'       => \$validated['location'] ?? null,
                'status'         => 'scheduled',
            ]);

            if (!empty(\$panelistIds)) {
                \$schedule->panelists()->sync(\$panelistIds);
            }

            // Send invitation to candidate
            try {
                \$application->candidate->notify(new \App\Notifications\ScheduleInvitationNotification(\$schedule));
            } catch (\Throwable \$e) {
                \Illuminate\Support\Facades\Log::warning('Failed to send schedule invitation: ' . \$e->getMessage());
            }

            \$created++;
        }

        // Redirect back to the job posting pipeline (Step 3)
        return redirect()
            ->route('job-postings.show', \$validated['job_posting_id'])
            ->with('success', \"Scheduled {$created} applicant(s) and sent invitations.\");
    }

    public function destroy(\$id)
    {
        \$schedule = InterviewSchedule::findOrFail(\$id);
        \$schedule->delete();
        return redirect()
            ->route('interviews.index')
            ->with('success', 'Schedule deleted successfully.');
    }",
    'InterviewScheduleController: add storeForPosting()'
);

// ═══════════════════════════════════════════════════════════════════════════
// 5. Routes: add interviews.store-for-posting + assessments.template/import
//            + job-postings.export-qualifications
// ═══════════════════════════════════════════════════════════════════════════

echo "\n[5] Adding routes...\n";

apply_patch(
    $webPath,
    "// Scheduling
Route::get('/interviews', [InterviewScheduleController::class, 'index'])->name('interviews.index');
Route::post('/interviews', [InterviewScheduleController::class, 'store'])->name('interviews.store');
Route::put('/interviews/{id}', [InterviewScheduleController::class, 'update'])->name('interviews.update');
Route::delete('/interviews/{id}', [InterviewScheduleController::class, 'destroy'])->name('interviews.destroy');",
    "// Scheduling
Route::get('/interviews', [InterviewScheduleController::class, 'index'])->name('interviews.index');
Route::post('/interviews', [InterviewScheduleController::class, 'store'])->name('interviews.store');
Route::post('/interviews/for-posting', [InterviewScheduleController::class, 'storeForPosting'])->name('interviews.store-for-posting');
Route::put('/interviews/{id}', [InterviewScheduleController::class, 'update'])->name('interviews.update');
Route::delete('/interviews/{id}', [InterviewScheduleController::class, 'destroy'])->name('interviews.destroy');",
    'routes/web.php: add interviews.store-for-posting route'
);

// Add assessment template + import routes if not already present
$webContent = file_get_contents($webPath);
if (strpos($webContent, 'assessments.template') === false) {
    apply_patch(
        $webPath,
        "Route::get('/assessments', [AssessmentController::class, 'index'])->name('assessments.index');",
        "Route::get('/assessments', [AssessmentController::class, 'index'])->name('assessments.index');
Route::get('/assessments/template', [AssessmentController::class, 'downloadImportTemplate'])->name('assessments.template');
Route::post('/assessments/import', [AssessmentController::class, 'importScores'])->name('assessments.import');",
        'routes/web.php: add assessments.template + assessments.import routes'
    );
}

// Add export-qualifications route to job-postings
apply_patch(
    $webPath,
    "// Advance posting to next pipeline step\nRoute::post('/job-postings/{id}/advance-step', [JobPostingController::class, 'advanceStep'])->name('job-postings.advance-step');",
    "// Advance posting to next pipeline step\nRoute::post('/job-postings/{id}/advance-step', [JobPostingController::class, 'advanceStep'])->name('job-postings.advance-step');\n// Export qualification check results to Excel\nRoute::get('/job-postings/{id}/export-qualifications', [JobPostingController::class, 'exportQualifications'])->name('job-postings.export-qualifications');",
    'routes/web.php: add job-postings.export-qualifications route'
);

// ═══════════════════════════════════════════════════════════════════════════
// 6. JobPostingController: add exportQualifications()
// ═══════════════════════════════════════════════════════════════════════════

echo "\n[6] Adding exportQualifications() to JobPostingController...\n";

$jpCtrlPath = ROOT . '/app/Http/Controllers/JobPostingController.php';

apply_patch(
    $jpCtrlPath,
    "    public function destroy(\$id)
    {
        \$posting = JobPosting::findOrFail(\$id);
        \$posting->delete();

        return redirect()
            ->route('job-postings.index')
            ->with('success', 'Job posting deleted successfully.');
    }",
    "    /**
     * Export qualification check results for all applicants on this posting
     * to an Excel file. Only available once all applicants have been checked.
     */
    public function exportQualifications(\$id)
    {
        \$posting = JobPosting::with('locations')->findOrFail(\$id);

        \$applications = Application::with('candidate')
            ->where('job_posting_id', \$id)
            ->get();

        \$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        \$sheet = \$spreadsheet->getActiveSheet();
        \$sheet->setTitle('Qualification Check');

        // Headers
        \$headers = ['Candidate Name', 'Email', 'Place of Assignment', 'Education', 'Training', 'Experience', 'Eligibility', 'Overall Result', 'Notes'];
        foreach (\$headers as \$i => \$h) {
            \$col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(\$i + 1);
            \$sheet->setCellValue(\$col . '1', \$h);
        }
        \$sheet->getStyle('A1:I1')->getFont()->setBold(true);

        \$row = 2;
        foreach (\$applications as \$app) {
            \$check    = \$app->qualification_check ?? [];
            \$criteria = \$check['criteria'] ?? [];
            \$location = \$posting->locations->firstWhere('id', \$app->job_posting_location_id);

            \$sheet->setCellValue('A' . \$row, \$app->candidate?->full_name ?? '—');
            \$sheet->setCellValue('B' . \$row, \$app->candidate?->email ?? '—');
            \$sheet->setCellValue('C' . \$row, \$location?->place_of_assignment ?? '—');
            \$sheet->setCellValue('D' . \$row, (\$criteria['education']['passed'] ?? null) === true ? 'Qualified' : ((\$criteria['education']['passed'] ?? null) === false ? 'Not Qualified' : '—'));
            \$sheet->setCellValue('E' . \$row, (\$criteria['training']['passed'] ?? null) === true ? 'Qualified' : ((\$criteria['training']['passed'] ?? null) === false ? 'Not Qualified' : '—'));
            \$sheet->setCellValue('F' . \$row, (\$criteria['experience']['passed'] ?? null) === true ? 'Qualified' : ((\$criteria['experience']['passed'] ?? null) === false ? 'Not Qualified' : '—'));
            \$sheet->setCellValue('G' . \$row, (\$criteria['eligibility']['passed'] ?? null) === true ? 'Qualified' : ((\$criteria['eligibility']['passed'] ?? null) === false ? 'Not Qualified' : '—'));
            \$sheet->setCellValue('H' . \$row, ucfirst(str_replace('_', ' ', \$app->qualification_result ?? '—')));
            \$sheet->setCellValue('I' . \$row, \$check['notes'] ?? '');
            \$row++;
        }

        foreach (range(1, 9) as \$c) {
            \$sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(\$c))->setAutoSize(true);
        }

        \$writer   = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx(\$spreadsheet);
        \$filename = 'qualification-check-' . \$id . '-' . now()->format('Ymd') . '.xlsx';

        return response()->streamDownload(function () use (\$writer) {
            \$writer->save('php://output');
        }, \$filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function destroy(\$id)
    {
        \$posting = JobPosting::findOrFail(\$id);
        \$posting->delete();

        return redirect()
            ->route('job-postings.index')
            ->with('success', 'Job posting deleted successfully.');
    }",
    'JobPostingController: add exportQualifications()'
);

echo <<<TEXT

✅ All patches applied. No migration needed.

WHAT CHANGED:

  Step 2 — Qualification Checking:
    • Location filter now correctly handles applicants with null location IDs
      (rows without a location show for all filter options, not hidden)
    • "Export qualifications" button appears once ALL applicants are checked
      → Downloads Excel with name, location, Education/Training/Experience/
        Eligibility result, overall result, and notes per applicant

  Step 3 — Open Ranking & Scheduling:
    • Schedule modal now schedules ALL qualified applicants at once
    • Optional location dropdown filters to one place of assignment
    • Panelist checkboxes pre-loaded from the posting's assigned panelists
    • One schedule + invitation email created per applicant on submit
    • New route: POST /interviews/for-posting

  Step 4 — Assessment & Results:
    • Import scores from Excel button (reuses AssessmentController)
    • Download template button
    • View/Print CAR button (modal with public toggle + print)
    • Send all notifications button
    • New routes: GET /assessments/template, POST /assessments/import
    • New route: GET /job-postings/{id}/export-qualifications

DELETE this script after running.

TEXT;
