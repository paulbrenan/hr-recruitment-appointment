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
            and places of assignment are pre-filled from the PDF table (editable).
            Rows marked ⚠️ were unreadable and need manual entry.
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
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Posted date</label>
                    <input type="date" class="form-control form-control-sm" name="rows[{{ $i }}][posted_at]" value="{{ now()->toDateString() }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Closing date</label>
                    <input type="date" class="form-control form-control-sm" name="rows[{{ $i }}][closes_at]" min="{{ now()->toDateString() }}">
                </div>
                <div class="col-12">
                    <label class="form-label small text-muted mb-1">Panelists</label>
                    <div class="border rounded p-2" style="background: #fafafa;">
                        @if ($panelists->isEmpty())
                            <div class="text-muted small mb-2">No panelists in the pool yet — add one below.</div>
                        @else
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            @foreach ($panelists as $p)
                            <label class="form-check form-check-inline border rounded px-2 py-1 small mb-0">
                                <input type="checkbox" class="form-check-input" name="rows[{{ $i }}][panelist_ids][]" value="{{ $p->id }}">
                                {{ $p->name }}
                            </label>
                            @endforeach
                        </div>
                        @endif
                        <table class="table table-sm mb-2 align-middle new-panelist-tbody-wrapper" style="font-size: 0.82rem;">
                            <tbody class="new-panelist-tbody" data-group="{{ $i }}"></tbody>
                        </table>
                        <button type="button" class="btn btn-sm btn-outline-secondary add-import-panelist" data-group="{{ $i }}">
                            <i class="bi bi-plus-lg me-1"></i> Add new panelist
                        </button>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label small text-muted mb-1">
                        Places of assignment
                        <span class="text-muted fw-normal" style="font-size: 0.72rem;">
                            — pre-filled from PDF. Edit, remove, or add rows as needed. Duplicate rows = more vacancies for that school.
                        </span>
                    </label>
                    <div class="border rounded p-2" style="background: #fafafa;">
                        <table class="table table-sm mb-2 align-middle" style="font-size: 0.82rem;">
                            <thead>
                                <tr>
                                    <th>Place of assignment</th>
                                    <th style="width: 40px;"></th>
                                </tr>
                            </thead>
                            <tbody class="location-tbody" data-group="{{ $i }}">
                                @foreach ($group['parsed_locations'] as $loc)
                                <tr class="location-import-row">
                                    <td>
                                        <div class="position-relative location-import-wrapper">
                                            @php
                                                $isTbd = ($loc['school'] === null && !$loc['unrecoverable']);
                                                $locValue = $isTbd ? 'To be determined' : ($loc['school'] ?? '');
                                                $locClass = $loc['unrecoverable'] ? 'border-warning' : ($isTbd ? 'border-warning text-muted' : '');
                                                $locPlaceholder = $loc['unrecoverable']
                                                    ? 'Row ' . $loc['row_number'] . ' unreadable — type school name manually'
                                                    : ($isTbd ? 'To be determined — update if school is now known' : 'Search or type a school...');
                                            @endphp
                                            <input
                                                type="text"
                                                class="form-control form-control-sm location-import-input {{ $locClass }}"
                                                name="rows[{{ $i }}][location_place][]"
                                                autocomplete="off"
                                                placeholder="{{ $locPlaceholder }}"
                                                value="{{ $locValue }}"
                                            >
                                            <div class="list-group position-absolute w-100 shadow-sm location-import-results"
                                                 style="z-index:1050;max-height:180px;overflow-y:auto;display:none;top:100%;"></div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        @if ($loc['unrecoverable'])
                                            <span title="This row was unreadable in the PDF" style="font-size:0.8rem;">⚠️</span>
                                        @endif
                                        <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-import-location"
                                                title="Remove row"><i class="bi bi-x-lg"></i></button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <button type="button" class="btn btn-sm btn-outline-secondary add-import-location" data-group="{{ $i }}">
                            <i class="bi bi-plus-lg me-1"></i> Add row
                        </button>
                    </div>
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
<link rel="stylesheet" href="{{ asset('css/review-polish.css') }}">
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
    // ── School search for import location rows ────────────────────────
    @php
        $importSchoolOptions = array_merge(config('schools.schools', []), config('schools.sdo_units', []));
    @endphp
    const importSchools = @json($importSchoolOptions);

    function initImportLocationRow(row) {
        const input      = row.querySelector('.location-import-input');
        const resultsBox = row.querySelector('.location-import-results');
        if (!input || input._importInited) return;
        input._importInited = true;

        function render(filter) {
            const q = filter.trim().toLowerCase();
            const matches = q === ''
                ? importSchools
                : importSchools.filter(s => s.toLowerCase().includes(q));
            resultsBox.innerHTML = '';
            if (!matches.length) { resultsBox.style.display = 'none'; return; }
            matches.slice(0, 50).forEach(function (school) {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action small py-1';
                item.textContent = school;
                item.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    input.value = school;
                    resultsBox.style.display = 'none';
                });
                resultsBox.appendChild(item);
            });
            resultsBox.style.display = 'block';
        }

        input.addEventListener('input',  () => render(input.value));
        input.addEventListener('focus',  () => render(input.value));
        input.addEventListener('blur',   () => setTimeout(() => { resultsBox.style.display = 'none'; }, 200));
    }

    // Init all existing rows on page load
    document.querySelectorAll('.location-import-row').forEach(initImportLocationRow);

    // ── New-panelist rows (name + optional email), added per group ────
    document.querySelectorAll('.add-import-panelist').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const group = this.dataset.group;
            const tbody = document.querySelector('.new-panelist-tbody[data-group="' + group + '"]');

            const row = document.createElement('tr');
            row.innerHTML =
                '<td style="width:45%;">' +
                    '<input type="text" class="form-control form-control-sm" ' +
                    'name="rows[' + group + '][new_panelist_names][]" placeholder="Panelist name">' +
                '</td>' +
                '<td style="width:45%;">' +
                    '<input type="email" class="form-control form-control-sm" ' +
                    'name="rows[' + group + '][new_panelist_emails][]" placeholder="Email (optional)">' +
                '</td>' +
                '<td class="text-center" style="width:10%;">' +
                    '<button type="button" class="btn btn-sm btn-link text-danger p-0 remove-new-panelist" title="Remove"><i class="bi bi-x-lg"></i></button>' +
                '</td>';

            tbody.appendChild(row);
        });
    });

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.remove-new-panelist');
        if (!btn) return;
        btn.closest('tr').remove();
    });

    // Remove row (keep at least 1)
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.remove-import-location');
        if (!btn) return;
        const tbody = btn.closest('tbody');
        if (tbody.querySelectorAll('.location-import-row').length <= 1) {
            // just clear it
            const input = btn.closest('tr').querySelector('.location-import-input');
            if (input) input.value = '';
            return;
        }
        btn.closest('tr').remove();
    });

    // Add row
    document.querySelectorAll('.add-import-location').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const group  = this.dataset.group;
            const tbody  = document.querySelector('.location-tbody[data-group="' + group + '"]');
            const tmpl   = tbody.querySelector('.location-import-row');
            const newRow = tmpl.cloneNode(true);
            const input  = newRow.querySelector('.location-import-input');
            const results = newRow.querySelector('.location-import-results');
            input.value = '';
            input._importInited = false;
            results.innerHTML = '';
            results.style.display = 'none';
            tbody.appendChild(newRow);
            initImportLocationRow(newRow);
            input.focus();
        });
    });

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
<script src="{{ asset('js/review-polish.js') }}"></script>
@endpush