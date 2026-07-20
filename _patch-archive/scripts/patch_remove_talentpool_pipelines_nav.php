<?php
/**
 * patch_remove_talentpool_pipelines_nav.php
 *
 * Removes the "Talent pool" and "Pipelines" links from the main left
 * sidebar nav (layouts/app.blade.php). Routes, controllers, and views
 * for talent-pool.* and pipelines.* are left untouched -- only the nav
 * entries are hidden.
 *
 * Run once from the project root:
 *   php patch_remove_talentpool_pipelines_nav.php
 * Then delete this file — it is a one-shot installer, not idempotent.
 */

function apply_patch($path, $old, $new, $label) {
    if (!file_exists($path)) {
        fwrite(STDERR, "[ABORT] File not found: $path ($label)\n");
        exit(1);
    }
    $contents = file_get_contents($path);
    if (strpos($contents, $old) === false) {
        fwrite(STDERR, "[ABORT] Expected content not found for: $label\n");
        fwrite(STDERR, "        File may already be patched or is a different version. No changes made.\n");
        exit(1);
    }
    copy($path, $path . '.bak');
    $updated = str_replace($old, $new, $contents, $count);
    if ($count !== 1) {
        fwrite(STDERR, "[ABORT] Expected exactly 1 match for '$label', found $count. Restoring backup.\n");
        copy($path . '.bak', $path);
        exit(1);
    }
    file_put_contents($path, $updated);
    echo "[OK] $label\n";
}

$file = __DIR__ . '/resources/views/layouts/app.blade.php';

$old = <<<'OLD'
                <a href="{{ route('talent-pool.index') }}" class="nav-link {{ request()->routeIs('talent-pool.*') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Talent pool">
                    <i class="bi bi-people"></i> <span class="nav-label">Talent pool</span>
                </a>
                <a href="{{ route('pipelines.index') }}" class="nav-link {{ request()->routeIs('pipelines.*') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Pipelines">
                    <i class="bi bi-diagram-3"></i> <span class="nav-label">Pipelines</span>
                </a>
OLD;

$new = <<<'NEW'
                {{-- "Talent pool" (talent-pool.*) and "Pipelines" (pipelines.*)
                     removed from the sidebar per request. Routes/controllers/
                     views are untouched -- only these nav links are hidden. --}}
NEW;

apply_patch($file, $old, $new, 'Sidebar nav: remove Talent pool and Pipelines links');

echo "\nDone. Reload any page and confirm 'Talent pool' and 'Pipelines' no longer\n";
echo "appear in the left sidebar.\n";
