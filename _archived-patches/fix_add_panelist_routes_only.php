<?php
/**
 * fix_add_panelist_routes_only.php
 *
 * PanelistController already exists (update() + destroy(), both correct)
 * -- the 404 was purely because routes/web.php never registered routes
 * for it. This adds just the two missing routes; no controller changes.
 *
 * HOW TO RUN:
 *   php fix_add_panelist_routes_only.php   (from project root)
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

echo "\n=== fix_add_panelist_routes_only.php ===\n\n";

$routesPath = ROOT . '/routes/web.php';

echo "[1] Adding PanelistController import...\n";

apply_patch(
    $routesPath,
    "use App\Http\Controllers\JobPostingController;",
    "use App\Http\Controllers\JobPostingController;\nuse App\Http\Controllers\PanelistController;",
    'web.php: import PanelistController'
);

echo "\n[2] Adding panelist rename/delete routes...\n";

apply_patch(
    $routesPath,
    "Route::delete('/job-postings/{posting}/panelists/{panelist}', [JobPostingController::class, 'detachPanelist'])->name('job-postings.panelists.detach');",
    "Route::delete('/job-postings/{posting}/panelists/{panelist}', [JobPostingController::class, 'detachPanelist'])->name('job-postings.panelists.detach');
Route::put('/panelists/{id}', [PanelistController::class, 'update'])->name('panelists.update');
Route::delete('/panelists/{id}', [PanelistController::class, 'destroy'])->name('panelists.destroy');",
    'web.php: panelists.update / panelists.destroy routes'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - PUT  /panelists/{id}    -> PanelistController::update (rename)\n";
echo "  - DELETE /panelists/{id}  -> PanelistController::destroy (delete)\n\n";
echo "No controller files were touched -- your existing PanelistController\n";
echo "is used as-is. Delete/rename should both work now instead of 404ing.\n\n";
echo "DELETE this script after running.\n";
