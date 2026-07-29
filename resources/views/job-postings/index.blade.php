@extends('layouts.app')

@section('title', 'Job Vacancy')
@section('page-title', 'Job Vacancy')

@section('page-subtitle')
Manage open positions, qualifications, and assignment details
@endsection

@section('content')
<link rel="stylesheet" href="{{ asset('css/jobpostings-index-polish.css') }}">
@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="row mb-3 g-2">
    @if ($showArchived ?? false)
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Archived</div>
            <div class="fs-4 fw-semibold">{{ $postings->total() }}</div>
        </div>
    </div>
    @else
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Open</div>
            <div class="fs-4 fw-semibold">{{ $statusCounts->get('open', 0) }}</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Interview</div>
            <div class="fs-4 fw-semibold">{{ $statusCounts->get('interview_scheduled', 0) }}</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Ranking</div>
            <div class="fs-4 fw-semibold">{{ $statusCounts->get('ranking', 0) }}</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Closed</div>
            <div class="fs-4 fw-semibold">{{ $statusCounts->get('closed', 0) }}</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Total vacancies</div>
            <div class="fs-4 fw-semibold">{{ $totalVacancies }}</div>
        </div>
    </div>
    @endif
</div>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="input-group input-group-sm" style="max-width: 320px;">
        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
        <input
            type="text"
            id="jobTitleSearch"
            class="form-control"
            placeholder="Search by job title..."
            autocomplete="off"
        >
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
        <a href="{{ route('job-postings.import.create') }}" class="btn btn-sm btn-success">
            <i class="bi bi-file-earmark-pdf me-1"></i> Import from PDF
        </a>
        <a href="{{ route('job-postings.create') }}" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">
            <i class="bi bi-plus-lg me-1"></i> New posting
        </a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-top mb-0" id="jobPostingsTable" style="vertical-align: top; table-layout: fixed; width: 100%;">
            <colgroup>
                <col style="width: 32%;">  {{-- Title --}}
                <col style="width: 8%;">   {{-- Vacancies --}}
                <col style="width: 13%;">  {{-- Employment type --}}
                <col style="width: 6%;">   {{-- SG --}}
                <col style="width: 9%;">   {{-- Posted --}}
                <col style="width: 9%;">   {{-- Closes --}}
                <col style="width: 11%;">  {{-- Status --}}
                <col style="width: 12%;">  {{-- Actions — trimmed, only needs room for 3 icon buttons --}}
            </colgroup>
            <thead>
                <tr>
                    <th>Title</th>
                    <th class="text-nowrap">Vacancies</th>
                    <th style="white-space: normal; word-break: break-word;">Employment type</th>
                    <th class="text-nowrap">SG</th>
                    <th class="text-nowrap">Posted</th>
                    <th class="text-nowrap">Closes</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($postings as $posting)
                <tr class="posting-row" style="cursor: pointer; vertical-align: top;" data-href="{{ route('job-postings.show', $posting->id) }}" data-title="{{ strtolower($posting->title) }}">
                    <td class="fw-medium" style="word-break: break-word;">
                        {{ $posting->title }}
                        <div class="text-muted fw-normal" style="font-size: 0.75rem;">
                            <i class="bi bi-person-lines-fill"></i> {{ $posting->applicant_count }} {{ Str::plural('applicant', $posting->applicant_count) }}
                        </div>
                    </td>
                    <td class="text-nowrap">
                        {{ $posting->locations->sum('vacancies') ?: ($posting->vacancies ?? '—') }}
                    </td>
                    <td>{{ $posting->employment_type }}</td>
                    <td class="text-nowrap">
                        @if ($posting->salary_grade)
                            {{ Str::startsWith($posting->salary_grade, 'SG-') ? $posting->salary_grade : 'SG-' . $posting->salary_grade }}
                        @else
                            —
                        @endif
                    </td>
                    {{-- Vacancies shown per-location --}}
                    <td>{{ $posting->posted_at ? \Carbon\Carbon::parse($posting->posted_at)->format('M d, Y') : '—' }}</td>
                    <td>
                        @php
                            $closingSoon = $posting->closes_at
                                && !in_array($posting->status, ['closed', 'archived'])
                                && \Carbon\Carbon::parse($posting->closes_at)->isFuture()
                                && now()->diffInDays(\Carbon\Carbon::parse($posting->closes_at), false) <= 3;
                        @endphp
                        {{ $posting->closes_at ? \Carbon\Carbon::parse($posting->closes_at)->format('M d, Y') : '—' }}
                        @if ($closingSoon)
                            <br><span class="badge text-bg-warning" style="font-size:0.65rem;">Closing soon</span>
                        @endif
                    </td>
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
                        <span class="badge badge-status text-bg-{{ $statusColors[$posting->status] ?? 'secondary' }}" style="white-space: nowrap;">
                            {{ $statusLabels[$posting->status] ?? ucfirst($posting->status) }}
                        </span>
                    </td>
                    <td class="text-end" onclick="event.stopPropagation()" style="vertical-align: top; padding-top: 10px; white-space: nowrap;">
                        <a href="{{ route('job-postings.show', $posting->id) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-eye"></i>
                        </a>
                        @if ($posting->status === 'open')
                        <a href="{{ route('job-postings.edit', $posting->id) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        @else
                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled
                                title="This posting can no longer be edited once it's no longer open.">
                            <i class="bi bi-lock"></i>
                        </button>
                        @endif
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
<div class="mt-3">
    {{ $postings->onEachSide(1)->links() }}
</div>
@push('scripts')
<script>
    // Clickable rows
    document.querySelectorAll('.posting-row').forEach(function (row) {
        row.addEventListener('click', function () {
            window.location = this.dataset.href;
        });
    });

    // Job title search — client-side filter, all postings are already
    // rendered in the table so no extra request is needed.
    (function () {
        var searchInput = document.getElementById('jobTitleSearch');
        var table = document.getElementById('jobPostingsTable');
        if (!searchInput || !table) return;

        var rows = table.querySelectorAll('tbody tr.posting-row');

        searchInput.addEventListener('input', function () {
            var query = this.value.trim().toLowerCase();
            rows.forEach(function (row) {
                var title = row.dataset.title || '';
                row.style.display = title.indexOf(query) !== -1 ? '' : 'none';
            });
        });
    })();
</script>
<script src="{{ asset('js/jobpostings-index-polish.js') }}"></script>
@endpush
@endsection