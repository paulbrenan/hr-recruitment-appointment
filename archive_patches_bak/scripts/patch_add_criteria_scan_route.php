<?php
/**
 * patch_add_criteria_scan_route.php
 *
 * Run from the project root:
 *   php patch_add_criteria_scan_route.php
 *
 * What it does:
 *  routes/web.php — adds the 'assessments.criteria.import-scan' route
 *  right after 'assessments.criteria.store', for the "Scan file for
 *  criteria" feature added by patch_criteria_file_scan.php.
 *
 * Safe to run multiple times: aborts with no changes if the expected
 * anchor line isn't found exactly, or does nothing if the route already
 * exists. A .bak copy is made before any write.
 */

$root = __DIR__;
$path = $root . '/routes/web.php';

if (!file_exists($path)) {
    echo "[SKIP] web.php — file not found: $path\n";
    exit;
}

$content = file_get_contents($path);
$original = $content;

if (strpos($content, "assessments.criteria.import-scan") !== false) {
    echo "[SKIP] web.php — route already present.\n";
    exit;
}

$anchor = "Route::post('/assessments/criteria', [AssessmentController::class, 'storeCriterion'])->name('assessments.criteria.store');";

if (strpos($content, $anchor) === false) {
    echo "[ABORT] web.php — expected anchor line not found (file may have changed). No changes written.\n";
    exit;
}

$replacement = $anchor . "\n"
    . "Route::post('/assessments/criteria/import-scan', [AssessmentController::class, 'importCriteriaScan'])->name('assessments.criteria.import-scan');";

$content = str_replace($anchor, $replacement, $content);

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
