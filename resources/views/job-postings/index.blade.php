@extends('layouts.app')

@section('title', 'Job postings')
@section('page-title', 'Job postings')

@section('content')
@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <p class="text-muted mb-0 small">Manage open positions, qualifications, and assignment details</p>
    </div>
    <a href="{{ route('job-postings.create') }}" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">
        <i class="bi bi-plus-lg me-1"></i> New posting
    </a>
</div>

<div class="row mb-3 g-2">
    <div class="col-md-3">
        <div class="card p-3">
            <div class="text-muted small">Open postings</div>
            <div class="fs-4 fw-semibold">{{ $postings->where('status', 'open')->count() }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3">
            <div class="text-muted small">Total vacancies</div>
            <div class="fs-4 fw-semibold">{{ $postings->sum('vacancies') }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3">
            <div class="text-muted small">Filled</div>
            <div class="fs-4 fw-semibold">{{ $postings->where('status', 'filled')->count() }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3">
            <div class="text-muted small">Closed</div>
            <div class="fs-4 fw-semibold">{{ $postings->where('status', 'closed')->count() }}</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Place of assignment</th>
                    <th>Employment type</th>
                    <th>Vacancies</th>
                    <th>Posted</th>
                    <th>Closes</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($postings as $posting)
                <tr>
                    <td class="fw-medium">{{ $posting->title }}</td>
                    <td>{{ $posting->place_of_assignment }}</td>
                    <td>{{ $posting->employment_type }}</td>
                    <td>{{ $posting->vacancies }}</td>
                    <td>{{ $posting->posted_at ? \Carbon\Carbon::parse($posting->posted_at)->format('M d, Y') : '—' }}</td>
                    <td>{{ $posting->closes_at ? \Carbon\Carbon::parse($posting->closes_at)->format('M d, Y') : '—' }}</td>
                    <td>
                        @php
                            $statusColors = [
                                'draft' => 'secondary',
                                'open' => 'success',
                                'filled' => 'primary',
                                'closed' => 'dark',
                            ];
                        @endphp
                        <span class="badge badge-status text-bg-{{ $statusColors[$posting->status] ?? 'secondary' }}">
                            {{ ucfirst($posting->status) }}
                        </span>
                    </td>
                    <td class="text-end">
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
@endsection