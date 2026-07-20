<?php
/**
 * fix_applicant_info_modal.php
 *
 * Supersedes fix_clickable_applicant_names.php's "open in new tab"
 * approach -- that left applicants with no way back to the pipeline
 * once on the applications.show page. Switched to a modal instead: no
 * navigation away at all, same pattern already used elsewhere in this
 * file (qualCheckModal, editScoresModal) -- a shared modal populated
 * from data-* attributes on each trigger.
 *
 * This patch:
 *   1. Reverts the <a> link wrapper in all 3 panels back to a clickable
 *      span/name that opens a new shared #applicantInfoModal instead.
 *   2. Adds the #applicantInfoModal markup once, near the other modals.
 *   3. Adds JS to populate it from the clicked element's data-info
 *      (JSON: name, email, phone, address, age, sex, civil status,
 *      religion, disability, ethnic group, education, training hours,
 *      years experience, eligibility, transaction number, applied date,
 *      status).
 *
 * REQUIRES fix_clickable_applicant_names.php already applied (this
 * patch's old_str targets exactly what that script produced).
 *
 * HOW TO RUN:
 *   php fix_applicant_info_modal.php   (from project root)
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

echo "\n=== fix_applicant_info_modal.php ===\n\n";

$showPath = ROOT . '/resources/views/job-postings/show.blade.php';

// ─── Helper: build the data-info JSON blob for a candidate ──────────────
// (documented here for reference; the actual PHP is inline in each patch)

// ─── 1. Qualification Checking panel (step 2) ───────────────────────────

echo "[1] Qualification Checking: name opens modal instead of new tab...\n";

apply_patch(
    $showPath,
    '                            <div>
                                <a href="{{ route(\'applications.show\', $app->id) }}" target="_blank" rel="noopener"
                                   class="fw-medium text-decoration-none" style="color: inherit; border-bottom: 1px dashed #adb5bd;"
                                   title="View applicant information" onclick="event.stopPropagation()">
                                    {{ $app->candidate->full_name }}
                                </a>
                                <div class="text-muted small">
                                    Applied {{ $app->applied_at ? \Carbon\Carbon::parse($app->applied_at)->format(\'M d, Y\') : \'—\' }}
                                </div>
                            </div>',
    '                            @php
                                $appInfoData = [
                                    \'name\' => $app->candidate->full_name,
                                    \'email\' => $app->candidate->email,
                                    \'phone\' => $app->candidate->phone,
                                    \'address\' => $app->candidate->address,
                                    \'age\' => $app->candidate->age,
                                    \'sex\' => $app->candidate->sex,
                                    \'civil_status\' => $app->candidate->civil_status,
                                    \'religion\' => $app->candidate->religion,
                                    \'disability\' => $app->candidate->disability,
                                    \'ethnic_group\' => $app->candidate->ethnic_group,
                                    \'education\' => $app->candidate->education,
                                    \'training_hours\' => $app->candidate->training_hours,
                                    \'years_experience\' => $app->candidate->years_experience,
                                    \'eligibility\' => $app->candidate->eligibility,
                                    \'transaction_number\' => $app->transaction_number,
                                    \'applied_at\' => $app->applied_at ? \Carbon\Carbon::parse($app->applied_at)->format(\'M d, Y\') : null,
                                    \'status\' => str_replace(\'_\', \' \', ucfirst($app->status)),
                                ];
                            @endphp
                            <div>
                                <span class="fw-medium" role="button"
                                      style="border-bottom: 1px dashed #adb5bd;"
                                      title="View applicant information"
                                      onclick="event.stopPropagation(); showApplicantInfo(this)"
                                      data-info="{{ json_encode($appInfoData) }}">
                                    {{ $app->candidate->full_name }}
                                </span>
                                <div class="text-muted small">
                                    Applied {{ $app->applied_at ? \Carbon\Carbon::parse($app->applied_at)->format(\'M d, Y\') : \'—\' }}
                                </div>
                            </div>',
    'show.blade.php: Qualification Checking name opens applicant info modal'
);

// ─── 2. Open Ranking & Scheduling table (step 3) ────────────────────────

echo "\n[2] Scheduling: name opens modal instead of new tab...\n";

apply_patch(
    $showPath,
    '                                <td class="fw-medium">
                                    <a href="{{ route(\'applications.show\', $first->application_id) }}" target="_blank" rel="noopener"
                                       class="text-decoration-none" style="color: inherit; border-bottom: 1px dashed #adb5bd;"
                                       title="View applicant information">
                                        {{ $first->application->candidate->full_name }}
                                    </a>
                                </td>',
    '                                @php
                                    $schedCand = $first->application->candidate;
                                    $schedInfoData = [
                                        \'name\' => $schedCand->full_name,
                                        \'email\' => $schedCand->email,
                                        \'phone\' => $schedCand->phone,
                                        \'address\' => $schedCand->address,
                                        \'age\' => $schedCand->age,
                                        \'sex\' => $schedCand->sex,
                                        \'civil_status\' => $schedCand->civil_status,
                                        \'religion\' => $schedCand->religion,
                                        \'disability\' => $schedCand->disability,
                                        \'ethnic_group\' => $schedCand->ethnic_group,
                                        \'education\' => $schedCand->education,
                                        \'training_hours\' => $schedCand->training_hours,
                                        \'years_experience\' => $schedCand->years_experience,
                                        \'eligibility\' => $schedCand->eligibility,
                                        \'transaction_number\' => $first->application->transaction_number,
                                        \'applied_at\' => $first->application->applied_at ? \Carbon\Carbon::parse($first->application->applied_at)->format(\'M d, Y\') : null,
                                        \'status\' => str_replace(\'_\', \' \', ucfirst($first->application->status)),
                                    ];
                                @endphp
                                <td class="fw-medium">
                                    <span role="button" style="border-bottom: 1px dashed #adb5bd;"
                                          title="View applicant information"
                                          onclick="showApplicantInfo(this)"
                                          data-info="{{ json_encode($schedInfoData) }}">
                                        {{ $schedCand->full_name }}
                                    </span>
                                </td>',
    'show.blade.php: Scheduling name opens applicant info modal'
);

// ─── 3. Assessment & Results table (step 4) ─────────────────────────────

echo "\n[3] Assessment & Results: name opens modal instead of new tab...\n";

apply_patch(
    $showPath,
    '                                <td class="fw-medium">
                                    <a href="{{ route(\'applications.show\', $cand->application_id) }}" target="_blank" rel="noopener"
                                       class="text-decoration-none" style="color: inherit; border-bottom: 1px dashed #adb5bd;"
                                       title="View applicant information">
                                        {{ $cand->candidate_name }}
                                    </a>
                                </td>',
    '                                @php
                                    $rankCand = $cand->candidate;
                                    $rankInfoData = [
                                        \'name\' => $cand->candidate_name,
                                        \'email\' => $rankCand->email ?? null,
                                        \'phone\' => $rankCand->phone ?? null,
                                        \'address\' => $rankCand->address ?? null,
                                        \'age\' => $rankCand->age ?? null,
                                        \'sex\' => $rankCand->sex ?? null,
                                        \'civil_status\' => $rankCand->civil_status ?? null,
                                        \'religion\' => $rankCand->religion ?? null,
                                        \'disability\' => $rankCand->disability ?? null,
                                        \'ethnic_group\' => $rankCand->ethnic_group ?? null,
                                        \'education\' => $rankCand->education ?? null,
                                        \'training_hours\' => $rankCand->training_hours ?? null,
                                        \'years_experience\' => $rankCand->years_experience ?? null,
                                        \'eligibility\' => $rankCand->eligibility ?? null,
                                        \'transaction_number\' => null,
                                        \'applied_at\' => null,
                                        \'status\' => null,
                                    ];
                                @endphp
                                <td class="fw-medium">
                                    <span role="button" style="border-bottom: 1px dashed #adb5bd;"
                                          title="View applicant information"
                                          onclick="showApplicantInfo(this)"
                                          data-info="{{ json_encode($rankInfoData) }}">
                                        {{ $cand->candidate_name }}
                                    </span>
                                </td>',
    'show.blade.php: Assessment & Results name opens applicant info modal'
);

// ─── 4. Add the shared modal markup (once, before </body> equivalent --
//        right before the qualCheckModal, matching existing modal
//        placement convention in this file) ─────────────────────────────

echo "\n[4] Adding the shared Applicant Info modal markup...\n";

apply_patch(
    $showPath,
    '<div class="modal fade" id="qualCheckModal"',
    '<div class="modal fade" id="applicantInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="applicantInfoName">Applicant Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 small">
                    <div class="col-md-6"><span class="text-muted">Email</span><div id="ai-email" class="fw-medium">—</div></div>
                    <div class="col-md-6"><span class="text-muted">Phone</span><div id="ai-phone" class="fw-medium">—</div></div>
                    <div class="col-12"><span class="text-muted">Address</span><div id="ai-address" class="fw-medium">—</div></div>
                    <div class="col-md-4"><span class="text-muted">Age</span><div id="ai-age" class="fw-medium">—</div></div>
                    <div class="col-md-4"><span class="text-muted">Sex</span><div id="ai-sex" class="fw-medium">—</div></div>
                    <div class="col-md-4"><span class="text-muted">Civil Status</span><div id="ai-civil_status" class="fw-medium">—</div></div>
                    <div class="col-md-6"><span class="text-muted">Religion</span><div id="ai-religion" class="fw-medium">—</div></div>
                    <div class="col-md-6"><span class="text-muted">Disability</span><div id="ai-disability" class="fw-medium">—</div></div>
                    <div class="col-12"><span class="text-muted">Ethnic Group</span><div id="ai-ethnic_group" class="fw-medium">—</div></div>
                    <div class="col-12"><hr class="my-1"></div>
                    <div class="col-12"><span class="text-muted">Highest Education</span><div id="ai-education" class="fw-medium">—</div></div>
                    <div class="col-md-6"><span class="text-muted">Training Hours</span><div id="ai-training_hours" class="fw-medium">—</div></div>
                    <div class="col-md-6"><span class="text-muted">Years of Experience</span><div id="ai-years_experience" class="fw-medium">—</div></div>
                    <div class="col-12"><span class="text-muted">Eligibility</span><div id="ai-eligibility" class="fw-medium">—</div></div>
                    <div class="col-12" id="ai-app-meta-wrap"><hr class="my-1"></div>
                    <div class="col-md-4" id="ai-txn-wrap"><span class="text-muted">Transaction No.</span><div id="ai-transaction_number" class="fw-medium">—</div></div>
                    <div class="col-md-4" id="ai-applied-wrap"><span class="text-muted">Applied</span><div id="ai-applied_at" class="fw-medium">—</div></div>
                    <div class="col-md-4" id="ai-status-wrap"><span class="text-muted">Status</span><div id="ai-status" class="fw-medium">—</div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="qualCheckModal"',
    'show.blade.php: add shared Applicant Info modal markup'
);

// ─── 5. Add the JS to populate the modal ─────────────────────────────────

echo "\n[5] Adding showApplicantInfo() JS...\n";

apply_patch(
    $showPath,
    '    // ── Panelist JS ──────────────────────────────────────────────────────────',
    '    // ── Applicant Info modal ─────────────────────────────────────────────────
    function showApplicantInfo(el) {
        const data = JSON.parse(el.dataset.info || \'{}\');
        const set = (id, val) => {
            const target = document.getElementById(id);
            if (target) target.textContent = (val === null || val === undefined || val === \'\') ? \'—\' : val;
        };

        document.getElementById(\'applicantInfoName\').textContent = data.name || \'Applicant Information\';
        set(\'ai-email\', data.email);
        set(\'ai-phone\', data.phone);
        set(\'ai-address\', data.address);
        set(\'ai-age\', data.age);
        set(\'ai-sex\', data.sex);
        set(\'ai-civil_status\', data.civil_status);
        set(\'ai-religion\', data.religion);
        set(\'ai-disability\', data.disability);
        set(\'ai-ethnic_group\', data.ethnic_group);
        set(\'ai-education\', data.education);
        set(\'ai-training_hours\', data.training_hours);
        set(\'ai-years_experience\', data.years_experience);
        set(\'ai-eligibility\', data.eligibility);

        const hasAppMeta = data.transaction_number || data.applied_at || data.status;
        document.getElementById(\'ai-app-meta-wrap\').style.display = hasAppMeta ? \'\' : \'none\';
        document.getElementById(\'ai-txn-wrap\').style.display = data.transaction_number ? \'\' : \'none\';
        document.getElementById(\'ai-applied-wrap\').style.display = data.applied_at ? \'\' : \'none\';
        document.getElementById(\'ai-status-wrap\').style.display = data.status ? \'\' : \'none\';
        set(\'ai-transaction_number\', data.transaction_number);
        set(\'ai-applied_at\', data.applied_at);
        set(\'ai-status\', data.status);

        new bootstrap.Modal(document.getElementById(\'applicantInfoModal\')).show();
    }

    // ── Panelist JS ──────────────────────────────────────────────────────────',
    'show.blade.php: add showApplicantInfo() JS'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - Applicant names in all 3 pipeline panels now open a shared\n";
echo "    modal with full applicant info instead of navigating to a new\n";
echo "    tab -- the pipeline never loses its place.\n";
echo "  - Assessment & Results modal omits transaction number/applied\n";
echo "    date/status rows (that data wasn't available on \$cand there);\n";
echo "    the other two panels show everything.\n\n";
echo "DELETE this script after running.\n";
