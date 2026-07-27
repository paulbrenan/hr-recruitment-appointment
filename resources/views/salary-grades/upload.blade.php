@extends('layouts.app')

@section('title', 'Import Salary Grade Schedule')
@section('page-title', 'Import Salary Grade Schedule')

@section('content')
<p class="text-muted small mb-3">Upload the DBM Budget Circular (PDF) or an Excel/CSV export of the Annex A salary schedule.</p>

@if ($errors->any())
<div class="alert alert-danger small py-2">
    <ul class="mb-0">
        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<div class="card">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('salary-grades.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="mb-3">
                <label class="form-label small fw-medium">Circular / schedule file</label>
                <input type="file" name="sg_file" class="form-control" accept=".pdf,.xlsx,.xls,.csv" required>
                <div class="form-text">PDF (scanned or digital) or Excel/CSV, up to 20MB.</div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label small fw-medium">Circular No. <span class="text-muted">(optional)</span></label>
                    <input type="text" name="circular_no" class="form-control form-control-sm" placeholder="e.g. 601" value="{{ old('circular_no') }}">
                    <div class="form-text">Leave blank to auto-detect from the PDF text.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-medium">Effective date <span class="text-muted">(optional)</span></label>
                    <input type="date" name="effective_date" class="form-control form-control-sm" value="{{ old('effective_date') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-medium">Subject <span class="text-muted">(optional)</span></label>
                    <input type="text" name="subject" class="form-control form-control-sm" value="{{ old('subject') }}">
                </div>
            </div>

            <div class="alert alert-info small py-2 mb-3">
                This won't change anything system-wide yet -- you'll review the parsed
                table on the next screen and confirm it before it becomes the active schedule.
            </div>

            <button type="submit" class="btn" style="background-color: var(--hr-primary); color: #fff;">
                Upload &amp; parse
            </button>
            <a href="{{ route('salary-grades.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/salary-grade-polish.css') }}">
@endpush
