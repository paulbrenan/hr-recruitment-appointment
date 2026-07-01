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
            {{ collect($batch->candidates)->count() }} candidate posting(s) detected across {{ $grouped->count() }} position(s).
            Review and edit the fields below, check the ones you want to import, then confirm.
            Imported postings are created as <span class="badge text-bg-secondary">Draft</span> so you can publish them when ready.
        </div>
    </div>
</div>

<form method="POST" action="{{ route('job-postings.import.confirm', $batch->id) }}" id="importForm">
    @csrf

    @php $globalIndex = 0; @endphp
    @foreach ($grouped as $groupKey => $group)
    <div class="card mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#group_{{ $loop->index }}">
            <div>
                <span class="fw-medium">{{ $group['label'] }}</span>
                <span class="badge text-bg-light text-dark border ms-2">{{ $group['rows']->count() }} row{{ $group['rows']->count() === 1 ? '' : 's' }}</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary select-all-btn" data-group="{{ $loop->index }}">Select all</button>
                <button type="button" class="btn btn-sm btn-outline-secondary deselect-all-btn" data-group="{{ $loop->index }}">Deselect all</button>
                <i class="bi bi-chevron-down"></i>
            </div>
        </div>
        <div class="collapse show" id="group_{{ $loop->index }}">
            <div class="card-body p-3">
                @foreach ($group['rows'] as $row)
                @php $i = $globalIndex; $globalIndex++; @endphp
                <div class="border rounded p-3 mb-2 candidate-row" data-group="{{ $loop->parent->index }}">
                    <div class="d-flex align-items-start gap-2 mb-2">
                        <input type="checkbox" class="form-check-input mt-1" name="selected[]" value="{{ $i }}" checked>
                        <div class="flex-grow-1 row g-2">
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-1">Title</label>
                                <input type="text" class="form-control form-control-sm" name="rows[{{ $i }}][title]" value="{{ $row['title'] }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-muted mb-1">SG</label>
                                <input type="text" class="form-control form-control-sm" name="rows[{{ $i }}][salary_grade]" value="{{ $row['salary_grade'] }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-muted mb-1">Vacancies</label>
                                <input type="number" class="form-control form-control-sm" name="rows[{{ $i }}][vacancies]" value="{{ $row['vacancies'] }}" min="1">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-muted mb-1">Status on import</label>
                                <input type="text" class="form-control form-control-sm" value="Draft" disabled>
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-muted mb-1">Place of assignment</label>
                                <input type="text" class="form-control form-control-sm" name="rows[{{ $i }}][place_of_assignment]" value="{{ $row['place_of_assignment'] }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1">Education</label>
                                <textarea class="form-control form-control-sm" name="rows[{{ $i }}][qualification_education]" rows="2">{{ $row['qualification_education'] }}</textarea>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1">Training</label>
                                <textarea class="form-control form-control-sm" name="rows[{{ $i }}][qualification_training]" rows="2">{{ $row['qualification_training'] }}</textarea>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1">Experience</label>
                                <textarea class="form-control form-control-sm" name="rows[{{ $i }}][qualification_experience]" rows="2">{{ $row['qualification_experience'] }}</textarea>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1">Eligibility</label>
                                <textarea class="form-control form-control-sm" name="rows[{{ $i }}][qualification_eligibility]" rows="2">{{ $row['qualification_eligibility'] }}</textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-muted mb-1">Duties and responsibilities</label>
                                <textarea class="form-control form-control-sm" name="rows[{{ $i }}][duties_responsibilities]" rows="2">{{ $row['duties_responsibilities'] }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
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
    document.querySelectorAll('.select-all-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const group = btn.getAttribute('data-group');
            document.querySelectorAll('.candidate-row[data-group="' + group + '"] input[type="checkbox"]').forEach(function (cb) {
                cb.checked = true;
            });
        });
    });

    document.querySelectorAll('.deselect-all-btn').forEach(function (btn) {
        btn.addEventListener('click', function (event) {
            event.stopPropagation();
            const group = btn.getAttribute('data-group');
            document.querySelectorAll('.candidate-row[data-group="' + group + '"] input[type="checkbox"]').forEach(function (cb) {
                cb.checked = false;
            });
        });
    });

    // Prevent the select-all/deselect-all buttons from also toggling the
    // collapse, since they sit inside the clickable card-header.
    document.querySelectorAll('.select-all-btn, .deselect-all-btn').forEach(function (btn) {
        btn.addEventListener('click', function (event) {
            event.stopPropagation();
        });
    });

    // ── Floating confirm bar ──────────────────────────────────────────
    var totalRows = document.querySelectorAll('input[name="selected[]"]').length;

    function updateCount() {
        var checked = document.querySelectorAll('input[name="selected[]"]:checked').length;
        document.getElementById('fab-count').innerHTML =
            '<strong>' + checked + ' of ' + totalRows + '</strong> posting(s) selected';
        document.getElementById('fab-submit').disabled = checked === 0;
    }

    document.querySelectorAll('input[name="selected[]"]').forEach(function (cb) {
        cb.addEventListener('change', updateCount);
    });

    // Also update when select-all / deselect-all buttons are used
    document.querySelectorAll('.select-all-btn, .deselect-all-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setTimeout(updateCount, 0);
        });
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