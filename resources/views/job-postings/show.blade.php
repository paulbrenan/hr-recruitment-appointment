@extends('layouts.app')

@section('title', $posting->title . ' — Pipeline')
@section('page-title', 'Job posting pipeline')

@section('content')
@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show small py-2">
        {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show small py-2">
        {{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show small py-2">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@php
    $sg = $posting->salary_grade
        ? (Str::startsWith($posting->salary_grade, 'SG-') ? $posting->salary_grade : 'SG-' . $posting->salary_grade)
        : null;

    $steps = [
        1 => ['label' => 'Overview',                'icon' => 'bi-info-circle'],
        2 => ['label' => 'Qualification Checking',  'icon' => 'bi-clipboard-check'],
        3 => ['label' => 'Open Ranking & Scheduling','icon' => 'bi-calendar-event'],
        4 => ['label' => 'Assessment & Results',     'icon' => 'bi-bar-chart-line'],
        5 => ['label' => 'Offer Management',         'icon' => 'bi-envelope-paper'],
    ];

    $statusColors = [
        'open'                => 'success',
        'interview_scheduled' => 'primary',
        'ranking'             => 'warning',
        'closed'              => 'dark',
        'archived'            => 'secondary',
    ];
    $statusLabels = [
        'open'                => 'Open',
        'interview_scheduled' => 'Interview',
        'ranking'             => 'Ranking',
        'closed'              => 'Closed',
        'archived'            => 'Archived',
    ];
@endphp

{{-- Breadcrumb --}}
<div class="d-flex gap-1 align-items-center mb-3 small text-muted">
    <a href="{{ route('job-postings.index') }}" class="text-decoration-none text-muted">Job postings</a>
    <i class="bi bi-chevron-right" style="font-size:0.7rem;"></i>
    <span>{{ $posting->title }}</span>
</div>

<div class="row g-3">

    {{-- ── LEFT: Step tracker ──────────────────────────────────────────── --}}
    <div class="col-md-3">
        <div class="card" style="position:sticky; top:80px;">
            <div class="card-body p-3">
                <div class="fw-semibold mb-1" style="font-size:0.95rem;">{{ $posting->title }}</div>
                @if ($sg)
                <div class="text-muted small mb-1">{{ $sg }} &middot; {{ $posting->employment_type }}</div>
                @endif
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="badge text-bg-{{ $statusColors[$posting->status] ?? 'secondary' }}">
                        {{ $statusLabels[$posting->status] ?? ucfirst($posting->status) }}
                    </span>
                    <span class="text-muted small">
                        <i class="bi bi-person-lines-fill"></i> {{ $applications->count() }} {{ Str::plural('applicant', $applications->count()) }}
                    </span>
                </div>

                {{-- Steps --}}
                <div class="d-flex flex-column" id="stepTracker">
                    @foreach ($steps as $num => $step)
                    @php
                        // isDone/isActive reflect which panel is actually being
                        // $isDone reflects real progress ($currentStep, the
                        // lock boundary) so a step's checkmark persists no
                        // matter which panel you're currently viewing.
                        // $isActive reflects which panel you're viewing
                        // ($activeStep) and is independent of $isDone -- you
                        // can be actively viewing a step that's already done.
                        $isDone   = $currentStep > $num;
                        $isActive = $activeStep === $num;
                        $isLocked = $currentStep < $num;
                    @endphp
                    <div class="step-row d-flex align-items-start gap-2 py-2 px-2 rounded
                            {{ $isActive ? 'bg-primary bg-opacity-10' : '' }}"
                         id="step-row-{{ $num }}"
                         data-step="{{ $num }}"
                         style="cursor:{{ $isLocked ? 'default' : 'pointer' }};
                                border-left:3px solid {{ $isActive ? 'var(--hr-primary,#0d6efd)' : ($isDone ? '#198754' : '#dee2e6') }};
                                transform:{{ $isActive ? 'translateX(6px)' : 'translateX(0)' }};
                                transition:transform .15s ease, background-color .15s ease;"
                         onclick="{{ $isLocked ? '' : 'switchStep(' . $num . ')' }}">
                        <div class="step-circle flex-shrink-0 d-flex align-items-center justify-content-center mt-1"
                             id="step-circle-{{ $num }}"
                             style="width:20px;height:20px;border-radius:50%;
                                    background:{{ $isDone ? '#198754' : ($isActive ? 'var(--hr-primary,#0d6efd)' : '#dee2e6') }};">
                            <span id="step-circle-inner-{{ $num }}">
                            @if ($isDone)
                                <i class="bi bi-check text-white" style="font-size:0.7rem;"></i>
                            @else
                                <span style="font-size:0.6rem;font-weight:600;color:{{ $isActive ? '#fff' : '#6c757d' }};">{{ $num }}</span>
                            @endif
                            </span>
                        </div>
                        <div class="small fw-medium" id="step-label-{{ $num }}"
                             style="color:{{ $isActive ? 'var(--hr-primary,#0d6efd)' : ($isDone ? '#198754' : '#6c757d') }};">
                            {{ $step['label'] }}
                        </div>
                    </div>
                    @if ($num < count($steps))
                    <div class="step-connector" id="step-connector-{{ $num }}"
                         style="width:3px;height:14px;margin-left:calc(0.5rem + 10px);
                                background:{{ $currentStep > $num ? '#198754' : '#dee2e6' }};"></div>
                    @endif
                    @endforeach
                </div>

                {{-- Advance button --}}
                @if ($posting->status === 'open')
                    {{-- Overview (1) and Qualification Checking (2) share
                         status "open" -- only Qualification Checking can
                         trigger the real status advance. switchStep() below
                         toggles which of these two slots is visible to match
                         whichever panel is actually on screen, so this stays
                         correct even when navigating client-side via the
                         step tracker (no page reload happens there). --}}
                    <div class="mt-3 advance-slot" data-for-step="1">
                        <button type="button" class="btn btn-sm w-100 btn-outline-secondary" onclick="switchStep(2)">
                            <i class="bi bi-arrow-right me-1"></i> Next: Qualification Checking
                        </button>
                    </div>
                    <div class="mt-3 advance-slot d-none" data-for-step="2">
                        <button id="advanceBtn" class="btn btn-sm w-100"
                                style="background-color:var(--hr-primary);color:#fff;"
                                onclick="advanceStep()">
                            <i class="bi bi-arrow-right me-1"></i> Move to Scheduling
                        </button>
                    </div>
                @elseif ($posting->status !== 'closed')
                <div class="mt-3">
                    <button id="advanceBtn" class="btn btn-sm w-100"
                            style="background-color:var(--hr-primary);color:#fff;"
                            onclick="advanceStep()">
                        @if ($posting->status === 'interview_scheduled')
                            <i class="bi bi-arrow-right me-1"></i> Move to Assessment
                        @elseif ($posting->status === 'ranking')
                            <i class="bi bi-arrow-right me-1"></i> Move to Offer Management
                        @endif
                    </button>
                </div>
                @endif

                @if ($posting->status === 'closed')
                <div class="mt-3">
                    <form action="{{ route('job-postings.archive', $posting->id) }}" method="POST"
                          onsubmit="return confirm('Archive this posting? It will move out of the active job postings list.');">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-dark w-100">
                            <i class="bi bi-archive me-1"></i> Archive posting
                        </button>
                    </form>
                </div>
                @endif

                <div class="mt-3 pt-3 border-top">
                    @if ($currentStep < 3)
                    <a href="{{ route('job-postings.edit', $posting->id) }}"
                       class="btn btn-sm btn-outline-secondary w-100">
                        <i class="bi bi-pencil me-1"></i> Edit posting
                    </a>
                    @else
                    <button type="button" class="btn btn-sm btn-outline-secondary w-100" disabled
                            title="This posting can no longer be edited once scheduling has started.">
                        <i class="bi bi-lock me-1"></i> Edit posting
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ── RIGHT: Step panels ───────────────────────────────────────────── --}}
    <div class="col-md-9">

        {{-- ══ STEP 1 ══════════════════════════════════════════════════════ --}}
        <div class="step-panel" id="panel-1">

            {{-- Job details --}}
            <div class="card mb-3">
                <div class="card-body p-4">

                    {{-- Full job header — the complete job view, not a summary --}}
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                        <div>
                            <h5 class="mb-1">{{ $posting->title }}</h5>
                            <div class="text-muted small">
                                @if ($sg)<span class="me-2">{{ $sg }}</span>@endif
                                @if ($posting->employment_type)<span>&middot; {{ $posting->employment_type }}</span>@endif
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge text-bg-{{ $statusColors[$posting->status] ?? 'secondary' }}">
                                {{ $statusLabels[$posting->status] ?? ucfirst($posting->status) }}
                            </span>
                            @if ($currentStep < 3)
                            <a href="{{ route('job-postings.edit', $posting->id) }}"
                               class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil me-1"></i> Edit posting
                            </a>
                            @else
                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled
                                    title="This posting can no longer be edited once scheduling has started.">
                                <i class="bi bi-lock me-1"></i> Edit posting
                            </button>
                            @endif
                        </div>
                    </div>
                    <hr class="mt-0">

                    <h6 class="mb-3">Posting details</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <div class="text-muted small">Posted</div>
                            <div class="fw-medium">{{ $posting->posted_at ? \Carbon\Carbon::parse($posting->posted_at)->format('M d, Y') : '—' }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted small">Closes</div>
                            <div class="fw-medium">{{ $posting->closes_at ? \Carbon\Carbon::parse($posting->closes_at)->format('M d, Y') : '—' }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted small">Total vacancies</div>
                            <div class="fw-medium">{{ $locations->sum('vacancies') ?: ($posting->vacancies ?? '—') }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted small">Total applicants</div>
                            <div class="fw-medium">{{ $applications->count() }}</div>
                        </div>
                    </div>

                    {{-- Places-of-assignment breakdown removed — Total vacancies above covers it. --}}

                    {{-- Qualification standards — moved above duties --}}
                    @if ($posting->qualification_education || $posting->qualification_training || $posting->qualification_experience || $posting->qualification_eligibility)
                    <div class="mb-3">
                        <div class="text-muted small mb-2">Qualification standards</div>
                        <div class="row g-2">
                            @foreach (['Education' => $posting->qualification_education, 'Training' => $posting->qualification_training, 'Experience' => $posting->qualification_experience, 'Eligibility' => $posting->qualification_eligibility] as $lbl => $val)
                            @if ($val)
                            <div class="col-md-6">
                                <div class="text-muted" style="font-size:0.75rem;">{{ $lbl }}</div>
                                <p class="small mb-1">{{ $val }}</p>
                            </div>
                            @endif
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Duties and responsibilities — parsed into headings/bullets
                         instead of a raw pre-line text dump --}}
                    @if ($posting->duties_responsibilities)
                    <div class="mb-3">
                        <div class="text-muted small mb-2">Duties and responsibilities</div>
                        <div style="font-size:0.875rem;">
                            @php $dutyLines = preg_split('/\r\n|\r|\n/', trim($posting->duties_responsibilities)); @endphp
                            @foreach ($dutyLines as $dutyLine)
                                @php $dutyLine = trim($dutyLine); @endphp
                                @continue(empty($dutyLine))
                                @if (preg_match('/^([A-Z])\.\s+(.*)$/', $dutyLine, $m))
                                    <div class="fw-semibold mt-3 mb-1" style="color:var(--hr-primary,#0d6efd);">
                                        {{ $m[1] }}. {{ $m[2] }}
                                    </div>
                                @elseif (preg_match('/^([a-z]|\d+)\.\s+(.*)$/', $dutyLine, $m))
                                    <div class="d-flex gap-2 mb-1 ps-2">
                                        <i class="bi bi-dot text-muted"></i>
                                        <span>{{ $m[2] }}</span>
                                    </div>
                                @elseif (preg_match('/^[•\-\*]\s*(.*)$/', $dutyLine, $m))
                                    <div class="d-flex gap-2 mb-1 ps-2">
                                        <i class="bi bi-dot text-muted"></i>
                                        <span>{{ $m[1] }}</span>
                                    </div>
                                @else
                                    <p class="mb-1 ps-2">{{ $dutyLine }}</p>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    @endif

                    @php $mandatoryList = $posting->mandatoryRequirementsList(); $additionalList = $posting->additionalRequirementsList(); @endphp
                    @if (!empty($mandatoryList) || !empty($additionalList))
                    <hr>
                    <div class="text-muted small mb-2">Requirements checklist</div>
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

            {{-- Panelists --}}
            @if ($panelists->isNotEmpty())
            <div class="card mb-3">
                <div class="card-body p-4">
                    <h6 class="mb-3">Interview panel / ranking committee</h6>
                    <ul class="list-group list-group-flush">
                        @foreach ($panelists as $panelist)
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                            <span class="small fw-medium">{{ $panelist->name }}</span>
                            @if ($currentStep < 3)
                            <form action="{{ route('job-postings.panelists.detach', [$posting->id, $panelist->id]) }}"
                                  method="POST" class="m-0"
                                  onsubmit="return confirm('Remove {{ addslashes($panelist->name) }} from this posting\'s panel?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-link text-danger p-0">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </form>
                            @endif
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
            @endif

        </div>

        {{-- ══ STEP 2 — Qualification Checking ════════════════════════════ --}}
        <div class="step-panel d-none" id="panel-2">
            <div class="card">
                <div class="card-body p-4">
                    @php
                        $allChecked = $applications->count() > 0
                            && $applications->every(fn($a) => !empty($a->qualification_result));
                    @endphp
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h6 class="mb-0">
                            Qualification checking
                            <span class="badge text-bg-light text-dark border ms-1">{{ $applications->count() }}</span>
                        </h6>
                        <div class="d-flex align-items-center gap-2 flex-wrap">

                            @if ($allChecked)
                            <a href="{{ route('job-postings.export-qualifications', $posting->id) }}"
                               id="export-qualifications-btn"
                               data-no-loader
                               class="btn btn-sm btn-outline-success">
                                <i class="bi bi-file-earmark-excel me-1"></i> Export qualifications
                            </a>
                            @else
                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled
                                    title="Check all applicants before exporting">
                                <i class="bi bi-file-earmark-excel me-1"></i> Export qualifications
                            </button>
                            @endif
                        </div>
                    </div>

                    @if ($applications->isEmpty())
                    <p class="text-muted small mb-0 text-center py-3">No applications yet for this posting.</p>
                    @else
                    @php
                        // Group by the PERSISTED qualification_result, not by
                        // status -- status keeps advancing (interview_scheduled,
                        // ranked, hired, etc.) once an applicant qualifies, but
                        // qualification_result stays 'qualified' until HR re-runs
                        // the check, so this is what keeps the grouping stable.
                        // Applicants who haven't been checked yet have a null
                        // qualification_result and land in "Pending".
                        // Pending listed FIRST -- the pill switcher below marks
                        // whichever group is first in this array as the default
                        // active tab ($loop->first), and Pending is the one HR
                        // actually needs to act on.
                        $qualGroups = [
                            'pending'       => $applications->whereNull('qualification_result')->values(),
                            'qualified'     => $applications->where('qualification_result', 'qualified')->values(),
                            'not_qualified' => $applications->where('qualification_result', 'not_qualified')->values(),
                        ];
                        $qualGroupMeta = [
                            'pending'       => ['label' => 'Pending', 'color' => 'secondary'],
                            'qualified'     => ['label' => 'Qualified', 'color' => 'success'],
                            'not_qualified' => ['label' => 'Disqualified', 'color' => 'danger'],
                        ];
                    @endphp

                    {{-- Pill switcher — pick one group to view at a time --}}
                    <div class="qual-pill-tabs mb-3" role="tablist">
                        @foreach ($qualGroups as $groupKey => $groupApps)
                        <button type="button"
                                class="qual-pill-tab {{ $loop->first ? 'active' : '' }}"
                                data-qual-tab="{{ $groupKey }}"
                                role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                            {{ $qualGroupMeta[$groupKey]['label'] }}
                            <span class="qual-pill-count">{{ $groupApps->count() }}</span>
                        </button>
                        @endforeach
                    </div>

                    <style>
                        .qual-pill-tabs {
                            display: inline-flex;
                            gap: 2px;
                            background: #eef1f4;
                            border-radius: 999px;
                            padding: 3px;
                        }
                        .qual-pill-tab {
                            border: 0;
                            background: transparent;
                            color: #6c757d;
                            font-size: 0.82rem;
                            font-weight: 600;
                            padding: 6px 14px;
                            border-radius: 999px;
                            display: inline-flex;
                            align-items: center;
                            gap: 6px;
                            transition: background-color .15s ease, color .15s ease;
                        }
                        .qual-pill-tab .qual-pill-count {
                            background: rgba(0,0,0,0.08);
                            color: inherit;
                            font-size: 0.72rem;
                            font-weight: 700;
                            border-radius: 999px;
                            padding: 1px 7px;
                            line-height: 1.5;
                        }
                        .qual-pill-tab.active {
                            background: #fff;
                            color: #212529;
                            box-shadow: 0 1px 3px rgba(0,0,0,0.12);
                        }
                        .qual-pill-tab.active .qual-pill-count {
                            background: var(--hr-primary, #0d6efd);
                            color: #fff;
                        }
                    </style>

                    @foreach ($qualGroups as $groupKey => $groupApps)
                    <div class="qual-tab-panel {{ $loop->first ? '' : 'd-none' }}" data-qual-panel="{{ $groupKey }}">
                        @forelse ($groupApps as $app)
                        @php
                            $qColors = ['qualified'=>'success','not_qualified'=>'danger','hired'=>'dark','ranking_sent'=>'primary','interview_scheduled'=>'info','submitted'=>'secondary','rejected'=>'secondary'];
                            $qColor = $qColors[$app->status] ?? 'secondary';
                            $appCheck = $app->qualification_check ?? [];
                            // Candidate's self-reported qualifications — used only
                            // as the modal's starting point when no qualification
                            // check has been saved yet for that criterion; a saved
                            // "actual" value always takes precedence (see the
                            // qualCheckModal JS below). Same fields/precedence as
                            // the standalone /applications/{id} page.
                            $appSelfReported = [
                                'education'   => $app->candidate->education ?? null,
                                'experience'  => $app->candidate->years_experience ?? null,
                                'training'    => $app->candidate->training_hours ?? null,
                                'eligibility' => $app->candidate->eligibility ?? null,
                            ];
                        @endphp
                        <div class="border rounded p-3 mb-2 d-flex justify-content-between align-items-center flex-wrap gap-2" style="font-size:0.875rem;" data-location-id="{{ $app->job_posting_location_id }}">
                            @php
                                $appPlace = optional($app->jobPostingLocation)->place_of_assignment
                                    ?? $posting->place_of_assignment
                                    ?? null;
                                $appCheckData = $app->qualification_check ?? [];
                                $appCriteria = [];
                                foreach (['education' => 'Education', 'experience' => 'Experience', 'training' => 'Training', 'eligibility' => 'Eligibility'] as $ck => $cl) {
                                    if (isset($appCheckData['criteria'][$ck])) {
                                        $appCriteria[] = [
                                            'label' => $cl,
                                            'actual' => $appCheckData['criteria'][$ck]['actual'] ?? null,
                                            'passed' => (bool) ($appCheckData['criteria'][$ck]['passed'] ?? false),
                                        ];
                                    }
                                }
                                $appInfoData = [
                                    'name' => $app->candidate->full_name,
                                    'email' => $app->candidate->email,
                                    'phone' => $app->candidate->phone,
                                    'address' => $app->candidate->address,
                                    'age' => $app->candidate->age,
                                    'sex' => $app->candidate->sex,
                                    'civil_status' => $app->candidate->civil_status,
                                    'religion' => $app->candidate->religion,
                                    'disability' => $app->candidate->disability,
                                    'ethnic_group' => $app->candidate->ethnic_group,
                                    'education' => $app->candidate->education,
                                    'training_hours' => $app->candidate->training_hours,
                                    'years_experience' => $app->candidate->years_experience,
                                    'eligibility' => $app->candidate->eligibility,
                                    'transaction_number' => $app->transaction_number,
                                    'applied_at' => $app->applied_at ? \Carbon\Carbon::parse($app->applied_at)->format('M d, Y') : null,
                                    'status' => str_replace('_', ' ', ucfirst($app->status)),
                                    'place_of_assignment' => $appPlace,
                                    'notes' => $app->notes,
                                    'qualification_result' => $app->qualification_result ? ucfirst(str_replace('_', ' ', $app->qualification_result)) : null,
                                    'criteria' => $appCriteria,
                                ];
                            @endphp
                            <div>
                                <span class="fw-medium" role="button"
                                      style="border-bottom: 1px dashed #adb5bd;"
                                      title="View applicant information"
                                      onclick="event.stopPropagation(); showApplicantInfo(this)"
                                      data-info="{{ json_encode($appInfoData) }}">
                                    {{ $app->candidate->full_name }}
                                </span>
                                <div class="text-muted small">
                                    Applied {{ $app->applied_at ? \Carbon\Carbon::parse($app->applied_at)->format('M d, Y') : '—' }}
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge text-bg-{{ $qColor }}">
                                    {{ str_replace('_', ' ', ucfirst($app->status)) }}
                                </span>
                                @if (!in_array($app->status, ['hired', 'ranking_sent']))
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="modal" data-bs-target="#qualCheckModal"
                                        data-application-id="{{ $app->id }}"
                                        data-candidate-name="{{ addslashes($app->candidate->full_name) }}"
                                        data-check="{{ json_encode($appCheck) }}"
                                        data-self-reported="{{ json_encode($appSelfReported) }}">
                                    <i class="bi bi-clipboard-check me-1"></i> Check qualifications
                                </button>
                                @endif
                            </div>
                        </div>
                        @empty
                        <p class="text-muted small mb-0 py-3 text-center">None in this group.</p>
                        @endforelse
                    </div>
                    @endforeach

                    <script>
                        document.querySelectorAll('.qual-pill-tab').forEach(function (tab) {
                            tab.addEventListener('click', function () {
                                document.querySelectorAll('.qual-pill-tab').forEach(function (t) {
                                    t.classList.remove('active');
                                    t.setAttribute('aria-selected', 'false');
                                });
                                this.classList.add('active');
                                this.setAttribute('aria-selected', 'true');

                                var key = this.dataset.qualTab;
                                document.querySelectorAll('.qual-tab-panel').forEach(function (panel) {
                                    panel.classList.toggle('d-none', panel.dataset.qualPanel !== key);
                                });
                            });
                        });
                    </script>
                    @endif
                </div>
            </div>
        </div>

        {{-- ══ STEP 3 ══════════════════════════════════════════════════════ --}}
        <div class="step-panel d-none" id="panel-3">
            <div class="card mb-3">
                <div class="card-body p-4">
                    @php
                        $pendingScheduleNotices = $applications
                            ->whereNotNull('qualification_result')
                            ->whereNull('schedule_notice_sent_at')
                            ->count();
                    @endphp
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h6 class="mb-0">Interview / exam schedules</h6>
                        <div class="d-flex gap-2">
                            @if ($pendingScheduleNotices > 0)
                            <form action="{{ route('applications.schedule-notices.send-all', $posting->id) }}" method="POST" class="m-0">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-primary"
                                        onclick="return confirm('Send emails to {{ $pendingScheduleNotices }} applicant(s)? Qualified applicants with a schedule get the qualified letter + schedule; everyone else gets a disqualification notice.')">
                                    <i class="bi bi-envelope me-1"></i> Send all emails ({{ $pendingScheduleNotices }})
                                </button>
                            </form>
                            @endif
                            @if ($currentStep < 4)
                            <button class="btn btn-sm" style="background-color:var(--hr-primary);color:#fff;"
                                    data-bs-toggle="modal" data-bs-target="#newScheduleModal">
                                <i class="bi bi-plus-lg me-1"></i> New schedule
                            </button>
                            @endif
                            <a href="{{ route('job-postings.export-ier', $posting->id) }}" id="export-ier-btn" data-no-loader class="btn btn-sm btn-outline-secondary ms-2">
                                <i class="bi bi-file-earmark-excel me-1"></i> Export IER
                            </a>
                        </div>
                    </div>

                    @if ($schedules->isEmpty())
                        <p class="text-muted small mb-0 text-center py-3">No schedules yet.</p>
                    @else
                    @php
                        $sc = ['scheduled'=>'primary','completed'=>'success','cancelled'=>'danger','no_show'=>'secondary'];
                        // One "session" = everything created together in a single
                        // bulk "New schedule" action, which always shares the same
                        // date/time + venue across every applicant included.
                        $sessionGroups = $schedules->groupBy(function ($s) {
                            return $s->scheduled_at . '|' . $s->location;
                        })->values();
                    @endphp
                    <table class="table align-middle mb-0" style="font-size:0.875rem;">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th class="text-nowrap">Date &amp; time</th>
                                <th>Venue</th>
                                <th>Panelists</th>
                                <th>Applicants</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sessionGroups as $sessIdx => $sessionSchedules)
                            @php
                                $sessFirst    = $sessionSchedules->first();
                                $sessTypes    = $sessionSchedules->pluck('type')->unique();
                                $sessAppCount = $sessionSchedules->pluck('application_id')->unique()->count();
                                $sessStatuses = $sessionSchedules->pluck('status')->unique();
                            @endphp
                            <tr>
                                <td>
                                    @php
                                        $sessInfoData = [
                                            'scheduled_at' => $sessFirst->scheduled_at ? \Carbon\Carbon::parse($sessFirst->scheduled_at)->format('M d, Y h:i A') : null,
                                            'location' => $sessFirst->location,
                                            'applicant_count' => $sessAppCount,
                                            'panelists' => $sessFirst->panelists->map(fn ($p) => ['name' => $p->name, 'email' => $p->email])->values(),
                                            'types' => $sessTypes->map(function ($t) use ($sessionSchedules) {
                                                $typeSchedules = $sessionSchedules->where('type', $t);
                                                $statuses = $typeSchedules->pluck('status')->unique()->map(fn ($s) => str_replace('_', ' ', ucfirst($s)))->implode(', ');
                                                $remarks = $typeSchedules->pluck('remarks')->filter()->unique()->implode(' | ');
                                                return [
                                                    'type' => str_replace('_', ' ', ucfirst($t)),
                                                    'status' => $statuses,
                                                    'remarks' => $remarks ?: null,
                                                ];
                                            })->values(),
                                        ];
                                    @endphp
                                    <div class="d-flex flex-wrap gap-1" role="button" title="View schedule details"
                                         onclick="showScheduleInfo(this)" data-info="{{ json_encode($sessInfoData) }}">
                                        @foreach ($sessTypes as $t)
                                        <span class="badge text-bg-light text-dark border" style="font-size:0.75rem;">{{ str_replace('_',' ',ucfirst($t)) }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td>{{ $sessFirst->scheduled_at ? \Carbon\Carbon::parse($sessFirst->scheduled_at)->format('M d, Y h:i A') : '—' }}</td>
                                <td>{{ $sessFirst->location ?: '—' }}</td>
                                <td class="small">
                                    @if ($sessFirst->panelists->isNotEmpty())
                                        {{ $sessFirst->panelists->pluck('name')->implode(', ') }}
                                    @elseif ($sessFirst->interviewer_name)
                                        {{ $sessFirst->interviewer_name }}
                                    @else —
                                    @endif
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="modal" data-bs-target="#sessionApplicantsModal{{ $sessIdx }}">
                                        <i class="bi bi-people me-1"></i> {{ $sessAppCount }} {{ Str::plural('applicant', $sessAppCount) }}
                                    </button>
                                </td>
                                <td>
                                    @if ($sessStatuses->count() === 1)
                                        <span class="badge text-bg-{{ $sc[$sessStatuses->first()] ?? 'secondary' }}">{{ str_replace('_',' ',ucfirst($sessStatuses->first())) }}</span>
                                    @else
                                        @foreach ($sessStatuses as $st)
                                            <span class="badge text-bg-{{ $sc[$st] ?? 'secondary' }} me-1">{{ str_replace('_',' ',ucfirst($st)) }}</span>
                                        @endforeach
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>

                    {{-- One modal per session, listing the individual applicants
                         it covers -- same per-applicant detail (info popover,
                         per-type remove, per-type status) that used to live
                         directly in the main table. --}}
                    @foreach ($sessionGroups as $sessIdx => $sessionSchedules)
                    @php $sessByApp = $sessionSchedules->groupBy('application_id'); @endphp
                    <div class="modal fade" id="sessionApplicantsModal{{ $sessIdx }}" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h6 class="modal-title">Scheduled applicants</h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <table class="table align-middle mb-0" style="font-size:0.875rem;">
                                        <thead>
                                            <tr>
                                                <th>Candidate</th>
                                                <th>Type</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($sessByApp as $appId => $group)
                                            @php
                                                $first = $group->first();
                                                $statuses = $group->pluck('status')->unique();
                                                $schedCand = $first->application->candidate;
                                                $schedPlace = optional($first->application->jobPostingLocation)->place_of_assignment
                                                    ?? $posting->place_of_assignment
                                                    ?? null;
                                                $schedCheckData = $first->application->qualification_check ?? [];
                                                $schedCriteria = [];
                                                foreach (['education' => 'Education', 'experience' => 'Experience', 'training' => 'Training', 'eligibility' => 'Eligibility'] as $ck => $cl) {
                                                    if (isset($schedCheckData['criteria'][$ck])) {
                                                        $schedCriteria[] = [
                                                            'label' => $cl,
                                                            'actual' => $schedCheckData['criteria'][$ck]['actual'] ?? null,
                                                            'passed' => (bool) ($schedCheckData['criteria'][$ck]['passed'] ?? false),
                                                        ];
                                                    }
                                                }
                                                $schedInfoData = [
                                                    'name' => $schedCand->full_name,
                                                    'email' => $schedCand->email,
                                                    'phone' => $schedCand->phone,
                                                    'address' => $schedCand->address,
                                                    'age' => $schedCand->age,
                                                    'sex' => $schedCand->sex,
                                                    'civil_status' => $schedCand->civil_status,
                                                    'religion' => $schedCand->religion,
                                                    'disability' => $schedCand->disability,
                                                    'ethnic_group' => $schedCand->ethnic_group,
                                                    'education' => $schedCand->education,
                                                    'training_hours' => $schedCand->training_hours,
                                                    'years_experience' => $schedCand->years_experience,
                                                    'eligibility' => $schedCand->eligibility,
                                                    'transaction_number' => $first->application->transaction_number,
                                                    'applied_at' => $first->application->applied_at ? \Carbon\Carbon::parse($first->application->applied_at)->format('M d, Y') : null,
                                                    'status' => str_replace('_', ' ', ucfirst($first->application->status)),
                                                    'place_of_assignment' => $schedPlace,
                                                    'notes' => $first->application->notes,
                                                    'qualification_result' => $first->application->qualification_result ? ucfirst(str_replace('_', ' ', $first->application->qualification_result)) : null,
                                                    'criteria' => $schedCriteria,
                                                ];
                                            @endphp
                                            <tr>
                                                <td class="fw-medium">
                                                    <span role="button" style="border-bottom: 1px dashed #adb5bd;"
                                                          title="View applicant information"
                                                          onclick="showApplicantInfo(this)"
                                                          data-info="{{ json_encode($schedInfoData) }}">
                                                        {{ $schedCand->full_name }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-wrap gap-1">
                                                        @foreach ($group as $s)
                                                        <span class="badge text-bg-light text-dark border d-inline-flex align-items-center gap-1" style="font-size:0.75rem;">
                                                            {{ str_replace('_',' ',ucfirst($s->type)) }}
                                                            @if ($currentStep < 4)
                                                            <form action="{{ route('interviews.destroy', $s->id) }}" method="POST" class="d-inline m-0 p-0"
                                                                  onsubmit="return confirm('Remove the {{ str_replace('_',' ',ucfirst($s->type)) }} schedule for {{ addslashes($first->application->candidate->full_name) }}?')">
                                                                @csrf @method('DELETE')
                                                                <button type="submit" class="btn btn-link btn-sm p-0 text-danger" style="line-height:1;" title="Remove">
                                                                    <i class="bi bi-x-lg" style="font-size:0.65rem;"></i>
                                                                </button>
                                                            </form>
                                                            @endif
                                                        </span>
                                                        @endforeach
                                                    </div>
                                                </td>
                                                <td>
                                                    @if ($statuses->count() === 1)
                                                        <span class="badge text-bg-{{ $sc[$statuses->first()] ?? 'secondary' }}">{{ str_replace('_',' ',ucfirst($statuses->first())) }}</span>
                                                    @else
                                                        @foreach ($statuses as $st)
                                                            <span class="badge text-bg-{{ $sc[$st] ?? 'secondary' }} me-1">{{ str_replace('_',' ',ucfirst($st)) }}</span>
                                                        @endforeach
                                                    @endif
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                    @endif

                    <div class="modal fade" id="scheduleInfoModal" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h6 class="modal-title">Schedule details</h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <div class="text-muted small">Date &amp; time</div>
                                        <div class="fw-medium" id="si-scheduled-at">—</div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="text-muted small">Venue</div>
                                        <div class="fw-medium" id="si-location">—</div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="text-muted small">Applicants</div>
                                        <div class="fw-medium" id="si-applicant-count">—</div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="text-muted small mb-1">Type breakdown</div>
                                        <table class="table table-sm mb-0" style="font-size:0.85rem;">
                                            <thead>
                                                <tr><th>Type</th><th>Status</th><th>Remarks</th></tr>
                                            </thead>
                                            <tbody id="si-types-body"></tbody>
                                        </table>
                                    </div>
                                    <div>
                                        <div class="text-muted small mb-1">Panelists</div>
                                        <ul class="mb-0 ps-3" id="si-panelists-list" style="font-size:0.85rem;"></ul>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══ STEP 4 ══════════════════════════════════════════════════════ --}}
        <div class="step-panel d-none" id="panel-4">

            {{-- Assessment toolbar --}}
            <div class="d-flex flex-wrap gap-2 mb-3">
                @if ($rankedCandidates->isNotEmpty())
                <form method="POST" action="{{ route('assessments.send-all') }}" class="m-0">
                    @csrf
                    <input type="hidden" name="job_posting_id" value="{{ $posting->id }}">
                    <button type="submit" class="btn btn-sm btn-outline-primary"
                            onclick="return confirm('Send ranking notifications to all {{ $rankedCandidates->count() }} applicant(s)?')">
                        <i class="bi bi-envelope me-1"></i> Send all notifications
                    </button>
                </form>
                @endif
                @if ($criteria->isNotEmpty())
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importScoresModal">
                    <i class="bi bi-upload me-1"></i> Import scores from Excel
                </button>
                                <a href="{{ route('assessments.template') }}?job_posting_id={{ $posting->id }}"
                   id="download-template-btn"
                   data-no-loader
                   class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-download me-1"></i>
                    <span class="btn-label">Download template</span>
                </a>
                @endif
                @if ($rankedCandidates->isNotEmpty())
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#carDocumentModal">
                    <i class="bi bi-file-earmark-text me-1"></i> View / Print CAR
                </button>
                @endif
            </div>

            {{-- Ranking --}}
            <div class="card mb-3">
                <div class="card-body p-4">
                    <h6 class="mb-3">Candidate ranking</h6>
                    @if ($rankedCandidates->isEmpty())
                        <p class="text-muted small mb-0 text-center py-3">No applications to rank yet.</p>
                    @else
                    <div class="table-responsive">
                    <table class="table align-middle mb-0" style="font-size:0.875rem;">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Candidate</th>
                                @foreach ($criteria as $c)
                                <th class="text-nowrap">{{ $c->name }}
                                    <span class="text-muted">({{ rtrim(rtrim(number_format($c->weight_percentage,2),'0'),'.') }}%)</span>
                                </th>
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
                                @php
                                    $rankCand = $cand->candidate;
                                    $rankApp = $applications->firstWhere('id', $cand->application_id);
                                    $rankPlace = $rankApp ? (optional($rankApp->jobPostingLocation)->place_of_assignment ?? $posting->place_of_assignment ?? null) : null;
                                    $rankCheckData = $rankApp->qualification_check ?? [];
                                    $rankCriteria = [];
                                    foreach (['education' => 'Education', 'experience' => 'Experience', 'training' => 'Training', 'eligibility' => 'Eligibility'] as $ck => $cl) {
                                        if (isset($rankCheckData['criteria'][$ck])) {
                                            $rankCriteria[] = [
                                                'label' => $cl,
                                                'actual' => $rankCheckData['criteria'][$ck]['actual'] ?? null,
                                                'passed' => (bool) ($rankCheckData['criteria'][$ck]['passed'] ?? false),
                                            ];
                                        }
                                    }
                                    $rankInfoData = [
                                        'name' => $cand->candidate_name,
                                        'email' => $rankCand->email ?? null,
                                        'phone' => $rankCand->phone ?? null,
                                        'address' => $rankCand->address ?? null,
                                        'age' => $rankCand->age ?? null,
                                        'sex' => $rankCand->sex ?? null,
                                        'civil_status' => $rankCand->civil_status ?? null,
                                        'religion' => $rankCand->religion ?? null,
                                        'disability' => $rankCand->disability ?? null,
                                        'ethnic_group' => $rankCand->ethnic_group ?? null,
                                        'education' => $rankCand->education ?? null,
                                        'training_hours' => $rankCand->training_hours ?? null,
                                        'years_experience' => $rankCand->years_experience ?? null,
                                        'eligibility' => $rankCand->eligibility ?? null,
                                        'transaction_number' => $rankApp->transaction_number ?? null,
                                        'applied_at' => $rankApp && $rankApp->applied_at ? \Carbon\Carbon::parse($rankApp->applied_at)->format('M d, Y') : null,
                                        'status' => $rankApp ? str_replace('_', ' ', ucfirst($rankApp->status)) : null,
                                        'place_of_assignment' => $rankPlace,
                                        'notes' => $rankApp->notes ?? null,
                                        'qualification_result' => ($rankApp && $rankApp->qualification_result) ? ucfirst(str_replace('_', ' ', $rankApp->qualification_result)) : null,
                                        'criteria' => $rankCriteria,
                                    ];
                                @endphp
                                <td class="fw-medium">
                                    <span role="button" style="border-bottom: 1px dashed #adb5bd;"
                                          title="View applicant information"
                                          onclick="showApplicantInfo(this)"
                                          data-info="{{ json_encode($rankInfoData) }}">
                                        {{ $cand->candidate_name }}
                                    </span>
                                </td>
                                @foreach ($criteria as $c)
                                    <td>{{ $cand->scores[$c->id] ?? '—' }}</td>
                                @endforeach
                                <td class="fw-semibold">{{ $cand->total_score }}</td>
                                <td>
                                    @if ($cand->notification_sent)
                                        <span class="text-success small"><i class="bi bi-check-lg"></i> Sent</span>
                                    @else <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        @if ($posting->status !== 'closed')
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                data-bs-toggle="modal" data-bs-target="#editScoresModal"
                                                data-application-id="{{ $cand->application_id }}"
                                                data-candidate-name="{{ $cand->candidate_name }}"
                                                data-scores="{{ json_encode($cand->scores) }}">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        @endif
                                        <button type="button" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;"
                                                onclick="switchStep(5)" title="Go to Offer Management">
                                            <i class="bi bi-envelope-paper me-1"></i> Offer
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
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
                                @if ($posting->status !== 'closed')
                                <form method="POST" action="{{ route('assessments.criteria.destroy', $c->id) }}"
                                      onsubmit="return confirm('Remove this criterion?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-lg"></i></button>
                                </form>
                                @endif
                            </div>
                        </div>
                        @empty
                        <div class="col-12"><p class="text-muted small mb-0">No criteria defined yet.</p></div>
                        @endforelse
                    </div>
                    @if ($posting->status === 'closed')
                    <button class="btn btn-sm btn-outline-secondary" disabled title="This posting is closed.">
                        <i class="bi bi-plus-lg me-1"></i> Add criterion
                    </button>
                    @elseif ($remainingWeight > 0)
                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addCriterionModal">
                        <i class="bi bi-plus-lg me-1"></i> Add criterion
                    </button>
                    @else
                    <button class="btn btn-sm btn-outline-secondary" disabled title="No weight remaining">
                        <i class="bi bi-plus-lg me-1"></i> Add criterion
                    </button>
                    @endif

                    @if ($criteria->isNotEmpty() && $posting->status !== 'closed')
                    <form method="POST" action="{{ route('assessments.criteria.destroy-all') }}" class="d-inline ms-2"
                          onsubmit="return confirm('Delete ALL {{ $criteria->count() }} assessment criteria for this posting? This cannot be undone.')">
                        @csrf @method('DELETE')
                        <input type="hidden" name="job_posting_id" value="{{ $posting->id }}">
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash me-1"></i> Delete all
                        </button>
                    </form>
                    @endif

                    @if ($posting->status !== 'closed')
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-2" data-bs-toggle="modal" data-bs-target="#importCriteriaModal">
                        <i class="bi bi-upload me-1"></i> Scan file for criteria
                    </button>
                    @endif
                </div>
            </div>
        </div>

        {{-- ══ STEP 5 ══════════════════════════════════════════════════════ --}}
        <div class="step-panel d-none" id="panel-5">
            <div class="card mb-3">
                <div class="card-body p-4">
                    <h6 class="mb-3">Job offers</h6>
                    @if ($offers->isEmpty())
                        <p class="text-muted small mb-0 text-center py-3">No offers yet.</p>
                    @else
                    <div class="table-responsive mb-4">
                        <table class="table align-middle mb-0" style="font-size:0.875rem;">
                            <thead>
                                <tr>
                                    <th>Candidate</th>
                                    <th>Email</th>
                                    <th>Sent</th>
                                    <th>Email delivery</th>
                                    <th>Response by</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($offers as $o)
                                @php
                                    $offerColors = ['draft' => 'secondary', 'sent' => 'primary', 'accepted' => 'success', 'declined' => 'danger', 'expired' => 'dark'];
                                    $offerApp = $o->application;
                                    $offerCand = $offerApp->candidate ?? null;
                                    $offerPlace = $offerApp ? (optional($offerApp->jobPostingLocation)->place_of_assignment ?? $posting->place_of_assignment ?? null) : null;
                                    $offerCheckData = $offerApp->qualification_check ?? [];
                                    $offerCriteria = [];
                                    foreach (['education' => 'Education', 'experience' => 'Experience', 'training' => 'Training', 'eligibility' => 'Eligibility'] as $ock => $ocl) {
                                        if (isset($offerCheckData['criteria'][$ock])) {
                                            $offerCriteria[] = [
                                                'label' => $ocl,
                                                'actual' => $offerCheckData['criteria'][$ock]['actual'] ?? null,
                                                'passed' => (bool) ($offerCheckData['criteria'][$ock]['passed'] ?? false),
                                            ];
                                        }
                                    }
                                    $offerInfoData = [
                                        'name' => $offerCand->full_name ?? 'Unknown',
                                        'email' => $offerCand->email ?? null,
                                        'phone' => $offerCand->phone ?? null,
                                        'address' => $offerCand->address ?? null,
                                        'age' => $offerCand->age ?? null,
                                        'sex' => $offerCand->sex ?? null,
                                        'civil_status' => $offerCand->civil_status ?? null,
                                        'religion' => $offerCand->religion ?? null,
                                        'disability' => $offerCand->disability ?? null,
                                        'ethnic_group' => $offerCand->ethnic_group ?? null,
                                        'education' => $offerCand->education ?? null,
                                        'training_hours' => $offerCand->training_hours ?? null,
                                        'years_experience' => $offerCand->years_experience ?? null,
                                        'eligibility' => $offerCand->eligibility ?? null,
                                        'transaction_number' => $offerApp->transaction_number ?? null,
                                        'applied_at' => $offerApp && $offerApp->applied_at ? \Carbon\Carbon::parse($offerApp->applied_at)->format('M d, Y') : null,
                                        'status' => $offerApp ? str_replace('_', ' ', ucfirst($offerApp->status)) : null,
                                        'place_of_assignment' => $offerPlace,
                                        'notes' => $offerApp->notes ?? null,
                                        'qualification_result' => ($offerApp && $offerApp->qualification_result) ? ucfirst(str_replace('_', ' ', $offerApp->qualification_result)) : null,
                                        'criteria' => $offerCriteria,
                                    ];
                                @endphp
                                <tr>
                                    <td class="fw-medium">
                                        <span role="button" style="border-bottom: 1px dashed #adb5bd;"
                                              title="View applicant information"
                                              onclick="showApplicantInfo(this)"
                                              data-info="{{ json_encode($offerInfoData) }}">
                                            {{ $offerCand->full_name ?? 'Unknown' }}
                                        </span>
                                    </td>
                                    <td>{{ $o->application->candidate->email ?? '—' }}</td>
                                    <td>{{ $o->offer_sent_at ? \Carbon\Carbon::parse($o->offer_sent_at)->format('M d, Y') : '—' }}</td>
                                    <td>
                                        @if ($o->email_sent_at)
                                            <span class="badge text-bg-success">Sent</span>
                                            <div class="text-muted" style="font-size:0.72rem;">{{ \Carbon\Carbon::parse($o->email_sent_at)->format('M d, Y g:i A') }}</div>
                                        @else
                                            <span class="badge text-bg-secondary">Not sent</span>
                                        @endif
                                    </td>
                                    <td>{{ $o->response_deadline ? \Carbon\Carbon::parse($o->response_deadline)->format('M d, Y') : '—' }}</td>
                                    <td>
                                        <span class="badge badge-status text-bg-{{ $offerColors[$o->status] ?? 'secondary' }}">{{ ucfirst($o->status) }}</span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex gap-1 justify-content-end">
                                            @if ($o->status === 'draft')
                                            <form method="POST" action="{{ route('offers.send', $o->id) }}" class="d-inline">
                                                @csrf @method('PUT')
                                                <button type="submit" class="btn btn-sm" style="background-color:var(--hr-primary);color:#fff;">Send</button>
                                            </form>
                                            @elseif ($o->status === 'sent')
                                            <form method="POST" action="{{ route('offers.respond', $o->id) }}" class="d-inline"
                                                  onsubmit="return confirm('Mark this offer as accepted?')">
                                                @csrf @method('PUT')
                                                <input type="hidden" name="response" value="accepted">
                                                <button type="submit" class="btn btn-sm btn-outline-success">Accept</button>
                                            </form>
                                            <form method="POST" action="{{ route('offers.respond', $o->id) }}" class="d-inline"
                                                  onsubmit="return confirm('Mark this offer as declined?')">
                                                @csrf @method('PUT')
                                                <input type="hidden" name="response" value="declined">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Decline</button>
                                            </form>
                                            @else
                                            <span class="text-muted small">No actions</span>
                                            @endif
                                            <form method="POST" action="{{ route('offers.destroy', $o->id) }}" class="d-inline" onsubmit="return confirm('Delete this offer? This cannot be undone.');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif

                    <h6 class="mb-3">Generate new offer{{ $offerVacancyLimit > 1 ? 's' : '' }}</h6>
                    @if ($eligibleOfferApplications->isEmpty())
                        <p class="text-muted small mb-0">No candidates on this posting are currently eligible for an offer. Candidates become eligible once shortlisted, assessed, or hired, and don't already have an offer.</p>
                    @elseif ($offerVacancyLimit < 1)
                        <p class="text-muted small mb-0">All {{ $posting->vacancies }} vacanc{{ $posting->vacancies == 1 ? 'y' : 'ies' }} for this posting already have an active offer.</p>
                    @else
                    @if ($errors->has('application_ids') || $errors->has('compensation_override'))
                    <div class="alert alert-danger small py-2">{{ $errors->first('application_ids') ?: $errors->first('compensation_override') }}</div>
                    @endif
                    <p class="text-muted small mb-2">
                        Select up to <strong>{{ $offerVacancyLimit }}</strong> candidate{{ $offerVacancyLimit == 1 ? '' : 's' }} (this posting's remaining vacancy slots). Compensation defaults to SG {{ $posting->salary_grade }} Step 1 &mdash; override below if needed.
                    </p>
                    <form method="POST" action="{{ route('offers.store') }}" id="generateOfferForm">
                        @csrf
                        <input type="hidden" name="job_posting_id" value="{{ $posting->id }}">
                        <div class="table-responsive mb-3">
                        <table class="table align-middle mb-0" style="font-size:0.875rem;">
                            <thead>
                                <tr>
                                    <th style="width:2.5rem;"></th>
                                    <th>Rank</th>
                                    <th>Candidate</th>
                                    <th>Education</th>
                                    <th>Experience</th>
                                    <th>Eligibility</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="offerCandidateRows">
                                @foreach ($eligibleOfferApplications as $cand)
                                <tr>
                                    <td>
                                        <input class="form-check-input offer-candidate-checkbox" type="checkbox"
                                               name="application_ids[]" value="{{ $cand->application_id }}"
                                               {{ in_array($cand->application_id, old('application_ids', [])) ? 'checked' : '' }}>
                                    </td>
                                    <td>
                                        @if ($cand->rank === 1)
                                            <span class="badge text-bg-warning">#1</span>
                                        @else
                                            <span class="text-muted">#{{ $cand->rank }}</span>
                                        @endif
                                    </td>
                                    <td class="fw-medium">{{ $cand->candidate_name }}</td>
                                    <td>{{ $cand->candidate->education ?? '—' }}</td>
                                    <td>{{ $cand->candidate->years_experience ?? '—' }}{{ $cand->candidate->years_experience ? ' yrs' : '' }}</td>
                                    <td>{{ $cand->candidate->eligibility ?? '—' }}</td>
                                    <td class="text-end">
                                        {{-- Deliberately NOT a <form> -- it used to be, nested inside the
                                             bulk #generateOfferForm above, which is invalid HTML (forms
                                             can't nest). Browsers silently drop the inner <form> tag and
                                             reparent its hidden application_ids[] input into the OUTER bulk
                                             form instead, so every row's hidden value rode along with the
                                             bulk submission and duplicated whatever was also checked. This
                                             button builds and submits its own isolated form via JS instead. --}}
                                        <button type="button" class="btn btn-sm offer-single-btn"
                                                style="background-color: var(--hr-primary); color: #fff;"
                                                data-application-id="{{ $cand->application_id }}"
                                                data-candidate-name="{{ $cand->candidate_name }}">
                                            <i class="bi bi-envelope-paper me-1"></i> Offer
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-2">
                                <label class="form-label small text-muted mb-1">Override SG (optional)</label>
                                <select name="sg_override" id="offerSgOverrideSelect" class="form-select form-select-sm">
                                    <option value="">Inherit: SG {{ $posting->salary_grade }}</option>
                                    @for ($sgOpt = 1; $sgOpt <= 33; $sgOpt++)
                                        <option value="{{ $sgOpt }}" {{ old('sg_override') == $sgOpt ? 'selected' : '' }}>SG {{ $sgOpt }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1">Override compensation (optional)</label>
                                <input type="number" step="0.01" min="0" name="compensation_override" id="offerCompensationOverride" class="form-control form-control-sm"
                                       placeholder="Default: SG {{ $posting->salary_grade }} Step 1" value="{{ old('compensation_override') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1">Response deadline</label>
                                <input type="date" name="response_deadline" class="form-control form-control-sm" min="{{ now()->toDateString() }}" value="{{ old('response_deadline') }}">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-sm w-100" style="background-color:var(--hr-primary);color:#fff;">
                                    Generate offer<span id="offerSelectedCountLabel"></span>
                                </button>
                            </div>
                        </div>
                    </form>
                    <script>
                    (function () {
                        const limit = {{ (int) $offerVacancyLimit }};
                        const boxes = document.querySelectorAll('.offer-candidate-checkbox');
                        const countLabel = document.getElementById('offerSelectedCountLabel');

                        function refresh() {
                            const checked = document.querySelectorAll('.offer-candidate-checkbox:checked');
                            if (countLabel) countLabel.textContent = checked.length ? ' (' + checked.length + ')' : '';
                            const atLimit = checked.length >= limit;
                            boxes.forEach(function (b) {
                                if (!b.checked) b.disabled = atLimit;
                            });
                        }

                        boxes.forEach(function (b) { b.addEventListener('change', refresh); });
                        refresh();

                        // Per-row single-candidate "Offer" button. Builds a
                        // fully standalone form (not nested anywhere) and
                        // submits it directly -- isolated from the bulk
                        // #generateOfferForm above on purpose.
                        document.querySelectorAll('.offer-single-btn').forEach(function (btn) {
                            btn.addEventListener('click', function () {
                                const appId = btn.dataset.applicationId;
                                const candidateName = btn.dataset.candidateName;
                                if (!confirm('Generate a draft offer for ' + candidateName + ' at SG {{ $posting->salary_grade }} Step 1?')) {
                                    return;
                                }

                                const f = document.createElement('form');
                                f.method = 'POST';
                                f.action = '{{ route("offers.store") }}';
                                f.style.display = 'none';

                                const csrf = document.createElement('input');
                                csrf.type = 'hidden';
                                csrf.name = '_token';
                                csrf.value = '{{ csrf_token() }}';
                                f.appendChild(csrf);

                                const postingIdInput = document.createElement('input');
                                postingIdInput.type = 'hidden';
                                postingIdInput.name = 'job_posting_id';
                                postingIdInput.value = '{{ $posting->id }}';
                                f.appendChild(postingIdInput);

                                const appIdInput = document.createElement('input');
                                appIdInput.type = 'hidden';
                                appIdInput.name = 'application_ids[]';
                                appIdInput.value = appId;
                                f.appendChild(appIdInput);

                                document.body.appendChild(f);
                                f.submit();
                            });
                        });

                        // SG override -> auto-fill the peso field with that
                        // grade's Step 1 amount. Still just a starting
                        // point -- HR can edit the peso field afterward and
                        // that typed value always wins on submit.
                        const sgTable = @json(\App\Models\SalaryGrade::currentTableArray());
                        const compInput = document.getElementById('offerCompensationOverride');
                        sgOverrideSel?.addEventListener('change', function () {
                            const grade = parseInt(this.value, 10);
                            if (grade && sgTable[grade] && sgTable[grade][0] !== undefined) {
                                compInput.value = sgTable[grade][0];
                            }
                        });
                    })();
                    </script>
                    @endif
                </div>
            </div>
        </div>

    </div>{{-- col-md-9 --}}
</div>{{-- row --}}

{{-- ── Modals ────────────────────────────────────────────────────────────── --}}

{{-- Scan file for assessment criteria --}}
<div class="modal fade" id="importCriteriaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('assessments.criteria.import-scan') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="job_posting_id" value="{{ $posting->id }}">
                <div class="modal-header">
                    <h6 class="modal-title">Scan file for assessment criteria</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        Upload a PDF, Word document, Excel file, or photo of the criteria table
                        (e.g. a CSC merit selection form). The system scans it for recognized
                        criteria names — Education, Training, Experience, Performance,
                        Outstanding Accomplishments, Application of Education, Application of
                        Learning and Development, Potential — and adds whichever ones it finds,
                        using their standard weight.
                    </p>
                    <input type="file" name="criteria_file" class="form-control form-control-sm"
                           accept=".pdf,.docx,.xlsx,.xls,.jpg,.jpeg,.png" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background-color:var(--hr-primary);color:#fff;">
                        <i class="bi bi-upload me-1"></i> Scan &amp; add criteria
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- New Schedule (per-job: schedules ALL qualified applicants at once) --}}
<div class="modal fade" id="newScheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('interviews.store-for-posting') }}" method="POST">
                @csrf
                <input type="hidden" name="job_posting_id" value="{{ $posting->id }}">
                <div class="modal-header">
                    <h6 class="modal-title">Schedule interview / exam</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small py-2 mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        This will schedule <strong>all qualified applicants</strong> on this posting at once.
                        {{ $applications->whereIn('status', ['qualified','interview_scheduled','ranked'])->count() }} applicant(s) will be scheduled.
                    </div>

                    <div class="mb-2">
                        <label class="form-label small d-flex justify-content-between align-items-center mb-1">
                            <span>Type</span>
                            <span class="form-check form-check-inline m-0">
                                <input class="form-check-input" type="checkbox" id="schedTypeSelectAll">
                                <label class="form-check-label small" for="schedTypeSelectAll">Select all</label>
                            </span>
                        </label>
                        <div class="border rounded p-2">
                            <div class="form-check">
                                <input class="form-check-input sched-type-checkbox" type="checkbox" name="type[]" value="open_ranking" id="schedTypeOpenRanking" checked>
                                <label class="form-check-label small" for="schedTypeOpenRanking">Open ranking</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input sched-type-checkbox" type="checkbox" name="type[]" value="interview" id="schedTypeInterview">
                                <label class="form-check-label small" for="schedTypeInterview">Interview</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input sched-type-checkbox" type="checkbox" name="type[]" value="exam" id="schedTypeExam">
                                <label class="form-check-label small" for="schedTypeExam">Exam</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Date &amp; time</label>
                        <input type="datetime-local" name="scheduled_at" class="form-control form-control-sm"
                               min="{{ now()->format('Y-m-d\TH:i') }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Venue</label>
                        <input type="text" name="location" class="form-control form-control-sm" placeholder="e.g. SDO Conference Room">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Panel members</label>
                        <div id="schedPanelistBox" class="border rounded p-2" style="min-height:48px;background:#f8f9fa;">
                            @if ($panelists->isNotEmpty())
                                @foreach ($panelists as $p)
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" name="panelist_ids[]"
                                           value="{{ $p->id }}" id="sp{{ $p->id }}">
                                    <label class="form-check-label small" for="sp{{ $p->id }}">
                                        {{ $p->name }}
                                    </label>
                                </div>
                                @endforeach
                            @else
                                <span class="text-muted small">No panelists assigned to this posting.</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background-color:var(--hr-primary);color:#fff;">
                        <i class="bi bi-calendar-check me-1"></i> Schedule &amp; send invitations
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Add Criterion --}}
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
                    <button type="submit" class="btn btn-sm" style="background-color:var(--hr-primary);color:#fff;">Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Qualification Check --}}
<div class="modal fade" id="applicantInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content" style="border: none; border-radius: 12px; overflow: hidden;">

            {{-- Header: avatar initial + name + status pill --}}
            <div class="modal-header" style="background: var(--hr-primary); color: #fff; border: none; padding: 20px 24px;">
                <div style="min-width: 0;">
                    <h5 class="modal-title mb-0" id="applicantInfoName" style="font-weight: 700;">Applicant Information</h5>
                    <span id="ai-status" class="badge mt-1" style="background: rgba(255,255,255,.22); font-weight: 600; font-size: .72rem;">—</span>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-0" style="background: #f8f9fb;">

                {{-- Qualification Check Breakdown --}}
                <div class="p-3 pb-2" id="ai-criteria-wrap">
                    <div class="bg-white rounded-3 p-3 border">
                        <div class="text-uppercase text-muted fw-semibold mb-2" style="font-size: .7rem; letter-spacing: .04em;">
                            <i class="bi bi-clipboard-check me-1"></i> Qualification Check Breakdown
                        </div>
                        <table class="table table-sm mb-0" id="ai-criteria-table" style="font-size: .82rem;">
                            <thead>
                                <tr>
                                    <th class="text-muted" style="font-size:.7rem; text-transform:uppercase; letter-spacing:.03em; border-top:0;">Criterion</th>
                                    <th class="text-muted" style="font-size:.7rem; text-transform:uppercase; letter-spacing:.03em; border-top:0;">Candidate's Qualification</th>
                                    <th class="text-muted text-end" style="font-size:.7rem; text-transform:uppercase; letter-spacing:.03em; border-top:0;">Result</th>
                                </tr>
                            </thead>
                            <tbody id="ai-criteria-tbody"></tbody>
                        </table>
                    </div>
                </div>

                {{-- Position & Application --}}
                <div class="px-3 pb-2" id="ai-app-meta-wrap">
                    <div class="bg-white rounded-3 p-3 border">
                        <div class="text-uppercase text-muted fw-semibold mb-2" style="font-size: .7rem; letter-spacing: .04em;">
                            <i class="bi bi-briefcase me-1"></i> Position &amp; Application
                        </div>
                        <div class="row g-3 small">
                            <div class="col-md-6" id="ai-txn-wrap"><span class="text-muted d-block" style="font-size:.72rem;">Transaction No.</span><div id="ai-transaction_number" class="fw-medium font-monospace">—</div></div>
                            <div class="col-md-6" id="ai-applied-wrap"><span class="text-muted d-block" style="font-size:.72rem;">Applied</span><div id="ai-applied_at" class="fw-medium">—</div></div>
                            <div class="col-md-6" id="ai-place-wrap"><span class="text-muted d-block" style="font-size:.72rem;">Place of Assignment</span><div id="ai-place_of_assignment" class="fw-medium">—</div></div>
                            <div class="col-md-6" id="ai-qualresult-wrap"><span class="text-muted d-block" style="font-size:.72rem;">Qualification Result</span><div id="ai-qualification_result" class="fw-medium">—</div></div>
                            <div class="col-12" id="ai-notes-wrap"><span class="text-muted d-block" style="font-size:.72rem;">Notes</span><div id="ai-notes" class="fw-medium">—</div></div>
                        </div>
                    </div>
                </div>

                {{-- Qualifications (self-reported) --}}
                <div class="px-3 pb-2">
                    <div class="bg-white rounded-3 p-3 border">
                        <div class="text-uppercase text-muted fw-semibold mb-2" style="font-size: .7rem; letter-spacing: .04em;">
                            <i class="bi bi-mortarboard me-1"></i> Qualifications
                        </div>
                        <div class="row g-3 small">
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Highest Education</span><div id="ai-education" class="fw-medium">—</div></div>
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Eligibility</span><div id="ai-eligibility" class="fw-medium">—</div></div>
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Training Hours</span><div id="ai-training_hours" class="fw-medium">—</div></div>
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Years of Experience</span><div id="ai-years_experience" class="fw-medium">—</div></div>
                        </div>
                    </div>
                </div>

                {{-- Contact --}}
                <div class="px-3 pb-2">
                    <div class="bg-white rounded-3 p-3 border">
                        <div class="text-uppercase text-muted fw-semibold mb-2" style="font-size: .7rem; letter-spacing: .04em;">
                            <i class="bi bi-person-lines-fill me-1"></i> Contact
                        </div>
                        <div class="row g-3 small">
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Email</span><div id="ai-email" class="fw-medium">—</div></div>
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Phone</span><div id="ai-phone" class="fw-medium">—</div></div>
                            <div class="col-12"><span class="text-muted d-block" style="font-size:.72rem;">Address</span><div id="ai-address" class="fw-medium">—</div></div>
                        </div>
                    </div>
                </div>

                {{-- Personal Details --}}
                <div class="px-3 pb-3">
                    <div class="bg-white rounded-3 p-3 border">
                        <div class="text-uppercase text-muted fw-semibold mb-2" style="font-size: .7rem; letter-spacing: .04em;">
                            <i class="bi bi-card-list me-1"></i> Personal Details
                        </div>
                        <div class="row g-3 small">
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Age</span><div id="ai-age" class="fw-medium">—</div></div>
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Sex</span><div id="ai-sex" class="fw-medium">—</div></div>
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Civil Status</span><div id="ai-civil_status" class="fw-medium">—</div></div>
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Religion</span><div id="ai-religion" class="fw-medium">—</div></div>
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Disability</span><div id="ai-disability" class="fw-medium">—</div></div>
                            <div class="col-md-6"><span class="text-muted d-block" style="font-size:.72rem;">Ethnic Group</span><div id="ai-ethnic_group" class="fw-medium">—</div></div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="qualCheckModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="qualCheckForm" action="">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title">Qualification check — <span id="qualCheckCandidateName"></span></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="job_posting_id" value="{{ $posting->id }}">
                    @php
                        $qualCriteriaFields = [
                            'education'   => ['label' => 'Education',   'required' => $posting->qualification_education   ?? null],
                            'experience'  => ['label' => 'Experience',  'required' => $posting->qualification_experience  ?? null],
                            'training'    => ['label' => 'Training',    'required' => $posting->qualification_training    ?? null],
                            'eligibility' => ['label' => 'Eligibility', 'required' => $posting->qualification_eligibility ?? null],
                        ];
                    @endphp
                    @foreach ($qualCriteriaFields as $key => $meta)
                    <div class="border-bottom py-2">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <label class="fw-medium mb-0">{{ $meta['label'] }}</label>
                            <div class="btn-group btn-group-sm" role="group">
                                <input type="radio" class="btn-check qual-passed-input" name="{{ $key }}_passed" value="1"
                                       id="qc_{{ $key }}_yes" data-criterion="{{ $key }}" autocomplete="off">
                                <label class="btn btn-outline-success" for="qc_{{ $key }}_yes" style="font-size:.7rem;padding:.15rem .5rem;">Qualified</label>

                                <input type="radio" class="btn-check qual-passed-input" name="{{ $key }}_passed" value="0"
                                       id="qc_{{ $key }}_no" data-criterion="{{ $key }}" autocomplete="off">
                                <label class="btn btn-outline-danger" for="qc_{{ $key }}_no" style="font-size:.7rem;padding:.15rem .5rem;">Not qualified</label>
                            </div>
                        </div>
                        @if ($meta['required'])
                        <div class="text-muted mb-1" style="font-size:.75rem;">Required: {{ $meta['required'] }}</div>
                        @endif
                        <input type="text" name="{{ $key }}_actual" class="form-control form-control-sm qual-actual-input"
                               data-criterion="{{ $key }}"
                               data-required="{{ $meta['required'] }}"
                               placeholder="Candidate's actual {{ strtolower($meta['label']) }}...">
                    </div>
                    @endforeach
                    <textarea name="check_notes" id="qualCheckNotes" class="form-control form-control-sm mt-2" rows="2"
                              placeholder="Notes about this qualification check..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background-color:var(--hr-primary);color:#fff;">Save qualification check</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit Scores --}}
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
                    <input type="hidden" name="application_id" id="editScoresAppId">
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
                    <button type="submit" class="btn btn-sm" style="background-color:var(--hr-primary);color:#fff;">Save scores</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Import Scores --}}
<div class="modal fade" id="importScoresModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('assessments.import') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="job_posting_id" value="{{ $posting->id }}">
                <div class="modal-header">
                    <h6 class="modal-title">Import scores from Excel</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">Upload the filled-in Excel template. Application codes and criterion names must match exactly.</p>
                    <input type="file" name="import_file" class="form-control form-control-sm" accept=".xlsx,.xls" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background-color:var(--hr-primary);color:#fff;">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- CAR Document --}}
<div class="modal fade" id="carDocumentModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-file-earmark-text me-2"></i>Comparative Assessment Result</h6>
                <div class="d-flex align-items-center gap-2 ms-auto me-2">
                    <div class="form-check form-check-inline mb-0" style="font-size:0.8rem;">
                        <input type="checkbox" class="form-check-input" id="carPublicToggle">
                        <label for="carPublicToggle" class="form-check-label">Public view (conceal names)</label>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i> Print
                    </button>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="carDocumentPrintArea">
                    <div class="text-center fw-bold mb-1">Comparative Assessment Result (CAR)</div>
                    <div class="text-center text-muted small mb-3">{{ $posting->title }}</div>
                    <div class="row mb-2" style="font-size:0.8rem;">
                        <div class="col-6">Position: <strong>{{ $posting->title }}</strong></div>
                        <div class="col-6">Date: <strong>{{ now()->format('M d, Y') }}</strong></div>
                        <div class="col-6">SG: <strong>{{ $posting->salary_grade ?? '—' }}</strong></div>
                        <div class="col-6">Office: <strong>DepEd Division of Cavite Province</strong></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered" style="font-size:0.78rem;" id="carDocTable">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th class="car-confidential">Name</th>
                                    <th>App. Code</th>
                                    @foreach ($criteria as $c)
                                    <th>{{ $c->name }} ({{ rtrim(rtrim(number_format($c->weight_percentage,2),'0'),'.') }}%)</th>
                                    @endforeach
                                    <th>Total</th>
                                    <th>Passed</th>
                                    <th class="car-doc-fillable">Background Investigation</th>
                                    <th class="car-doc-fillable">Appointment</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rankedCandidates as $i => $cand)
                                <tr>
                                    <td class="text-center fw-bold">#{{ $i + 1 }}</td>
                                    <td class="car-confidential">{{ $cand->candidate_name }}</td>
                                    <td>{{ $cand->application_code ?? '—' }}</td>
                                    @foreach ($criteria as $c)
                                    <td class="text-center">{{ $cand->scores[$c->id] ?? '—' }}</td>
                                    @endforeach
                                    <td class="text-center fw-bold">{{ $cand->total_score }}</td>
                                    <td class="text-center">
                                        @if ($cand->passed ?? false)
                                            <span class="badge text-bg-success">Passed</span>
                                        @else
                                            <span class="badge text-bg-secondary">—</span>
                                        @endif
                                    </td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// ── Applicant Info modal ─────────────────────────────────────────────────
function showApplicantInfo(el) {
    const data = JSON.parse(el.dataset.info || '{}');
    const set = (id, val) => {
        const target = document.getElementById(id);
        if (target) target.textContent = (val === null || val === undefined || val === '') ? '—' : val;
    };

    document.getElementById('applicantInfoName').textContent = data.name || 'Applicant Information';
    set('ai-email', data.email);
    set('ai-phone', data.phone);
    set('ai-address', data.address);
    set('ai-age', data.age);
    set('ai-sex', data.sex);
    set('ai-civil_status', data.civil_status);
    set('ai-religion', data.religion);
    set('ai-disability', data.disability);
    set('ai-ethnic_group', data.ethnic_group);
    set('ai-education', data.education);
    set('ai-training_hours', data.training_hours);
    set('ai-years_experience', data.years_experience);
    set('ai-eligibility', data.eligibility);

    const hasAppMeta = data.transaction_number || data.applied_at || data.status;
    document.getElementById('ai-app-meta-wrap').style.display = hasAppMeta ? '' : 'none';
    document.getElementById('ai-txn-wrap').style.display = data.transaction_number ? '' : 'none';
    document.getElementById('ai-applied-wrap').style.display = data.applied_at ? '' : 'none';
    set('ai-transaction_number', data.transaction_number);
    set('ai-applied_at', data.applied_at);
    set('ai-status', data.status);

    document.getElementById('ai-place-wrap').style.display = data.place_of_assignment ? '' : 'none';
    set('ai-place_of_assignment', data.place_of_assignment);

    document.getElementById('ai-qualresult-wrap').style.display = data.qualification_result ? '' : 'none';
    set('ai-qualification_result', data.qualification_result);

    document.getElementById('ai-notes-wrap').style.display = data.notes ? '' : 'none';
    set('ai-notes', data.notes);

    const criteria = data.criteria || [];
    const tbody = document.getElementById('ai-criteria-tbody');
    document.getElementById('ai-criteria-wrap').style.display = criteria.length ? '' : 'none';
    tbody.innerHTML = '';
    criteria.forEach(row => {
        const tr = document.createElement('tr');
        const badgeClass = row.passed ? 'text-bg-success' : 'text-bg-danger';
        const badgeText  = row.passed ? 'Qualified' : 'Not qualified';
        tr.innerHTML = '<td>' + row.label + '</td>'
            + '<td>' + (row.actual || '—') + '</td>'
            + '<td class="text-end"><span class="badge ' + badgeClass + '">' + badgeText + '</span></td>';
        tbody.appendChild(tr);
    });

    new bootstrap.Modal(document.getElementById('applicantInfoModal')).show();
}

// ── Step switching ──────────────────────────────────────────────────────────
const currentStep = {{ $currentStep }};
const activeStep  = {{ $activeStep }};

function updateStepTracker(n) {
    // Re-render the sidebar tracker to match whichever step is now active.
    // Previously this DOM was only ever rendered once server-side from
    // $activeStep at page load, so clicking between steps (e.g. Overview
    // <-> Qualification Checking, which share status "open") changed the
    // panel but left the tracker frozen -- Qualification Checking never
    // appeared highlighted/green/active.
    document.querySelectorAll('.step-row').forEach(row => {
        const step     = parseInt(row.dataset.step, 10);
        const isActive = step === n;
        const isDone   = currentStep > step; // real progress, not the step being viewed

        row.classList.toggle('bg-primary', isActive);
        row.classList.toggle('bg-opacity-10', isActive);
        row.style.borderLeft = '3px solid ' + (isActive ? 'var(--hr-primary,#0d6efd)' : (isDone ? '#198754' : '#dee2e6'));
        row.style.transform  = isActive ? 'translateX(6px)' : 'translateX(0)';

        const circle = document.getElementById('step-circle-' + step);
        if (circle) {
            circle.style.background = isDone ? '#198754' : (isActive ? 'var(--hr-primary,#0d6efd)' : '#dee2e6');
        }

        const inner = document.getElementById('step-circle-inner-' + step);
        if (inner) {
            inner.innerHTML = isDone
                ? '<i class="bi bi-check text-white" style="font-size:0.7rem;"></i>'
                : '<span style="font-size:0.6rem;font-weight:600;color:' + (isActive ? '#fff' : '#6c757d') + ';">' + step + '</span>';
        }

        const label = document.getElementById('step-label-' + step);
        if (label) {
            label.style.color = isActive ? 'var(--hr-primary,#0d6efd)' : (isDone ? '#198754' : '#6c757d');
        }
    });

    document.querySelectorAll('.step-connector').forEach(conn => {
        const step = parseInt(conn.id.replace('step-connector-', ''), 10);
        conn.style.background = currentStep > step ? '#198754' : '#dee2e6';
    });
}

function switchStep(n) {
    if (n > currentStep) return; // can't jump ahead
    document.querySelectorAll('.step-panel').forEach(p => p.classList.add('d-none'));
    document.getElementById('panel-' + n)?.classList.remove('d-none');
    // Keep the sidebar's Overview-vs-Qualification-Checking button slot in
    // sync with whichever panel is actually visible (see the two
    // .advance-slot divs in the sidebar above -- only relevant while
    // status is "open", harmless no-op otherwise since none exist).
    document.querySelectorAll('.advance-slot').forEach(el => {
        el.classList.toggle('d-none', el.dataset.forStep !== String(n));
    });
    updateStepTracker(n);
}

// Show the active step on load
switchStep(activeStep);

// ── Advance pipeline ────────────────────────────────────────────────────────
// ── Step 5: SG/step -> compensation live preview ────────────────────────
(function () {
    const sgTable = @json(\App\Models\SalaryGrade::currentTableArray());
    const sgSel   = document.getElementById('offerSgSelect');
    const stepSel = document.getElementById('offerStepSelect');
    const hint    = document.getElementById('offerSgAmountHint');
    if (!sgSel || !stepSel || !hint) return;

    function updateOfferAmountHint() {
        const sg   = parseInt(sgSel.value, 10);
        const step = parseInt(stepSel.value, 10);
        if (sg && step && sgTable[sg] && sgTable[sg][step - 1]) {
            hint.textContent = '₱' + Number(sgTable[sg][step - 1]).toLocaleString('en-PH');
            hint.style.color = 'var(--hr-primary)';
        } else {
            hint.textContent = '\u00a0';
        }
    }

    sgSel.addEventListener('change', updateOfferAmountHint);
    stepSel.addEventListener('change', updateOfferAmountHint);
    updateOfferAmountHint();
})();

// ── Schedule info modal (triggered by clicking a session's Type badges) ─
function showScheduleInfo(el) {
    const data = JSON.parse(el.getAttribute('data-info'));

    document.getElementById('si-scheduled-at').textContent = data.scheduled_at || '—';
    document.getElementById('si-location').textContent = data.location || '—';
    document.getElementById('si-applicant-count').textContent = data.applicant_count + (data.applicant_count === 1 ? ' applicant' : ' applicants');

    const typesBody = document.getElementById('si-types-body');
    typesBody.innerHTML = '';
    (data.types || []).forEach(function (t) {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td>' + t.type + '</td><td>' + (t.status || '—') + '</td><td>' + (t.remarks || '—') + '</td>';
        typesBody.appendChild(tr);
    });

    const panelistsList = document.getElementById('si-panelists-list');
    panelistsList.innerHTML = '';
    if (!data.panelists || data.panelists.length === 0) {
        panelistsList.innerHTML = '<li class="text-muted">No panelists assigned</li>';
    } else {
        data.panelists.forEach(function (p) {
            const li = document.createElement('li');
            li.textContent = p.name + (p.email ? ' — ' + p.email : '');
            panelistsList.appendChild(li);
        });
    }

    new bootstrap.Modal(document.getElementById('scheduleInfoModal')).show();
}

function advanceStep() {
    const msgs = {
        2: 'Move this posting to Interview Scheduling? Status will update to "Interview".',
        3: 'Move this posting to Assessment & Results? Status will update to "Ranking".',
        4: 'Move this posting to Offer Management? The top-ranked passing candidate(s) for each place of assignment will be hired automatically; remaining applicants will be rejected.',
    };
    if (!confirm(msgs[currentStep] || 'Advance to next stage?')) return;

    const btn = document.getElementById('advanceBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Updating...';

    fetch('{{ route('job-postings.advance', $posting->id) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
    })
    .then(r => r.json())
    .then(() => {
        // Navigate to the CLEAN show URL, not reload() -- reload() reuses
        // whatever query string is currently in the address bar (e.g. a
        // leftover ?step=2 from an earlier "save qualification check"
        // redirect), which would clamp activeStep back down to a step
        // BEFORE the one this posting just advanced to.
        window.location.href = '{{ route('job-postings.show', $posting->id) }}';
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = 'Advance';
        alert('Failed to advance. Please try again.');
    });
}

// ── CAR public toggle ───────────────────────────────────────────────────────
document.getElementById('carPublicToggle')?.addEventListener('change', function () {
    document.getElementById('carDocTable')?.classList.toggle('public-mode', this.checked);
});

// ── Print CAR ───────────────────────────────────────────────────────────────
// Scoped print CSS added inline so it works without a separate stylesheet
if (!document.getElementById('carPrintStyle')) {
    const s = document.createElement('style');
    s.id = 'carPrintStyle';
    s.textContent = `@media print {
        body * { visibility: hidden; }
        #carDocumentPrintArea, #carDocumentPrintArea * { visibility: visible; }
        #carDocumentPrintArea { position: absolute; top: 0; left: 0; width: 100%; }
        .car-confidential.public-mode { display: none !important; }
    }`;
    document.head.appendChild(s);
}

// ── Edit scores modal ───────────────────────────────────────────────────────
document.getElementById('editScoresModal')?.addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    document.getElementById('editScoresAppId').value         = btn.dataset.applicationId;
    document.getElementById('editScoresCandidateName').textContent = btn.dataset.candidateName;
    const scores = JSON.parse(btn.dataset.scores || '{}');
    document.querySelectorAll('.score-input').forEach(input => {
        const v = scores[input.dataset.criterionId];
        input.value = (v === null || v === undefined) ? '' : v;
    });
});

// ── Qualification check modal ───────────────────────────────────────────────
document.getElementById('qualCheckModal')?.addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    const appId = btn.dataset.applicationId;
    document.getElementById('qualCheckForm').action = '/applications/' + appId + '/qualification-check';
    document.getElementById('qualCheckCandidateName').textContent = btn.dataset.candidateName;

    const check = JSON.parse(btn.dataset.check || '{}');
    const criteria = check.criteria || {};
    const selfReported = JSON.parse(btn.dataset.selfReported || '{}');

    document.querySelectorAll('.qual-actual-input').forEach(input => {
        const key = input.dataset.criterion;
        // Saved "actual" value takes precedence; otherwise fall back to the
        // candidate's self-reported value as an editable starting point.
        input.value = criteria[key]?.actual ?? selfReported[key] ?? '';
    });
    document.querySelectorAll('.qual-passed-input').forEach(input => { input.checked = false; });
    Object.keys(criteria).forEach(key => {
        const passed = criteria[key]?.passed;
        const targetId = passed === true ? 'qc_' + key + '_yes' : (passed === false ? 'qc_' + key + '_no' : null);
        if (targetId) document.getElementById(targetId)?.setAttribute('checked', 'checked'), document.getElementById(targetId).checked = true;
    });

    // Auto-suggest Qualified/Not-qualified for criteria where both the
    // requirement and the candidate's actual value are plain numbers
    // (experience years, training hours). This only pre-checks a radio
    // as a starting suggestion -- HR can still click the other option
    // before saving. Education/eligibility are never auto-suggested,
    // since matching those safely requires human judgment (degree
    // equivalencies, substitutable eligibilities, etc.), not a number
    // comparison. A criterion HR already saved a decision for (handled
    // above) is never touched here.
    const qcNumericCriteria = ['experience', 'training'];
    function qcExtractNumber(str) {
        if (!str) return null;
        const match = String(str).match(/(\d+(\.\d+)?)/);
        return match ? parseFloat(match[1]) : null;
    }
    qcNumericCriteria.forEach(key => {
        if (criteria[key]?.passed !== undefined) return; // already decided -- leave as-is
        const input = document.querySelector('.qual-actual-input[data-criterion="' + key + '"]');
        if (!input) return;
        const requiredNum = qcExtractNumber(input.dataset.required);
        const actualNum = qcExtractNumber(input.value);
        if (requiredNum === null || actualNum === null) return; // can't parse cleanly -- leave blank, HR decides
        const suggestedId = actualNum >= requiredNum ? 'qc_' + key + '_yes' : 'qc_' + key + '_no';
        const el = document.getElementById(suggestedId);
        if (el) el.checked = true;
    });

    document.getElementById('qualCheckNotes').value = check.notes ?? '';
});

// ── Qualification checking: filter by place of assignment ──────────────────
document.getElementById('qualLocationFilter')?.addEventListener('change', function () {
    const val = this.value;
    document.querySelectorAll('#panel-2 [data-location-id]').forEach(row => {
        if (!val) {
            row.style.display = ''; // show all when no filter
        } else {
            // Rows with no location (null) match all filters — don't hide them
            const rowLoc = row.dataset.locationId;
            row.style.display = (!rowLoc || rowLoc === 'null' || rowLoc === val) ? '' : 'none';
        }
    });
});

// ── Schedule modal: update applicant count when location filter changes ────
document.getElementById('schedLocationSelect')?.addEventListener('change', function () {
    // Nothing needed — the server handles filtering on submit.
    // Could show a live count here in future.
});

// ── Schedule modal: type checkboxes "select all" + at-least-one guard ──────
document.getElementById('schedTypeSelectAll')?.addEventListener('change', function () {
    document.querySelectorAll('.sched-type-checkbox').forEach(cb => cb.checked = this.checked);
});
document.querySelectorAll('.sched-type-checkbox').forEach(function (cb) {
    cb.addEventListener('change', function () {
        const boxes = document.querySelectorAll('.sched-type-checkbox');
        const checkedCount = document.querySelectorAll('.sched-type-checkbox:checked').length;
        const selectAll = document.getElementById('schedTypeSelectAll');
        if (selectAll) selectAll.checked = checkedCount === boxes.length;
    });
});
document.querySelector('#newScheduleModal form')?.addEventListener('submit', function (e) {
    const checkedCount = document.querySelectorAll('.sched-type-checkbox:checked').length;
    if (checkedCount === 0) {
        e.preventDefault();
        alert('Please select at least one schedule type.');
        return;
    }

    // Prevent duplicate schedules from a double-click or a slow request:
    // disable the submit button right away so a second click can't fire
    // the form again before the page navigates away.
    const submitBtn = this.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Scheduling...';
    }
});

// Export qualifications: fetch as blob so the button never stays stuck
// in "Exporting..." -- no dependency on a page navigation event to
// reset it (a plain <a> download never actually navigates the page).
// The anchor has data-no-loader, so page-loader.js's global click
// listener skips it and never shows the full-screen loading overlay
// for this button in the first place.
(function () {
    var btn = document.getElementById('export-qualifications-btn');
    if (!btn) return;

    btn.addEventListener('click', function (e) {
        e.preventDefault();

        // No safety net needed here: the button has data-no-loader,
        // so page-loader.js's own click listener skips it and the
        // global overlay never gets shown for this click at all.

        var url = btn.getAttribute('href');
        var originalHtml = btn.innerHTML;
        btn.classList.add('disabled');
        btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Exporting…';

        fetch(url, { credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) {
                    return response.text().then(function (text) {
                        throw new Error('Export failed (HTTP ' + response.status + '). ' + text.slice(0, 200));
                    });
                }
                var disposition = response.headers.get('Content-Disposition') || '';
                var match = disposition.match(/filename="?([^";]+)"?/);
                var filename = match ? match[1] : 'qualifications.xlsx';
                return response.blob().then(function (blob) {
                    return { blob: blob, filename: filename };
                });
            })
            .then(function (result) {
                var blobUrl = window.URL.createObjectURL(result.blob);
                var tempLink = document.createElement('a');
                tempLink.href = blobUrl;
                tempLink.download = result.filename;
                document.body.appendChild(tempLink);
                tempLink.click();
                document.body.removeChild(tempLink);
                window.URL.revokeObjectURL(blobUrl);
            })
            .catch(function (err) {
                alert('Could not export: ' + err.message);
            })
            .finally(function () {
                btn.classList.remove('disabled');
                btn.innerHTML = originalHtml;
            });
    });
})();

// Export IER: same problem/fix as the export-qualifications button above --
// fetch as blob so the button never depends on a page navigation event to
// reset it. The anchor has data-no-loader, so page-loader.js's global
// click listener skips it and never shows the full-screen overlay for
// this button in the first place.
(function () {
    var btn = document.getElementById('export-ier-btn');
    if (!btn) return;

    btn.addEventListener('click', function (e) {
        e.preventDefault();

        var url = btn.getAttribute('href');
        var originalHtml = btn.innerHTML;
        btn.classList.add('disabled');
        btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Exporting…';

        fetch(url, { credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) {
                    return response.text().then(function (text) {
                        throw new Error('Export failed (HTTP ' + response.status + '). ' + text.slice(0, 200));
                    });
                }
                var disposition = response.headers.get('Content-Disposition') || '';
                var match = disposition.match(/filename="?([^";]+)"?/);
                var filename = match ? match[1] : 'IER.xlsx';
                return response.blob().then(function (blob) {
                    return { blob: blob, filename: filename };
                });
            })
            .then(function (result) {
                var blobUrl = window.URL.createObjectURL(result.blob);
                var tempLink = document.createElement('a');
                tempLink.href = blobUrl;
                tempLink.download = result.filename;
                document.body.appendChild(tempLink);
                tempLink.click();
                document.body.removeChild(tempLink);
                window.URL.revokeObjectURL(blobUrl);
            })
            .catch(function (err) {
                alert('Could not export: ' + err.message);
            })
            .finally(function () {
                btn.classList.remove('disabled');
                btn.innerHTML = originalHtml;
            });
    });
})();

// Download template: same problem as the export button above -- a plain
// <a> file download never navigates the page, so the global page-loader's
// full-screen overlay (shown on every internal link click) would never
// get hidden again. data-no-loader stops that overlay from appearing at
// all; this handler swaps in a small inline spinner on the button itself
// instead, so there's always visible feedback that something is happening
// without freezing the whole screen.
(function () {
    var btn = document.getElementById('download-template-btn');
    if (!btn) return;

    var label = btn.querySelector('.btn-label');
    var icon = btn.querySelector('i.bi-download');
    var originalIconClass = icon ? icon.className : '';
    var originalLabel = label ? label.textContent : '';

    btn.addEventListener('click', function (e) {
        e.preventDefault();

        var url = btn.getAttribute('href');
        btn.classList.add('disabled');
        if (icon) icon.className = 'spinner-border spinner-border-sm me-1';
        if (label) label.textContent = 'Downloading…';

        fetch(url, { credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) {
                    return response.text().then(function (text) {
                        throw new Error('Download failed (HTTP ' + response.status + '). ' + text.slice(0, 200));
                    });
                }
                var disposition = response.headers.get('Content-Disposition') || '';
                var match = disposition.match(/filename="?([^";]+)"?/);
                var filename = match ? match[1] : 'template.xlsx';
                return response.blob().then(function (blob) {
                    return { blob: blob, filename: filename };
                });
            })
            .then(function (result) {
                var blobUrl = window.URL.createObjectURL(result.blob);
                var tempLink = document.createElement('a');
                tempLink.href = blobUrl;
                tempLink.download = result.filename;
                document.body.appendChild(tempLink);
                tempLink.click();
                document.body.removeChild(tempLink);
                window.URL.revokeObjectURL(blobUrl);
            })
            .catch(function (err) {
                alert('Could not download template: ' + err.message);
            })
            .finally(function () {
                btn.classList.remove('disabled');
                if (icon) icon.className = originalIconClass;
                if (label) label.textContent = originalLabel;
            });
    });
})();
</script>
@endpush
@endsection