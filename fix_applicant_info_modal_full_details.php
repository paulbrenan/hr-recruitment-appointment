<?php
/**
 * fix_applicant_info_modal_full_details.php
 *
 * Follow-up to fix_applicant_info_modal.php + fix_applicant_info_modal_js_only.php
 * (both already applied successfully). Comparing the modal against the
 * standalone applications/show.blade.php page revealed it was missing:
 *   - Place of Assignment (applicant-specific on multi-location postings)
 *   - Application notes
 *   - The actual Qualification Check breakdown (per-criterion actual
 *     value + pass/fail, not just the overall qualified/not_qualified
 *     word already shown as a status badge)
 *
 * Position title / Salary Grade / Employment type were deliberately left
 * OUT -- every applicant on this page already belongs to the same
 * posting, so that's redundant with the page you're already on.
 *
 * REQUIRES fix_applicant_info_modal.php AND fix_applicant_info_modal_js_only.php
 * already applied.
 *
 * HOW TO RUN:
 *   php fix_applicant_info_modal_full_details.php   (from project root)
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
    if ($count === 0) {
        echo "\n❌ PATCH ABORTED — content not found in $path\nLabel: $label\n";
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

echo "\n=== fix_applicant_info_modal_full_details.php ===\n\n";

$showPath = ROOT . '/resources/views/job-postings/show.blade.php';

// ─── 1. Qualification Checking panel: expand data payload ──────────────

echo "[1] Expanding data payload in Qualification Checking...\n";

apply_patch(
    $showPath,
    "                            @php
                                \$appInfoData = [
                                    'name' => \$app->candidate->full_name,
                                    'email' => \$app->candidate->email,
                                    'phone' => \$app->candidate->phone,
                                    'address' => \$app->candidate->address,
                                    'age' => \$app->candidate->age,
                                    'sex' => \$app->candidate->sex,
                                    'civil_status' => \$app->candidate->civil_status,
                                    'religion' => \$app->candidate->religion,
                                    'disability' => \$app->candidate->disability,
                                    'ethnic_group' => \$app->candidate->ethnic_group,
                                    'education' => \$app->candidate->education,
                                    'training_hours' => \$app->candidate->training_hours,
                                    'years_experience' => \$app->candidate->years_experience,
                                    'eligibility' => \$app->candidate->eligibility,
                                    'transaction_number' => \$app->transaction_number,
                                    'applied_at' => \$app->applied_at ? \Carbon\Carbon::parse(\$app->applied_at)->format('M d, Y') : null,
                                    'status' => str_replace('_', ' ', ucfirst(\$app->status)),
                                ];
                            @endphp",
    "                            @php
                                \$appPlace = optional(\$app->jobPostingLocation)->place_of_assignment
                                    ?? \$posting->place_of_assignment
                                    ?? null;
                                \$appCheckData = \$app->qualification_check ?? [];
                                \$appCriteria = [];
                                foreach (['education' => 'Education', 'experience' => 'Experience', 'training' => 'Training', 'eligibility' => 'Eligibility'] as \$ck => \$cl) {
                                    if (isset(\$appCheckData['criteria'][\$ck])) {
                                        \$appCriteria[] = [
                                            'label' => \$cl,
                                            'actual' => \$appCheckData['criteria'][\$ck]['actual'] ?? null,
                                            'passed' => (bool) (\$appCheckData['criteria'][\$ck]['passed'] ?? false),
                                        ];
                                    }
                                }
                                \$appInfoData = [
                                    'name' => \$app->candidate->full_name,
                                    'email' => \$app->candidate->email,
                                    'phone' => \$app->candidate->phone,
                                    'address' => \$app->candidate->address,
                                    'age' => \$app->candidate->age,
                                    'sex' => \$app->candidate->sex,
                                    'civil_status' => \$app->candidate->civil_status,
                                    'religion' => \$app->candidate->religion,
                                    'disability' => \$app->candidate->disability,
                                    'ethnic_group' => \$app->candidate->ethnic_group,
                                    'education' => \$app->candidate->education,
                                    'training_hours' => \$app->candidate->training_hours,
                                    'years_experience' => \$app->candidate->years_experience,
                                    'eligibility' => \$app->candidate->eligibility,
                                    'transaction_number' => \$app->transaction_number,
                                    'applied_at' => \$app->applied_at ? \Carbon\Carbon::parse(\$app->applied_at)->format('M d, Y') : null,
                                    'status' => str_replace('_', ' ', ucfirst(\$app->status)),
                                    'place_of_assignment' => \$appPlace,
                                    'notes' => \$app->notes,
                                    'qualification_result' => \$app->qualification_result ? ucfirst(str_replace('_', ' ', \$app->qualification_result)) : null,
                                    'criteria' => \$appCriteria,
                                ];
                            @endphp",
    'show.blade.php: Qualification Checking data payload includes place/notes/criteria'
);

// ─── 2. Scheduling panel: expand data payload ───────────────────────────

echo "\n[2] Expanding data payload in Scheduling...\n";

apply_patch(
    $showPath,
    "                                @php
                                    \$schedCand = \$first->application->candidate;
                                    \$schedInfoData = [
                                        'name' => \$schedCand->full_name,
                                        'email' => \$schedCand->email,
                                        'phone' => \$schedCand->phone,
                                        'address' => \$schedCand->address,
                                        'age' => \$schedCand->age,
                                        'sex' => \$schedCand->sex,
                                        'civil_status' => \$schedCand->civil_status,
                                        'religion' => \$schedCand->religion,
                                        'disability' => \$schedCand->disability,
                                        'ethnic_group' => \$schedCand->ethnic_group,
                                        'education' => \$schedCand->education,
                                        'training_hours' => \$schedCand->training_hours,
                                        'years_experience' => \$schedCand->years_experience,
                                        'eligibility' => \$schedCand->eligibility,
                                        'transaction_number' => \$first->application->transaction_number,
                                        'applied_at' => \$first->application->applied_at ? \Carbon\Carbon::parse(\$first->application->applied_at)->format('M d, Y') : null,
                                        'status' => str_replace('_', ' ', ucfirst(\$first->application->status)),
                                    ];
                                @endphp",
    "                                @php
                                    \$schedCand = \$first->application->candidate;
                                    \$schedPlace = optional(\$first->application->jobPostingLocation)->place_of_assignment
                                        ?? \$posting->place_of_assignment
                                        ?? null;
                                    \$schedCheckData = \$first->application->qualification_check ?? [];
                                    \$schedCriteria = [];
                                    foreach (['education' => 'Education', 'experience' => 'Experience', 'training' => 'Training', 'eligibility' => 'Eligibility'] as \$ck => \$cl) {
                                        if (isset(\$schedCheckData['criteria'][\$ck])) {
                                            \$schedCriteria[] = [
                                                'label' => \$cl,
                                                'actual' => \$schedCheckData['criteria'][\$ck]['actual'] ?? null,
                                                'passed' => (bool) (\$schedCheckData['criteria'][\$ck]['passed'] ?? false),
                                            ];
                                        }
                                    }
                                    \$schedInfoData = [
                                        'name' => \$schedCand->full_name,
                                        'email' => \$schedCand->email,
                                        'phone' => \$schedCand->phone,
                                        'address' => \$schedCand->address,
                                        'age' => \$schedCand->age,
                                        'sex' => \$schedCand->sex,
                                        'civil_status' => \$schedCand->civil_status,
                                        'religion' => \$schedCand->religion,
                                        'disability' => \$schedCand->disability,
                                        'ethnic_group' => \$schedCand->ethnic_group,
                                        'education' => \$schedCand->education,
                                        'training_hours' => \$schedCand->training_hours,
                                        'years_experience' => \$schedCand->years_experience,
                                        'eligibility' => \$schedCand->eligibility,
                                        'transaction_number' => \$first->application->transaction_number,
                                        'applied_at' => \$first->application->applied_at ? \Carbon\Carbon::parse(\$first->application->applied_at)->format('M d, Y') : null,
                                        'status' => str_replace('_', ' ', ucfirst(\$first->application->status)),
                                        'place_of_assignment' => \$schedPlace,
                                        'notes' => \$first->application->notes,
                                        'qualification_result' => \$first->application->qualification_result ? ucfirst(str_replace('_', ' ', \$first->application->qualification_result)) : null,
                                        'criteria' => \$schedCriteria,
                                    ];
                                @endphp",
    'show.blade.php: Scheduling data payload includes place/notes/criteria'
);

// ─── 3. Assessment & Results panel: expand data payload ─────────────────

echo "\n[3] Expanding data payload in Assessment & Results...\n";

apply_patch(
    $showPath,
    "                                @php
                                    \$rankCand = \$cand->candidate;
                                    \$rankInfoData = [
                                        'name' => \$cand->candidate_name,
                                        'email' => \$rankCand->email ?? null,
                                        'phone' => \$rankCand->phone ?? null,
                                        'address' => \$rankCand->address ?? null,
                                        'age' => \$rankCand->age ?? null,
                                        'sex' => \$rankCand->sex ?? null,
                                        'civil_status' => \$rankCand->civil_status ?? null,
                                        'religion' => \$rankCand->religion ?? null,
                                        'disability' => \$rankCand->disability ?? null,
                                        'ethnic_group' => \$rankCand->ethnic_group ?? null,
                                        'education' => \$rankCand->education ?? null,
                                        'training_hours' => \$rankCand->training_hours ?? null,
                                        'years_experience' => \$rankCand->years_experience ?? null,
                                        'eligibility' => \$rankCand->eligibility ?? null,
                                        'transaction_number' => null,
                                        'applied_at' => null,
                                        'status' => null,
                                    ];
                                @endphp",
    "                                @php
                                    \$rankCand = \$cand->candidate;
                                    \$rankApp = \$applications->firstWhere('id', \$cand->application_id);
                                    \$rankPlace = \$rankApp ? (optional(\$rankApp->jobPostingLocation)->place_of_assignment ?? \$posting->place_of_assignment ?? null) : null;
                                    \$rankCheckData = \$rankApp->qualification_check ?? [];
                                    \$rankCriteria = [];
                                    foreach (['education' => 'Education', 'experience' => 'Experience', 'training' => 'Training', 'eligibility' => 'Eligibility'] as \$ck => \$cl) {
                                        if (isset(\$rankCheckData['criteria'][\$ck])) {
                                            \$rankCriteria[] = [
                                                'label' => \$cl,
                                                'actual' => \$rankCheckData['criteria'][\$ck]['actual'] ?? null,
                                                'passed' => (bool) (\$rankCheckData['criteria'][\$ck]['passed'] ?? false),
                                            ];
                                        }
                                    }
                                    \$rankInfoData = [
                                        'name' => \$cand->candidate_name,
                                        'email' => \$rankCand->email ?? null,
                                        'phone' => \$rankCand->phone ?? null,
                                        'address' => \$rankCand->address ?? null,
                                        'age' => \$rankCand->age ?? null,
                                        'sex' => \$rankCand->sex ?? null,
                                        'civil_status' => \$rankCand->civil_status ?? null,
                                        'religion' => \$rankCand->religion ?? null,
                                        'disability' => \$rankCand->disability ?? null,
                                        'ethnic_group' => \$rankCand->ethnic_group ?? null,
                                        'education' => \$rankCand->education ?? null,
                                        'training_hours' => \$rankCand->training_hours ?? null,
                                        'years_experience' => \$rankCand->years_experience ?? null,
                                        'eligibility' => \$rankCand->eligibility ?? null,
                                        'transaction_number' => \$rankApp->transaction_number ?? null,
                                        'applied_at' => \$rankApp && \$rankApp->applied_at ? \Carbon\Carbon::parse(\$rankApp->applied_at)->format('M d, Y') : null,
                                        'status' => \$rankApp ? str_replace('_', ' ', ucfirst(\$rankApp->status)) : null,
                                        'place_of_assignment' => \$rankPlace,
                                        'notes' => \$rankApp->notes ?? null,
                                        'qualification_result' => (\$rankApp && \$rankApp->qualification_result) ? ucfirst(str_replace('_', ' ', \$rankApp->qualification_result)) : null,
                                        'criteria' => \$rankCriteria,
                                    ];
                                @endphp",
    'show.blade.php: Assessment & Results data payload includes place/notes/criteria (looked up via $applications)'
);

// ─── 4. Modal markup: add Place, Notes, Qualification Check section ─────

echo "\n[4] Adding Place of Assignment, Notes, and Qualification Check rows to modal...\n";

apply_patch(
    $showPath,
    '                    <div class="col-12" id="ai-app-meta-wrap"><hr class="my-1"></div>
                    <div class="col-md-4" id="ai-txn-wrap"><span class="text-muted">Transaction No.</span><div id="ai-transaction_number" class="fw-medium">—</div></div>
                    <div class="col-md-4" id="ai-applied-wrap"><span class="text-muted">Applied</span><div id="ai-applied_at" class="fw-medium">—</div></div>
                    <div class="col-md-4" id="ai-status-wrap"><span class="text-muted">Status</span><div id="ai-status" class="fw-medium">—</div></div>
                </div>
            </div>
        </div>
    </div>
</div>',
    '                    <div class="col-12" id="ai-app-meta-wrap"><hr class="my-1"></div>
                    <div class="col-md-4" id="ai-txn-wrap"><span class="text-muted">Transaction No.</span><div id="ai-transaction_number" class="fw-medium">—</div></div>
                    <div class="col-md-4" id="ai-applied-wrap"><span class="text-muted">Applied</span><div id="ai-applied_at" class="fw-medium">—</div></div>
                    <div class="col-md-4" id="ai-status-wrap"><span class="text-muted">Status</span><div id="ai-status" class="fw-medium">—</div></div>
                    <div class="col-md-6" id="ai-place-wrap"><span class="text-muted">Place of Assignment</span><div id="ai-place_of_assignment" class="fw-medium">—</div></div>
                    <div class="col-md-6" id="ai-qualresult-wrap"><span class="text-muted">Qualification Result</span><div id="ai-qualification_result" class="fw-medium">—</div></div>
                    <div class="col-12" id="ai-notes-wrap"><span class="text-muted">Notes</span><div id="ai-notes" class="fw-medium">—</div></div>
                    <div class="col-12" id="ai-criteria-wrap">
                        <hr class="my-1">
                        <span class="text-muted d-block mb-2">Qualification Check Breakdown</span>
                        <table class="table table-sm mb-0" id="ai-criteria-table">
                            <thead>
                                <tr><th>Criterion</th><th>Candidate\'s Qualification</th><th class="text-end">Result</th></tr>
                            </thead>
                            <tbody id="ai-criteria-tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>',
    'show.blade.php: modal adds place/notes/qualification result/criteria table rows'
);

// ─── 5. JS: populate the new fields ──────────────────────────────────────

echo "\n[5] Updating showApplicantInfo() JS to populate the new fields...\n";

apply_patch(
    $showPath,
    "    set('ai-transaction_number', data.transaction_number);
    set('ai-applied_at', data.applied_at);
    set('ai-status', data.status);

    new bootstrap.Modal(document.getElementById('applicantInfoModal')).show();",
    "    set('ai-transaction_number', data.transaction_number);
    set('ai-applied_at', data.applied_at);
    set('ai-status', data.status);

    document.getElementById('ai-place-wrap').style.display = data.place_of_assignment ? '' : 'none';
    set('ai-place_of_assignment', data.place_of_assignment);

    document.getElementById('ai-qualresult-wrap').style.display = data.qualification_result ? '' : 'none';
    set('ai-qualification_result', data.qualification_result);

    document.getElementById('ai-notes-wrap').style.display = data.notes ? '' : 'none';
    set('ai-notes', data.notes);

    const criteria = data.criteria || [];
    const tbody = document.getElementById('ai-criteria-tbody');
    document.getElementById('ai-criteria-wrap').style.display = criteria.length ? '' : 'none';
    tbody.innerHTML = '';
    criteria.forEach(row => {
        const tr = document.createElement('tr');
        const badgeClass = row.passed ? 'text-bg-success' : 'text-bg-danger';
        const badgeText  = row.passed ? 'Qualified' : 'Not qualified';
        tr.innerHTML = '<td>' + row.label + '</td>'
            + '<td>' + (row.actual || '—') + '</td>'
            + '<td class=\"text-end\"><span class=\"badge ' + badgeClass + '\">' + badgeText + '</span></td>';
        tbody.appendChild(tr);
    });

    new bootstrap.Modal(document.getElementById('applicantInfoModal')).show();",
    'show.blade.php: JS populates place/notes/qualification result/criteria table'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - Modal now also shows Place of Assignment, Notes, overall\n";
echo "    Qualification Result, and a full per-criterion breakdown table\n";
echo "    (candidate's actual qualification text + pass/fail per\n";
echo "    criterion) -- matching what's on the standalone\n";
echo "    applications/show.blade.php page.\n";
echo "  - Each new row/section only shows if there's actually data for\n";
echo "    it (e.g. no criteria table at all if qualification checking\n";
echo "    hasn't happened yet for that applicant).\n";
echo "  - Position title / SG / Employment type deliberately NOT added --\n";
echo "    every applicant here already belongs to the one posting you're\n";
echo "    already viewing.\n\n";
echo "DELETE this script after running.\n";
