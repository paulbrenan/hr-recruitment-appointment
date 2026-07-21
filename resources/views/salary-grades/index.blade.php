@extends('layouts.app')

@section('title', 'Salary Grade')
@section('page-title', 'Salary Grade')

@section('content')
@if (session('success'))
<div class="alert alert-success alert-dismissible fade show small py-2" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if (session('error'))
<div class="alert alert-danger alert-dismissible fade show small py-2" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted small mb-0">
        @if ($currentCircular)
            Active schedule: <strong>Budget Circular No. {{ $currentCircular->circular_no ?? '—' }}</strong>
            @if ($currentCircular->effective_date)
                &middot; effective {{ $currentCircular->effective_date->format('M d, Y') }}
            @endif
        @else
            No salary schedule has been imported yet -- using the built-in default table.
        @endif
    </p>
    <a href="{{ route('salary-grades.create') }}" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">
        <i class="bi bi-upload"></i> Import new circular
    </a>
</div>

<div class="card mb-3">
    <div class="card-body p-3">
        <h6 class="mb-3">Current salary grade schedule</h6>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0 sg-table">
                <thead>
                    <tr>
                        <th>SG</th>
                        @for ($s = 1; $s <= 8; $s++)<th>Step {{ $s }}</th>@endfor
                    </tr>
                </thead>
                <tbody>
                    @forelse ($currentTable as $grade => $steps)
                    <tr>
                        <td class="fw-semibold">{{ $grade }}</td>
                        @for ($s = 0; $s < 8; $s++)
                        <td>{{ isset($steps[$s]) ? '₱' . number_format($steps[$s], 2) : '—' }}</td>
                        @endfor
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted py-3">No schedule loaded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-3">
        <h6 class="mb-3">Import history</h6>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Circular No.</th>
                        <th>Subject</th>
                        <th>Effective</th>
                        <th>Uploaded</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($circulars as $c)
                    <tr>
                        <td class="fw-medium">{{ $c->circular_no ?? '—' }}</td>
                        <td class="small">{{ $c->subject ?? '—' }}</td>
                        <td class="small">{{ $c->effective_date?->format('M d, Y') ?? '—' }}</td>
                        <td class="small text-muted">{{ $c->created_at->format('M d, Y g:i A') }}</td>
                        <td>
                            <span class="badge sg-status-{{ $c->status }}">{{ ucfirst($c->status) }}</span>
                            @if ($c->is_current)
                                <span class="badge text-bg-primary">Active</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if (in_array($c->status, ['ready', 'applied']))
                            <a href="{{ route('salary-grades.review', $c->id) }}" class="btn btn-sm btn-outline-secondary">Review</a>
                            @endif
                            @if (!$c->is_current)
                            <form method="POST" action="{{ route('salary-grades.destroy', $c->id) }}" class="d-inline" onsubmit="return confirm('Delete this import?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No imports yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $circulars->links() }}
    </div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/salary-grade-polish.css') }}">
@endpush
