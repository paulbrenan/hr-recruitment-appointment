<?php
/**
 * fix_view_print_car_green.php
 *
 * Changes the "View / Print CAR" button from outline-secondary (gray)
 * to green (btn-success), matching the same treatment given to
 * "Import from PDF" earlier.
 *
 * HOW TO RUN:
 *   php fix_view_print_car_green.php   (from project root)
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

echo "\n=== fix_view_print_car_green.php ===\n\n";

$showPath = ROOT . '/resources/views/job-postings/show.blade.php';

echo "[1] Making 'View / Print CAR' button green...\n";

apply_patch(
    $showPath,
    '<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#carDocumentModal">
                    <i class="bi bi-file-earmark-text me-1"></i> View / Print CAR
                </button>',
    '<button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#carDocumentModal">
                    <i class="bi bi-file-earmark-text me-1"></i> View / Print CAR
                </button>',
    'show.blade.php: View / Print CAR button is green'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - 'View / Print CAR' is now green (btn-success) instead of gray.\n\n";
echo "DELETE this script after running.\n";
