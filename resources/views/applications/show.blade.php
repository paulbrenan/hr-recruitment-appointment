@extends('layouts.app')

@section('title', 'Application details')
@section('page-title', 'Application details')

@section('content')
<link rel="stylesheet" href="{{ asset('css/application-show-polish.css') }}">
@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@php
    $statusColors = [
        'submitted'           => 'secondary',
        'screening'           => 'info',
        'shortlisted'         => 'primary',
        'interview_scheduled' => 'primary',
        'assessed'            => 'primary',
        'ranked'              => 'primary',
        'offer_sent'          => 'warning',
        'offer_accepted'      => 'success',
        'offer_declined'      => 'danger',
        'hired'               => 'success',
        'rejected'            => 'danger',
    ];
    $color = $statusColors[$application->status] ?? 'secondary';

    // "shortlisted" and "assessed" are hidden from the dropdown per the
    // current workflow simplification, but if a record already carries one
    // of those statuses (from before), we still include it here so the
    // select shows the true current value instead of silently defaulting
    // to the first option.
    $statusOptions = ['submitted', 'screening', 'interview_scheduled', 'ranked', 'offer_sent', 'offer_accepted', 'offer_declined', 'hired', 'rejected'];
    if (!in_array($application->status, $statusOptions, true)) {
        $statusOptions[] = $application->status;
    }

    $check = $application->qualification_check ?? [];
    $jobPosting = $application->jobPosting;
    $candidate = $application->candidate;
@endphp

<div class="row g-3">

    {{-- ── LEFT column ──────────────────────────────────────────────────── --}}
    <div class="col-md-4 d-flex flex-column gap-3">

        {{-- Candidate card --}}
        <div class="card">
            <div class="card-body p-3 text-center">
                <h6 class="mb-0">{{ $application->candidate->full_name }}</h6>
                <p class="text-muted small mb-1">{{ $application->candidate->email }}</p>
                @if($application->candidate->phone)
                    <p class="text-muted small mb-2">
                        <i class="bi bi-telephone me-1"></i>{{ $application->candidate->phone }}
                    </p>
                @endif
                <hr class="my-2">
                <p class="small text-muted mb-1">Applying for</p>
                <p class="fw-medium small mb-0">{{ $application->jobPosting->title ?? '—' }}</p>

                @if($application->status === 'rejected')
                <hr class="my-2">
                @if($application->talentPool)
                    <span class="badge bg-success">Already in Talent Pool</span>
                @else
                    <form action="{{ route('talent-pool.store-from-application', $application->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                            <i class="bi bi-bookmark-plus me-1"></i> Add to Talent Pool
                        </button>
                    </form>
                @endif
                @endif
            </div>
        </div>

        {{-- Update status --}}
        <div class="card">
            <div class="card-header bg-white py-2">
                <span class="fw-medium small">Update Status</span>
            </div>
            <div class="card-body p-3">
                @if ($errors->any())
                    <div class="alert alert-danger small py-2">
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <form action="{{ route('applications.updateStatus', $application->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <select name="status" class="form-select form-select-sm">
                        @foreach ($statusOptions as $s)
                            <option value="{{ $s }}" {{ old('status', $application->status) === $s ? 'selected' : '' }}>
                                {{ str_replace('_', ' ', ucfirst($s)) }}
                            </option>
                        @endforeach
                    </select>
                    <textarea name="notes" class="form-control form-control-sm mt-2" rows="2"
                              placeholder="Add notes...">{{ old('notes', $application->notes) }}</textarea>
                    <button type="submit" class="btn btn-sm w-100 mt-2"
                            style="background-color: var(--hr-primary); color: #fff;">
                        Update status
                    </button>
                </form>
            </div>
        </div>

        {{-- Interview / exam schedule --}}
        <div class="card">
            <div class="card-header bg-white py-2">
                <span class="fw-medium small">Interview / Exam Schedule</span>
            </div>
            <div class="card-body p-3">
                @forelse ($schedules as $s)
                <div class="d-flex justify-content-between align-items-start border-bottom py-2">
                    <div>
                        <div class="fw-medium small">{{ str_replace('_', ' ', ucfirst($s->type)) }}</div>
                        <div class="text-muted" style="font-size:.75rem;">
                            {{ \Carbon\Carbon::parse($s->scheduled_at)->format('M d, Y h:i A') }}
                            @if($s->location) <br>{{ $s->location }} @endif
                        </div>
                    </div>
                    <span class="badge text-bg-secondary ms-2">{{ ucfirst($s->status) }}</span>
                </div>
                @empty
                <p class="text-muted small mb-2">No schedule set yet.</p>
                @endforelse
                <button class="btn btn-sm btn-outline-secondary w-100 mt-2">
                    <i class="bi bi-plus-lg me-1"></i> Schedule
                </button>
            </div>
        </div>

    </div>

    {{-- ── RIGHT column ─────────────────────────────────────────────────── --}}
    <div class="col-md-8 d-flex flex-column gap-3">

        {{-- Application details --}}
        <div class="card">
            <div class="card-header bg-white py-2">
                <span class="fw-medium small">Application Details</span>
            </div>
            <div class="card-body p-3">
                <div class="row g-2 small">
                    @if($application->transaction_number)
                    <div class="col-6">
                        <div class="text-muted mb-1">Transaction No.</div>
                        <div class="fw-medium font-monospace">{{ $application->transaction_number }}</div>
                    </div>
                    @endif
                    <div class="col-6">
                        <div class="text-muted mb-1">Date Applied</div>
                        <div class="fw-medium">
                            {{ $application->applied_at ? \Carbon\Carbon::parse($application->applied_at)->format('M d, Y') : '—' }}
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted mb-1">Position</div>
                        <div class="fw-medium">{{ $application->jobPosting->title ?? '—' }}</div>
                    </div>
                    @if(!empty($application->jobPosting->salary_grade))
                    <div class="col-6">
                        <div class="text-muted mb-1">Salary Grade</div>
                        <div class="fw-medium">SG-{{ $application->jobPosting->salary_grade }}</div>
                    </div>
                    @endif
                    @php
                        // Prefer the specific place the candidate actually
                        // picked (job_posting_location_id) over the
                        // posting's legacy place_of_assignment column,
                        // which is only kept in sync to the FIRST location
                        // for postings created before per-location places
                        // existed and is not applicant-specific.
                        $placeOfAssignment = $application->jobPostingLocation->place_of_assignment
                            ?? $application->jobPosting->place_of_assignment
                            ?? null;
                    @endphp
                    @if(!empty($placeOfAssignment))
                    <div class="col-6">
                        <div class="text-muted mb-1">Place of Assignment</div>
                        <div class="fw-medium">{{ $placeOfAssignment }}</div>
                    </div>
                    @endif
                    @if(!empty($application->jobPosting->employment_type))
                    <div class="col-6">
                        <div class="text-muted mb-1">Employment Type</div>
                        <div class="fw-medium">{{ $application->jobPosting->employment_type }}</div>
                    </div>
                    @endif
                    <div class="col-6">
                        <div class="text-muted mb-1">Current Status</div>
                        <span class="badge text-bg-{{ $color }}">
                            {{ str_replace('_', ' ', ucfirst($application->status)) }}
                        </span>
                    </div>
                    @if($application->notes)
                    <div class="col-12">
                        <div class="text-muted mb-1">Notes</div>
                        <div class="fw-medium">{{ $application->notes }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Position requirements (from the job posting this candidate applied to) --}}
        <div class="card">
            <div class="card-header bg-white py-2">
                <span class="fw-medium small">Position Requirements</span>
            </div>
            <div class="card-body p-3">
                @if ($jobPosting && ($jobPosting->qualification_education || $jobPosting->qualification_training || $jobPosting->qualification_experience || $jobPosting->qualification_eligibility))
                    <div class="row g-2 small">
                        @if ($jobPosting->qualification_education)
                        <div class="col-md-6">
                            <div class="text-muted mb-1">Education required</div>
                            <div class="fw-medium">{{ $jobPosting->qualification_education }}</div>
                        </div>
                        @endif
                        @if ($jobPosting->qualification_training)
                        <div class="col-md-6">
                            <div class="text-muted mb-1">Training required</div>
                            <div class="fw-medium">{{ $jobPosting->qualification_training }}</div>
                        </div>
                        @endif
                        @if ($jobPosting->qualification_experience)
                        <div class="col-md-6">
                            <div class="text-muted mb-1">Experience required</div>
                            <div class="fw-medium">{{ $jobPosting->qualification_experience }}</div>
                        </div>
                        @endif
                        @if ($jobPosting->qualification_eligibility)
                        <div class="col-md-6">
                            <div class="text-muted mb-1">Eligibility required</div>
                            <div class="fw-medium">{{ $jobPosting->qualification_eligibility }}</div>
                        </div>
                        @endif
                    </div>
                @else
                    <p class="text-muted small mb-0">No qualification standards specified for this posting.</p>
                @endif
            </div>
        </div>

        {{-- Qualification checklist --}}
        <div class="card">
            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                <span class="fw-medium small">Qualification Checklist</span>
                @if ($application->qualification_result)
                    <span class="badge text-bg-{{ $application->qualification_result === 'qualified' ? 'success' : 'danger' }}">
                        {{ ucfirst($application->qualification_result) }}
                    </span>
                @endif
            </div>
            <div class="card-body p-3">
                <form action="{{ route('applications.qualification-check', $application->id) }}" method="POST">
                    @csrf

                    {{-- Notice header fields — typed manually each time, per the current workflow --}}
                    <div class="row g-2 small mb-3">
                        <div class="col-sm-6">
                            <label class="text-muted mb-1 d-block" for="item_number">Plantilla Item No.</label>
                            <input type="text" name="item_number" id="item_number" class="form-control form-control-sm"
                                   placeholder="e.g. OSEC-DECSB-ADOF4-123456-2015"
                                   value="{{ old('item_number', $check['item_number'] ?? '') }}">
                        </div>
                        <div class="col-sm-6">
                            <label class="text-muted mb-1 d-block" for="chair_name">Sub-Committee Chair</label>
                            <input type="text" name="chair_name" id="chair_name" class="form-control form-control-sm"
                                   placeholder="Full name"
                                   value="{{ old('chair_name', $check['chair_name'] ?? '') }}">
                        </div>
                        <div class="col-sm-6">
                            <label class="text-muted mb-1 d-block" for="evaluation_date">Evaluation Date</label>
                            <input type="date" name="evaluation_date" id="evaluation_date" class="form-control form-control-sm"
                                   value="{{ old('evaluation_date', $check['evaluation_date'] ?? '') }}">
                        </div>
                    </div>

                    {{--
                        Per-criterion rows matching the official CSC-format notice:
                        each row = required QS (reference only) + the candidate's
                        actual qualification text (typed by HR) + Qualified/Not
                        qualified radio buttons. Overall result = qualified only
                        if every row is marked Qualified (see
                        ApplicationController::saveQualificationCheck).
                    --}}
                    @php
                        $criteriaFields = [
                            'education' => ['label' => 'Education', 'required' => $jobPosting->qualification_education ?? null],
                            'experience' => ['label' => 'Experience', 'required' => $jobPosting->qualification_experience ?? null],
                            'training' => ['label' => 'Training', 'required' => $jobPosting->qualification_training ?? null],
                            'eligibility' => ['label' => 'Eligibility', 'required' => $jobPosting->qualification_eligibility ?? null],
                        ];
                    @endphp

                    <div class="mb-2">
                        @foreach ($criteriaFields as $key => $meta)
                            @php
                                $rowActual = old($key . '_actual', $check['criteria'][$key]['actual'] ?? '');
                                $rowPassed = old($key . '_passed', array_key_exists($key, $check['criteria'] ?? []) ? ($check['criteria'][$key]['passed'] ? '1' : '0') : null);
                            @endphp
                            <div class="border-bottom py-2">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <label class="fw-medium mb-0">{{ $meta['label'] }}</label>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <input type="radio" class="btn-check" name="{{ $key }}_passed" value="1"
                                               id="{{ $key }}_passed_yes" autocomplete="off"
                                               {{ $rowPassed === '1' ? 'checked' : '' }}>
                                        <label class="btn btn-outline-success" for="{{ $key }}_passed_yes" style="font-size:.7rem; padding:.15rem .5rem;">Qualified</label>

                                        <input type="radio" class="btn-check" name="{{ $key }}_passed" value="0"
                                               id="{{ $key }}_passed_no" autocomplete="off"
                                               {{ $rowPassed === '0' ? 'checked' : '' }}>
                                        <label class="btn btn-outline-danger" for="{{ $key }}_passed_no" style="font-size:.7rem; padding:.15rem .5rem;">Not qualified</label>
                                    </div>
                                </div>
                                @if ($meta['required'])
                                    <div class="text-muted mb-1" style="font-size:.75rem;">
                                        Required: {{ $meta['required'] }}
                                    </div>
                                @endif
                                <input type="text" name="{{ $key }}_actual" class="form-control form-control-sm"
                                       placeholder="Candidate's actual {{ strtolower($meta['label']) }}..."
                                       value="{{ $rowActual }}">
                            </div>
                        @endforeach
                    </div>

                    <textarea name="check_notes" class="form-control form-control-sm mb-2" rows="2"
                              placeholder="Notes about this qualification check...">{{ old('check_notes', $check['notes'] ?? '') }}</textarea>

                    <button type="submit" class="btn btn-sm btn-outline-secondary w-100">
                        Save qualification check
                    </button>
                </form>

                @if ($application->qualification_result)
                    <hr class="my-2">
                    <form action="{{ route('applications.qualification-notice', $application->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-sm w-100" style="background-color: var(--hr-primary); color: #fff;">
                            {{ $application->qualification_notified_at ? 'Resend result email' : 'Email result to candidate' }}
                        </button>
                    </form>
                    @if ($application->qualification_notified_at)
                        <p class="text-muted mb-0 mt-2" style="font-size:.75rem;">
                            Last sent {{ \Carbon\Carbon::parse($application->qualification_notified_at)->format('M d, Y h:i A') }}
                        </p>
                    @endif
                @endif
            </div>
        </div>

        {{-- Personal information --}}
        <div class="card">
            <div class="card-header bg-white py-2">
                <span class="fw-medium small">Personal Information</span>
            </div>
            <div class="card-body p-3">
                <div class="row g-2 small">
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Full Name</div>
                        <div class="fw-medium">{{ $application->candidate->full_name }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Email</div>
                        <div class="fw-medium">{{ $application->candidate->email }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Contact No.</div>
                        <div class="fw-medium">{{ $application->candidate->phone ?? '—' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Age</div>
                        <div class="fw-medium">{{ $application->candidate->age ?? '—' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Sex</div>
                        <div class="fw-medium">{{ $application->candidate->sex ?? '—' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Civil Status</div>
                        <div class="fw-medium">{{ $application->candidate->civil_status ?? '—' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Religion</div>
                        <div class="fw-medium">{{ $application->candidate->religion ?? '—' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Disability</div>
                        <div class="fw-medium">{{ $application->candidate->disability ?? '—' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Ethnic Group</div>
                        <div class="fw-medium">{{ $application->candidate->ethnic_group ?? '—' }}</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted mb-1">Address</div>
                        <div class="fw-medium">{{ $application->candidate->address ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Qualifications --}}
        <div class="card">
            <div class="card-header bg-white py-2">
                <span class="fw-medium small">Qualifications</span>
            </div>
            <div class="card-body p-3">
                <div class="row g-2 small">
                    <div class="col-12">
                        <div class="text-muted mb-1">Highest Educational Attainment</div>
                        <div class="fw-medium">{{ $application->candidate->education ?? '—' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Training Hours</div>
                        <div class="fw-medium">{{ $application->candidate->training_hours ?? '—' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Years of Experience</div>
                        <div class="fw-medium">{{ $application->candidate->years_experience ?? '—' }}</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted mb-1">Eligibility</div>
                        <div class="fw-medium">{{ $application->candidate->eligibility ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

@push('scripts')
<script src="{{ asset('js/application-show-polish.js') }}"></script>
@endpush
@endsection