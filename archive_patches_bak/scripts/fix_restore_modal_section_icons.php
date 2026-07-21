<?php
/**
 * fix_restore_modal_section_icons.php
 *
 * Adds the section header icons back to all 5 cards in the Applicant
 * Info modal (Contact, Qualifications, Personal Details, Position &
 * Application, Qualification Check Breakdown) -- these were removed in
 * fix_applicant_info_modal_layout_cleanup.php along with the (separate)
 * header avatar circle, but only the avatar circle was actually meant
 * to go.
 *
 * REQUIRES fix_applicant_info_modal_layout_cleanup.php already applied
 * (this patch targets the reordered, icon-free section headers that
 * produced).
 *
 * HOW TO RUN:
 *   php fix_restore_modal_section_icons.php   (from project root)
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

echo "\n=== fix_restore_modal_section_icons.php ===\n\n";

$showPath = ROOT . '/resources/views/job-postings/show.blade.php';

$sections = [
    'Contact' => 'bi-person-lines-fill',
    'Qualifications' => 'bi-mortarboard',
    'Personal Details' => 'bi-card-list',
    'Position &amp; Application' => 'bi-briefcase',
    'Qualification Check Breakdown' => 'bi-clipboard-check',
];

$i = 1;
foreach ($sections as $label => $icon) {
    echo "[$i] Adding icon to \"$label\" section header...\n";
    apply_patch(
        $showPath,
        "                        <div class=\"text-uppercase text-muted fw-semibold mb-2\" style=\"font-size: .7rem; letter-spacing: .04em;\">\n                            {$label}\n                        </div>",
        "                        <div class=\"text-uppercase text-muted fw-semibold mb-2\" style=\"font-size: .7rem; letter-spacing: .04em;\">\n                            <i class=\"bi {$icon} me-1\"></i> {$label}\n                        </div>",
        "show.blade.php: icon added to \"$label\" section header"
    );
    $i++;
}

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - All 5 section headers have their icons back (Contact,\n";
echo "    Qualifications, Personal Details, Position & Application,\n";
echo "    Qualification Check Breakdown).\n";
echo "  - The header avatar circle stays removed -- that one's unaffected\n";
echo "    by this patch.\n\n";
echo "DELETE this script after running.\n";
