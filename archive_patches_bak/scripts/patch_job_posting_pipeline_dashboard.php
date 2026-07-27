<?php

/**
 * patch_job_posting_pipeline_dashboard.php
 *
 * WHAT THIS DOES:
 *   Rebuilds the job posting show page as a pipeline dashboard:
 *   - Vertical step tracker on the left (3 steps)
 *   - Content panel on the right changes per step
 *   - Step 1: Overview + Applications + Qualification Checking
 *   - Step 2: Open Ranking & Scheduling (panelists, interview schedule)
 *   - Step 3: Comparative Assessment & Results
 *   - Advancing a step updates the job posting status
 *
 *   Also removes 'screening' from the status pipeline:
 *   open → interview_scheduled → ranking → closed
 *
 * HOW TO RUN:
 *   php patch_job_posting_pipeline_dashboard.php    (from project root)
 *   php artisan migrate                             (removes screening from enum)
 *
 * DELETE this script after running.
 */

define('ROOT', __DIR__);

function backup(string $path): void {
    if (!file_exists($path)) return;
    $bak = $path . '.bak';
    $i = 2;
    while (file_exists($bak)) { $bak = $path . '.bak' . $i++; }
    copy($path, $bak);
    echo "  [bak] $bak\n";
}

function apply_patch(string $path, string $old, string $new, string $label): void {
    if (!file_exists($path)) { echo "\n❌ File not found: $path\n"; exit(1); }
    $content = file_get_contents($path);
    $count = substr_count($content, $old);
    if ($count === 0) { echo "\n❌ PATCH ABORTED — content not found in:\n  $path\nLabel: $label\n"; exit(1); }
    if ($count > 1)   { echo "\n❌ PATCH ABORTED — found $count times in:\n  $path\nLabel: $label\n"; exit(1); }
    backup($path);
    file_put_contents($path, str_replace($old, $new, $content));
    echo "  [ok ] $label\n";
}

function write_new(string $path, string $content, string $label): void {
    backup($path);
    file_put_contents($path, $content);
    echo "  [ok ] $label\n";
}

echo "\n=== patch_job_posting_pipeline_dashboard.php ===\n\n";

// ─── 1. Migration: remove 'screening' from job_postings.status enum ────────

echo "[1] Creating migration to remove screening from status enum...\n";

$migrationFile = ROOT . '/database/migrations/' . date('Y_m_d_His') . '_remove_screening_from_job_postings_status.php';

write_new($migrationFile, <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Move any postings stuck on 'screening' to 'interview_scheduled'
        DB::table('job_postings')->where('status', 'screening')->update(['status' => 'interview_scheduled']);

        Schema::table('job_postings', function (Blueprint $table) {
            $table->string('status', 30)->default('open')->change();
        });

        Schema::table('job_postings', function (Blueprint $table) {
            $table->enum('status', ['open', 'interview_scheduled', 'ranking', 'closed'])
                  ->default('open')
                  ->change();
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->string('status', 30)->default('open')->change();
        });
        Schema::table('job_postings', function (Blueprint $table) {
            $table->enum('status', ['open', 'screening', 'interview_scheduled', 'ranking', 'closed'])
                  ->default('open')
                  ->change();
        });
    }
};
PHP, 'Migration: remove screening from status enum');

// ─── 2. Update JobPostingController — status cascade without screening ──────

echo "\n[2] Patching JobPostingController — remove screening from cascade map...\n";

$controllerPath = ROOT . '/app/Http/Controllers/JobPostingController.php';

apply_patch(
    $controllerPath,
    "'status' => ['required', 'in:open,screening,interview_scheduled,ranking,closed'],",
    "'status' => ['required', 'in:open,interview_scheduled,ranking,closed'],",
    'Controller: remove screening from validation'
);

apply_patch(
    $controllerPath,
    <<<'PHP'
        $map = [
            'open'                 => 'submitted',
            'screening'            => 'screening',
            'interview_scheduled'  => 'interview_scheduled',
            'ranking'              => 'ranked',
            'closed'               => 'rejected',
        ];
PHP,
    <<<'PHP'
        $map = [
            'open'                => 'submitted',
            'interview_scheduled' => 'interview_scheduled',
            'ranking'             => 'ranked',
            'closed'              => 'rejected',
        ];
PHP,
    'Controller: remove screening from cascade map'
);

// Add advanceStep() method before hireApplicant()
apply_patch(
    $controllerPath,
    "    /**\n     * Mark one applicant as Hired, reject all others on the same posting,",
    <<<'PHP'
    /**
     * Advance the job posting to the next pipeline step.
     * Step 1 (open) → Step 2 (interview_scheduled) → Step 3 (ranking)
     * Cascades application statuses accordingly.
     */
    public function advanceStep(Request $request, $id)
    {
        $posting = JobPosting::findOrFail($id);

        $next = match($posting->status) {
            'open'                => 'interview_scheduled',
            'interview_scheduled' => 'ranking',
            'ranking'             => 'ranking', // already at last step before hire
            default               => $posting->status,
        };

        if ($next !== $posting->status) {
            $posting->update(['status' => $next]);
            $this->cascadeStatusToApplications($posting, $next);
        }

        return redirect()
            ->route('job-postings.show', $posting->id)
            ->with('success', 'Posting advanced to next stage.');
    }

    /**
     * Mark one applicant as Hired, reject all others on the same posting,
PHP,
    'Controller: add advanceStep() method'
);

// ─── 3. Add route for advanceStep ──────────────────────────────────────────

echo "\n[3] Adding advanceStep route to routes/web.php...\n";

$webPath = ROOT . '/routes/web.php';

apply_patch(
    $webPath,
    "// Mark one applicant as hired → rejects all others on same posting + closes posting\nRoute::post('/job-postings/{postingId}/hire/{applicationId}', [JobPostingController::class, 'hireApplicant'])->name('job-postings.hire');",
    "// Mark one applicant as hired → rejects all others on same posting + closes posting\nRoute::post('/job-postings/{postingId}/hire/{applicationId}', [JobPostingController::class, 'hireApplicant'])->name('job-postings.hire');\n// Advance posting to next pipeline step\nRoute::post('/job-postings/{id}/advance-step', [JobPostingController::class, 'advanceStep'])->name('job-postings.advance-step');",
    'web.php: add advanceStep route'
);

// ─── 4. Rebuild show.blade.php as pipeline dashboard ───────────────────────

echo "\n[4] Rebuilding show.blade.php as pipeline dashboard...\n";

$showPath = ROOT . '/resources/views/job-postings/show.blade.php';

$newShow = <<<'BLADE'
@extends('layouts.app')

@section('title', 'Job posting — ' . $posting->title)
@section('page-title', 'Job posting')

@section('content')

@php
    // Map posting status to step number
    $currentStep = match($posting->status) {
        'open'                => 1,
        'interview_scheduled' => 2,
        'ranking'             => 3,
        'closed'              => 3,
        default               => 1,
    };

    $steps = [
        1 => [
            'label'    => 'Overview & Qualification',
            'sublabel' => 'Review applicants and check qualifications',
            'icon'     => 'bi-person-check',
        ],
        2 => [
            'label'    => 'Open Ranking & Scheduling',
            'sublabel' => 'Schedule interviews and rank candidates',
            'icon'     => 'bi-calendar-event',
        ],
        3 => [
            'label'    => 'Assessment & Results',
            'sublabel' => 'Comparative assessment and final selection',
            'icon'     => 'bi-trophy',
        ],
    ];

    // Active tab within a step (from query string)
    $activeTab = request('tab', 'overview');
@endphp

@if (session('success'))
<div class="alert alert-success alert-dismissible fade show small py-2">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="d-flex gap-0" style="min-height: 80vh; align-items: flex-start;">

    {{-- ── Left: Vertical Step Tracker ───────────────────────────────────── --}}
    <div style="width: 260px; flex-shrink: 0; position: sticky; top: 80px;">
        <div class="card p-3 me-3">
            {{-- Posting title & status --}}
            <div class="mb-4">
                <div class="fw-semibold" style="font-size: 0.9rem; line-height: 1.3;">{{ $posting->title }}</div>
                <div class="text-muted small mt-1">
                    @if ($posting->salary_grade)
                        {{ Str::startsWith($posting->salary_grade, 'SG-') ? $posting->salary_grade : 'SG-' . $posting->salary_grade }}
                        &middot;
                    @endif
                    {{ $posting->employment_type ?? 'Regular' }}
                </div>
                @php
                    $statusColors = [
                        'open'                => 'success',
                        'interview_scheduled' => 'primary',
                        'ranking'             => 'warning',
                        'closed'              => 'dark',
                    ];
                    $statusLabels = [
                        'open'                => 'Open',
                        'interview_scheduled' => 'Interview',
                        'ranking'             => 'Ranking',
                        'closed'              => 'Closed',
                    ];
                @endphp
                <span class="badge text-bg-{{ $statusColors[$posting->status] ?? 'secondary' }} mt-2">
                    {{ $statusLabels[$posting->status] ?? ucfirst($posting->status) }}
                </span>
            </div>

            {{-- Step tracker --}}
            <div class="d-flex flex-column">
                @foreach ($steps as $num => $step)
                @php
                    $isDone    = $num < $currentStep;
                    $isActive  = $num === $currentStep;
                    $isPending = $num > $currentStep;
                @endphp
                <div class="d-flex gap-3 {{ !$loop->last ? 'mb-0' : '' }}">
                    {{-- Line + circle column --}}
                    <div class="d-flex flex-column align-items-center" style="width: 28px; flex-shrink: 0;">
                        {{-- Circle --}}
                        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width: 28px; height: 28px; font-size: 0.75rem;
                                    background: {{ $isDone ? '#198754' : ($isActive ? 'var(--hr-primary, #0d6efd)' : '#e9ecef') }};
                                    color: {{ $isDone || $isActive ? '#fff' : '#adb5bd' }};
                                    border: 2px solid {{ $isDone ? '#198754' : ($isActive ? 'var(--hr-primary, #0d6efd)' : '#dee2e6') }};">
                            @if ($isDone)
                                <i class="bi bi-check-lg"></i>
                            @else
                                {{ $num }}
                            @endif
                        </div>
                        {{-- Connector line --}}
                        @if (!$loop->last)
                        <div style="width: 2px; flex: 1; min-height: 36px;
                                    background: {{ $isDone ? '#198754' : '#dee2e6' }};
                                    margin: 3px 0;"></div>
                        @endif
                    </div>

                    {{-- Label column --}}
                    <div class="pb-4">
                        <div class="small fw-semibold {{ $isActive ? '' : ($isDone ? 'text-success' : 'text-muted') }}"
                             style="{{ $isActive ? 'color: var(--hr-primary, #0d6efd);' : '' }}">
                            {{ $step['label'] }}
                        </div>
                        <div class="text-muted" style="font-size: 0.7rem; line-height: 1.3;">
                            {{ $step['sublabel'] }}
                        </div>

                        {{-- Sub-tabs for active step --}}
                        @if ($isActive)
                        <div class="d-flex flex-column gap-1 mt-2">
                            @if ($num === 1)
                                <a href="{{ route('job-postings.show', $posting->id) }}?tab=overview"
                                   class="btn btn-sm text-start py-1 px-2 {{ $activeTab === 'overview' ? 'btn-primary' : 'btn-outline-secondary' }}"
                                   style="font-size: 0.72rem;">
                                   <i class="bi bi-info-circle me-1"></i> Overview
                                </a>
                                <a href="{{ route('job-postings.show', $posting->id) }}?tab=applicants"
                                   class="btn btn-sm text-start py-1 px-2 {{ $activeTab === 'applicants' ? 'btn-primary' : 'btn-outline-secondary' }}"
                                   style="font-size: 0.72rem;">
                                   <i class="bi bi-people me-1"></i> Applicants
                                   <span class="badge bg-secondary ms-1" style="font-size: 0.65rem;">{{ $applications->count() }}</span>
                                </a>
                            @elseif ($num === 2)
                                <a href="{{ route('job-postings.show', $posting->id) }}?tab=schedule"
                                   class="btn btn-sm text-start py-1 px-2 {{ $activeTab === 'schedule' ? 'btn-primary' : 'btn-outline-secondary' }}"
                                   style="font-size: 0.72rem;">
                                   <i class="bi bi-calendar-event me-1"></i> Schedule
                                </a>
                                <a href="{{ route('job-postings.show', $posting->id) }}?tab=panelists"
                                   class="btn btn-sm text-start py-1 px-2 {{ $activeTab === 'panelists' ? 'btn-primary' : 'btn-outline-secondary' }}"
                                   style="font-size: 0.72rem;">
                                   <i class="bi bi-people me-1"></i> Panel
                                </a>
                            @elseif ($num === 3)
                                <a href="{{ route('job-postings.show', $posting->id) }}?tab=assessment"
                                   class="btn btn-sm text-start py-1 px-2 {{ $activeTab === 'assessment' ? 'btn-primary' : 'btn-outline-secondary' }}"
                                   style="font-size: 0.72rem;">
                                   <i class="bi bi-bar-chart me-1"></i> Assessment
                                </a>
                                <a href="{{ route('job-postings.show', $posting->id) }}?tab=results"
                                   class="btn btn-sm text-start py-1 px-2 {{ $activeTab === 'results' ? 'btn-primary' : 'btn-outline-secondary' }}"
                                   style="font-size: 0.72rem;">
                                   <i class="bi bi-trophy me-1"></i> Results
                                </a>
                            @endif
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Advance step button --}}
            @if ($posting->status !== 'closed' && $currentStep < 3)
            <form method="POST" action="{{ route('job-postings.advance-step', $posting->id) }}"
                  onsubmit="return confirm('Advance this posting to the next stage? This will update all applicant statuses.')">
                @csrf
                <button type="submit" class="btn btn-sm w-100 mt-2"
                        style="background-color: var(--hr-primary); color: #fff;">
                    <i class="bi bi-arrow-right-circle me-1"></i>
                    Advance to Step {{ $currentStep + 1 }}
                </button>
            </form>
            @endif

            {{-- Edit link --}}
            <a href="{{ route('job-postings.edit', $posting->id) }}"
               class="btn btn-sm btn-outline-secondary w-100 mt-2">
                <i class="bi bi-pencil me-1"></i> Edit posting
            </a>
        </div>
    </div>

    {{-- ── Right: Content Panel ────────────────────────────────────────────── --}}
    <div style="flex: 1; min-width: 0;">

        {{-- ═══ STEP 1: Overview ════════════════════════════════════════════ --}}
        @if ($activeTab === 'overview')
        <div class="card mb-3">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h6 class="mb-0">Job overview</h6>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="text-muted small">Posted</div>
                        <div class="fw-medium">{{ $posting->posted_at ? \Carbon\Carbon::parse($posting->posted_at)->format('M d, Y') : '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Closes</div>
                        <div class="fw-medium">{{ $posting->closes_at ? \Carbon\Carbon::parse($posting->closes_at)->format('M d, Y') : '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Total vacancies</div>
                        <div class="fw-medium">{{ $locations->sum('vacancies') ?: $posting->vacancies }}</div>
                    </div>
                </div>

                @if ($locations->isNotEmpty())
                <div class="mb-3">
                    <div class="text-muted small mb-2">Places of assignment</div>
                    <table class="table table-sm table-bordered mb-0" style="font-size: 0.875rem;">
                        <thead class="table-light">
                            <tr><th>Place</th><th class="text-center" style="width:100px;">Vacancies</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($locations as $loc)
                            <tr>
                                <td>{{ $loc->place_of_assignment }}</td>
                                <td class="text-center">{{ $loc->vacancies }}</td>
                            </tr>
                            @endforeach
                            <tr class="table-light fw-medium">
                                <td class="text-end text-muted small">Total</td>
                                <td class="text-center">{{ $locations->sum('vacancies') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                @endif

                <hr>

                @if ($posting->duties_responsibilities)
                <div class="mb-3">
                    <div class="text-muted small mb-1">Duties and responsibilities</div>
                    <p class="mb-0 small">{{ $posting->duties_responsibilities }}</p>
                </div>
                @endif

                <div>
                    <div class="text-muted small mb-1">Qualification standards</div>
                    @if ($posting->qualification_education || $posting->qualification_training || $posting->qualification_experience || $posting->qualification_eligibility)
                    <div class="row g-2">
                        @foreach (['Education' => $posting->qualification_education, 'Training' => $posting->qualification_training, 'Experience' => $posting->qualification_experience, 'Eligibility' => $posting->qualification_eligibility] as $label => $value)
                        @if ($value)
                        <div class="col-md-6">
                            <div class="text-muted small">{{ $label }}</div>
                            <p class="mb-1 small">{{ $value }}</p>
                        </div>
                        @endif
                        @endforeach
                    </div>
                    @else
                    <p class="text-muted small mb-0">Not specified.</p>
                    @endif
                </div>

                @php $mandatoryList = $posting->mandatoryRequirementsList(); $additionalList = $posting->additionalRequirementsList(); @endphp
                @if (!empty($mandatoryList) || !empty($additionalList))
                <hr>
                <div class="text-muted small mb-2">Requirements</div>
                <div class="row g-3">
                    @if (!empty($mandatoryList))
                    <div class="col-md-6">
                        <div class="small fw-medium text-muted mb-1">Mandatory</div>
                        <ul class="small mb-0 ps-3">@foreach ($mandatoryList as $item)<li>{{ $item }}</li>@endforeach</ul>
                    </div>
                    @endif
                    @if (!empty($additionalList))
                    <div class="col-md-6">
                        <div class="small fw-medium text-muted mb-1">Additional</div>
                        <ul class="small mb-0 ps-3">@foreach ($additionalList as $item)<li>{{ $item }}</li>@endforeach</ul>
                    </div>
                    @endif
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- ═══ STEP 1: Applicants tab ══════════════════════════════════════ --}}
        @if ($activeTab === 'applicants')
        <div class="card">
            <div class="card-body p-4">
                <h6 class="mb-3">
                    Applicants
                    <span class="badge bg-secondary ms-1">{{ $applications->count() }}</span>
                </h6>
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Candidate</th>
                            <th>Applied</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($applications as $app)
                        <tr>
                            <td class="fw-medium">{{ $app->candidate->full_name }}</td>
                            <td class="small text-muted">{{ $app->applied_at ? \Carbon\Carbon::parse($app->applied_at)->format('M d, Y') : '—' }}</td>
                            <td>
                                @php
                                    $appColors = ['submitted'=>'secondary','interview_scheduled'=>'primary','ranked'=>'warning','hired'=>'success','rejected'=>'danger'];
                                @endphp
                                <span class="badge text-bg-{{ $appColors[$app->status] ?? 'secondary' }}">
                                    {{ str_replace('_', ' ', ucfirst($app->status)) }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('applications.show', $app->id) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted small py-4">No applications yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- ═══ STEP 2: Schedule tab ════════════════════════════════════════ --}}
        @if ($activeTab === 'schedule')
        <div class="card">
            <div class="card-body p-4">
                <h6 class="mb-3">Interview / Open Ranking Schedule</h6>
                @if ($schedules->isEmpty())
                <p class="text-muted small">No schedules yet for this posting.
                    <a href="{{ route('interviews.index') }}">Go to Scheduling</a> to add one.
                </p>
                @else
                <table class="table align-middle mb-0">
                    <thead>
                        <tr><th>Candidate</th><th>Date & Time</th><th>Type</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($schedules as $s)
                        <tr>
                            <td>{{ $s->application->candidate->full_name ?? '—' }}</td>
                            <td class="small">{{ $s->scheduled_at ? \Carbon\Carbon::parse($s->scheduled_at)->format('M d, Y g:i A') : '—' }}</td>
                            <td class="small">{{ $s->type ?? '—' }}</td>
                            <td><span class="badge text-bg-info">{{ $s->status ?? 'scheduled' }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>
        @endif

        {{-- ═══ STEP 2: Panelists tab ═══════════════════════════════════════ --}}
        @if ($activeTab === 'panelists')
        <div class="card">
            <div class="card-body p-4">
                <h6 class="mb-3">Interview Panel / Ranking Committee</h6>
                @if ($panelists->isEmpty())
                <p class="text-muted small">No panelists assigned.
                    <a href="{{ route('job-postings.edit', $posting->id) }}">Edit this posting</a> to assign panelists.
                </p>
                @else
                <ul class="list-group">
                    @foreach ($panelists as $p)
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                        <span class="small fw-medium">{{ $p->name }}</span>
                        @if ($p->pivot->is_available)
                            <span class="badge text-bg-success">Available</span>
                        @else
                            <span class="badge text-bg-secondary">Unavailable</span>
                        @endif
                    </li>
                    @endforeach
                </ul>
                @endif
            </div>
        </div>
        @endif

        {{-- ═══ STEP 3: Assessment tab ══════════════════════════════════════ --}}
        @if ($activeTab === 'assessment')
        <div class="card">
            <div class="card-body p-4">
                <h6 class="mb-3">Comparative Assessment</h6>
                <p class="text-muted small mb-0">
                    Assessment scores and criteria are managed in
                    <a href="{{ route('assessments.index') }}">Assessments</a>.
                    Ranked candidates for this posting will appear there.
                </p>
            </div>
        </div>
        @endif

        {{-- ═══ STEP 3: Results tab ═════════════════════════════════════════ --}}
        @if ($activeTab === 'results')
        <div class="card">
            <div class="card-body p-4">
                <h6 class="mb-3">Final Results</h6>
                <table class="table align-middle mb-0">
                    <thead>
                        <tr><th>Candidate</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($applications->whereIn('status', ['ranked', 'hired', 'rejected', 'offer_sent', 'offer_accepted']) as $app)
                        <tr>
                            <td class="fw-medium">{{ $app->candidate->full_name }}</td>
                            <td>
                                @php $appColors = ['ranked'=>'warning','hired'=>'success','rejected'=>'danger','offer_sent'=>'primary','offer_accepted'=>'success']; @endphp
                                <span class="badge text-bg-{{ $appColors[$app->status] ?? 'secondary' }}">
                                    {{ str_replace('_', ' ', ucfirst($app->status)) }}
                                </span>
                            </td>
                            <td class="text-end">
                                @if ($app->status === 'ranked' && $posting->status === 'ranking')
                                <form method="POST" action="{{ route('job-postings.hire', [$posting->id, $app->id]) }}"
                                      class="d-inline"
                                      onsubmit="return confirm('Mark {{ $app->candidate->full_name }} as hired? All other applicants will be rejected and this posting will close.')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success">Mark Hired</button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted small py-4">No ranked candidates yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

    </div>{{-- end right panel --}}
</div>{{-- end flex container --}}

@endsection
BLADE;

write_new($showPath, $newShow, 'show.blade.php: pipeline dashboard');

// ─── 5. Update JobPostingController@show to pass schedules ─────────────────

echo "\n[5] Patching JobPostingController@show to pass schedules...\n";

apply_patch(
    $controllerPath,
    <<<'PHP'
        $panelists = $posting->panelists()->orderBy('name')->get();
        $locations = $posting->locations()->get();

        return view('job-postings.show', compact('posting', 'applications', 'panelists', 'locations'));
PHP,
    <<<'PHP'
        $panelists = $posting->panelists()->orderBy('name')->get();
        $locations = $posting->locations()->get();

        // Schedules for all applications on this posting
        $schedules = \App\Models\InterviewSchedule::whereIn(
            'application_id',
            $applications->pluck('id')
        )->with('application.candidate')->orderBy('scheduled_at')->get();

        return view('job-postings.show', compact('posting', 'applications', 'panelists', 'locations', 'schedules'));
PHP,
    'Controller: show() passes schedules'
);

// ─── 6. Update index badge colors to remove screening ──────────────────────

echo "\n[6] Updating index.blade.php badge colors...\n";

$indexPath = ROOT . '/resources/views/job-postings/index.blade.php';

apply_patch(
    $indexPath,
    <<<'PHP'
                            $statusColors = [
                                'open'                => 'success',
                                'screening'           => 'info',
                                'interview_scheduled' => 'primary',
                                'ranking'             => 'warning',
                                'closed'              => 'dark',
                            ];
                            $statusLabels = [
                                'open'                => 'Open',
                                'screening'           => 'Screening',
                                'interview_scheduled' => 'Interview',
                                'ranking'             => 'Ranking',
                                'closed'              => 'Closed',
                            ];
PHP,
    <<<'PHP'
                            $statusColors = [
                                'open'                => 'success',
                                'interview_scheduled' => 'primary',
                                'ranking'             => 'warning',
                                'closed'              => 'dark',
                            ];
                            $statusLabels = [
                                'open'                => 'Open',
                                'interview_scheduled' => 'Interview',
                                'ranking'             => 'Ranking',
                                'closed'              => 'Closed',
                            ];
PHP,
    'index.blade.php: remove screening badge'
);

// ─── 7. Update form dropdown ────────────────────────────────────────────────

echo "\n[7] Updating form.blade.php status dropdown...\n";

$formPath = ROOT . '/resources/views/job-postings/form.blade.php';

apply_patch(
    $formPath,
    <<<'PHP'
                        $pipelineStages = [
                                'open'                => 'Open',
                                'screening'           => 'Screening',
                                'interview_scheduled' => 'Interview Scheduled',
                                'ranking'             => 'Ranking',
                                'closed'              => 'Closed',
                            ];
PHP,
    <<<'PHP'
                        $pipelineStages = [
                                'open'                => 'Open',
                                'interview_scheduled' => 'Interview Scheduled',
                                'ranking'             => 'Ranking',
                                'closed'              => 'Closed',
                            ];
PHP,
    'form.blade.php: remove screening from dropdown'
);

echo <<<TEXT

✅ All patches applied.

NEXT STEPS:
  1. php artisan migrate
     → Removes 'screening' from status enum, migrates existing records

  2. Open any job posting → the show page is now a pipeline dashboard:
     - Vertical step tracker on the left
     - Step 1: Overview tab + Applicants tab
     - Step 2: Schedule tab + Panel tab  
     - Step 3: Assessment tab + Results tab (with Mark Hired button)
     - "Advance to Step N" button moves the posting to the next stage

  3. Delete this script.

TEXT;
