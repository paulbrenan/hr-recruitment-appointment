<?php
/**
 * patch_remove_scheduling_assessment_nav.php
 *
 * Removes the "Scheduling" and "Assessment & ranking" links from the main
 * left sidebar nav (layouts/app.blade.php). Routes, controllers, and views
 * for interviews.* and assessments.* are left untouched -- only the nav
 * entries are hidden. Pages remain reachable by direct URL if needed.
 *
 * Run once from the project root:
 *   php patch_remove_scheduling_assessment_nav.php
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
                <a href="{{ route('interviews.index') }}" class="nav-link {{ request()->routeIs('interviews.*') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Scheduling">
                    <i class="bi bi-calendar-event"></i> <span class="nav-label">Scheduling</span>
                </a>
                <a href="{{ route('assessments.index') }}" class="nav-link {{ request()->routeIs('assessments.*') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Assessment & ranking">
                    <i class="bi bi-clipboard-check"></i> <span class="nav-label">Assessment &amp; ranking</span>
                </a>
OLD;

$new = <<<'NEW'
                {{-- "Scheduling" (interviews.*) and "Assessment & ranking"
                     (assessments.*) removed from the sidebar per request.
                     Routes/controllers/views are untouched -- only these
                     nav links are hidden. --}}
NEW;

apply_patch($file, $old, $new, 'Sidebar nav: remove Scheduling and Assessment & ranking links');

echo "\nDone. Reload any page and confirm 'Scheduling' and 'Assessment & ranking' no longer\n";
echo "appear in the left sidebar.\n";
