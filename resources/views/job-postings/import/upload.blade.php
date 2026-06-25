@extends('layouts.app')

@section('title', 'Import job postings from PDF')
@section('page-title', 'Import job postings from PDF')

@section('content')
<div class="card">
    <div class="card-body p-4">
        <p class="text-muted small mb-3">
            Upload a "Call for Application" PDF (e.g. a DepEd Division Memorandum) to extract its text.
            <strong>This is a diagnostic step</strong> — it shows the raw extracted text per page so we can
            confirm extraction works correctly before building the actual posting parser.
        </p>

        @if ($errors->any())
        <div class="alert alert-danger small">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
        @endif

        <form method="POST" action="{{ route('job-postings.import.extract') }}" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label class="form-label small fw-medium">PDF file</label>
                <input type="file" class="form-control" name="pdf_file" accept="application/pdf" required>
                <div class="form-text" style="font-size: 0.72rem;">Max 20MB.</div>
            </div>
            <button type="submit" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">
                <i class="bi bi-file-earmark-text me-1"></i> Extract text
            </button>
            <a href="{{ route('job-postings.index') }}" class="btn btn-sm btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>
@endsection