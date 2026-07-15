<?php
/**
 * fix_pending_default_pill_tab.php
 *
 * Follow-up to fix_advance_redirect_and_pending_order.php -- that
 * script's advanceStep() patch already succeeded. Only the Pending-first
 * reordering failed to match, because the Qualification Checking panel
 * had since grown into a pill-tab switcher (qual-pill-tabs), and the
 * default active tab is whichever group is FIRST in $qualGroups
 * (`{{ $loop->first ? 'active' : '' }}`) -- not a stacked-sections layout
 * like the old patch assumed. Reordering the array is still exactly the
 * right fix, just re-targeted to the current markup.
 *
 * HOW TO RUN:
 *   php fix_pending_default_pill_tab.php   (from project root)
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

echo "\n=== fix_pending_default_pill_tab.php ===\n\n";

$showPath = ROOT . '/resources/views/job-postings/show.blade.php';

echo "[1] Reordering qualGroups so Pending is first (= default active pill tab)...\n";

apply_patch(
    $showPath,
    "                        \$qualGroups = [
                            'qualified'     => \$applications->where('qualification_result', 'qualified')->values(),
                            'not_qualified' => \$applications->where('qualification_result', 'not_qualified')->values(),
                            'pending'       => \$applications->whereNull('qualification_result')->values(),
                        ];
                        \$qualGroupMeta = [
                            'qualified'     => ['label' => 'Qualified', 'color' => 'success'],
                            'not_qualified' => ['label' => 'Disqualified', 'color' => 'danger'],
                            'pending'       => ['label' => 'Pending', 'color' => 'secondary'],
                        ];",
    "                        // Pending listed FIRST -- the pill switcher below marks\n" .
    "                        // whichever group is first in this array as the default\n" .
    "                        // active tab (\$loop->first), and Pending is the one HR\n" .
    "                        // actually needs to act on.\n" .
    "                        \$qualGroups = [\n" .
    "                            'pending'       => \$applications->whereNull('qualification_result')->values(),\n" .
    "                            'qualified'     => \$applications->where('qualification_result', 'qualified')->values(),\n" .
    "                            'not_qualified' => \$applications->where('qualification_result', 'not_qualified')->values(),\n" .
    "                        ];\n" .
    "                        \$qualGroupMeta = [\n" .
    "                            'pending'       => ['label' => 'Pending', 'color' => 'secondary'],\n" .
    "                            'qualified'     => ['label' => 'Qualified', 'color' => 'success'],\n" .
    "                            'not_qualified' => ['label' => 'Disqualified', 'color' => 'danger'],\n" .
    "                        ];",
    'show.blade.php: Pending listed first in qualGroups (default active pill tab)'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - Qualification Checking's pill switcher now opens on \"Pending\" by\n";
echo "    default instead of \"Qualified\". Tab order is now\n";
echo "    Pending -> Qualified -> Disqualified.\n\n";
echo "DELETE this script after running.\n";
