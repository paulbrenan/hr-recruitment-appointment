@extends('layouts.app')

@section('title', 'Extracted text')
@section('page-title', 'Extracted text')

@section('content')
<link rel="stylesheet" href="{{ asset('css/extracted-polish.css') }}">
<div class="card mb-3">
    <div class="card-body p-3 d-flex justify-content-between align-items-center">
        <div>
            <div class="fw-medium small">{{ $originalName }}</div>
            <div class="text-muted small">{{ $pageCount }} page{{ $pageCount === 1 ? '' : 's' }} extracted</div>
        </div>
        <a href="{{ route('job-postings.import.create') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Try another PDF
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body p-4">
        <p class="text-muted small mb-3">
            Raw text per page, exactly as extracted by the PDF parser. Use this to check where the
            cover-memo content ends and the "LIST OF VACANT POSITIONS" section begins, and whether
            tables/headings come through cleanly.
        </p>

        <div class="accordion" id="pageAccordion">
            @foreach ($pageTexts as $page)
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#page{{ $page['number'] }}">
                        Page {{ $page['number'] }}
                    </button>
                </h2>
                <div id="page{{ $page['number'] }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}">
                    <div class="accordion-body">
                        <pre class="small mb-0" style="white-space: pre-wrap; font-family: var(--font-mono, monospace);">{{ $page['text'] }}</pre>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

<script src="{{ asset('js/extracted-polish.js') }}"></script>
@endsection