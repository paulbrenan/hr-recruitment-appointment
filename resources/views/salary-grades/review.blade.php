@extends('layouts.app')

@section('title', 'Review Salary Grade Import')
@section('page-title', 'Review Salary Grade Import')

@section('page-subtitle')
Status: <span class="badge sg-status-{{ $circular->status }}">{{ ucfirst($circular->status) }}</span>
&middot; source: {{ $circular->original_filename }}
@endsection

@php
    $parsed = $circular->tableArray();
    $previous = \App\Models\SalaryGrade::currentTableArray();
@endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    @if (session('success'))
    <div class="alert alert-success fade show small py-2 px-3 mb-0 d-flex align-items-center gap-2" role="alert">
        <span>{{ session('success') }}</span>
        <button type="button" class="btn-close" style="font-size: 0.65rem;" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif
    <a href="{{ route('salary-grades.index') }}" class="small ms-auto">&larr; Back to Salary Grade</a>
</div>

@if ($circular->status === 'processing')
<div class="alert alert-info small">Still parsing -- refresh this page in a moment.</div>
@elseif ($circular->status === 'failed')
<div class="alert alert-danger small">{{ $circular->error_message }}</div>
@else

<form method="POST" action="{{ route('salary-grades.update', $circular->id) }}" id="salaryCorrectionsForm">
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

    </form>

<div class="d-flex justify-content-end gap-2 mt-3">
    <button type="submit" form="salaryCorrectionsForm" class="btn btn-primary">Save corrections</button>

    @if ($circular->status === 'ready')
    <form method="POST" action="{{ route('salary-grades.confirm', $circular->id) }}" class="d-inline"
          onsubmit="return confirm('Make this the active salary schedule system-wide?');">
        @csrf
        @method('PUT')
        <button type="submit" class="btn btn-success">
            Confirm as active schedule
        </button>
    </form>
    @endif
</div>

@endif

@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/salary-grade-polish.css') }}">
@endpush
