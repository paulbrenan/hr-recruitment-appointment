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