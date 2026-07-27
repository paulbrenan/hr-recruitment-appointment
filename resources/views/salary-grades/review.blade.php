@extends('layouts.app')

@section('title', 'Review Salary Grade Import')
@section('page-title', 'Review Salary Grade Import')

@php
    $parsed = $circular->tableArray();
    $previous = \App\Models\SalaryGrade::currentTableArray();
@endphp

@section('content')
@if (session('success'))
<div class="alert alert-success alert-dismissible fade show small py-2" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="d-flex justify-content-between align-items-start mb-3">
    <p class="text-muted small mb-0">
        Status: <span class="badge sg-status-{{ $circular->status }}">{{ ucfirst($circular->status) }}</span>
        &middot; source: {{ $circular->original_filename }}
    </p>
    <a href="{{ route('salary-grades.index') }}" class="small">&larr; Back to Salary Grade</a>
</div>

@if ($circular->status === 'processing')
<div class="alert alert-info small">Still parsing -- refresh this page in a moment.</div>
@elseif ($circular->status === 'failed')
<div class="alert alert-danger small">{{ $circular->error_message }}</div>
@else

<form method="POST" action="{{ route('salary-grades.update', $circular->id) }}">
    @csrf
    @method('PUT')

    <div class="card mb-3">
        <div class="card-body p-3">
            <h6 class="mb-3">Circular details</h6>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small">Circular No.</label>
                    <input type="text" name="circular_no" class="form-control form-control-sm" value="{{ $circular->circular_no }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Effective date</label>
                    <input type="date" name="effective_date" class="form-control form-control-sm" value="{{ $circular->effective_date?->format('Y-m-d') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Subject</label>
                    <input type="text" name="subject" class="form-control form-control-sm" value="{{ $circular->subject }}">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">Parsed salary schedule</h6>
                <span class="small text-muted">
                    <span class="sg-legend-dot sg-legend-changed"></span> changed from active schedule
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 sg-table sg-review-table">
                    <thead>
                        <tr>
                            <th>SG</th>
                            @for ($s = 1; $s <= 8; $s++)<th>Step {{ $s }}</th>@endfor
                        </tr>
                    </thead>
                    <tbody>
                        @for ($grade = 1; $grade <= 33; $grade++)
                        <tr>
                            <td class="fw-semibold">{{ $grade }}</td>
                            @for ($s = 0; $s < 8; $s++)
                                @php
                                    $val = $parsed[$grade][$s] ?? null;
                                    $prevVal = $previous[$grade][$s] ?? null;
                                    $changed = $val !== null && $prevVal !== null && (float) $val !== (float) $prevVal;
                                @endphp
                                <td>
                                    @if ($val !== null || $grade <= 32 || $s < 2)
                                    <input type="text"
                                           name="amounts[{{ $grade }}][{{ $s + 1 }}]"
                                           value="{{ $val !== null ? number_format($val, 2, '.', '') : '' }}"
                                           class="form-control form-control-sm sg-cell {{ $changed ? 'sg-cell-changed' : '' }}"
                                           placeholder="—">
                                    @endif
                                </td>
                            @endfor
                        </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-outline-secondary">Save corrections</button>
</form>

@if ($circular->status === 'ready')
<form method="POST" action="{{ route('salary-grades.confirm', $circular->id) }}" class="d-inline"
      onsubmit="return confirm('Make this the active salary schedule system-wide?');">
    @csrf
    @method('PUT')
    <button type="submit" class="btn" style="background-color: var(--hr-primary); color: #fff;">
        Confirm as active schedule
    </button>
</form>
@endif

@endif

@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/salary-grade-polish.css') }}">
@endpush
