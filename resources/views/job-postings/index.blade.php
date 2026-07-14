@extends('layouts.app')

@section('title', 'Job postings')
@section('page-title', 'Job postings')

@section('content')
<link rel="stylesheet" href="{{ asset('css/jobpostings-index-polish.css') }}">
@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <p class="text-muted mb-0 small">Manage open positions, qualifications, and assignment details</p>
    </div>
    <div class="d-flex gap-2">
        @if ($showArchived ?? false)
            <a href="{{ route('job-postings.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to active postings
            </a>
        @else
            <a href="{{ route('job-postings.index', ['archived' => 1]) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-archive me-1"></i> Show archived
            </a>
        @endif
        <a href="{{ route('job-postings.import.create') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-file-earmark-pdf me-1"></i> Import from PDF
        </a>
        <a href="{{ route('job-postings.create') }}" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">
            <i class="bi bi-plus-lg me-1"></i> New posting
        </a>
    </div>
</div>

<div class="row mb-3 g-2">
    @if ($showArchived ?? false)
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Archived</div>
            <div class="fs-4 fw-semibold">{{ $postings->count() }}</div>
        </div>
    </div>
    @else
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Open</div>
            <div class="fs-4 fw-semibold">{{ $postings->where('status', 'open')->count() }}</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Interview</div>
            <div class="fs-4 fw-semibold">{{ $postings->where('status', 'interview_scheduled')->count() }}</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Ranking</div>
            <div class="fs-4 fw-semibold">{{ $postings->where('status', 'ranking')->count() }}</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Closed</div>
            <div class="fs-4 fw-semibold">{{ $postings->where('status', 'closed')->count() }}</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Total vacancies</div>
            <div class="fs-4 fw-semibold">{{ $postings->sum(fn($p) => $p->locations->sum('vacancies') ?: $p->vacancies) }}</div>
        </div>
    </div>
    @endif
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-top mb-0" style="vertical-align: top; table-layout: fixed; width: 100%;">
            <colgroup>
                <col style="width: 22%;">  {{-- Title --}}
                <col style="width: 28%;">  {{-- Place of assignment --}}
                <col style="width: 10%;">  {{-- Employment type --}}
                <col style="width: 5%;">   {{-- SG --}}
                <col style="width: 8%;">   {{-- Posted --}}
                <col style="width: 8%;">   {{-- Closes --}}
                <col style="width: 8%;">   {{-- Status --}}
                <col style="width: 11%;">  {{-- Actions — tightened to fit 3 buttons without excess dead space --}}
            </colgroup>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Place of assignment</th>
                    <th class="text-nowrap">Employment type</th>
                    <th class="text-nowrap ps-4">SG</th>
                    {{-- Vacancies now shown per-location in the Places column --}}
                    <th class="text-nowrap ps-4">Posted</th>
                    <th class="text-nowrap">Closes</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($postings as $posting)
                <tr class="posting-row" style="cursor: pointer; vertical-align: top;" data-href="{{ route('job-postings.show', $posting->id) }}">
                    <td class="fw-medium" style="word-break: break-word;">{{ $posting->title }}</td>
                    <td>
                        @if ($posting->locations->isNotEmpty())
                            @php $locs = $posting->locations; $extra = $locs->count() - 2; @endphp
                            <div class="d-flex flex-column" style="gap: 2px;">
                                @foreach ($locs->take(2) as $loc)
                                    <span style="font-size: 0.82rem; line-height: 1.3;">{{ $loc->place_of_assignment }}
                                        <span class="text-muted" style="font-size: 0.75rem;">({{ $loc->vacancies }} {{ Str::plural('vacancy', $loc->vacancies) }})</span>
                                    </span>
                                @endforeach
                                @if ($extra > 0)
                                    <div class="location-extra d-none" style="margin-top: 2px;">
                                        @foreach ($locs->skip(2) as $loc)
                                            <span class="d-block" style="font-size: 0.82rem; line-height: 1.3;">{{ $loc->place_of_assignment }}
                                                <span class="text-muted" style="font-size: 0.75rem;">({{ $loc->vacancies }} {{ Str::plural('vacancy', $loc->vacancies) }})</span>
                                            </span>
                                        @endforeach
                                    </div>
                                    <button type="button"
                                        class="btn btn-link btn-sm p-0 text-start location-toggle"
                                        style="font-size: 0.75rem; text-decoration: none; color: var(--hr-primary);">
                                        +{{ $extra }} more
                                    </button>
                                @endif
                            </div>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>{{ $posting->employment_type }}</td>
                    <td class="text-nowrap ps-4">
                        @if ($posting->salary_grade)
                            {{ Str::startsWith($posting->salary_grade, 'SG-') ? $posting->salary_grade : 'SG-' . $posting->salary_grade }}
                        @else
                            —
                        @endif
                    </td>
                    {{-- Vacancies shown per-location --}}
                    <td class="ps-4">{{ $posting->posted_at ? \Carbon\Carbon::parse($posting->posted_at)->format('M d, Y') : '—' }}</td>
                    <td>{{ $posting->closes_at ? \Carbon\Carbon::parse($posting->closes_at)->format('M d, Y') : '—' }}</td>
                    <td>
                        @php
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
                        <span class="badge badge-status text-bg-{{ $statusColors[$posting->status] ?? 'secondary' }}">
                            {{ $statusLabels[$posting->status] ?? ucfirst($posting->status) }}
                        </span>
                    </td>
                    <td class="text-end" onclick="event.stopPropagation()" style="vertical-align: top; padding-top: 10px; white-space: nowrap;">
                        <a href="{{ route('job-postings.show', $posting->id) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="{{ route('job-postings.edit', $posting->id) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="{{ route('job-postings.destroy', $posting->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this job posting? This cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@push('scripts')
<script>
    // Clickable rows
    document.querySelectorAll('.posting-row').forEach(function (row) {
        row.addEventListener('click', function () {
            window.location = this.dataset.href;
        });
    });

    // Collapsible extra locations — stop click from triggering row nav
    document.querySelectorAll('.location-toggle').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            const extra = this.previousElementSibling;
            const isHidden = extra.classList.contains('d-none');
            extra.classList.toggle('d-none', !isHidden);
            this.textContent = isHidden
                ? 'Show less'
                : '+' + extra.querySelectorAll('.small').length + ' more';
        });
    });
</script>
<script src="{{ asset('js/jobpostings-index-polish.js') }}"></script>
@endpush
@endsection