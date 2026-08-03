<?php
/**
 * patch_admin_logout_dropdown.php
 *
 * Turns the static "HR Staff" pill in the pagebar (top-right of every
 * admin page, since it lives in the shared layout) into a Bootstrap
 * dropdown showing the logged-in user's name, with a "Log out" option
 * that POSTs to the existing route('logout').
 *
 * Depends on patch_dashboard_header_beige.php having already been
 * applied (this patches the same pagebar block it introduced).
 *
 * Usage: php patch_admin_logout_dropdown.php
 */

function apply_patch(string $file, string $search, string $replace, string $label): void
{
    if (!file_exists($file)) {
        echo "[ABORT] File not found: {$file}\n";
        exit(1);
    }

    $contents = file_get_contents($file);

    if (strpos($contents, $search) === false) {
        echo "[ABORT] {$label}: expected text not found in {$file}. File may have drifted — aborting without changes.\n";
        exit(1);
    }

    if (substr_count($contents, $search) > 1) {
        echo "[ABORT] {$label}: expected text is not unique in {$file}. Aborting to avoid ambiguous edit.\n";
        exit(1);
    }

    $backup = $file . '.bak';
    if (!file_exists($backup)) {
        copy($file, $backup);
    }

    $new_contents = str_replace($search, $replace, $contents);
    file_put_contents($file, $new_contents);

    echo "[OK] {$label} applied to {$file}\n";
}

$layout = __DIR__ . '/resources/views/layouts/app.blade.php';

apply_patch(
    $layout,
    '<div class="text-muted small">
                        <i class="bi bi-person-circle me-1"></i> HR Staff
                    </div>',
    '<div class="dropdown">
                        <button type="button" class="btn btn-sm text-muted small dropdown-toggle border-0 bg-transparent"
                                data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i> {{ auth()->user()->name ?? \'HR Staff\' }}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <form action="{{ route(\'logout\') }}" method="POST" class="m-0">
                                    @csrf
                                    <button type="submit" class="dropdown-item">
                                        <i class="bi bi-box-arrow-right me-2"></i> Log out
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>',
    'Turn HR Staff pill into a logout dropdown'
);

echo "\nDone. Reload any admin page to verify:\n";
echo " - Clicking the 'HR Staff' pill (now showing the logged-in user's name) opens a dropdown.\n";
echo " - 'Log out' submits a POST to /logout and ends the session.\n";
echo " - Since this lives in the shared layout, it appears on every admin page automatically -- no per-page changes needed.\n";
