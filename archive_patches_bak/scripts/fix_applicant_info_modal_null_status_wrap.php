<?php
/**
 * fix_applicant_info_modal_null_status_wrap.php
 *
 * fix_applicant_info_modal_redesign.php moved the status field into the
 * modal header as a plain <span id="ai-status"> badge (always visible,
 * no wrapper needed) -- but the JS from fix_applicant_info_modal_js_only.php
 * still tries to toggle visibility on a #ai-status-wrap element that no
 * longer exists after the redesign. getElementById() returns null, and
 * .style on null throws a TypeError -- which stops showApplicantInfo()
 * from running at all, so clicking an applicant name appeared to do
 * nothing.
 *
 * Fix: remove that now-dead line. Status is always shown in the header
 * pill regardless, so there's nothing to hide/show for it anymore.
 *
 * HOW TO RUN:
 *   php fix_applicant_info_modal_null_status_wrap.php   (from project root)
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

echo "\n=== fix_applicant_info_modal_null_status_wrap.php ===\n\n";

$showPath = ROOT . '/resources/views/job-postings/show.blade.php';

echo "[1] Removing dead reference to #ai-status-wrap...\n";

apply_patch(
    $showPath,
    "    document.getElementById('ai-txn-wrap').style.display = data.transaction_number ? '' : 'none';
    document.getElementById('ai-applied-wrap').style.display = data.applied_at ? '' : 'none';
    document.getElementById('ai-status-wrap').style.display = data.status ? '' : 'none';
    set('ai-transaction_number', data.transaction_number);
    set('ai-applied_at', data.applied_at);
    set('ai-status', data.status);",
    "    document.getElementById('ai-txn-wrap').style.display = data.transaction_number ? '' : 'none';
    document.getElementById('ai-applied-wrap').style.display = data.applied_at ? '' : 'none';
    set('ai-transaction_number', data.transaction_number);
    set('ai-applied_at', data.applied_at);
    set('ai-status', data.status);",
    'show.blade.php: remove dead ai-status-wrap toggle causing null.style TypeError'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - Removed the line trying to toggle visibility on the no-longer-\n";
echo "    existing #ai-status-wrap element.\n";
echo "  - Clicking an applicant name should now work again -- the header\n";
echo "    status pill still gets its text set via set('ai-status', ...),\n";
echo "    just without the (now meaningless) show/hide wrapper logic.\n\n";
echo "DELETE this script after running.\n";
