<?php

/**
 * patch_pipeline_dashboard.php
 *
 * WHAT THIS DOES:
 *   Replaces the current job posting show page with a full pipeline dashboard.
 *   URL stays at /job-postings/{id} (same route, enhanced view).
 *
 *   PIPELINE STEPS:
 *   Step 1 — Overview & Qualification Checking  → status: open
 *   Step 2 — Open Ranking & Scheduling          → status: interview_scheduled
 *   Step 3 — Comparative Assessment & Results   → status: ranking
 *   Hired  → status: closed
 *
 *   LAYOUT:
 *   - Vertical step tracker on the left (sticky)
 *   - Content panels on the right (one per step, shown/hidden by JS)
 *   - "Advance to next step" button auto-updates posting status via AJAX
 *
 *   STEP 1 CONTENT:
 *   - Job details (title, SG, locations, qualifications, duties, requirements)
 *   - Applicant list with qualification check (Pass/Fail toggle per applicant)
 *   - Panelist list (no availability badge — just names, removable)
 *
 *   STEP 2 CONTENT:
 *   - Interview/exam scheduling (inline from interviews/index logic)
 *   - Panelist assignment for this posting
 *
 *   STEP 3 CONTENT:
 *   - Assessment criteria management
 *   - Ranked candidates table with score editing
 *   - Send notifications
 *
 *   CHANGES:
 *   1. JobPostingController::show() — passes all data needed for all 3 steps
 *   2. New route: POST /job-postings/{id}/advance — advances pipeline step
 *   3. resources/views/job-postings/show.blade.php — full pipeline dashboard
 *   4. routes/web.php — adds advance route
 *
 * HOW TO RUN:
 *   php patch_pipeline_dashboard.php    (from project root)
 *   No migration needed.
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
    if ($count === 0) {
        echo "\n❌ PATCH ABORTED — content not found in $path\nLabel: $label\nSearched for:\n---\n$old\n---\n";
        exit(1);
    }
    if ($count > 1) {
        echo "\n❌ PATCH ABORTED — pattern found $count times (expected 1) in $path\nLabel: $label\n";
        exit(1);
    }
    backup($path);
    file_put_contents($path, str_replace($old, $new, $content));
    echo "  [ok ] $label\n";
}

function write_file(string $path, string $content, string $label): void {
    backup($path);
    file_put_contents($path, $content);
    echo "  [ok ] $label\n";
}

echo "\n=== patch_pipeline_dashboard.php ===\n\n";

// ─── 1. JobPostingController::show() ──────────────────────────────────────

echo "[1] Patching JobPostingController::show()...\n";

$controllerPath = ROOT . '/app/Http/Controllers/JobPostingController.php';

// Add missing use statements
$controllerContent = file_get_contents($controllerPath);
if (strpos($controllerContent, 'use App\Models\AssessmentCriterion;') === false) {
    apply_patch(
        $controllerPath,
        'use App\Models\JobPosting;',
        "use App\Models\AssessmentCriterion;\nuse App\Models\Application;\nuse App\Models\InterviewSchedule;\nuse App\Models\JobPosting;",
        'JobPostingController: add use statements'
    );
}

// Replace show() method
apply_patch(
    $controllerPath,
    '    public function show($id)
    {
        $posting      = JobPosting::findOrFail($id);
        $locations    = $posting->locations()->get();
        $panelists    = $posting->panelists()->get();
        $applications = $posting->applications()->with(\'candidate\')->get();

        return view(\'job-postings.show\', compact(\'posting\', \'locations\', \'panelists\', \'applications\'));
    }',
    '    public function show($id)
    {
        $posting      = JobPosting::with([\'locations\', \'panelists\', \'assessmentCriteria\'])->findOrFail($id);
        $locations    = $posting->locations;
        $panelists    = $posting->panelists;
        $applications = $posting->applications()->with([\'candidate\', \'assessments\'])->get();

        // Step 2 — interview schedules for this posting\'s applications
        $applicationIds = $applications->pluck(\'id\');
        $schedules = InterviewSchedule::with([\'application.candidate\', \'panelists\'])
            ->whereIn(\'application_id\', $applicationIds)
            ->orderBy(\'scheduled_at\', \'desc\')
            ->get();

        // Step 3 — assessment criteria + ranking
        $criteria    = $posting->assessmentCriteria()->orderBy(\'id\')->get();
        $usedWeight  = $criteria->sum(\'weight_percentage\');
        $remainingWeight = max(0, 100 - $usedWeight);

        $rankedCandidates = $applications->map(function ($app) use ($criteria) {
            $scores = [];
            $total  = 0;
            foreach ($criteria as $c) {
                $assessment = $app->assessments->firstWhere(\'assessment_criteria_id\', $c->id);
                $score = $assessment ? (float) $assessment->score : null;
                $scores[$c->id] = $score;
                if ($score !== null) $total += $score;
            }
            return (object) [
                \'application_id\'   => $app->id,
                \'candidate\'        => $app->candidate,
                \'candidate_name\'   => $app->candidate?->full_name ?? \'Unknown\',
                \'scores\'           => $scores,
                \'total_score\'      => $total,
                \'notification_sent\'=> $app->status === \'ranking_sent\',
            ];
        })->sortByDesc(\'total_score\')->values()->map(function ($c, $i) use ($applications) {
            $c->rank   = $i + 1;
            $c->passed = $c->total_score >= 75;
            $c->total  = $applications->count();
            return $c;
        });

        // Current pipeline step derived from status
        $stepMap = [
            \'open\'                 => 1,
            \'interview_scheduled\'  => 2,
            \'ranking\'              => 3,
            \'closed\'               => 3,
        ];
        $currentStep = $stepMap[$posting->status] ?? 1;

        return view(\'job-postings.show\', compact(
            \'posting\', \'locations\', \'panelists\', \'applications\',
            \'schedules\', \'criteria\', \'usedWeight\', \'remainingWeight\',
            \'rankedCandidates\', \'currentStep\'
        ));
    }

    /**
     * POST /job-postings/{id}/advance
     * Advances the posting to the next pipeline step and updates status.
     */
    public function advance(Request $request, $id)
    {
        $posting = JobPosting::findOrFail($id);

        $transitions = [
            \'open\'                => \'interview_scheduled\',
            \'interview_scheduled\' => \'ranking\',
            \'ranking\'             => \'closed\',
        ];

        $nextStatus = $transitions[$posting->status] ?? null;

        if ($nextStatus) {
            $posting->update([\'status\' => $nextStatus]);
        }

        if ($request->expectsJson()) {
            return response()->json([\'status\' => $posting->status, \'ok\' => true]);
        }

        return redirect()->route(\'job-postings.show\', $posting->id)
            ->with(\'success\', \'Posting advanced to next stage.\');
    }',
    'JobPostingController: enhanced show() + advance() method'
);

// ─── 2. routes/web.php — advance route ────────────────────────────────────

echo "\n[2] Adding advance route to routes/web.php...\n";

$webPath = ROOT . '/routes/web.php';
$webContent = file_get_contents($webPath);

if (strpos($webContent, 'job-postings.advance') === false) {
    apply_patch(
        $webPath,
        "Route::delete('/job-postings/{id}', [JobPostingController::class, 'destroy'])->name('job-postings.destroy');",
        "Route::delete('/job-postings/{id}', [JobPostingController::class, 'destroy'])->name('job-postings.destroy');
Route::post('/job-postings/{id}/advance', [JobPostingController::class, 'advance'])->name('job-postings.advance');",
        'web.php: add job-postings.advance route'
    );
}

// ─── 3. show.blade.php — full pipeline dashboard ──────────────────────────

echo "\n[3] Writing pipeline dashboard view...\n";

$showPath = ROOT . '/resources/views/job-postings/show.blade.php';

write_file($showPath, <<<'BLADE'
@extends('layouts.app')

@section('title', $posting->title . ' — Pipeline')
@section('page-title', 'Job posting pipeline')

@section('content')
@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show small py-2">
        {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@php
    $sg = $posting->salary_grade
        ? (Str::startsWith($posting->salary_grade, 'SG-') ? $posting->salary_grade : 'SG-' . $posting->salary_grade)
        : null;

    $steps = [
        1 => ['label' => 'Overview & Qualification Checking', 'status' => 'open',                'icon' => 'bi-clipboard-check'],
        2 => ['label' => 'Open Ranking & Scheduling',         'status' => 'interview_scheduled', 'icon' => 'bi-calendar-event'],
        3 => ['label' => 'Assessment & Results',              'status' => 'ranking',             'icon' => 'bi-bar-chart-line'],
    ];

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

<div class="d-flex gap-1 align-items-center mb-3 small text-muted">
    <a href="{{ route('job-postings.index') }}" class="text-decoration-none text-muted">Job postings</a>
    <i class="bi bi-chevron-right" style="font-size: 0.7rem;"></i>
    <span>{{ $posting->title }}</span>
</div>

<div class="row g-3">
    {{-- ── Left: vertical step tracker ─────────────────────────────────── --}}
    <div class="col-md-3">
        <div class="card" style="position: sticky; top: 80px;">
            <div class="card-body p-3">
                <div class="fw-semibold mb-1" style="font-size: 0.95rem;">{{ $posting->title }}</div>
                @if ($sg)<div class="text-muted small mb-1">{{ $sg }} &middot; {{ $posting->employment_type }}</div>@endif
                <span class="badge text-bg-{{ $statusColors[$posting->status] ?? 'secondary' }} mb-3">
                    {{ $statusLabels[$posting->status] ?? ucfirst($posting->status) }}
                </span>

                {{-- Step list --}}
                <div class="d-flex flex-column gap-0" id="stepTracker">
                    @foreach ($steps as $num => $step)
                    @php
                        $isDone    = $currentStep > $num;
                        $isActive  = $currentStep === $num;
                        $isLocked  = $currentStep < $num;
                    @endphp
                    <div class="step-item d-flex align-items-start gap-2 py-2 px-2 rounded
                            {{ $isActive ? 'bg-primary bg-opacity-10' : '' }}"
                         style="cursor: {{ $isLocked ? 'default' : 'pointer' }}; border-left: 3px solid {{ $isActive ? 'var(--hr-primary, #0d6efd)' : ($isDone ? '#198754' : '#dee2e6') }};"
                         data-step="{{ $num }}"
                         onclick="{{ $isLocked ? '' : 'switchStep(' . $num . ')' }}">
                        <div class="flex-shrink-0 mt-1" style="width: 20px; height: 20px; border-radius: 50%;
                            background: {{ $isDone ? '#198754' : ($isActive ? 'var(--hr-primary, #0d6efd)' : '#dee2e6') }};
                            display: flex; align-items: center; justify-content: center;">
                            @if ($isDone)
                                <i class="bi bi-check text-white" style="font-size: 0.7rem;"></i>
                            @else
                                <span style="font-size: 0.6rem; color: {{ $isActive ? '#fff' : '#6c757d' }}; font-weight: 600;">{{ $num }}</span>
                            @endif
                        </div>
                        <div>
                            <div class="small fw-medium {{ $isActive ? 'text-primary' : ($isDone ? 'text-success' : 'text-muted') }}">
                                {{ $step['label'] }}
                            </div>
                        </div>
                    </div>
                    @if ($num < 3)
                    <div style="width: 3px; height: 16px; background: {{ $currentStep > $num ? '#198754' : '#dee2e6' }}; margin-left: calc(0.5rem + 10px);"></div>
                    @endif
                    @endforeach
                </div>

                {{-- Advance button --}}
                @if ($posting->status !== 'closed')
                <div class="mt-3">
                    <button id="advanceBtn" class="btn btn-sm w-100"
                        style="background-color: var(--hr-primary); color: #fff;"
                        onclick="advanceStep()">
                        @if ($posting->status === 'open')
                            <i class="bi bi-arrow-right me-1"></i> Move to Scheduling
                        @elseif ($posting->status === 'interview_scheduled')
                            <i class="bi bi-arrow-right me-1"></i> Move to Assessment
                        @elseif ($posting->status === 'ranking')
                            <i class="bi bi-check-lg me-1"></i> Close Posting
                        @endif
                    </button>
                </div>
                @endif

                <div class="mt-3 pt-3 border-top">
                    <a href="{{ route('job-postings.edit', $posting->id) }}" class="btn btn-sm btn-outline-secondary w-100">
                        <i class="bi bi-pencil me-1"></i> Edit posting
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Right: step content panels ───────────────────────────────────── --}}
    <div class="col-md-9">

        {{-- ══ STEP 1: Overview & Qualification Checking ═════════════════ --}}
        <div class="step-panel" id="panel-1">

            {{-- Job details card --}}
            <div class="card mb-3">
                <div class="card-body p-4">
                    <h6 class="mb-3">Posting details</h6>

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
                        <table class="table table-sm table-bordered mb-0" style="font-size: 0.85rem;">
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

                    @if ($posting->duties_responsibilities)
                    <div class="mb-3">
                        <div class="text-muted small mb-1">Duties and responsibilities</div>
                        <p class="small mb-0">{{ $posting->duties_responsibilities }}</p>
                    </div>
                    @endif

                    @if ($posting->qualification_education || $posting->qualification_training || $posting->qualification_experience || $posting->qualification_eligibility)
                    <div class="mb-0">
                        <div class="text-muted small mb-2">Qualification standards</div>
                        <div class="row g-2">
                            @foreach ([
                                'Education'   => $posting->qualification_education,
                                'Training'    => $posting->qualification_training,
                                'Experience'  => $posting->qualification_experience,
                                'Eligibility' => $posting->qualification_eligibility,
                            ] as $label => $value)
                            @if ($value)
                            <div class="col-md-6">
                                <div class="text-muted" style="font-size:0.75rem;">{{ $label }}</div>
                                <p class="small mb-1">{{ $value }}</p>
                            </div>
                            @endif
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Panelists card --}}
            @if ($panelists->isNotEmpty())
            <div class="card mb-3">
                <div class="card-body p-4">
                    <h6 class="mb-3">Interview panel / ranking committee</h6>
                    <ul class="list-group list-group-flush">
                        @foreach ($panelists as $panelist)
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                            <span class="small fw-medium">{{ $panelist->name }}</span>
                            <form action="{{ route('job-postings.panelists.detach', [$posting->id, $panelist->id]) }}" method="POST" class="m-0"
                                  onsubmit="return confirm('Remove {{ $panelist->name }} from this posting\'s panel?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-link text-danger p-0">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </form>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
            @endif

            {{-- Applicants + qualification check --}}
            <div class="card">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Applicants <span class="badge text-bg-light text-dark border ms-1">{{ $applications->count() }}</span></h6>
                    </div>

                    @forelse ($applications as $app)
                    <div class="border rounded p-3 mb-2" style="font-size: 0.875rem;">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-medium">{{ $app->candidate->full_name }}</div>
                                <div class="text-muted small">Applied {{ $app->applied_at ? \Carbon\Carbon::parse($app->applied_at)->format('M d, Y') : '—' }}</div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                @php
                                    $qualColors = [
                                        'qualified'     => 'success',
                                        'not_qualified' => 'danger',
                                        'hired'         => 'dark',
                                        'ranking_sent'  => 'primary',
                                    ];
                                    $qualColor = $qualColors[$app->status] ?? 'secondary';
                                @endphp
                                <span class="badge text-bg-{{ $qualColor }}">
                                    {{ str_replace('_', ' ', ucfirst($app->status)) }}
                                </span>
                                {{-- Qualify/Disqualify toggle --}}
                                @if (!in_array($app->status, ['hired', 'ranking_sent']))
                                <div class="d-flex gap-1">
                                    <form action="{{ route('applications.status', $app->id) }}" method="POST" class="m-0">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="status" value="qualified">
                                        <input type="hidden" name="job_posting_id" value="{{ $posting->id }}">
                                        <button type="submit" class="btn btn-xs btn-outline-success py-0 px-2"
                                            style="font-size:0.72rem;"
                                            {{ $app->status === 'qualified' ? 'disabled' : '' }}>✓ Qualify</button>
                                    </form>
                                    <form action="{{ route('applications.status', $app->id) }}" method="POST" class="m-0">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="status" value="not_qualified">
                                        <input type="hidden" name="job_posting_id" value="{{ $posting->id }}">
                                        <button type="submit" class="btn btn-xs btn-outline-danger py-0 px-2"
                                            style="font-size:0.72rem;"
                                            {{ $app->status === 'not_qualified' ? 'disabled' : '' }}>✗ Disqualify</button>
                                    </form>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @empty
                    <p class="text-muted small mb-0 text-center py-3">No applications yet for this posting.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ══ STEP 2: Open Ranking & Scheduling ══════════════════════════ --}}
        <div class="step-panel d-none" id="panel-2">
            <div class="card mb-3">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Interview / exam schedules</h6>
                        <button class="btn btn-sm" style="background-color: var(--hr-primary); color:#fff;"
                            data-bs-toggle="modal" data-bs-target="#newScheduleModal">
                            <i class="bi bi-plus-lg me-1"></i> New schedule
                        </button>
                    </div>

                    @if ($schedules->isEmpty())
                        <p class="text-muted small mb-0 text-center py-3">No schedules yet. Add one above.</p>
                    @else
                    <table class="table align-middle mb-0" style="font-size: 0.875rem;">
                        <thead>
                            <tr>
                                <th>Candidate</th>
                                <th>Type</th>
                                <th>Date &amp; time</th>
                                <th>Panelists</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($schedules as $s)
                            <tr>
                                <td class="fw-medium">{{ $s->application->candidate->full_name }}</td>
                                <td><span class="badge text-bg-light text-dark border">{{ str_replace('_', ' ', ucfirst($s->type)) }}</span></td>
                                <td>{{ $s->scheduled_at ? \Carbon\Carbon::parse($s->scheduled_at)->format('M d, Y h:i A') : '—' }}</td>
                                <td class="small">
                                    @if ($s->panelists->isNotEmpty())
                                        {{ $s->panelists->pluck('name')->implode(', ') }}
                                    @elseif ($s->interviewer_name)
                                        {{ $s->interviewer_name }}
                                    @else —
                                    @endif
                                </td>
                                <td>
                                    @php $sColors = ['scheduled'=>'primary','completed'=>'success','cancelled'=>'danger','no_show'=>'secondary']; @endphp
                                    <span class="badge text-bg-{{ $sColors[$s->status] ?? 'secondary' }}">{{ str_replace('_', ' ', ucfirst($s->status)) }}</span>
                                </td>
                                <td class="text-end">
                                    <form action="{{ route('interviews.destroy', $s->id) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Delete this schedule?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif
                </div>
            </div>
        </div>

        {{-- ══ STEP 3: Assessment & Results ═══════════════════════════════ --}}
        <div class="step-panel d-none" id="panel-3">

            {{-- Ranking table --}}
            <div class="card mb-3">
                <div class="card-body p-4">
                    <h6 class="mb-3">Candidate ranking</h6>
                    @if ($rankedCandidates->isEmpty())
                        <p class="text-muted small mb-0 text-center py-3">No applications to rank yet.</p>
                    @else
                    <table class="table align-middle mb-0" style="font-size: 0.875rem;">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Candidate</th>
                                @foreach ($criteria as $c)
                                    <th class="text-nowrap">{{ $c->name }} <span class="text-muted">({{ rtrim(rtrim(number_format($c->weight_percentage,2),'0'),'.') }}%)</span></th>
                                @endforeach
                                <th>Total</th>
                                <th>Notified</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rankedCandidates as $i => $cand)
                            <tr>
                                <td>
                                    @if ($i === 0 && $cand->total_score > 0)
                                        <span class="badge text-bg-warning">#1</span>
                                    @else
                                        <span class="text-muted">#{{ $i + 1 }}</span>
                                    @endif
                                </td>
                                <td class="fw-medium">{{ $cand->candidate_name }}</td>
                                @foreach ($criteria as $c)
                                    <td>{{ $cand->scores[$c->id] ?? '—' }}</td>
                                @endforeach
                                <td class="fw-semibold">{{ $cand->total_score }}</td>
                                <td>
                                    @if ($cand->notification_sent)
                                        <span class="text-success small"><i class="bi bi-check-lg"></i> Sent</span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="modal" data-bs-target="#editScoresModal"
                                        data-application-id="{{ $cand->application_id }}"
                                        data-candidate-name="{{ $cand->candidate_name }}"
                                        data-scores="{{ json_encode($cand->scores) }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif
                </div>
            </div>

            {{-- Assessment criteria --}}
            <div class="card">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Assessment criteria</h6>
                        <span class="badge {{ $remainingWeight > 0 ? 'text-bg-light text-dark border' : 'text-bg-success' }}">
                            {{ $usedWeight }}% used &middot; {{ $remainingWeight }}% remaining
                        </span>
                    </div>

                    <div class="row g-2 mb-3">
                        @forelse ($criteria as $c)
                        <div class="col-md-4">
                            <div class="border rounded p-2 small d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-medium">{{ $c->name }}</div>
                                    <div class="text-muted">{{ rtrim(rtrim(number_format($c->weight_percentage,2),'0'),'.') }}% weight</div>
                                </div>
                                <form method="POST" action="{{ route('assessments.criteria.destroy', $c->id) }}"
                                      onsubmit="return confirm('Remove this criterion?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-lg"></i></button>
                                </form>
                            </div>
                        </div>
                        @empty
                        <div class="col-12"><p class="text-muted small mb-0">No criteria defined yet.</p></div>
                        @endforelse
                    </div>

                    @if ($remainingWeight > 0)
                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addCriterionModal">
                        <i class="bi bi-plus-lg me-1"></i> Add criterion
                    </button>
                    @else
                    <button class="btn btn-sm btn-outline-secondary" disabled>
                        <i class="bi bi-plus-lg me-1"></i> Add criterion
                    </button>
                    @endif
                </div>
            </div>
        </div>

    </div>{{-- end col-md-9 --}}
</div>{{-- end row --}}

{{-- ── New Schedule Modal ──────────────────────────────────────────────── --}}
<div class="modal fade" id="newScheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('interviews.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title">Schedule interview / exam</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small">Candidate</label>
                        <select name="application_id" class="form-select form-select-sm" required>
                            <option value="" disabled selected>Select candidate</option>
                            @foreach ($applications->where('status', 'qualified') as $app)
                                <option value="{{ $app->id }}" data-job-posting-id="{{ $posting->id }}">
                                    {{ $app->candidate->full_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Type</label>
                        <select name="type" class="form-select form-select-sm">
                            <option value="open_ranking">Open ranking</option>
                            <option value="interview">Interview</option>
                            <option value="exam">Exam</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Date &amp; time</label>
                        <input type="datetime-local" name="scheduled_at" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Location</label>
                        <input type="text" name="location" class="form-control form-control-sm">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Vacancy for Screening / Interview</label>
                        <div id="newSchedPanelistList" class="border rounded p-2" style="min-height:48px; background:#f8f9fa;">
                            <span class="text-muted small">Select a candidate above to load panelists.</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background-color: var(--hr-primary); color:#fff;">Send invitation</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Add Criterion Modal ─────────────────────────────────────────────── --}}
<div class="modal fade" id="addCriterionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('assessments.criteria.store') }}">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title">Add assessment criterion</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="job_posting_id" value="{{ $posting->id }}">
                    <div class="mb-2">
                        <label class="form-label small">Criterion name</label>
                        <input type="text" name="name" class="form-control form-control-sm" required placeholder="e.g. Technical skills">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Weight (%) <span class="text-muted">— {{ $remainingWeight }}% remaining</span></label>
                        <input type="number" name="weight_percentage" class="form-control form-control-sm"
                               min="0.01" max="{{ $remainingWeight }}" step="0.01" required value="{{ $remainingWeight }}">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Description (optional)</label>
                        <textarea name="description" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background-color: var(--hr-primary); color:#fff;">Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Edit Scores Modal ───────────────────────────────────────────────── --}}
<div class="modal fade" id="editScoresModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('assessments.scores.save') }}" id="editScoresForm">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title">Edit scores — <span id="editScoresCandidateName"></span></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="application_id" id="editScoresApplicationId">
                    <input type="hidden" name="job_posting_id" value="{{ $posting->id }}">
                    @foreach ($criteria as $c)
                    <div class="mb-2">
                        <label class="form-label small">{{ $c->name }}
                            <span class="text-muted">(max {{ rtrim(rtrim(number_format($c->weight_percentage,2),'0'),'.') }})</span>
                        </label>
                        <input type="number" name="scores[{{ $c->id }}]"
                               class="form-control form-control-sm score-input"
                               data-criterion-id="{{ $c->id }}"
                               min="0" max="{{ $c->weight_percentage }}" step="0.01">
                    </div>
                    @endforeach
                    <div class="mb-2">
                        <label class="form-label small">Evaluator remarks (optional)</label>
                        <textarea name="evaluator_remarks" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Evaluated by (optional)</label>
                        <input type="text" name="evaluated_by" class="form-control form-control-sm">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background-color: var(--hr-primary); color:#fff;">Save scores</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
// ── Step switching ──────────────────────────────────────────────────────────
let activeStep = {{ $currentStep }};

function switchStep(n) {
    // Don't allow jumping ahead past current step
    if (n > {{ $currentStep }}) return;

    document.querySelectorAll('.step-panel').forEach(p => p.classList.add('d-none'));
    document.getElementById('panel-' + n).classList.remove('d-none');
    activeStep = n;
}

// Init: show current step panel
switchStep({{ $currentStep }});

// ── Advance pipeline step ───────────────────────────────────────────────────
function advanceStep() {
    const labels = {
        1: 'Move this posting to Interview Scheduling?',
        2: 'Move this posting to Assessment & Results?',
        3: 'Close this posting? This marks it as filled.',
    };
    if (!confirm(labels[{{ $currentStep }}] || 'Advance to next stage?')) return;

    const btn = document.getElementById('advanceBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Updating...';

    fetch('{{ route('job-postings.advance', $posting->id) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                         || '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
    })
    .then(r => r.json())
    .then(() => { window.location.reload(); })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = 'Advance';
        alert('Failed to advance step. Please try again.');
    });
}

// ── Edit scores modal ───────────────────────────────────────────────────────
document.getElementById('editScoresModal')?.addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    document.getElementById('editScoresApplicationId').value = btn.getAttribute('data-application-id');
    document.getElementById('editScoresCandidateName').textContent = btn.getAttribute('data-candidate-name');
    const scores = JSON.parse(btn.getAttribute('data-scores') || '{}');
    document.querySelectorAll('.score-input').forEach(input => {
        const v = scores[input.getAttribute('data-criterion-id')];
        input.value = (v === null || v === undefined || v === '-') ? '' : v;
    });
});

// ── Panelist checklist in schedule modal ────────────────────────────────────
const jobPostingId = {{ $posting->id }};

document.querySelector('#newScheduleModal select[name="application_id"]')
    ?.addEventListener('change', function () {
        loadPanelists('newSchedPanelistList', jobPostingId, []);
    });

function loadPanelists(containerId, postingId, selectedIds) {
    const box = document.getElementById(containerId);
    if (!box) return;
    box.innerHTML = '<span class="text-muted small">Loading...</span>';
    fetch('/interviews/panelists-for-posting/' + postingId)
        .then(r => r.json())
        .then(panelists => {
            if (!panelists.length) {
                box.innerHTML = '<span class="text-muted small">No panelists assigned to this vacancy.</span>';
                return;
            }
            box.innerHTML = panelists.map(p => {
                const checked = selectedIds.includes(p.id) ? 'checked' : '';
                const badge = p.is_available
                    ? '<span class="badge text-bg-success ms-2" style="font-size:0.65rem;">Available</span>'
                    : '<span class="badge text-bg-secondary ms-2" style="font-size:0.65rem;">Unavailable</span>';
                return '<div class="form-check mb-1">' +
                    '<input class="form-check-input" type="checkbox" name="panelist_ids[]"' +
                    ' value="' + p.id + '" id="pc_' + p.id + '" ' + checked + '>' +
                    '<label class="form-check-label small" for="pc_' + p.id + '">' +
                    p.name + badge + '</label></div>';
            }).join('');
        })
        .catch(() => {
            box.innerHTML = '<span class="text-danger small">Failed to load panelists.</span>';
        });
}
</script>
@endpush
@endsection
BLADE, 'show.blade.php: full pipeline dashboard');

echo <<<TEXT

✅ Done. No migration needed.

WHAT WAS BUILT:

Layout:
  - Sticky vertical step tracker on the left (col-md-3)
  - Content panels on the right (col-md-9), one per step
  - Step tracker shows completed (green ✓), active (blue), locked (grey) states
  - Clicking a completed step switches back to it; locked steps are not clickable

Step 1 — Overview & Qualification Checking (status: open)
  - Full job details, locations table, qualifications, duties
  - Panelist list with remove button (no availability badge)
  - Applicant list with Qualify / Disqualify toggle buttons per applicant

Step 2 — Open Ranking & Scheduling (status: interview_scheduled)
  - Schedule table filtered to this posting's applicants
  - New Schedule modal with panelist checklist
  - Only qualified applicants appear in the candidate dropdown

Step 3 — Assessment & Results (status: ranking)
  - Ranked candidates table with scores per criterion
  - Edit scores modal
  - Assessment criteria management with Add/Remove

Advancing steps:
  - "Move to Scheduling" / "Move to Assessment" / "Close Posting" button
  - Confirmation prompt before advancing
  - AJAX POST to /job-postings/{id}/advance → reloads page on success
  - Auto-updates posting status: open → interview_scheduled → ranking → closed

ALSO NEEDED:
  - Add a 'job-postings.panelists.detach' route for removing a panelist from a posting.
    Add to routes/web.php:
    Route::delete('/job-postings/{posting}/panelists/{panelist}', [JobPostingController::class, 'detachPanelist'])->name('job-postings.panelists.detach');
  - Add detachPanelist() method to JobPostingController:
    public function detachPanelist(\$postingId, \$panelistId) {
        \$posting = JobPosting::findOrFail(\$postingId);
        \$posting->panelists()->detach(\$panelistId);
        return back()->with('success', 'Panelist removed from this posting.');
    }

DELETE this script after running.

TEXT;
