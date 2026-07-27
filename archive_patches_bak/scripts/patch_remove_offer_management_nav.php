<?php
/**
 * patch_remove_offer_management_nav.php
 *
 * Removes the "Offer management" link from the main left sidebar nav
 * (layouts/app.blade.php). Routes, controllers, and views for offers.*
 * are left untouched for now -- only the nav entry is hidden.
 *
 * This is step 1 of "move Offer Management into the pipeline as Step 5
 * (after Assessment & Results)". Once the pipeline Step 5 patch is built
 * and confirmed working, the standalone offers.index page itself can be
 * removed the same way the old Assessment/Scheduling pages were.
 *
 * Run once from the project root:
 *   php patch_remove_offer_management_nav.php
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
                <a href="{{ route('offers.index') }}" class="nav-link {{ request()->routeIs('offers.*') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Offer management">
                    <i class="bi bi-envelope-paper"></i> <span class="nav-label">Offer management</span>
                </a>
OLD;

$new = <<<'NEW'
                {{-- "Offer management" (offers.*) removed from the sidebar --
                     being moved into the job-postings pipeline as Step 5,
                     after Assessment & Results. Routes/controllers/views are
                     untouched -- only this nav link is hidden. --}}
NEW;

apply_patch($file, $old, $new, 'Sidebar nav: remove Offer management link');

echo "\nDone. 'Offer management' no longer appears in the left sidebar.\n";
