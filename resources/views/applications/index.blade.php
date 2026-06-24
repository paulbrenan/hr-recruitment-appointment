@extends('layouts.app')

@section('title', 'Applications')
@section('page-title', 'Candidate applications')

@section('content')
@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0 small">Track candidate applications from submission through hiring</p>
    <div class="d-flex gap-2">
        <form method="GET" action="{{ route('applications.index') }}" class="d-flex">
            <select name="status" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                <option value="" {{ request('status') === null || request('status') === '' ? 'selected' : '' }}>All statuses</option>
                @foreach (['submitted', 'screening', 'shortlisted', 'interview_scheduled', 'assessed', 'ranked', 'offer_sent', 'offer_accepted', 'offer_declined', 'hired', 'rejected'] as $statusOption)
                    <option value="{{ $statusOption }}" {{ request('status') === $statusOption ? 'selected' : '' }}>
                        {{ str_replace('_', ' ', ucfirst($statusOption)) }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Candidate</th>
                    <th>Job posting</th>
                    <th>Applied</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($applications as $app)
                <tr>
                    <td>
                        <div class="fw-medium">{{ $app->candidate->full_name }}</div>
                        <div class="text-muted small">{{ $app->candidate->email }}</div>
                    </td>
                    <td>{{ $app->jobPosting->title }}</td>
                    <td>{{ $app->applied_at ? \Carbon\Carbon::parse($app->applied_at)->format('M d, Y') : '—' }}</td>
                    <td>
                        @php
                            $statusColors = [
                                'submitted' => 'secondary',
                                'screening' => 'info',
                                'shortlisted' => 'primary',
                                'interview_scheduled' => 'primary',
                                'assessed' => 'warning',
                                'ranked' => 'warning',
                                'offer_sent' => 'success',
                                'offer_accepted' => 'success',
                                'offer_declined' => 'danger',
                                'hired' => 'success',
                                'rejected' => 'danger',
                            ];
                        @endphp
                        <span class="badge badge-status text-bg-{{ $statusColors[$app->status] ?? 'secondary' }}">
                            {{ str_replace('_', ' ', ucfirst($app->status)) }}
                        </span>
                    </td>
                    <td class="text-end">
                        <a href="{{ route('applications.show', $app->id) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-eye"></i> View
                        </a>
                        <form action="{{ route('applications.destroy', $app->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this application? This will also delete any linked documents, interview schedules, assessments, job offers, and appointments. This cannot be undone.')">
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