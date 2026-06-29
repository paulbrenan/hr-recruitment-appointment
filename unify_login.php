<?php
/**
 * unify_login.php
 *
 * One-shot script:
 * - Patches app/Http/Controllers/AuthController.php (login() now tries the
 *   web guard first, falls back to the candidate guard, redirects to the
 *   right dashboard depending on which one matched)
 * - Patches app/Http/Controllers/CandidateAuthController.php (removes the
 *   now-redundant showLogin()/login() methods, fixes logout()'s redirect
 *   target since /portal/login is going away)
 * - Patches routes/web.php (removes the GET+POST /portal/login routes)
 * - Patches resources/views/auth/login.blade.php (neutral wording instead of
 *   "Staff login", footer now offers both "Register as staff" and
 *   "Register as applicant" since one register link no longer makes sense)
 * - Patches resources/views/portal/register.blade.php ("Sign in" link now
 *   points to the unified /login instead of the retired /portal/login)
 *
 * NOTE: resources/views/portal/login.blade.php is left on disk but becomes
 * unreferenced by any route after this runs. Harmless to leave; delete it
 * yourself if you want the tree tidy.
 *
 * Usage: place this file in the project root (same folder as artisan) and run:
 *   php unify_login.php
 * Then delete this script. No migration needed for this change.
 *
 * Backs up every file it overwrites to .bak (or .bak2, .bak3, ... if needed).
 * Verifies every patch target exists exactly once before writing anything;
 * aborts with no changes if any check fails.
 */

function die_loud($msg) {
    fwrite(STDERR, "\n[ABORTED] $msg\n\n");
    exit(1);
}

function backup_file($path) {
    if (!file_exists($path)) {
        die_loud("Expected file not found: $path");
    }
    $backupPath = $path . '.bak';
    $n = 1;
    while (file_exists($backupPath)) {
        $n++;
        $backupPath = $path . '.bak' . $n;
    }
    if (!copy($path, $backupPath)) {
        die_loud("Could not create backup at $backupPath");
    }
    echo "Backed up " . $path . " -> " . basename($backupPath) . "\n";
}

function apply_patch($content, $old, $new, $label) {
    $count = substr_count($content, $old);
    if ($count !== 1) {
        die_loud("Patch '$label' expected exactly 1 match but found $count.\nThe file may have drifted from what was generated -- please paste its current content so the patch can be updated.");
    }
    return str_replace($old, $new, $content);
}

$root = __DIR__;

// ---------------------------------------------------------------------------
// 1. Patch app/Http/Controllers/AuthController.php
// ---------------------------------------------------------------------------
$authControllerPath = $root . '/app/Http/Controllers/AuthController.php';
$authControllerContent = file_get_contents($authControllerPath);
if ($authControllerContent === false) {
    die_loud("Could not read $authControllerPath");
}

$loginOld = <<<'LOGIN_OLD_EOF'
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (!Auth::attempt($credentials, $remember)) {
            return back()
                ->withErrors(['email' => 'Those credentials do not match our records.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
LOGIN_OLD_EOF;

$loginNew = <<<'LOGIN_NEW_EOF'
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        if (Auth::guard('candidate')->attempt($credentials, $remember)) {
            $request->session()->regenerate();

            return redirect()->intended(route('portal.dashboard'));
        }

        return back()
            ->withErrors(['email' => 'Those credentials do not match our records.'])
            ->onlyInput('email');
    }
LOGIN_NEW_EOF;

$newAuthControllerContent = apply_patch($authControllerContent, $loginOld, $loginNew, 'AuthController-login');

backup_file($authControllerPath);
if (file_put_contents($authControllerPath, $newAuthControllerContent) === false) {
    die_loud("Could not write $authControllerPath");
}
echo "Updated app/Http/Controllers/AuthController.php\n";

// ---------------------------------------------------------------------------
// 2. Patch app/Http/Controllers/CandidateAuthController.php
// ---------------------------------------------------------------------------
$candidateAuthControllerPath = $root . '/app/Http/Controllers/CandidateAuthController.php';
$candidateAuthControllerContent = file_get_contents($candidateAuthControllerPath);
if ($candidateAuthControllerContent === false) {
    die_loud("Could not read $candidateAuthControllerPath");
}

$removeLoginOld = <<<'REMOVE_LOGIN_OLD_EOF'
class CandidateAuthController extends Controller
{
    public function showLogin()
    {
        return view('portal.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (!Auth::guard('candidate')->attempt($credentials, $remember)) {
            return back()
                ->withErrors(['email' => 'Those credentials do not match our records.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('portal.dashboard'));
    }

    public function showRegister()
    {
REMOVE_LOGIN_OLD_EOF;

$removeLoginNew = <<<'REMOVE_LOGIN_NEW_EOF'
class CandidateAuthController extends Controller
{
    public function showRegister()
    {
REMOVE_LOGIN_NEW_EOF;

$logoutOld = <<<'LOGOUT_OLD_EOF'
    public function logout(Request $request)
    {
        Auth::guard('candidate')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
LOGOUT_OLD_EOF;

$logoutNew = <<<'LOGOUT_NEW_EOF'
    public function logout(Request $request)
    {
        Auth::guard('candidate')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
LOGOUT_NEW_EOF;

$newCandidateAuthControllerContent = $candidateAuthControllerContent;
$newCandidateAuthControllerContent = apply_patch($newCandidateAuthControllerContent, $removeLoginOld, $removeLoginNew, 'CandidateAuthController-remove-login-methods');
$newCandidateAuthControllerContent = apply_patch($newCandidateAuthControllerContent, $logoutOld, $logoutNew, 'CandidateAuthController-logout-redirect');

backup_file($candidateAuthControllerPath);
if (file_put_contents($candidateAuthControllerPath, $newCandidateAuthControllerContent) === false) {
    die_loud("Could not write $candidateAuthControllerPath");
}
echo "Updated app/Http/Controllers/CandidateAuthController.php\n";

// ---------------------------------------------------------------------------
// 3. Patch routes/web.php
// ---------------------------------------------------------------------------
$routesPath = $root . '/routes/web.php';
$routesContent = file_get_contents($routesPath);
if ($routesContent === false) {
    die_loud("Could not read $routesPath");
}

$routesOld = <<<'ROUTES_OLD_EOF'
// Applicant portal authentication
Route::get('/portal/login', [CandidateAuthController::class, 'showLogin'])->name('portal.login');
Route::post('/portal/login', [CandidateAuthController::class, 'login'])->name('portal.login.attempt');
Route::get('/portal/register', [CandidateAuthController::class, 'showRegister'])->name('portal.register');
ROUTES_OLD_EOF;

$routesNew = <<<'ROUTES_NEW_EOF'
// Applicant portal authentication
Route::get('/portal/register', [CandidateAuthController::class, 'showRegister'])->name('portal.register');
ROUTES_NEW_EOF;

$newRoutesContent = apply_patch($routesContent, $routesOld, $routesNew, 'routes-remove-portal-login');

backup_file($routesPath);
if (file_put_contents($routesPath, $newRoutesContent) === false) {
    die_loud("Could not write $routesPath");
}
echo "Updated routes/web.php\n";

// ---------------------------------------------------------------------------
// 4. Patch resources/views/auth/login.blade.php
// ---------------------------------------------------------------------------
$loginViewPath = $root . '/resources/views/auth/login.blade.php';
$loginViewContent = file_get_contents($loginViewPath);
if ($loginViewContent === false) {
    die_loud("Could not read $loginViewPath");
}

$titleOld = <<<'TITLE_OLD_EOF'
@section('title', 'Staff login')
@section('brand', 'HR Recruitment — Staff Login')

@section('content')
<h5 class="mb-3">Staff sign in</h5>
TITLE_OLD_EOF;

$titleNew = <<<'TITLE_NEW_EOF'
@section('title', 'Sign in')
@section('brand', 'HR Recruitment')

@section('content')
<h5 class="mb-3">Sign in</h5>
TITLE_NEW_EOF;

$footerOld = <<<'FOOTER_OLD_EOF'
<p class="text-center small text-muted mt-3 mb-0">
    Don't have an account? <a href="{{ route('register') }}">Register</a>
</p>
@endsection
FOOTER_OLD_EOF;

$footerNew = <<<'FOOTER_NEW_EOF'
<p class="text-center small text-muted mt-3 mb-2">
    Don't have an account?
</p>
<div class="d-flex gap-2">
    <a href="{{ route('register') }}" class="btn btn-outline-secondary btn-sm w-50">Register as staff</a>
    <a href="{{ route('portal.register') }}" class="btn btn-outline-secondary btn-sm w-50">Register as applicant</a>
</div>
@endsection
FOOTER_NEW_EOF;

$newLoginViewContent = $loginViewContent;
$newLoginViewContent = apply_patch($newLoginViewContent, $titleOld, $titleNew, 'login-view-title');
$newLoginViewContent = apply_patch($newLoginViewContent, $footerOld, $footerNew, 'login-view-footer');

backup_file($loginViewPath);
if (file_put_contents($loginViewPath, $newLoginViewContent) === false) {
    die_loud("Could not write $loginViewPath");
}
echo "Updated resources/views/auth/login.blade.php\n";

// ---------------------------------------------------------------------------
// 5. Patch resources/views/portal/register.blade.php
// ---------------------------------------------------------------------------
$portalRegisterPath = $root . '/resources/views/portal/register.blade.php';
$portalRegisterContent = file_get_contents($portalRegisterPath);
if ($portalRegisterContent === false) {
    die_loud("Could not read $portalRegisterPath");
}

$signinOld = <<<'SIGNIN_OLD_EOF'
<p class="text-center small text-muted mt-3 mb-0">
    Already have an account? <a href="{{ route('portal.login') }}">Sign in</a>
</p>
@endsection
SIGNIN_OLD_EOF;

$signinNew = <<<'SIGNIN_NEW_EOF'
<p class="text-center small text-muted mt-3 mb-0">
    Already have an account? <a href="{{ route('login') }}">Sign in</a>
</p>
@endsection
SIGNIN_NEW_EOF;

$newPortalRegisterContent = apply_patch($portalRegisterContent, $signinOld, $signinNew, 'portal-register-signin-link');

backup_file($portalRegisterPath);
if (file_put_contents($portalRegisterPath, $newPortalRegisterContent) === false) {
    die_loud("Could not write $portalRegisterPath");
}
echo "Updated resources/views/portal/register.blade.php\n";

echo "\nDone.\n";
echo "Next steps:\n";
echo "  1. Visit /login and sign in with an HR staff account -- confirm it lands on /dashboard.\n";
echo "  2. Visit /login again and sign in with an applicant account -- confirm it lands on /portal/dashboard.\n";
echo "  3. Confirm /portal/login now 404s (it's retired) and the old route name is gone.\n";
echo "  4. Confirm the 'Register as staff' / 'Register as applicant' links on /login both work.\n";
echo "  5. resources/views/portal/login.blade.php is now unused -- delete it yourself if you want, not required.\n";
echo "  6. Delete this script once you've confirmed everything works.\n";
