@extends('layouts.app')

@section('title', $posting->exists ?? false ? 'Edit posting' : 'New posting')
@section('page-title', ($posting->exists ?? false) ? 'Edit job posting' : 'New job posting')

@section('content')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/form-polish.css') }}">
@endpush
<div class="card">
    <div class="card-body p-4">
        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Please fix the following:</strong>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form id="postingForm" action="{{ ($posting->exists ?? false) ? route('job-postings.update', $posting->id) : route('job-postings.store') }}" method="POST">
            @if ($posting->exists ?? false)
                @method('PUT')
            @endif
            @csrf
            <div class="row g-3">
                <div class="col-md-7">
                    <label class="form-label small fw-medium">Job title</label>
                    <div class="position-relative" id="titleSearchWrapper">
                        <input
                            type="text"
                            class="form-control"
                            id="titleSearchInput"
                            autocomplete="off"
                            placeholder="Type to search position titles..."
                            value="{{ old('title', $posting->title ?? '') }}"
                        >
                        <input type="hidden" name="title" id="titleHiddenInput" value="{{ old('title', $posting->title ?? '') }}">
                        <div
                            id="titleSearchResults"
                            class="list-group position-absolute w-100 shadow-sm"
                            style="z-index: 1050; max-height: 260px; overflow-y: auto; display: none; top: 100%;"
                        ></div>
                    </div>
                </div>
                {{-- Vacancies are now per-location below; this field is removed --}}
                <div class="col-md-2">
                    <label class="form-label small fw-medium">Salary Grade</label>
                    @php
                        $currentSg = old('salary_grade', $posting->salary_grade ?? '');
                        $currentSgNumber = $currentSg ? (int) preg_replace('/^sg-?/i', '', trim($currentSg)) : null;
                    @endphp
                    <select class="form-select" name="salary_grade">
                        <option value="">—</option>
                        @for ($sg = 1; $sg <= 33; $sg++)
                            <option value="SG-{{ $sg }}" {{ $currentSgNumber === $sg ? 'selected' : '' }}>SG-{{ $sg }}</option>
                        @endfor
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-medium">Vacancies</label>
                    <input type="number" class="form-control" name="vacancies"
                           value="{{ old('vacancies', $locations->sum('vacancies') ?: ($posting->vacancies ?? 1)) }}" min="1">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-medium">Employment type</label>
                    <select class="form-select" name="employment_type">
                        @foreach (['Regular', 'Provisional', 'Casual', 'Job Order', 'On-the-Job Trainee'] as $type)
                            <option value="{{ $type }}" {{ ($posting->employment_type ?? '') === $type ? 'selected' : '' }}>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-medium mb-2 d-block">Qualification standards</label>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Education</label>
                            <textarea class="form-control" name="qualification_education" rows="2">{{ old('qualification_education', $posting->qualification_education ?? '') }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Training</label>
                            <textarea class="form-control" name="qualification_training" rows="2">{{ old('qualification_training', $posting->qualification_training ?? '') }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Experience</label>
                            <textarea class="form-control" name="qualification_experience" rows="2">{{ old('qualification_experience', $posting->qualification_experience ?? '') }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Eligibility</label>
                            <textarea class="form-control" name="qualification_eligibility" rows="2">{{ old('qualification_eligibility', $posting->qualification_eligibility ?? '') }}</textarea>
                        </div>
                    </div>
                    @if ($posting->exists ?? false)
                        @if ($posting->qualification_standards)
                            <div class="form-text mt-2" style="font-size: 0.72rem;">
                                This posting has legacy "Qualification standards" text that predates the Education/Training/Experience/Eligibility breakdown. It's preserved and still shown on the view page, but editing here will not modify it. Fill in the fields above to add structured qualifications.
                            </div>
                        @endif
                    @endif
                </div>

                <div class="col-12">
                    <label class="form-label small fw-medium mb-2 d-block">Requirements checklist</label>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <div class="small fw-medium text-muted mb-2">Mandatory requirements</div>
                                <ul class="list-group mb-2" id="mandatoryList" style="font-size: 0.85rem;"></ul>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" id="mandatoryInput" placeholder="Type a requirement and press Add...">
                                    <button type="button" class="btn btn-outline-secondary" id="mandatoryAddBtn">Add</button>
                                </div>
                                <textarea name="mandatory_requirements" id="mandatoryHidden" class="d-none">{{ old('mandatory_requirements', $posting->mandatory_requirements ?? '') }}</textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <div class="small fw-medium text-muted mb-2">Additional requirements</div>
                                <ul class="list-group mb-2" id="additionalList" style="font-size: 0.85rem;"></ul>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" id="additionalInput" placeholder="Type a requirement and press Add...">
                                    <button type="button" class="btn btn-outline-secondary" id="additionalAddBtn">Add</button>
                                </div>
                                <textarea name="additional_requirements" id="additionalHidden" class="d-none">{{ old('additional_requirements', $posting->additional_requirements ?? '') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-medium">Duties and responsibilities</label>
                    <textarea class="form-control" name="duties_responsibilities" rows="3">{{ $posting->duties_responsibilities ?? '' }}</textarea>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-medium">Job description</label>
                    <textarea class="form-control" name="description" rows="3">{{ $posting->description ?? '' }}</textarea>
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-medium">Posted date</label>
                    <input type="date" class="form-control" name="posted_at"
                           value="{{ old('posted_at', optional($posting->posted_at ?? null)->format('Y-m-d')) }}"
                           @if (!$posting->exists) min="{{ now()->format('Y-m-d') }}" @endif>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-medium">Closes</label>
                    <input type="date" class="form-control" name="closes_at"
                           value="{{ old('closes_at', optional($posting->closes_at ?? null)->format('Y-m-d')) }}"
                           @if (!$posting->exists) min="{{ now()->format('Y-m-d') }}" @endif>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-medium">Status</label>
                    <select class="form-select" name="status">
                        @php
                            $pipelineStages = [
                                'open'                => 'Open',
                                'interview_scheduled' => 'Interview Scheduled',
                                'ranking'             => 'Ranking',
                                'closed'              => 'Closed',
                            ];
                        @endphp
                        @foreach ($pipelineStages as $value => $label)
                            <option value="{{ $value }}" {{ ($posting->status ?? 'open') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text" style="font-size: 0.72rem;">
                        Changing the stage will update all applicants on this posting.
                    </div>
                </div>
            </div>

                {{-- ── Panelist / Interview Panel ─────────────────────────── --}}
                <div class="col-12">
                    <div class="border rounded p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="small fw-medium text-muted">Interview Panel / Ranking Committee</div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="addPanelistBtn">
                                <i class="bi bi-plus-lg me-1"></i> Add panelist
                            </button>
                        </div>

                        {{-- New panelist name inputs (added dynamically) --}}
                        <div id="newPanelistInputs"></div>

                        {{-- Global panelist list --}}
                        @if ($panelists->isEmpty())
                            <p class="text-muted small mb-0" id="emptyPanelistMsg">No panelists in the system yet. Use "Add panelist" to create one.</p>
                        @else
                            <p class="text-muted small mb-2" style="font-size: 0.72rem;">Check a panelist to assign them to this posting.</p>
                            <ul class="list-group" id="panelistList">
                                @foreach ($panelists as $panelist)
                                    @php
                                        $assigned  = isset($assignedPanelists[$panelist->id]);
                                    @endphp
                                    <li class="list-group-item d-flex align-items-center gap-3 py-2" id="panelistRow{{ $panelist->id }}">
                                        {{-- Assign checkbox --}}
                                        <input
                                            type="checkbox"
                                            class="form-check-input panelist-assign-cb mt-0"
                                            name="panelist_ids[]"
                                            value="{{ $panelist->id }}"
                                            id="panelist{{ $panelist->id }}"
                                            {{ $assigned ? 'checked' : '' }}
                                        >
                                        {{-- Name + email — click either to edit both inline --}}
                                        <div class="flex-grow-1" style="min-width: 0;">
                                            <div class="d-flex align-items-center gap-2">
                                                <span
                                                    class="panelist-name-display small fw-medium"
                                                    data-panelist-id="{{ $panelist->id }}"
                                                    title="Click to edit"
                                                    style="cursor: pointer; border-bottom: 1px dashed #adb5bd;"
                                                >{{ $panelist->name }}</span>
                                                <input
                                                    type="text"
                                                    class="form-control form-control-sm panelist-name-input d-none"
                                                    data-panelist-id="{{ $panelist->id }}"
                                                    value="{{ $panelist->name }}"
                                                    placeholder="Name"
                                                    style="max-width: 200px;"
                                                >
                                                <span class="panelist-save-status small ms-1" data-panelist-id="{{ $panelist->id }}"></span>
                                            </div>
                                            <div class="d-flex align-items-center gap-2 mt-1">
                                                <span
                                                    class="panelist-email-display text-muted"
                                                    data-panelist-id="{{ $panelist->id }}"
                                                    title="Click to edit"
                                                    style="cursor: pointer; font-size: 0.72rem; border-bottom: 1px dashed #adb5bd;"
                                                >{{ $panelist->email ?: 'No email set — click to add' }}</span>
                                                <input
                                                    type="email"
                                                    class="form-control form-control-sm panelist-email-input d-none"
                                                    data-panelist-id="{{ $panelist->id }}"
                                                    value="{{ $panelist->email }}"
                                                    placeholder="Email"
                                                    style="max-width: 200px; font-size: 0.78rem;"
                                                >
                                            </div>
                                        </div>
                                        {{-- Delete button (calls a JS confirm; uses a hidden form) --}}
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-link text-danger p-0 ms-1 panelist-delete-btn"
                                            data-panelist-id="{{ $panelist->id }}"
                                            data-panelist-name="{{ $panelist->name }}"
                                            title="Remove panelist from system"
                                        ><i class="bi bi-trash"></i></button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn" style="background-color: var(--hr-primary); color: #fff;">Save posting</button>
                <a href="{{ route('job-postings.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

{{-- ── Floating save bar ──────────────────────────────────────────────── --}}
<div id="floatingSaveBar" style="
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 1040;
    background: rgba(255,255,255,0.92);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    border-top: 1px solid #dee2e6;
    padding: 10px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 -2px 12px rgba(0,0,0,0.08);
">
    <span class="small text-muted">
        @if ($posting->exists ?? false)
            Editing: <strong>{{ $posting->title ?? 'Job posting' }}</strong>
        @else
            New job posting
        @endif
    </span>
    <div class="d-flex gap-2">
        <a href="{{ route('job-postings.index') }}" class="btn btn-sm btn-outline-secondary">Cancel</a>
        <button type="button" id="floatingSaveBtn" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">
            <i class="bi bi-floppy me-1"></i> Save posting
        </button>
    </div>
</div>
{{-- Push page content up so the floating bar doesn't cover the bottom buttons --}}
<div style="height: 64px;"></div>

@push('scripts')
<script>
    // Floating save bar → submit the posting form
    document.getElementById('floatingSaveBtn').addEventListener('click', function () {
        document.getElementById('postingForm').requestSubmit();
    });

    (function () {
        const titles = @json($jobTitles ?? []);
        const searchInput = document.getElementById('titleSearchInput');
        const hiddenInput = document.getElementById('titleHiddenInput');
        const resultsBox = document.getElementById('titleSearchResults');
        const wrapper = document.getElementById('titleSearchWrapper');

        function renderResults(filter) {
            const query = filter.trim().toLowerCase();
            const matches = query === ''
                ? titles
                : titles.filter(t => t.toLowerCase().includes(query));

            resultsBox.innerHTML = '';

            if (matches.length === 0) {
                resultsBox.style.display = 'none';
                return;
            }

            matches.slice(0, 50).forEach(function (title) {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action small';
                item.textContent = title;
                item.addEventListener('click', function () {
                    searchInput.value = title;
                    hiddenInput.value = title;
                    resultsBox.style.display = 'none';
                    searchInput.classList.remove('is-invalid');
                });
                resultsBox.appendChild(item);
            });

            resultsBox.style.display = 'block';
        }

        searchInput.addEventListener('input', function () {
            hiddenInput.value = '';
            renderResults(searchInput.value);
        });

        searchInput.addEventListener('focus', function () {
            renderResults(searchInput.value);
        });

        document.addEventListener('click', function (event) {
            if (!wrapper.contains(event.target)) {
                resultsBox.style.display = 'none';
            }
        });

        // A typed title that ISN'T already in the list is now allowed
        // through -- the backend auto-registers genuinely new titles on
        // submit (see JobPostingController::autoRegisterTitle()). Only
        // block submission if the field is empty.
        searchInput.closest('form').addEventListener('submit', function (event) {
            const value = searchInput.value.trim();

            if (value === '') {
                event.preventDefault();
                searchInput.classList.add('is-invalid');
                searchInput.focus();
                renderResults(searchInput.value);

                let feedback = wrapper.querySelector('.invalid-feedback');
                if (!feedback) {
                    feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback d-block';
                    wrapper.appendChild(feedback);
                }
                feedback.textContent = 'Please enter a position title.';
            } else {
                hiddenInput.value = value;
                searchInput.classList.remove('is-invalid');
            }
        });
    })();

    function initRequirementList(listId, inputId, addBtnId, hiddenId) {
        const listEl = document.getElementById(listId);
        const inputEl = document.getElementById(inputId);
        const addBtn = document.getElementById(addBtnId);
        const hiddenEl = document.getElementById(hiddenId);

        function getItems() {
            return hiddenEl.value
                .split('\n')
                .map(line => line.trim())
                .filter(line => line !== '');
        }

        function syncHidden(items) {
            hiddenEl.value = items.join('\n');
        }

        function render() {
            const items = getItems();
            listEl.innerHTML = '';

            if (items.length === 0) {
                const empty = document.createElement('li');
                empty.className = 'list-group-item text-muted small py-2';
                empty.textContent = 'No items added yet.';
                listEl.appendChild(empty);
                return;
            }

            items.forEach(function (item, index) {
                const li = document.createElement('li');
                li.className = 'list-group-item d-flex justify-content-between align-items-start py-2';

                const span = document.createElement('span');
                span.textContent = item;
                span.style.paddingRight = '0.5rem';

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'btn btn-sm btn-link text-danger p-0';
                removeBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
                removeBtn.addEventListener('click', function () {
                    const current = getItems();
                    current.splice(index, 1);
                    syncHidden(current);
                    render();
                });

                li.appendChild(span);
                li.appendChild(removeBtn);
                listEl.appendChild(li);
            });
        }

        function addItem() {
            const value = inputEl.value.trim();
            if (value === '') {
                return;
            }
            const items = getItems();
            items.push(value);
            syncHidden(items);
            inputEl.value = '';
            render();
            inputEl.focus();
        }

        addBtn.addEventListener('click', addItem);
        inputEl.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                addItem();
            }
        });

        render();
    }

    initRequirementList('mandatoryList', 'mandatoryInput', 'mandatoryAddBtn', 'mandatoryHidden');
    initRequirementList('additionalList', 'additionalInput', 'additionalAddBtn', 'additionalHidden');

    // ── Panelist JS ──────────────────────────────────────────────────────────

    // Add new panelist input row
    let newPanelistCount = 0;
    document.getElementById('addPanelistBtn').addEventListener('click', function () {
        newPanelistCount++;
        const wrapper = document.getElementById('newPanelistInputs');
        const div = document.createElement('div');
        div.className = 'input-group input-group-sm mb-2';
        div.innerHTML = `
            <span class="input-group-text"><i class="bi bi-person-plus"></i></span>
            <input type="text" class="form-control" name="new_panelist_names[]" placeholder="New panelist name..." autocomplete="off" style="flex:1 1 45%;">
            <input type="email" class="form-control" name="new_panelist_emails[]" placeholder="Email (optional)" autocomplete="off" style="flex:1 1 45%;">
            <button type="button" class="btn btn-outline-danger" onclick="this.closest('.input-group').remove()">
                <i class="bi bi-x-lg"></i>
            </button>
        `;
        wrapper.appendChild(div);
        div.querySelector('input').focus();
    });

    // Delete panelist from system (submits a hidden DELETE form via JS)
    document.querySelectorAll('.panelist-delete-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const name = this.dataset.panelistName;
            const id   = this.dataset.panelistId;
            if (!confirm('Remove "' + name + '" from the panelist pool? This cannot be undone.')) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/panelists/' + id;
            form.innerHTML = `
                @csrf
                <input type="hidden" name="_method" value="DELETE">
            `;
            document.body.appendChild(form);
            form.submit();
        });
    });

    // ── Inline panelist name editing ─────────────────────────────────────────
    const csrfToken = document.querySelector('meta[name="csrf-token"]')
        ? document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        : '{{ csrf_token() }}';

    function savePanelist(id, statusEl) {
        const nameInput  = document.querySelector('.panelist-name-input[data-panelist-id="' + id + '"]');
        const nameDisplay = document.querySelector('.panelist-name-display[data-panelist-id="' + id + '"]');
        const emailInput  = document.querySelector('.panelist-email-input[data-panelist-id="' + id + '"]');
        const emailDisplay = document.querySelector('.panelist-email-display[data-panelist-id="' + id + '"]');

        const newName  = nameInput.value.trim();
        const newEmail = emailInput.value.trim();

        if (!newName) {
            nameInput.focus();
            return;
        }

        const unchanged = newName === nameDisplay.textContent.trim()
            && newEmail === (emailDisplay.textContent.trim() === 'No email set — click to add' ? '' : emailDisplay.textContent.trim());

        if (unchanged) {
            nameInput.classList.add('d-none');
            nameDisplay.classList.remove('d-none');
            emailInput.classList.add('d-none');
            emailDisplay.classList.remove('d-none');
            statusEl.textContent = '';
            return;
        }

        statusEl.innerHTML = '<span class="text-muted"><i class="bi bi-hourglass-split"></i></span>';

        fetch('/panelists/' + id, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ name: newName, email: newEmail }),
        })
        .then(function (res) {
            if (!res.ok) throw new Error('Server error ' + res.status);
            return res.json();
        })
        .then(function () {
            nameDisplay.textContent = newName;
            emailDisplay.textContent = newEmail || 'No email set — click to add';
            const row = nameDisplay.closest('li');
            if (row) {
                const deleteBtn = row.querySelector('.panelist-delete-btn');
                if (deleteBtn) deleteBtn.dataset.panelistName = newName;
            }
            nameInput.classList.add('d-none');
            nameDisplay.classList.remove('d-none');
            emailInput.classList.add('d-none');
            emailDisplay.classList.remove('d-none');
            statusEl.innerHTML = '<span class="text-success"><i class="bi bi-check-lg"></i></span>';
            setTimeout(() => { statusEl.textContent = ''; }, 2000);
        })
        .catch(function () {
            statusEl.innerHTML = '<span class="text-danger small">Save failed</span>';
        });
    }

    function enterEditMode(id) {
        const nameDisplay  = document.querySelector('.panelist-name-display[data-panelist-id="' + id + '"]');
        const nameInput    = document.querySelector('.panelist-name-input[data-panelist-id="' + id + '"]');
        const emailDisplay = document.querySelector('.panelist-email-display[data-panelist-id="' + id + '"]');
        const emailInput   = document.querySelector('.panelist-email-input[data-panelist-id="' + id + '"]');
        if (!nameInput || !emailInput) return;

        nameDisplay.classList.add('d-none');
        nameInput.classList.remove('d-none');
        nameInput.value = nameDisplay.textContent.trim();

        emailDisplay.classList.add('d-none');
        emailInput.classList.remove('d-none');
        emailInput.value = emailDisplay.textContent.trim() === 'No email set — click to add' ? '' : emailDisplay.textContent.trim();

        nameInput.focus();
        nameInput.select();
    }

    // Click on name OR email display → switch both to edit mode
    document.querySelectorAll('.panelist-name-display, .panelist-email-display').forEach(function (display) {
        display.addEventListener('click', function () {
            enterEditMode(this.dataset.panelistId);
        });
    });

    // Enter → save; Escape → cancel; Tab between name/email is native
    document.querySelectorAll('.panelist-name-input, .panelist-email-input').forEach(function (input) {
        const id     = input.dataset.panelistId;
        const status = document.querySelector('.panelist-save-status[data-panelist-id="' + id + '"]');

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                savePanelist(id, status);
            }
            if (e.key === 'Escape') {
                const nameDisplay  = document.querySelector('.panelist-name-display[data-panelist-id="' + id + '"]');
                const nameInput    = document.querySelector('.panelist-name-input[data-panelist-id="' + id + '"]');
                const emailDisplay = document.querySelector('.panelist-email-display[data-panelist-id="' + id + '"]');
                const emailInput   = document.querySelector('.panelist-email-input[data-panelist-id="' + id + '"]');
                nameInput.classList.add('d-none');
                nameDisplay.classList.remove('d-none');
                emailInput.classList.add('d-none');
                emailDisplay.classList.remove('d-none');
                status.textContent = '';
            }
        });

        input.addEventListener('blur', function () {
            // Small delay so Enter keydown / focus-to-sibling-field fires first
            setTimeout(function () {
                const nameInput  = document.querySelector('.panelist-name-input[data-panelist-id="' + id + '"]');
                const emailInput = document.querySelector('.panelist-email-input[data-panelist-id="' + id + '"]');
                const stillEditing = document.activeElement === nameInput || document.activeElement === emailInput;
                if (!stillEditing && !nameInput.classList.contains('d-none')) {
                    savePanelist(id, status);
                }
            }, 150);
        });
    });
</script>
<script src="{{ asset('js/form-polish.js') }}"></script>
@endpush
@endsection