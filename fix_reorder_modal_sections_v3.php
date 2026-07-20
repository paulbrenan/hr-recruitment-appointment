<?php
/**
 * fix_reorder_modal_sections_v3.php
 *
 * Reorders the Applicant Info modal's 5 section cards to:
 *   1. Qualification Check Breakdown
 *   2. Position & Application
 *   3. Qualifications
 *   4. Contact
 *   5. Personal Details
 *
 * Padding classes adjusted so the top of the modal body (now
 * Qualification Check Breakdown) uses p-3 pb-2, and the bottom (now
 * Personal Details) uses px-3 pb-3 -- matching the original top/bottom
 * spacing convention, just applied to the new first/last sections.
 *
 * REQUIRES fix_restore_modal_section_icons.php already applied.
 *
 * HOW TO RUN:
 *   php fix_reorder_modal_sections_v3.php   (from project root)
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

echo "\n=== fix_reorder_modal_sections_v3.php ===\n\n";

$showPath = ROOT . '/resources/views/job-postings/show.blade.php';

echo "[1] Reordering all 5 modal sections (full block, including trailing table + modal-close tags)...\n";

$old = <<<'EOT'
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

                {{-- Qualifications (self-reported) --}}
                <div class="px-3 pb-2">
                    <div class="bg-white rounded-3 p-3 border">
                        <div class="text-uppercase text-muted fw-semibold mb-2" style="font-size: .7rem; letter-spacing: .04em;">
                            <i class="bi bi-mortarboard me-1"></i> Qualifications
                        </div>
                        <div class="row g-3 small">
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Highest Education</span><div id="ai-education" class="fw-medium">—</div></div>
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Eligibility</span><div id="ai-eligibility" class="fw-medium">—</div></div>
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Training Hours</span><div id="ai-training_hours" class="fw-medium">—</div></div>
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Years of Experience</span><div id="ai-years_experience" class="fw-medium">—</div></div>
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
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Age</span><div id="ai-age" class="fw-medium">—</div></div>
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Sex</span><div id="ai-sex" class="fw-medium">—</div></div>
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Civil Status</span><div id="ai-civil_status" class="fw-medium">—</div></div>
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Religion</span><div id="ai-religion" class="fw-medium">—</div></div>
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Disability</span><div id="ai-disability" class="fw-medium">—</div></div>
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Ethnic Group</span><div id="ai-ethnic_group" class="fw-medium">—</div></div>
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
                            <div class="col-md-6" id="ai-txn-wrap"><span class="text-muted d-block" style="font-size:.72rem;">Transaction No.</span><div id="ai-transaction_number" class="fw-medium font-monospace">—</div></div>
                            <div class="col-md-6" id="ai-applied-wrap"><span class="text-muted d-block" style="font-size:.72rem;">Applied</span><div id="ai-applied_at" class="fw-medium">—</div></div>
                            <div class="col-md-6" id="ai-place-wrap"><span class="text-muted d-block" style="font-size:.72rem;">Place of Assignment</span><div id="ai-place_of_assignment" class="fw-medium">—</div></div>
                            <div class="col-md-6" id="ai-qualresult-wrap"><span class="text-muted d-block" style="font-size:.72rem;">Qualification Result</span><div id="ai-qualification_result" class="fw-medium">—</div></div>
                            <div class="col-12" id="ai-notes-wrap"><span class="text-muted d-block" style="font-size:.72rem;">Notes</span><div id="ai-notes" class="fw-medium">—</div></div>
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
                                    <th class="text-muted" style="font-size:.7rem; text-transform:uppercase; letter-spacing:.03em; border-top:0;">Candidate's Qualification</th>
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
</div>
EOT;

$new = <<<'EOT'
                {{-- Qualification Check Breakdown --}}
                <div class="p-3 pb-2" id="ai-criteria-wrap">
                    <div class="bg-white rounded-3 p-3 border">
                        <div class="text-uppercase text-muted fw-semibold mb-2" style="font-size: .7rem; letter-spacing: .04em;">
                            <i class="bi bi-clipboard-check me-1"></i> Qualification Check Breakdown
                        </div>
                        <table class="table table-sm mb-0" id="ai-criteria-table" style="font-size: .82rem;">
                            <thead>
                                <tr>
                                    <th class="text-muted" style="font-size:.7rem; text-transform:uppercase; letter-spacing:.03em; border-top:0;">Criterion</th>
                                    <th class="text-muted" style="font-size:.7rem; text-transform:uppercase; letter-spacing:.03em; border-top:0;">Candidate's Qualification</th>
                                    <th class="text-muted text-end" style="font-size:.7rem; text-transform:uppercase; letter-spacing:.03em; border-top:0;">Result</th>
                                </tr>
                            </thead>
                            <tbody id="ai-criteria-tbody"></tbody>
                        </table>
                    </div>
                </div>

                {{-- Position & Application --}}
                <div class="px-3 pb-2" id="ai-app-meta-wrap">
                    <div class="bg-white rounded-3 p-3 border">
                        <div class="text-uppercase text-muted fw-semibold mb-2" style="font-size: .7rem; letter-spacing: .04em;">
                            <i class="bi bi-briefcase me-1"></i> Position &amp; Application
                        </div>
                        <div class="row g-3 small">
                            <div class="col-md-6" id="ai-txn-wrap"><span class="text-muted d-block" style="font-size:.72rem;">Transaction No.</span><div id="ai-transaction_number" class="fw-medium font-monospace">—</div></div>
                            <div class="col-md-6" id="ai-applied-wrap"><span class="text-muted d-block" style="font-size:.72rem;">Applied</span><div id="ai-applied_at" class="fw-medium">—</div></div>
                            <div class="col-md-6" id="ai-place-wrap"><span class="text-muted d-block" style="font-size:.72rem;">Place of Assignment</span><div id="ai-place_of_assignment" class="fw-medium">—</div></div>
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
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Highest Education</span><div id="ai-education" class="fw-medium">—</div></div>
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Eligibility</span><div id="ai-eligibility" class="fw-medium">—</div></div>
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Training Hours</span><div id="ai-training_hours" class="fw-medium">—</div></div>
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Years of Experience</span><div id="ai-years_experience" class="fw-medium">—</div></div>
                        </div>
                    </div>
                </div>

                {{-- Contact --}}
                <div class="px-3 pb-2">
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
                <div class="px-3 pb-3">
                    <div class="bg-white rounded-3 p-3 border">
                        <div class="text-uppercase text-muted fw-semibold mb-2" style="font-size: .7rem; letter-spacing: .04em;">
                            <i class="bi bi-card-list me-1"></i> Personal Details
                        </div>
                        <div class="row g-3 small">
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Age</span><div id="ai-age" class="fw-medium">—</div></div>
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Sex</span><div id="ai-sex" class="fw-medium">—</div></div>
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Civil Status</span><div id="ai-civil_status" class="fw-medium">—</div></div>
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Religion</span><div id="ai-religion" class="fw-medium">—</div></div>
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Disability</span><div id="ai-disability" class="fw-medium">—</div></div>
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Ethnic Group</span><div id="ai-ethnic_group" class="fw-medium">—</div></div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
EOT;

apply_patch($showPath, $old, $new, 'show.blade.php: full clean reorder of 5 modal sections');

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - Section order is now: Qualification Check Breakdown, Position\n";
echo "    & Application, Qualifications, Contact, Personal Details.\n";
echo "  - Top/bottom padding adjusted for the new first/last sections.\n\n";
echo "DELETE this script after running.\n";
