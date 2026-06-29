<?php
/**
 * build_portal.php
 *
 * Builds the candidate-facing application portal:
 *   - app/Http/Controllers/PortalController.php
 *   - resources/views/portal/dashboard.blade.php  (replaces placeholder)
 *   - resources/views/portal/jobs/index.blade.php
 *   - resources/views/portal/jobs/show.blade.php
 *   - resources/views/portal/my-applications.blade.php
 *   - Patches routes/web.php (adds portal routes + /login route for candidates)
 *
 * Usage: place in project root, run: php build_portal.php
 * Then delete this script.
 */

function die_loud(string $msg): void {
    fwrite(STDERR, "\n[ABORTED] $msg\n\n");
    exit(1);
}

function backup_file(string $path): void {
    if (!file_exists($path)) die_loud("Expected file not found: $path");
    $backup = $path . '.bak';
    $n = 1;
    while (file_exists($backup)) { $n++; $backup = $path . '.bak' . $n; }
    if (!copy($path, $backup)) die_loud("Could not create backup at $backup");
    echo "Backed up $path -> " . basename($backup) . "\n";
}

function apply_patch(string $content, string $old, string $new, string $label): string {
    $count = substr_count($content, $old);
    if ($count !== 1) die_loud("Patch '$label' expected 1 match, found $count. File may have drifted.");
    return str_replace($old, $new, $content);
}

function write_file(string $path, string $content, string $label, bool $overwrite = false): void {
    if (!$overwrite && file_exists($path)) die_loud("$label already exists at $path. Script may have already run.");
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) die_loud("Could not create directory: $dir");
    if (file_put_contents($path, $content) === false) die_loud("Could not write $path");
    echo ($overwrite ? "Replaced" : "Created") . " $label at $path\n";
}

$root = __DIR__;

// ============================================================
// 1. PortalController
// ============================================================
write_file($root . '/app/Http/Controllers/PortalController.php', <<<'PHP'
<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\JobPosting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PortalController extends Controller
{
    private function candidate()
    {
        return Auth::guard('candidate')->user();
    }

    /**
     * Portal home / open job listings.
     */
    public function index()
    {
        $postings = JobPosting::where('status', 'open')
            ->orderByDesc('posted_at')
            ->get();

        // IDs the candidate has already applied to (to disable Apply button)
        $appliedIds = Application::where('candidate_id', $this->candidate()->id)
            ->pluck('job_posting_id')
            ->toArray();

        return view('portal.jobs.index', compact('postings', 'appliedIds'));
    }

    /**
     * Single job detail + apply form.
     */
    public function showJob(int $id)
    {
        $posting = JobPosting::where('status', 'open')->findOrFail($id);

        $alreadyApplied = Application::where('candidate_id', $this->candidate()->id)
            ->where('job_posting_id', $id)
            ->exists();

        return view('portal.jobs.show', compact('posting', 'alreadyApplied'));
    }

    /**
     * Submit application.
     */
    public function apply(Request $request, int $id)
    {
        $posting = JobPosting::where('status', 'open')->findOrFail($id);

        $candidate = $this->candidate();

        // Prevent duplicate
        $exists = Application::where('candidate_id', $candidate->id)
            ->where('job_posting_id', $id)
            ->exists();

        if ($exists) {
            return redirect()
                ->route('portal.jobs.show', $id)
                ->with('error', 'You have already applied for this position.');
        }

        Application::create([
            'candidate_id'   => $candidate->id,
            'job_posting_id' => $posting->id,
            'status'         => 'submitted',
            'applied_at'     => now(),
            'notes'          => $request->input('cover_note'),
        ]);

        return redirect()
            ->route('portal.my-applications')
            ->with('success', 'Application submitted successfully for ' . $posting->title . '.');
    }

    /**
     * Candidate's submitted applications with status tracking.
     */
    public function myApplications()
    {
        $applications = Application::with('jobPosting')
            ->where('candidate_id', $this->candidate()->id)
            ->latest('applied_at')
            ->get();

        return view('portal.my-applications', compact('applications'));
    }
}
PHP, 'PortalController');

// ============================================================
// 2. portal/dashboard.blade.php  (replace placeholder)
// ============================================================
write_file($root . '/resources/views/portal/dashboard.blade.php', <<<'BLADE'
@extends('layouts.auth')

@section('title', 'Applicant Dashboard')
@section('brand', 'Applicant Portal')

@section('content')
<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h5 class="mb-0">Welcome, {{ auth()->guard('candidate')->user()->full_name }}</h5>
        <small class="text-muted">{{ auth()->guard('candidate')->user()->email }}</small>
    </div>
    <form action="{{ route('portal.logout') }}" method="POST" class="mb-0">
        @csrf
        <button type="submit" class="btn btn-outline-secondary btn-sm">Log out</button>
    </form>
</div>

<hr>

<div class="d-grid gap-2">
    <a href="{{ route('portal.jobs.index') }}" class="btn btn-hr-primary">
        <i class="bi bi-briefcase me-1"></i> Browse Open Positions
    </a>
    <a href="{{ route('portal.my-applications') }}" class="btn btn-outline-secondary">
        <i class="bi bi-file-earmark-text me-1"></i> My Applications
    </a>
</div>
@endsection
BLADE, 'portal/dashboard', overwrite: true);

// ============================================================
// 3. portal/jobs/index.blade.php
// ============================================================
write_file($root . '/resources/views/portal/jobs/index.blade.php', <<<'BLADE'
@extends('layouts.portal')

@section('title', 'Open Positions')

@section('content')
<div class="mb-4">
    <h4 class="fw-bold mb-0">Open Positions</h4>
    <p class="text-muted small mb-0">Click a position to view details and apply.</p>
</div>

@if (session('success'))
    <div class="alert alert-success small">{{ session('success') }}</div>
@endif

@if ($postings->isEmpty())
    <div class="text-center py-5 text-muted">
        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
        No open positions at this time. Check back later.
    </div>
@else
    <div class="row g-3">
        @foreach ($postings as $posting)
        <div class="col-12">
            <div class="card border shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold mb-1">{{ $posting->title }}</h6>
                            <div class="text-muted small">
                                @if ($posting->place_of_assignment)
                                    <i class="bi bi-geo-alt me-1"></i>{{ $posting->place_of_assignment }}
                                @endif
                                @if ($posting->salary_grade)
                                    &nbsp;&bull;&nbsp;<i class="bi bi-cash me-1"></i>Salary Grade {{ $posting->salary_grade }}
                                @endif
                                @if ($posting->employment_type)
                                    &nbsp;&bull;&nbsp;{{ $posting->employment_type }}
                                @endif
                            </div>
                            @if ($posting->closes_at)
                                <div class="small mt-1">
                                    <i class="bi bi-calendar-x text-danger me-1"></i>
                                    Closes {{ \Carbon\Carbon::parse($posting->closes_at)->format('M d, Y') }}
                                </div>
                            @endif
                        </div>
                        <div class="flex-shrink-0">
                            @if (in_array($posting->id, $appliedIds))
                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Applied</span>
                            @else
                                <a href="{{ route('portal.jobs.show', $posting->id) }}" class="btn btn-hr-primary btn-sm">
                                    View & Apply
                                </a>
                            @endif
                        </div>
                    </div>
                    @if ($posting->description)
                        <p class="small text-muted mt-2 mb-0" style="line-height:1.5;">
                            {{ Str::limit($posting->description, 160) }}
                        </p>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
@endif
@endsection
BLADE, 'portal/jobs/index');

// ============================================================
// 4. portal/jobs/show.blade.php
// ============================================================
write_file($root . '/resources/views/portal/jobs/show.blade.php', <<<'BLADE'
@extends('layouts.portal')

@section('title', $posting->title)

@section('content')
<a href="{{ route('portal.jobs.index') }}" class="btn btn-link ps-0 mb-3 text-decoration-none small">
    <i class="bi bi-arrow-left me-1"></i> Back to all positions
</a>

@if (session('success'))
    <div class="alert alert-success small">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger small">{{ session('error') }}</div>
@endif

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <h4 class="fw-bold mb-1">{{ $posting->title }}</h4>
        <div class="text-muted small mb-3 d-flex flex-wrap gap-3">
            @if ($posting->place_of_assignment)
                <span><i class="bi bi-geo-alt me-1"></i>{{ $posting->place_of_assignment }}</span>
            @endif
            @if ($posting->salary_grade)
                <span><i class="bi bi-cash me-1"></i>Salary Grade {{ $posting->salary_grade }}</span>
            @endif
            @if ($posting->employment_type)
                <span><i class="bi bi-briefcase me-1"></i>{{ $posting->employment_type }}</span>
            @endif
            @if ($posting->vacancies)
                <span><i class="bi bi-people me-1"></i>{{ $posting->vacancies }} {{ Str::plural('vacancy', $posting->vacancies) }}</span>
            @endif
        </div>

        @if ($posting->description)
            <h6 class="fw-semibold">Description</h6>
            <p class="small">{{ $posting->description }}</p>
        @endif

        @if ($posting->duties_responsibilities)
            <h6 class="fw-semibold">Duties & Responsibilities</h6>
            <p class="small" style="white-space: pre-line;">{{ $posting->duties_responsibilities }}</p>
        @endif

        {{-- Qualifications --}}
        @php
            $quals = array_filter([
                'Education'   => $posting->qualification_education,
                'Training'    => $posting->qualification_training,
                'Experience'  => $posting->qualification_experience,
                'Eligibility' => $posting->qualification_eligibility,
            ]);
        @endphp
        @if ($quals)
            <h6 class="fw-semibold mt-3">Qualifications</h6>
            <table class="table table-sm small">
                @foreach ($quals as $label => $value)
                <tr>
                    <th class="text-muted fw-medium pe-3" style="width:130px;white-space:nowrap;">{{ $label }}</th>
                    <td>{{ $value }}</td>
                </tr>
                @endforeach
            </table>
        @endif

        @if ($posting->mandatory_requirements)
            <h6 class="fw-semibold mt-3">Mandatory Requirements</h6>
            <ol class="small ps-3">
                @foreach (array_filter(array_map('trim', explode("\n", $posting->mandatory_requirements))) as $req)
                    <li class="mb-1">{{ $req }}</li>
                @endforeach
            </ol>
        @endif

        @if ($posting->closes_at)
            <div class="alert alert-warning small mt-3 mb-0 py-2">
                <i class="bi bi-calendar-x me-1"></i>
                Application deadline: <strong>{{ \Carbon\Carbon::parse($posting->closes_at)->format('F d, Y') }}</strong>
            </div>
        @endif
    </div>
</div>

{{-- Apply section --}}
@if ($alreadyApplied)
    <div class="alert alert-success">
        <i class="bi bi-check-circle-fill me-2"></i>
        You have already applied for this position.
        <a href="{{ route('portal.my-applications') }}" class="alert-link ms-1">View my applications →</a>
    </div>
@else
    <div class="card shadow-sm">
        <div class="card-body">
            <h6 class="fw-semibold mb-3">Apply for this position</h6>
            <form action="{{ route('portal.apply', $posting->id) }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label small fw-medium">Cover note <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea name="cover_note" rows="4" class="form-control"
                        placeholder="Briefly describe why you're a good fit for this role...">{{ old('cover_note') }}</textarea>
                </div>
                <div class="alert alert-info small py-2">
                    <i class="bi bi-info-circle me-1"></i>
                    Document uploads (resume, TOR, etc.) will be available after submitting your application.
                </div>
                <button type="submit" class="btn btn-hr-primary w-100">
                    <i class="bi bi-send me-1"></i> Submit Application
                </button>
            </form>
        </div>
    </div>
@endif
@endsection
BLADE, 'portal/jobs/show');

// ============================================================
// 5. portal/my-applications.blade.php
// ============================================================
write_file($root . '/resources/views/portal/my-applications.blade.php', <<<'BLADE'
@extends('layouts.portal')

@section('title', 'My Applications')

@section('content')
<div class="mb-4">
    <h4 class="fw-bold mb-0">My Applications</h4>
    <p class="text-muted small mb-0">Track the status of your submitted applications.</p>
</div>

@if (session('success'))
    <div class="alert alert-success small">{{ session('success') }}</div>
@endif

@if ($applications->isEmpty())
    <div class="text-center py-5 text-muted">
        <i class="bi bi-file-earmark-x fs-1 d-block mb-2"></i>
        You haven't submitted any applications yet.
        <div class="mt-3">
            <a href="{{ route('portal.jobs.index') }}" class="btn btn-hr-primary btn-sm">Browse open positions</a>
        </div>
    </div>
@else
    @php
        $statusColors = [
            'submitted'            => 'secondary',
            'screening'            => 'info',
            'shortlisted'          => 'primary',
            'interview_scheduled'  => 'warning',
            'assessed'             => 'warning',
            'ranked'               => 'primary',
            'ranking_sent'         => 'primary',
            'offer_sent'           => 'success',
            'offer_accepted'       => 'success',
            'offer_declined'       => 'danger',
            'hired'                => 'success',
            'rejected'             => 'danger',
        ];
        $statusSteps = [
            'submitted'           => 1,
            'screening'           => 2,
            'shortlisted'         => 3,
            'interview_scheduled' => 4,
            'assessed'            => 5,
            'ranked'              => 5,
            'ranking_sent'        => 5,
            'offer_sent'          => 6,
            'offer_accepted'      => 7,
            'offer_declined'      => 7,
            'hired'               => 7,
            'rejected'            => 7,
        ];
    @endphp

    <div class="d-flex flex-column gap-3">
        @foreach ($applications as $app)
        @php
            $color = $statusColors[$app->status] ?? 'secondary';
            $step  = $statusSteps[$app->status] ?? 1;
            $label = ucwords(str_replace('_', ' ', $app->status));
        @endphp
        <div class="card shadow-sm border">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                    <div>
                        <h6 class="fw-bold mb-0">{{ $app->jobPosting->title ?? 'Position' }}</h6>
                        @if ($app->jobPosting->place_of_assignment)
                            <small class="text-muted">
                                <i class="bi bi-geo-alt me-1"></i>{{ $app->jobPosting->place_of_assignment }}
                            </small>
                        @endif
                    </div>
                    <span class="badge bg-{{ $color }} text-white">{{ $label }}</span>
                </div>

                {{-- Progress bar --}}
                @php $pct = round(($step / 7) * 100); @endphp
                <div class="progress mb-2" style="height:6px;">
                    <div class="progress-bar bg-{{ in_array($app->status, ['rejected','offer_declined']) ? 'danger' : 'success' }}"
                         style="width: {{ $pct }}%"></div>
                </div>
                <div class="d-flex justify-content-between" style="font-size:0.7rem;color:#888;">
                    <span>Submitted</span>
                    <span>Screening</span>
                    <span>Shortlisted</span>
                    <span>Interview</span>
                    <span>Assessment</span>
                    <span>Offer</span>
                    <span>Final</span>
                </div>

                @if ($app->applied_at)
                    <div class="text-muted mt-2" style="font-size:0.75rem;">
                        Applied {{ \Carbon\Carbon::parse($app->applied_at)->format('M d, Y') }}
                    </div>
                @endif

                @if ($app->notes)
                    <div class="mt-2 small text-muted fst-italic">"{{ Str::limit($app->notes, 100) }}"</div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
@endif
@endsection
BLADE, 'portal/my-applications');

// ============================================================
// 6. layouts/portal.blade.php  (full portal layout with sidebar)
// ============================================================
write_file($root . '/resources/views/layouts/portal.blade.php', <<<'BLADE'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Applicant Portal') — HR System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        :root {
            --hr-primary: #2f4858;
            --hr-primary-dark: #233843;
            --hr-accent: #3f7d8c;
            --hr-bg: #f4f6f7;
        }
        body { background: var(--hr-bg); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .portal-navbar {
            background: var(--hr-primary);
            color: #fff;
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .portal-navbar a { color: rgba(255,255,255,0.85); text-decoration: none; font-size: 0.875rem; }
        .portal-navbar a:hover { color: #fff; }
        .portal-sidebar {
            width: 220px;
            min-height: calc(100vh - 52px);
            background: #fff;
            border-right: 1px solid #e3e8ec;
            padding: 1.25rem 0;
            flex-shrink: 0;
        }
        .portal-sidebar .nav-link {
            color: #4a5568;
            font-size: 0.875rem;
            padding: 0.5rem 1.25rem;
            border-radius: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .portal-sidebar .nav-link:hover,
        .portal-sidebar .nav-link.active {
            background: #f0f4f6;
            color: var(--hr-primary);
            font-weight: 600;
        }
        .portal-main { flex: 1; padding: 1.75rem; max-width: 900px; }
        .btn-hr-primary { background: var(--hr-primary); color: #fff; }
        .btn-hr-primary:hover { background: var(--hr-primary-dark); color: #fff; }
    </style>
    @stack('styles')
</head>
<body>
    {{-- Top navbar --}}
    <div class="portal-navbar">
        <span class="fw-semibold"><i class="bi bi-people-fill me-2"></i>Applicant Portal</span>
        <div class="d-flex align-items-center gap-3">
            <span style="font-size:0.8rem;opacity:.75;">{{ auth()->guard('candidate')->user()->full_name }}</span>
            <form action="{{ route('portal.logout') }}" method="POST" class="m-0">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-light py-0 px-2" style="font-size:0.8rem;">Log out</button>
            </form>
        </div>
    </div>

    <div class="d-flex">
        {{-- Sidebar --}}
        <nav class="portal-sidebar d-none d-md-block">
            <a href="{{ route('portal.dashboard') }}"
               class="nav-link {{ request()->routeIs('portal.dashboard') ? 'active' : '' }}">
                <i class="bi bi-house"></i> Dashboard
            </a>
            <a href="{{ route('portal.jobs.index') }}"
               class="nav-link {{ request()->routeIs('portal.jobs.*') ? 'active' : '' }}">
                <i class="bi bi-briefcase"></i> Open Positions
            </a>
            <a href="{{ route('portal.my-applications') }}"
               class="nav-link {{ request()->routeIs('portal.my-applications') ? 'active' : '' }}">
                <i class="bi bi-file-earmark-text"></i> My Applications
            </a>
        </nav>

        {{-- Main content --}}
        <main class="portal-main">
            @if ($errors->any())
                <div class="alert alert-danger small mb-3">
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @yield('content')
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
BLADE, 'layouts/portal');

// ============================================================
// 7. Patch routes/web.php
// ============================================================
$routesPath = $root . '/routes/web.php';
$routesContent = file_get_contents($routesPath);
if ($routesContent === false) die_loud("Could not read routes/web.php");

// Add PortalController use statement
$routesContent = apply_patch($routesContent,
    "use App\\Http\\Controllers\\CandidateAuthController;\n",
    "use App\\Http\\Controllers\\CandidateAuthController;\nuse App\\Http\\Controllers\\PortalController;\n",
    'add PortalController use'
);

// Replace the existing portal auth block to add /login route and portal routes
$routesContent = apply_patch($routesContent,
    <<<'OLD'
// Applicant portal authentication
Route::get('/portal/register', [CandidateAuthController::class, 'showRegister'])->name('portal.register');
Route::post('/portal/register', [CandidateAuthController::class, 'register'])->name('portal.register.attempt');
Route::post('/portal/logout', [CandidateAuthController::class, 'logout'])->name('portal.logout');
Route::get('/portal/dashboard', [CandidateAuthController::class, 'dashboard'])->name('portal.dashboard')->middleware('auth:candidate');
OLD,
    <<<'NEW'
// Applicant portal authentication (guest)
Route::get('/portal/login', [AuthController::class, 'showLogin'])->name('portal.login');
Route::get('/portal/register', [CandidateAuthController::class, 'showRegister'])->name('portal.register');
Route::post('/portal/register', [CandidateAuthController::class, 'register'])->name('portal.register.attempt');
Route::post('/portal/logout', [CandidateAuthController::class, 'logout'])->name('portal.logout');

// Applicant portal (authenticated candidates)
Route::middleware('auth:candidate')->prefix('portal')->name('portal.')->group(function () {
    Route::get('/dashboard', [CandidateAuthController::class, 'dashboard'])->name('dashboard');
    Route::get('/jobs', [PortalController::class, 'index'])->name('jobs.index');
    Route::get('/jobs/{id}', [PortalController::class, 'showJob'])->name('jobs.show');
    Route::post('/jobs/{id}/apply', [PortalController::class, 'apply'])->name('apply');
    Route::get('/my-applications', [PortalController::class, 'myApplications'])->name('my-applications');
});
NEW,
    'portal route block'
);

backup_file($routesPath);
if (file_put_contents($routesPath, $routesContent) === false) die_loud("Could not write routes/web.php");
echo "Updated routes/web.php\n";

echo "\n✅ Done! Next steps:\n";
echo "  1. Drop this script from project root.\n";
echo "  2. Make sure your Application model has \$fillable that includes 'notes'.\n";
echo "     If not: add 'notes' to \$fillable in app/Models/Application.php\n";
echo "  3. If 'notes' column doesn't exist on applications table, add a migration:\n";
echo "     php artisan make:migration add_notes_to_applications_table\n";
echo "     Schema::table('applications', fn(\$t) => \$t->text('notes')->nullable()->after('status'));\n";
echo "  4. Log in as a candidate → /portal/dashboard — should now show the real dashboard.\n";
echo "  5. Test: Browse jobs → apply → My Applications (check progress bar).\n";
