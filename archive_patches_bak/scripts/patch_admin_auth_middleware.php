<?php
/**
 * patch_admin_auth_middleware.php
 *
 * Right now none of the HR/admin routes in routes/web.php (Dashboard,
 * Job Postings, Applications, Salary Grade, Records, Signatories, etc.)
 * have any auth middleware at all -- they're wide open to anyone who
 * knows the URL, logged in or not. Only /portal/* (auth:candidate) and
 * /superadmin/* (auth:superadmin) are currently protected.
 *
 * There's no separate "admin role" column on users -- the AuthController
 * login (HR staff) is the only thing that authenticates against the
 * default 'web' guard / User model, so "logged in as admin" here means
 * "authenticated on the default guard." This wraps every HR/admin route
 * in Route::middleware('auth')->group(...), which uses that default
 * guard. Unauthenticated visitors hitting any of these routes will be
 * redirected to /login (Laravel's default unauthenticated behavior).
 *
 * NOT touched (left exactly as-is):
 *  - /login, /logout (must stay reachable while logged out)
 *  - /portal/* (candidate-facing, already on its own auth:candidate guard)
 *  - /superadmin/* (already on its own auth:superadmin guard)
 *  - / (public landing page)
 *  - /api/track (intentionally public — the applicant-facing AJAX tracker)
 *
 * Usage: php patch_admin_auth_middleware.php
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

$routes = __DIR__ . '/routes/web.php';

// ---------------------------------------------------------------------
// 1. Open the auth-protected group right before the Dashboard route,
//    which is the first HR/admin route after the public /api/track
//    endpoint.
// ---------------------------------------------------------------------
apply_patch(
    $routes,
    "Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');",
    "Route::middleware('auth')->group(function () {\n\nRoute::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');",
    'Open auth-protected route group before /dashboard'
);

// ---------------------------------------------------------------------
// 2. Close the group after the last HR/admin route in the file
//    (Activity Log Book).
// ---------------------------------------------------------------------
apply_patch(
    $routes,
    "Route::get('/activity-logs', [\\App\\Http\\Controllers\\ActivityLogController::class, 'index'])->name('activity-logs.index');",
    "Route::get('/activity-logs', [\\App\\Http\\Controllers\\ActivityLogController::class, 'index'])->name('activity-logs.index');\n\n}); // end auth-protected admin routes",
    'Close auth-protected route group after /activity-logs'
);

echo "\nDone. Verify:\n";
echo " - Log out, then try visiting /dashboard, /job-postings, /applications, /salary-grades, /records, /signatories directly -- each should redirect to /login.\n";
echo " - Log back in as HR staff -- all of those should work exactly as before.\n";
echo " - /portal/* and /superadmin/* behavior is unchanged (separate guards, untouched).\n";
echo " - / and /api/track are still public, unchanged.\n";
echo "\nNote: Laravel's default unauthenticated redirect goes through App\\Http\\Middleware\\Authenticate::redirectTo().\n";
echo "If that method doesn't already point unauthenticated web requests at /login, you may need to add/adjust it --\n";
echo "check app/Http/Middleware/Authenticate.php (or bootstrap/app.php on Laravel 11+/12 skeleton) if the redirect\n";
echo "doesn't land where expected.\n";
