@extends('layouts.app')

@section('title', 'View posting')
@section('page-title', 'Job posting details')

@section('content')
<div class="card mb-3">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <h5 class="mb-1">{{ $posting->title }}</h5>
                <p class="text-muted small mb-0">{{ $posting->place_of_assignment }} &middot; {{ $posting->employment_type }}</p>
            </div>
            <a href="{{ route('job-postings.edit', $posting->id) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-pencil me-1"></i> Edit
            </a>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="text-muted small">Vacancies</div>
                <div class="fw-medium">{{ $posting->vacancies }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Posted</div>
                <div class="fw-medium">{{ \Carbon\Carbon::parse($posting->posted_at)->format('M d, Y') }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Closes</div>
                <div class="fw-medium">{{ \Carbon\Carbon::parse($posting->closes_at)->format('M d, Y') }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Status</div>
                <span class="badge text-bg-success">{{ ucfirst($posting->status) }}</span>
            </div>
        </div>

        <hr>

        <div class="mb-3">
            <div class="text-muted small mb-1">Job description</div>
            <p class="mb-0">{{ $posting->description }}</p>
        </div>
        <div class="mb-3">
            <div class="text-muted small mb-1">Duties and responsibilities</div>
            <p class="mb-0">{{ $posting->duties_responsibilities }}</p>
        </div>
        <div>
            <div class="text-muted small mb-1">Qualification standards</div>
            <p class="mb-0">{{ $posting->qualification_standards }}</p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-4">
        <h6 class="mb-3">Applications for this posting</h6>
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Candidate</th>
                    <th>Applied</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($applications as $app)
                <tr>
                    <td>{{ $app->candidate_name }}</td>
                    <td>{{ \Carbon\Carbon::parse($app->applied_at)->format('M d, Y') }}</td>
                    <td><span class="badge text-bg-info">{{ str_replace('_', ' ', ucfirst($app->status)) }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection