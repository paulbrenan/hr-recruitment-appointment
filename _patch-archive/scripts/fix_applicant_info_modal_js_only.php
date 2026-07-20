<?php
/**
 * fix_applicant_info_modal_js_only.php
 *
 * Follow-up to fix_applicant_info_modal.php -- steps 1-4 (clickable
 * names + modal markup) already succeeded. Only step 5 (the JS) failed
 * to match because this file doesn't have a "Panelist JS" comment
 * anymore. Re-targeted to insert right after the main <script> tag
 * opens instead.
 *
 * HOW TO RUN:
 *   php fix_applicant_info_modal_js_only.php   (from project root)
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

echo "\n=== fix_applicant_info_modal_js_only.php ===\n\n";

$showPath = ROOT . '/resources/views/job-postings/show.blade.php';

echo "[1] Adding showApplicantInfo() JS after the main <script> tag opens...\n";

apply_patch(
    $showPath,
    "<script>
// ── Step switching ──────────────────────────────────────────────────────────
const currentStep = {{ \$currentStep }};",
    "<script>
// ── Applicant Info modal ─────────────────────────────────────────────────
function showApplicantInfo(el) {
    const data = JSON.parse(el.dataset.info || '{}');
    const set = (id, val) => {
        const target = document.getElementById(id);
        if (target) target.textContent = (val === null || val === undefined || val === '') ? '—' : val;
    };

    document.getElementById('applicantInfoName').textContent = data.name || 'Applicant Information';
    set('ai-email', data.email);
    set('ai-phone', data.phone);
    set('ai-address', data.address);
    set('ai-age', data.age);
    set('ai-sex', data.sex);
    set('ai-civil_status', data.civil_status);
    set('ai-religion', data.religion);
    set('ai-disability', data.disability);
    set('ai-ethnic_group', data.ethnic_group);
    set('ai-education', data.education);
    set('ai-training_hours', data.training_hours);
    set('ai-years_experience', data.years_experience);
    set('ai-eligibility', data.eligibility);

    const hasAppMeta = data.transaction_number || data.applied_at || data.status;
    document.getElementById('ai-app-meta-wrap').style.display = hasAppMeta ? '' : 'none';
    document.getElementById('ai-txn-wrap').style.display = data.transaction_number ? '' : 'none';
    document.getElementById('ai-applied-wrap').style.display = data.applied_at ? '' : 'none';
    document.getElementById('ai-status-wrap').style.display = data.status ? '' : 'none';
    set('ai-transaction_number', data.transaction_number);
    set('ai-applied_at', data.applied_at);
    set('ai-status', data.status);

    new bootstrap.Modal(document.getElementById('applicantInfoModal')).show();
}

// ── Step switching ──────────────────────────────────────────────────────────
const currentStep = {{ \$currentStep }};",
    'show.blade.php: add showApplicantInfo() JS after main script tag opens'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - showApplicantInfo() is now defined and wired up -- clicking any\n";
echo "    applicant name in the 3 pipeline panels should now correctly\n";
echo "    populate and open the Applicant Info modal.\n\n";
echo "DELETE this script after running.\n";
