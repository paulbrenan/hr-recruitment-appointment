<?php
/**
 * fix_signatories_orderby_role_label.php
 *
 * fix_remove_signatory_role.php dropped role_label from both signatory
 * tables, but SignatoriesPageController::index() still tried to
 * ->orderBy('role_label') on both queries -- that column no longer
 * exists, causing "Column not found: 1054 Unknown column 'role_label'".
 * Switched to ordering by name instead.
 *
 * HOW TO RUN:
 *   php fix_signatories_orderby_role_label.php   (from project root)
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

echo "\n=== fix_signatories_orderby_role_label.php ===\n\n";

$path = ROOT . '/app/Http/Controllers/SignatoriesPageController.php';

echo "[1] Switching orderBy('role_label') to orderBy('name')...\n";

apply_patch(
    $path,
    "        \$ierSignatories = IERSignatory::orderBy('role_label')->get();
        \$qualificationNoticeSignatories = QualificationNoticeSignatory::orderBy('role_label')->get();",
    "        \$ierSignatories = IERSignatory::orderBy('name')->get();
        \$qualificationNoticeSignatories = QualificationNoticeSignatory::orderBy('name')->get();",
    'SignatoriesPageController: order both lists by name, not the removed role_label'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - Both signatory lists on /signatories now order by name instead\n";
echo "    of the dropped role_label column.\n\n";
echo "DELETE this script after running.\n";
