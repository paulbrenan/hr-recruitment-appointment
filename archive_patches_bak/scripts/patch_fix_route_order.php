<?php
/**
 * patch_fix_route_order.php
 *
 * Run from the project root:
 *   php patch_fix_route_order.php
 *
 * What it does:
 *  routes/web.php — moves the static 'assessments/criteria/destroy-all'
 *  route to appear BEFORE the 'assessments/criteria/{id}' wildcard route.
 *  Laravel matches routes top-to-bottom, so the wildcard route was
 *  swallowing "destroy-all" as if it were an {id}, causing a 404.
 *
 * Safe to run multiple times: aborts with no changes if the expected block
 * isn't found exactly (e.g. already patched, or file has changed since).
 * A .bak copy is made before any write.
 */

$root = __DIR__;
$path = $root . '/routes/web.php';

if (!file_exists($path)) {
    echo "[SKIP] web.php — file not found: $path\n";
    exit;
}

$content = file_get_contents($path);
$original = $content;

$old = <<<'OLD'
Route::delete('/assessments/criteria/{id}', [AssessmentController::class, 'destroyCriterion'])->name('assessments.criteria.destroy');
Route::delete('/assessments/criteria/destroy-all', [AssessmentController::class, 'destroyAllCriteria'])->name('assessments.criteria.destroy-all');
OLD;

$new = <<<'NEW'
Route::delete('/assessments/criteria/destroy-all', [AssessmentController::class, 'destroyAllCriteria'])->name('assessments.criteria.destroy-all');
Route::delete('/assessments/criteria/{id}', [AssessmentController::class, 'destroyCriterion'])->name('assessments.criteria.destroy');
NEW;

if (strpos($content, $old) === false) {
    echo "[ABORT] web.php — expected two-line block not found in this exact order (file may have changed, or already patched). No changes written.\n";
    exit;
}

$content = str_replace($old, $new, $content);

if ($content === $original) {
    echo "[SKIP] web.php — no changes needed.\n";
    exit;
}

$backup = $path . '.bak';
if (!file_exists($backup)) {
    copy($path, $backup);
} else {
    copy($path, $path . '.bak.' . date('Ymd_His'));
}

file_put_contents($path, $content);
echo "[OK] web.php — patched. Backup at: $backup\n";
