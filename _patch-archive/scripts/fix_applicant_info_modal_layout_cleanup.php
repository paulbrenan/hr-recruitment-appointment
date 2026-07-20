<?php
/**
 * fix_applicant_info_modal_layout_cleanup.php
 *
 * Three cleanups to the Applicant Info modal, all visual only:
 *   1. Qualifications section now comes BEFORE Personal Details.
 *   2. Section header icons (bi-person-lines-fill, bi-card-list,
 *      bi-mortarboard, bi-briefcase, bi-clipboard-check) removed.
 *   3. Grid widths standardized to a clean 2-per-line layout
 *      (col-md-6) everywhere, instead of the previous mix of
 *      col-md-4/col-md-6/col-12 that produced uneven row heights and
 *      inconsistent whitespace between rows. Long free-text fields
 *      (Address, Notes) stay full-width (col-12) since cramming those
 *      into half-width would just wrap awkwardly.
 *
 * REQUIRES fix_applicant_info_modal_redesign.php already applied.
 *
 * HOW TO RUN:
 *   php fix_applicant_info_modal_layout_cleanup.php   (from project root)
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

echo "\n=== fix_applicant_info_modal_layout_cleanup.php ===\n\n";

$showPath = ROOT . '/resources/views/job-postings/show.blade.php';

echo "[1] Swapping section order (Qualifications before Personal Details), removing icons, standardizing grid...\n";

apply_patch(
    $showPath,
    '                {{-- Personal Details --}}
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
                        </div>',
    '                {{-- Qualifications (self-reported) --}}
                <div class="px-3 pb-2">
                    <div class="bg-white rounded-3 p-3 border">
                        <div class="text-uppercase text-muted fw-semibold mb-2" style="font-size: .7rem; letter-spacing: .04em;">
                            Qualifications
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
                            Personal Details
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
                            Position &amp; Application
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
                            Qualification Check Breakdown
                        </div>',
    'show.blade.php: reorder sections, remove icons, standardize to 2-column grid'
);

echo "\n[2] Removing icon from Contact section header too, for consistency...\n";

apply_patch(
    $showPath,
    '                        <div class="text-uppercase text-muted fw-semibold mb-2" style="font-size: .7rem; letter-spacing: .04em;">
                            <i class="bi bi-person-lines-fill me-1"></i> Contact
                        </div>',
    '                        <div class="text-uppercase text-muted fw-semibold mb-2" style="font-size: .7rem; letter-spacing: .04em;">
                            Contact
                        </div>',
    'show.blade.php: remove icon from Contact section header'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - Section order: Contact -> Qualifications -> Personal Details ->\n";
echo "    Position & Application -> Qualification Check Breakdown.\n";
echo "  - No icons on any section header, just the label text.\n";
echo "  - All fields now sit 2-per-line (col-md-6) consistently, except\n";
echo "    Address and Notes which stay full-width since they're long\n";
echo "    free-text fields.\n\n";
echo "DELETE this script after running.\n";
