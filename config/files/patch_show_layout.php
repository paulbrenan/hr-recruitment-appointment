<?php
/**
 * patch_show_layout.php
 *
 * Restructures application show page:
 * LEFT column:  candidate card → update status → interview schedule
 * RIGHT column: application details → personal information → qualifications
 *
 * Drop in project root, run once: php patch_show_layout.php
 * No migration needed. Delete after confirming it works.
 */

function do_backup(string $path): void {
    $bak = $path . '.bak';
    $i   = 2;
    while (file_exists($bak)) { $bak = $path . '.bak' . $i++; }
    file_put_contents($bak, file_get_contents($path));
    echo "  Backed up: $bak\n";
}

$viewPath = __DIR__ . '/resources/views/applications/show.blade.php';
if (!file_exists($viewPath)) { die("ERROR: Cannot find applications/show.blade.php\n"); }
do_backup($viewPath);

$newView = <<<'BLADE'
@extends('layouts.app')

@section('title', 'Application details')
@section('page-title', 'Application details')

@section('content')
@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
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

                <hr class="my-2">
                <form action="{{ route('applications.destroy', $application->id) }}" method="POST"
                      onsubmit="return confirm('Delete this application? This will also delete any linked documents, interview schedules, assessments, job offers, and appointments. This cannot be undone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                        <i class="bi bi-trash me-1"></i> Delete application
                    </button>
                </form>
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
                        @foreach (['submitted','screening','shortlisted','interview_scheduled','assessed','ranked','offer_sent','offer_accepted','offer_declined','hired','rejected'] as $s)
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
                    @if(!empty($application->jobPosting->place_of_assignment))
                    <div class="col-6">
                        <div class="text-muted mb-1">Place of Assignment</div>
                        <div class="fw-medium">{{ $application->jobPosting->place_of_assignment }}</div>
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
@endsection
BLADE;

file_put_contents($viewPath, $newView);
echo "  Written: resources/views/applications/show.blade.php\n";
echo "\n✓ Done.\n";
echo "  Left:  candidate card → update status → interview schedule\n";
echo "  Right: application details → personal information → qualifications\n";
echo "Delete this script when confirmed working.\n";
