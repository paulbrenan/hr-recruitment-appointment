@extends('layouts.app')

@section('title', 'Review imported postings')
@section('page-title', 'Review imported postings')

@section('content')
@if ($errors->any())
<div class="alert alert-danger small">
    @foreach ($errors->all() as $error)
        <div>{{ $error }}</div>
    @endforeach
</div>
@endif

<div class="card mb-3">
    <div class="card-body p-3">
        <div class="fw-medium small">{{ $batch->original_filename }}</div>
        <div class="text-muted small">
            {{ $grouped->count() }} position(s) detected ({{ collect($batch->candidates)->count() }} row(s) scanned from the PDF).
            Review and edit the fields below — vacancies defaults to the number of rows scanned for that position,
            and place of assignment is left blank for you to fill in manually since OCR placement isn't reliable.
            Check the ones you want to import, then confirm.
            Imported postings are created as <span class="badge text-bg-success">Open</span>.
        </div>
    </div>
</div>

<form method="POST" action="{{ route('job-postings.import.confirm', $batch->id) }}" id="importForm">
    @csrf

    @foreach ($grouped as $groupKey => $group)
    @php
        // Use the first scanned row in this group as the source for the
        // fields that should be consistent within a position (title, SG,
        // qualifications, duties). Vacancies defaults to the row count
        // (each scanned row = one detected vacancy slot in the PDF table).
        // Place of assignment is intentionally left blank -- OCR'd place
        // names are frequently wrong/garbled, so HR types the real one in.
        $first = $group['rows']->first();
        $i = $loop->index;
    @endphp
    <div class="card mb-3 candidate-row" data-group="{{ $i }}">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <input type="checkbox" class="form-check-input group-checkbox" name="selected[]" value="{{ $i }}" checked>
                <span class="fw-medium">{{ $group['label'] }}</span>
                <span class="badge text-bg-light text-dark border ms-2">{{ $group['rows']->count() }} row{{ $group['rows']->count() === 1 ? '' : 's' }} scanned</span>
            </div>
        </div>
        <div class="card-body p-3">
            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">Title</label>
                    <input type="text" class="form-control form-control-sm" name="rows[{{ $i }}][title]" value="{{ $first['title'] }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">SG</label>
                    <input type="text" class="form-control form-control-sm" name="rows[{{ $i }}][salary_grade]" value="{{ $first['salary_grade'] }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Vacancies</label>
                    <input type="number" class="form-control form-control-sm" name="rows[{{ $i }}][vacancies]" value="{{ $group['rows']->count() }}" min="1">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Status on import</label>
                    <input type="text" class="form-control form-control-sm" value="Open" disabled>
                </div>
                <div class="col-12">
                    <label class="form-label small text-muted mb-1">Place of assignment</label>
                    <input type="text" class="form-control form-control-sm" name="rows[{{ $i }}][place_of_assignment]" value="" placeholder="Enter the actual place of assignment (not reliably OCR'd — please type this in)">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Education</label>
                    <textarea class="form-control form-control-sm" name="rows[{{ $i }}][qualification_education]" rows="2">{{ $first['qualification_education'] }}</textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Training</label>
                    <textarea class="form-control form-control-sm" name="rows[{{ $i }}][qualification_training]" rows="2">{{ $first['qualification_training'] }}</textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Experience</label>
                    <textarea class="form-control form-control-sm" name="rows[{{ $i }}][qualification_experience]" rows="2">{{ $first['qualification_experience'] }}</textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Eligibility</label>
                    <textarea class="form-control form-control-sm" name="rows[{{ $i }}][qualification_eligibility]" rows="2">{{ $first['qualification_eligibility'] }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label small text-muted mb-1">Duties and responsibilities</label>
                    <textarea class="form-control form-control-sm" name="rows[{{ $i }}][duties_responsibilities]" rows="2">{{ $first['duties_responsibilities'] }}</textarea>
                </div>
            </div>
        </div>
    </div>
    @endforeach

    {{-- bottom spacer so content isn't hidden behind the floating bar --}}
    <div style="height: 80px;"></div>
</form>
@endsection

@push('scripts')
<style>
.import-fab {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 1040;
    background: #fff;
    border-top: 1px solid #dee2e6;
    padding: .75rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    box-shadow: 0 -2px 12px rgba(0,0,0,.08);
}
.import-fab .selected-count {
    font-size: .875rem;
    color: #6c757d;
}
.import-fab .selected-count strong {
    color: #212529;
}
</style>
<script>
    // ── Floating confirm bar ──────────────────────────────────────────
    var totalRows = document.querySelectorAll('input[name="selected[]"]').length;

    function updateCount() {
        var checked = document.querySelectorAll('input[name="selected[]"]:checked').length;
        document.getElementById('fab-count').innerHTML =
            '<strong>' + checked + ' of ' + totalRows + '</strong> position(s) selected';
        document.getElementById('fab-submit').disabled = checked === 0;
    }

    document.querySelectorAll('input[name="selected[]"]').forEach(function (cb) {
        cb.addEventListener('change', updateCount);
    });

    updateCount(); // initialise on page load
</script>

{{-- Floating confirm bar (outside the <form> so it uses JS submit) --}}
<div class="import-fab">
    <span class="selected-count" id="fab-count"></span>
    <div class="d-flex gap-2">
        <a href="{{ route('job-postings.import.create') }}" class="btn btn-outline-secondary btn-sm">
            Cancel
        </a>
        <button type="button" id="fab-submit"
                class="btn btn-sm"
                style="background-color: var(--hr-primary); color: #fff;"
                onclick="document.getElementById('importForm').submit()">
            <i class="bi bi-check-lg me-1"></i> Import selected postings
        </button>
    </div>
</div>
@endpush