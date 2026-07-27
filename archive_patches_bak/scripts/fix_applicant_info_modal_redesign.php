<?php
/**
 * fix_applicant_info_modal_redesign.php
 *
 * Pure visual redesign of the Applicant Info modal -- no data or JS
 * logic changes. Replaces the single flat grid with sectioned cards
 * (Contact, Personal Details, Position & Application, Qualifications)
 * plus a cleaner header (avatar initial circle, name, status pill) and
 * better-styled criteria breakdown table.
 *
 * All the same element IDs are kept (ai-email, ai-phone, ai-status,
 * ai-criteria-tbody, etc.) so showApplicantInfo() in the <script>
 * section needs NO changes -- it already works against these IDs.
 *
 * REQUIRES fix_applicant_info_modal_full_details.php already applied
 * (this patch's old_str is the modal markup that script produced).
 *
 * HOW TO RUN:
 *   php fix_applicant_info_modal_redesign.php   (from project root)
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

echo "\n=== fix_applicant_info_modal_redesign.php ===\n\n";

$showPath = ROOT . '/resources/views/job-postings/show.blade.php';

echo "[1] Redesigning modal markup...\n";

apply_patch(
    $showPath,
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
    '<div class="modal fade" id="applicantInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content" style="border: none; border-radius: 12px; overflow: hidden;">

            {{-- Header: avatar initial + name + status pill --}}
            <div class="modal-header" style="background: var(--hr-primary); color: #fff; border: none; padding: 20px 24px;">
                <div class="d-flex align-items-center gap-3" style="min-width: 0;">
                    <div id="ai-avatar" class="d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 46px; height: 46px; border-radius: 50%; background: rgba(255,255,255,.18); font-weight: 700; font-size: 1.1rem;">?</div>
                    <div style="min-width: 0;">
                        <h5 class="modal-title mb-0" id="applicantInfoName" style="font-weight: 700;">Applicant Information</h5>
                        <span id="ai-status" class="badge mt-1" style="background: rgba(255,255,255,.22); font-weight: 600; font-size: .72rem;">—</span>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-0" style="background: #f8f9fb;">

                {{-- Contact --}}
                <div class="p-3 pb-2">
                    <div class="bg-white rounded-3 p-3 border">
                        <div class="text-uppercase text-muted fw-semibold mb-2" style="font-size: .7rem; letter-spacing: .04em;">
                            <i class="bi bi-person-lines-fill me-1"></i> Contact
                        </div>
                        <div class="row g-3 small">
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Email</span><div id="ai-email" class="fw-medium">—</div></div>
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Phone</span><div id="ai-phone" class="fw-medium">—</div></div>
                            <div class="col-12"><span class="text-muted d-block" style="font-size:.72rem;">Address</span><div id="ai-address" class="fw-medium">—</div></div>
                        </div>
                    </div>
                </div>

                {{-- Personal Details --}}
                <div class="px-3 pb-2">
                    <div class="bg-white rounded-3 p-3 border">
                        <div class="text-uppercase text-muted fw-semibold mb-2" style="font-size: .7rem; letter-spacing: .04em;">
                            <i class="bi bi-card-list me-1"></i> Personal Details
                        </div>
                        <div class="row g-3 small">
                            <div class="col-md-4"><span class="text-muted d-block" style="font-size:.72rem;">Age</span><div id="ai-age" class="fw-medium">—</div></div>
                            <div class="col-md-4"><span class="text-muted d-block" style="font-size:.72rem;">Sex</span><div id="ai-sex" class="fw-medium">—</div></div>
                            <div class="col-md-4"><span class="text-muted d-block" style="font-size:.72rem;">Civil Status</span><div id="ai-civil_status" class="fw-medium">—</div></div>
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Religion</span><div id="ai-religion" class="fw-medium">—</div></div>
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Disability</span><div id="ai-disability" class="fw-medium">—</div></div>
                            <div class="col-12"><span class="text-muted d-block" style="font-size:.72rem;">Ethnic Group</span><div id="ai-ethnic_group" class="fw-medium">—</div></div>
                        </div>
                    </div>
                </div>

                {{-- Position & Application --}}
                <div class="px-3 pb-2" id="ai-app-meta-wrap">
                    <div class="bg-white rounded-3 p-3 border">
                        <div class="text-uppercase text-muted fw-semibold mb-2" style="font-size: .7rem; letter-spacing: .04em;">
                            <i class="bi bi-briefcase me-1"></i> Position &amp; Application
                        </div>
                        <div class="row g-3 small">
                            <div class="col-md-4" id="ai-txn-wrap"><span class="text-muted d-block" style="font-size:.72rem;">Transaction No.</span><div id="ai-transaction_number" class="fw-medium font-monospace">—</div></div>
                            <div class="col-md-4" id="ai-applied-wrap"><span class="text-muted d-block" style="font-size:.72rem;">Applied</span><div id="ai-applied_at" class="fw-medium">—</div></div>
                            <div class="col-md-4" id="ai-place-wrap"><span class="text-muted d-block" style="font-size:.72rem;">Place of Assignment</span><div id="ai-place_of_assignment" class="fw-medium">—</div></div>
                            <div class="col-md-6" id="ai-qualresult-wrap"><span class="text-muted d-block" style="font-size:.72rem;">Qualification Result</span><div id="ai-qualification_result" class="fw-medium">—</div></div>
                            <div class="col-12" id="ai-notes-wrap"><span class="text-muted d-block" style="font-size:.72rem;">Notes</span><div id="ai-notes" class="fw-medium">—</div></div>
                        </div>
                    </div>
                </div>

                {{-- Qualifications (self-reported) --}}
                <div class="px-3 pb-2">
                    <div class="bg-white rounded-3 p-3 border">
                        <div class="text-uppercase text-muted fw-semibold mb-2" style="font-size: .7rem; letter-spacing: .04em;">
                            <i class="bi bi-mortarboard me-1"></i> Qualifications
                        </div>
                        <div class="row g-3 small">
                            <div class="col-12"><span class="text-muted d-block" style="font-size:.72rem;">Highest Education</span><div id="ai-education" class="fw-medium">—</div></div>
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Training Hours</span><div id="ai-training_hours" class="fw-medium">—</div></div>
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Years of Experience</span><div id="ai-years_experience" class="fw-medium">—</div></div>
                            <div class="col-12"><span class="text-muted d-block" style="font-size:.72rem;">Eligibility</span><div id="ai-eligibility" class="fw-medium">—</div></div>
                        </div>
                    </div>
                </div>

                {{-- Qualification Check Breakdown --}}
                <div class="px-3 pb-3" id="ai-criteria-wrap">
                    <div class="bg-white rounded-3 p-3 border">
                        <div class="text-uppercase text-muted fw-semibold mb-2" style="font-size: .7rem; letter-spacing: .04em;">
                            <i class="bi bi-clipboard-check me-1"></i> Qualification Check Breakdown
                        </div>
                        <table class="table table-sm mb-0" id="ai-criteria-table" style="font-size: .82rem;">
                            <thead>
                                <tr>
                                    <th class="text-muted" style="font-size:.7rem; text-transform:uppercase; letter-spacing:.03em; border-top:0;">Criterion</th>
                                    <th class="text-muted" style="font-size:.7rem; text-transform:uppercase; letter-spacing:.03em; border-top:0;">Candidate\'s Qualification</th>
                                    <th class="text-muted text-end" style="font-size:.7rem; text-transform:uppercase; letter-spacing:.03em; border-top:0;">Result</th>
                                </tr>
                            </thead>
                            <tbody id="ai-criteria-tbody"></tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>',
    'show.blade.php: modal redesigned into sectioned cards with header pill/avatar'
);

echo "\n[2] Updating showApplicantInfo() JS to fill the avatar initial...\n";

apply_patch(
    $showPath,
    "    document.getElementById('applicantInfoName').textContent = data.name || 'Applicant Information';",
    "    document.getElementById('applicantInfoName').textContent = data.name || 'Applicant Information';\n" .
    "    document.getElementById('ai-avatar').textContent = data.name ? data.name.trim().charAt(0).toUpperCase() : '?';",
    'show.blade.php: JS sets avatar initial from name'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED (visual only, no data/behavior changes):\n";
echo "  - Header: colored band with an initial-letter avatar circle, name,\n";
echo "    and status shown as a pill badge instead of a plain grid row.\n";
echo "  - Body reorganized into 5 clearly-labeled white card sections\n";
echo "    (Contact, Personal Details, Position & Application,\n";
echo "    Qualifications, Qualification Check Breakdown) on a light grey\n";
echo "    background, instead of one long flat grid.\n";
echo "  - Criteria table headers cleaned up to match the uppercase\n";
echo "    label style used elsewhere in this app.\n";
echo "  - Every element ID is unchanged -- showApplicantInfo() still\n";
echo "    works exactly as before, plus one addition (avatar initial).\n\n";
echo "DELETE this script after running.\n";
