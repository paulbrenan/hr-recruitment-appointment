<?php
/**
 * patch_fix_applications_status_route.php
 *
 * FIXES: RouteNotFoundException — Route [applications.status] not defined.
 *
 * Root cause: both patch_pipeline_dashboard.php and _v2.php wrote the Qualify/
 * Disqualify buttons in show.blade.php pointing at route('applications.status', ...),
 * but the actual route (confirmed in routes/web.php) is named applications.updateStatus:
 *
 *   Route::put('/applications/{id}/status', [ApplicationController::class, 'updateStatus'])
 *       ->name('applications.updateStatus');
 *
 * This just corrects the route name in the two form actions. No route/controller
 * changes needed — the route already exists and already expects PUT with
 * `status` and `job_posting_id` fields, which the existing forms already send.
 *
 * HOW TO RUN:
 *   php patch_fix_applications_status_route.php   (from project root)
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

$path = ROOT . '/resources/views/job-postings/show.blade.php';

if (!file_exists($path)) {
    echo "\n❌ File not found: $path\n";
    exit(1);
}

$content = file_get_contents($path);
$count = substr_count($content, "route('applications.status', \$app->id)");

if ($count === 0) {
    echo "\n❌ ABORTED — \"route('applications.status', \$app->id)\" not found in $path\n";
    echo "File may have already been fixed, or drifted. No changes made.\n";
    exit(1);
}

echo "\n=== patch_fix_applications_status_route.php ===\n\n";
echo "Found $count occurrence(s) of the wrong route name.\n";

backup($path);
$patched = str_replace(
    "route('applications.status', \$app->id)",
    "route('applications.updateStatus', \$app->id)",
    $content
);
file_put_contents($path, $patched);

echo "  [ok ] Replaced $count occurrence(s): applications.status -> applications.updateStatus\n";
echo "\n✅ Done. Reload /job-postings/{id} — the RouteNotFoundException should be gone.\n";
echo "DELETE this script after running.\n";
