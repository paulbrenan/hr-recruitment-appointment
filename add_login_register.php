<?php
/**
 * add_login_register.php
 *
 * One-shot script:
 * - Creates database/migrations/2026_06_25_160000_add_password_to_candidates_table.php
 *   (password nullable + remember_token, so the 12 existing seeded candidates aren't broken)
 * - Creates app/Http/Controllers/AuthController.php (admin/HR login+register, web guard)
 * - Creates app/Http/Controllers/CandidateAuthController.php (applicant login+register,
 *   new 'candidate' guard, plus a placeholder /portal/dashboard so there's somewhere
 *   to land after login -- NOT the real portal, just proof the auth flow works end to end)
 * - Creates resources/views/layouts/auth.blade.php (shared minimal layout, styled to
 *   match the existing --hr-primary color scheme, no sidebar)
 * - Creates resources/views/auth/login.blade.php, auth/register.blade.php
 * - Creates resources/views/portal/login.blade.php, portal/register.blade.php, portal/dashboard.blade.php
 * - Patches config/auth.php (adds 'candidate' guard + 'candidates' provider)
 * - Patches app/Models/Candidate.php (extends Authenticatable, hashed password cast)
 * - Patches routes/web.php (adds the new routes + use statements)
 *
 * IMPORTANT: this does NOT protect any existing HR routes (dashboard, job-postings,
 * etc.) -- that is a deliberate, separate next step, per your decision to ship
 * login/register first and confirm it works before locking anything down.
 *
 * Usage: place this file in the project root (same folder as artisan) and run:
 *   php add_login_register.php
 * Then:
 *   php artisan migrate
 * Then delete this script.
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
        die_loud("Patch '$label' expected exactly 1 match but found $count.\nThe file may have drifted from what was pasted -- please re-paste it so the patch can be updated.");
    }
    return str_replace($old, $new, $content);
}

function create_new_file($path, $content, $label) {
    if (file_exists($path)) {
        die_loud("$label already exists at $path -- looks like this script already ran.");
    }
    $dir = dirname($path);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            die_loud("Could not create directory $dir");
        }
        echo "Created directory $dir\n";
    }
    if (file_put_contents($path, $content) === false) {
        die_loud("Could not write $path");
    }
    echo "Created $label at $path\n";
}

$root = __DIR__;

// ---------------------------------------------------------------------------
// 1. New migration: password + remember_token on candidates
// ---------------------------------------------------------------------------
$migrationContent = <<<'MIGRATION_EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
            $table->rememberToken()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn(['password', 'remember_token']);
        });
    }
};
MIGRATION_EOF;

create_new_file(
    $root . '/database/migrations/2026_06_25_160000_add_password_to_candidates_table.php',
    $migrationContent,
    'migration'
);

// ---------------------------------------------------------------------------
// 2. New controller: AuthController (admin / HR, web guard)
// ---------------------------------------------------------------------------
$authControllerContent = <<<'AUTHCONTROLLER_EOF'
<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

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

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
AUTHCONTROLLER_EOF;

create_new_file(
    $root . '/app/Http/Controllers/AuthController.php',
    $authControllerContent,
    'controller'
);

// ---------------------------------------------------------------------------
// 3. New controller: CandidateAuthController (applicant, candidate guard)
// ---------------------------------------------------------------------------
$candidateAuthControllerContent = <<<'CANDIDATEAUTHCONTROLLER_EOF'
<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

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
        return view('portal.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:candidates,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $candidate = Candidate::create([
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
        ]);

        Auth::guard('candidate')->login($candidate);

        $request->session()->regenerate();

        return redirect()->route('portal.dashboard');
    }

    public function dashboard()
    {
        return view('portal.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::guard('candidate')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
CANDIDATEAUTHCONTROLLER_EOF;

create_new_file(
    $root . '/app/Http/Controllers/CandidateAuthController.php',
    $candidateAuthControllerContent,
    'controller'
);

// ---------------------------------------------------------------------------
// 4. New layout: layouts/auth.blade.php
// ---------------------------------------------------------------------------
$authLayoutContent = <<<'AUTHLAYOUT_EOF'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'HR Recruitment') &mdash; HR System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        :root {
            --hr-primary: #2f4858;
            --hr-primary-dark: #233843;
            --hr-accent: #3f7d8c;
            --hr-bg: #f4f6f7;
        }
        html, body {
            background-color: var(--hr-bg);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh;
        }
        .auth-header {
            background-color: var(--hr-primary);
            color: #fff;
            padding: 0.9rem 1.5rem;
            font-weight: 600;
            font-size: 1.05rem;
        }
        .auth-wrapper {
            min-height: calc(100vh - 56px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .auth-card {
            width: 100%;
            max-width: 440px;
            border: 1px solid #e2e6e8;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        }
        .btn-hr-primary {
            background-color: var(--hr-primary);
            color: #fff;
        }
        .btn-hr-primary:hover {
            background-color: var(--hr-primary-dark);
            color: #fff;
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="auth-header">
        <i class="bi bi-people-fill me-2"></i>@yield('brand', 'HR Recruitment')
    </div>
    <div class="auth-wrapper">
        <div class="card auth-card">
            <div class="card-body p-4">
                @yield('content')
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
AUTHLAYOUT_EOF;

create_new_file(
    $root . '/resources/views/layouts/auth.blade.php',
    $authLayoutContent,
    'view'
);

// ---------------------------------------------------------------------------
// 5. New view: auth/login.blade.php
// ---------------------------------------------------------------------------
$adminLoginContent = <<<'ADMINLOGIN_EOF'
@extends('layouts.auth')

@section('title', 'Staff login')
@section('brand', 'HR Recruitment — Staff Login')

@section('content')
<h5 class="mb-3">Staff sign in</h5>

@if ($errors->any())
    <div class="alert alert-danger small">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if (session('status'))
    <div class="alert alert-success small">{{ session('status') }}</div>
@endif

<form action="{{ route('login.attempt') }}" method="POST">
    @csrf
    <div class="mb-3">
        <label class="form-label small fw-medium">Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus>
    </div>
    <div class="mb-3">
        <label class="form-label small fw-medium">Password</label>
        <input type="password" name="password" class="form-control" required>
    </div>
    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="remember" id="remember">
        <label class="form-check-label small" for="remember">Remember me</label>
    </div>
    <button type="submit" class="btn btn-hr-primary w-100">Sign in</button>
</form>

<p class="text-center small text-muted mt-3 mb-0">
    Don't have an account? <a href="{{ route('register') }}">Register</a>
</p>
@endsection
ADMINLOGIN_EOF;

create_new_file(
    $root . '/resources/views/auth/login.blade.php',
    $adminLoginContent,
    'view'
);

// ---------------------------------------------------------------------------
// 6. New view: auth/register.blade.php
// ---------------------------------------------------------------------------
$adminRegisterContent = <<<'ADMINREGISTER_EOF'
@extends('layouts.auth')

@section('title', 'Staff registration')
@section('brand', 'HR Recruitment — Staff Registration')

@section('content')
<h5 class="mb-3">Create staff account</h5>

@if ($errors->any())
    <div class="alert alert-danger small">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ route('register.attempt') }}" method="POST">
    @csrf
    <div class="mb-3">
        <label class="form-label small fw-medium">Full name</label>
        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required autofocus>
    </div>
    <div class="mb-3">
        <label class="form-label small fw-medium">Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
    </div>
    <div class="mb-3">
        <label class="form-label small fw-medium">Password</label>
        <input type="password" name="password" class="form-control" required>
        <div class="form-text" style="font-size: 0.72rem;">At least 8 characters.</div>
    </div>
    <div class="mb-3">
        <label class="form-label small fw-medium">Confirm password</label>
        <input type="password" name="password_confirmation" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-hr-primary w-100">Create account</button>
</form>

<p class="text-center small text-muted mt-3 mb-0">
    Already have an account? <a href="{{ route('login') }}">Sign in</a>
</p>
@endsection
ADMINREGISTER_EOF;

create_new_file(
    $root . '/resources/views/auth/register.blade.php',
    $adminRegisterContent,
    'view'
);

// ---------------------------------------------------------------------------
// 7. New view: portal/login.blade.php
// ---------------------------------------------------------------------------
$portalLoginContent = <<<'PORTALLOGIN_EOF'
@extends('layouts.auth')

@section('title', 'Applicant login')
@section('brand', 'Applicant Portal')

@section('content')
<h5 class="mb-3">Sign in to your account</h5>

@if ($errors->any())
    <div class="alert alert-danger small">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if (session('status'))
    <div class="alert alert-success small">{{ session('status') }}</div>
@endif

<form action="{{ route('portal.login.attempt') }}" method="POST">
    @csrf
    <div class="mb-3">
        <label class="form-label small fw-medium">Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus>
    </div>
    <div class="mb-3">
        <label class="form-label small fw-medium">Password</label>
        <input type="password" name="password" class="form-control" required>
    </div>
    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="remember" id="remember">
        <label class="form-check-label small" for="remember">Remember me</label>
    </div>
    <button type="submit" class="btn btn-hr-primary w-100">Sign in</button>
</form>

<p class="text-center small text-muted mt-3 mb-0">
    Don't have an account? <a href="{{ route('portal.register') }}">Register</a>
</p>
@endsection
PORTALLOGIN_EOF;

create_new_file(
    $root . '/resources/views/portal/login.blade.php',
    $portalLoginContent,
    'view'
);

// ---------------------------------------------------------------------------
// 8. New view: portal/register.blade.php
// ---------------------------------------------------------------------------
$portalRegisterContent = <<<'PORTALREGISTER_EOF'
@extends('layouts.auth')

@section('title', 'Applicant registration')
@section('brand', 'Applicant Portal — Registration')

@section('content')
<h5 class="mb-3">Create your account</h5>

@if ($errors->any())
    <div class="alert alert-danger small">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ route('portal.register.attempt') }}" method="POST">
    @csrf
    <div class="row g-2">
        <div class="col-md-4">
            <label class="form-label small fw-medium">First name</label>
            <input type="text" name="first_name" class="form-control" value="{{ old('first_name') }}" required autofocus>
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-medium">Middle name</label>
            <input type="text" name="middle_name" class="form-control" value="{{ old('middle_name') }}">
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-medium">Last name</label>
            <input type="text" name="last_name" class="form-control" value="{{ old('last_name') }}" required>
        </div>
    </div>
    <div class="mb-3 mt-2">
        <label class="form-label small fw-medium">Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
    </div>
    <div class="mb-3">
        <label class="form-label small fw-medium">Phone</label>
        <input type="text" name="phone" class="form-control" value="{{ old('phone') }}" placeholder="e.g. +639171234567">
    </div>
    <div class="mb-3">
        <label class="form-label small fw-medium">Password</label>
        <input type="password" name="password" class="form-control" required>
        <div class="form-text" style="font-size: 0.72rem;">At least 8 characters.</div>
    </div>
    <div class="mb-3">
        <label class="form-label small fw-medium">Confirm password</label>
        <input type="password" name="password_confirmation" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-hr-primary w-100">Create account</button>
</form>

<p class="text-center small text-muted mt-3 mb-0">
    Already have an account? <a href="{{ route('portal.login') }}">Sign in</a>
</p>
@endsection
PORTALREGISTER_EOF;

create_new_file(
    $root . '/resources/views/portal/register.blade.php',
    $portalRegisterContent,
    'view'
);

// ---------------------------------------------------------------------------
// 9. New view: portal/dashboard.blade.php (placeholder landing page)
// ---------------------------------------------------------------------------
$portalDashboardContent = <<<'PORTALDASHBOARD_EOF'
@extends('layouts.auth')

@section('title', 'Applicant dashboard')
@section('brand', 'Applicant Portal')

@section('content')
<h5 class="mb-2">Welcome, {{ auth()->guard('candidate')->user()->full_name }}</h5>
<p class="text-muted small mb-3">
    This is a placeholder landing page confirming the login/registration flow works end to end.
    Viewing open positions, applying, and tracking your application status will be built here next.
</p>
<form action="{{ route('portal.logout') }}" method="POST">
    @csrf
    <button type="submit" class="btn btn-outline-secondary btn-sm">Log out</button>
</form>
@endsection
PORTALDASHBOARD_EOF;

create_new_file(
    $root . '/resources/views/portal/dashboard.blade.php',
    $portalDashboardContent,
    'view'
);

// ---------------------------------------------------------------------------
// 10. Patch config/auth.php (add candidate guard + provider)
// ---------------------------------------------------------------------------
$authConfigPath = $root . '/config/auth.php';
$authConfigContent = file_get_contents($authConfigPath);
if ($authConfigContent === false) {
    die_loud("Could not read $authConfigPath");
}

$guardsOld = <<<'GUARDS_OLD_EOF'
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],
GUARDS_OLD_EOF;

$guardsNew = <<<'GUARDS_NEW_EOF'
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'candidate' => [
            'driver' => 'session',
            'provider' => 'candidates',
        ],
    ],
GUARDS_NEW_EOF;

$providersOld = <<<'PROVIDERS_OLD_EOF'
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],
PROVIDERS_OLD_EOF;

$providersNew = <<<'PROVIDERS_NEW_EOF'
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        'candidates' => [
            'driver' => 'eloquent',
            'model' => \App\Models\Candidate::class,
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],
PROVIDERS_NEW_EOF;

$newAuthConfigContent = $authConfigContent;
$newAuthConfigContent = apply_patch($newAuthConfigContent, $guardsOld, $guardsNew, 'auth-guards');
$newAuthConfigContent = apply_patch($newAuthConfigContent, $providersOld, $providersNew, 'auth-providers');

backup_file($authConfigPath);
if (file_put_contents($authConfigPath, $newAuthConfigContent) === false) {
    die_loud("Could not write $authConfigPath");
}
echo "Updated config/auth.php\n";

// ---------------------------------------------------------------------------
// 11. Patch app/Models/Candidate.php (Authenticatable conversion)
// ---------------------------------------------------------------------------
$candidatePath = $root . '/app/Models/Candidate.php';
$candidateContent = file_get_contents($candidatePath);
if ($candidateContent === false) {
    die_loud("Could not read $candidatePath");
}

$candidateOld = <<<'CANDIDATE_OLD_EOF'
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;

class Candidate extends Model
{
    use Notifiable;

    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'phone',
        'address',
        'resume_path',
        'photo_path',
    ];

    
    public function routeNotificationForMail(): string
CANDIDATE_OLD_EOF;

$candidateNew = <<<'CANDIDATE_NEW_EOF'
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Candidate extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'password',
        'phone',
        'address',
        'resume_path',
        'photo_path',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
    ];

    public function routeNotificationForMail(): string
CANDIDATE_NEW_EOF;

$newCandidateContent = apply_patch($candidateContent, $candidateOld, $candidateNew, 'Candidate-Authenticatable');

backup_file($candidatePath);
if (file_put_contents($candidatePath, $newCandidateContent) === false) {
    die_loud("Could not write $candidatePath");
}
echo "Updated app/Models/Candidate.php\n";

// ---------------------------------------------------------------------------
// 12. Patch routes/web.php (add use statements + new routes)
// ---------------------------------------------------------------------------
$routesPath = $root . '/routes/web.php';
$routesContent = file_get_contents($routesPath);
if ($routesContent === false) {
    die_loud("Could not read $routesPath");
}

$routesOld = <<<'ROUTES_OLD_EOF'
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JobPostingController;
use App\Http\Controllers\JobPostingImportController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\InterviewScheduleController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\JobOfferController;
use App\Http\Controllers\TalentPoolController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\RankingController;

Route::get('/', function () {
    return redirect()->route('dashboard');
});
ROUTES_OLD_EOF;

$routesNew = <<<'ROUTES_NEW_EOF'
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CandidateAuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JobPostingController;
use App\Http\Controllers\JobPostingImportController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\InterviewScheduleController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\JobOfferController;
use App\Http\Controllers\TalentPoolController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\RankingController;

// Admin (HR staff) authentication
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Applicant portal authentication
Route::get('/portal/login', [CandidateAuthController::class, 'showLogin'])->name('portal.login');
Route::post('/portal/login', [CandidateAuthController::class, 'login'])->name('portal.login.attempt');
Route::get('/portal/register', [CandidateAuthController::class, 'showRegister'])->name('portal.register');
Route::post('/portal/register', [CandidateAuthController::class, 'register'])->name('portal.register.attempt');
Route::post('/portal/logout', [CandidateAuthController::class, 'logout'])->name('portal.logout');
Route::get('/portal/dashboard', [CandidateAuthController::class, 'dashboard'])->name('portal.dashboard')->middleware('auth:candidate');

Route::get('/', function () {
    return redirect()->route('dashboard');
});
ROUTES_NEW_EOF;

$newRoutesContent = apply_patch($routesContent, $routesOld, $routesNew, 'routes-auth-block');

backup_file($routesPath);
if (file_put_contents($routesPath, $newRoutesContent) === false) {
    die_loud("Could not write $routesPath");
}
echo "Updated routes/web.php\n";

echo "\nDone.\n";
echo "Next steps:\n";
echo "  1. Run: php artisan migrate\n";
echo "  2. Visit /register and create an HR staff account, confirm it logs you in and lands on /dashboard.\n";
echo "  3. Visit /portal/register and create an applicant account, confirm it logs you in and lands on /portal/dashboard.\n";
echo "  4. Test /logout and /portal/logout separately -- confirm logging out of one doesn't affect the other.\n";
echo "  5. NOTE: no existing HR routes are protected yet -- that's a deliberate follow-up step.\n";
echo "  6. Delete this script once you've confirmed everything works.\n";
