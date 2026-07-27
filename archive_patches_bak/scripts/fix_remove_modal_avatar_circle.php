<?php
/**
 * fix_remove_modal_avatar_circle.php
 *
 * Removes the circle avatar (initial letter) next to the name in the
 * Applicant Info modal header. Section header icons (removed in
 * fix_applicant_info_modal_layout_cleanup.php) are unaffected -- they
 * stay removed.
 *
 * HOW TO RUN:
 *   php fix_remove_modal_avatar_circle.php   (from project root)
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

echo "\n=== fix_remove_modal_avatar_circle.php ===\n\n";

$showPath = ROOT . '/resources/views/job-postings/show.blade.php';

echo "[1] Removing avatar circle from modal header markup...\n";

apply_patch(
    $showPath,
    '                <div class="d-flex align-items-center gap-3" style="min-width: 0;">
                    <div id="ai-avatar" class="d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 46px; height: 46px; border-radius: 50%; background: rgba(255,255,255,.18); font-weight: 700; font-size: 1.1rem;">?</div>
                    <div style="min-width: 0;">
                        <h5 class="modal-title mb-0" id="applicantInfoName" style="font-weight: 700;">Applicant Information</h5>
                        <span id="ai-status" class="badge mt-1" style="background: rgba(255,255,255,.22); font-weight: 600; font-size: .72rem;">—</span>
                    </div>
                </div>',
    '                <div style="min-width: 0;">
                    <h5 class="modal-title mb-0" id="applicantInfoName" style="font-weight: 700;">Applicant Information</h5>
                    <span id="ai-status" class="badge mt-1" style="background: rgba(255,255,255,.22); font-weight: 600; font-size: .72rem;">—</span>
                </div>',
    'show.blade.php: remove avatar circle from modal header'
);

echo "\n[2] Removing the now-unused avatar-fill JS line...\n";

apply_patch(
    $showPath,
    "    document.getElementById('applicantInfoName').textContent = data.name || 'Applicant Information';\n" .
    "    document.getElementById('ai-avatar').textContent = data.name ? data.name.trim().charAt(0).toUpperCase() : '?';",
    "    document.getElementById('applicantInfoName').textContent = data.name || 'Applicant Information';",
    'show.blade.php: remove dead avatar-fill JS line'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - Header now shows just the name and status pill, no avatar\n";
echo "    circle.\n";
echo "  - Section header icons remain removed (unchanged from before).\n\n";
echo "DELETE this script after running.\n";
