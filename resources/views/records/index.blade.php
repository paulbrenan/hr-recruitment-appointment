{{-- TODO: swap 'layouts.app' for whatever your actual admin layout is called
     (this file wasn't provided, so this is a guess) --}}
@extends('layouts.app')

@section('title', 'Records')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Records — Pending Application Codes</h4>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<table class="table table-bordered align-middle">
    <thead>
        <tr>
            <th>Applicant</th>
            <th>Position</th>
            <th>Submitted</th>
            <th class="text-end">Action</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($pending as $application)
        <tr>
            <td>{{ $application->candidate->full_name ?? '—' }}</td>
            <td>{{ $application->jobPosting->title ?? '—' }}</td>
            <td>{{ $application->applied_at?->format('M d, Y') }}</td>
            <td class="text-end">
                <form action="{{ route('records.assign-code', $application->id) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary"
                        onclick="return confirm('Confirm requirements have been checked. This will assign the Application Code and email it to the applicant.');">
                        Assign Code
                    </button>
                </form>
            </td>
        </tr>
        @empty
        <tr><td colspan="4" class="text-center text-muted">No applications pending a code.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection