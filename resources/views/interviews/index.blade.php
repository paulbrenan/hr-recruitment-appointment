@extends('layouts.app')

@section('title', 'Scheduling')
@section('page-title', 'Open ranking, interview & exam scheduling')

@section('content')
@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0 small">Schedule and track interviews, exams, and open ranking sessions</p>
    <button class="btn btn-sm sched-new-btn" data-bs-toggle="modal" data-bs-target="#newScheduleModal">
        <i class="bi bi-plus-lg me-1"></i> New schedule
    </button>
</div>

<div class="card sched-table-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0 sched-table">
            <thead>
                <tr>
                    <th>Candidate</th>
                    <th>Type</th>
                    <th>Date &amp; time</th>
                    <th>Location</th>
                    <th>Interviewer</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($schedules as $s)
                <tr>
                    <td class="fw-medium">{{ $s->application->candidate->full_name }}</td>
                    <td>
                        @foreach (explode(',', $s->type) as $t)
                            <span class="badge sched-type-badge" data-type="{{ trim($t) }}">{{ str_replace('_', ' ', ucfirst(trim($t))) }}</span>
                        @endforeach
                    </td>
                    <td>{{ $s->scheduled_at ? \Carbon\Carbon::parse($s->scheduled_at)->format('M d, Y h:i A') : '—' }}</td>
                    <td>{{ $s->location ?? '—' }}</td>
                    <td>
                        @if ($s->panelists->isNotEmpty())
                            {{ $s->panelists->pluck('name')->implode(', ') }}
                        @elseif ($s->interviewer_name)
                            {{ $s->interviewer_name }}
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @php
                            $colors = ['scheduled' => 'primary', 'completed' => 'success', 'cancelled' => 'danger', 'no_show' => 'secondary'];
                        @endphp
                        <span class="badge badge-status text-bg-{{ $colors[$s->status] ?? 'secondary' }}">{{ str_replace('_', ' ', ucfirst($s->status)) }}</span>
                    </td>
                    <td class="text-end">
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary"
                            data-bs-toggle="modal"
                            data-bs-target="#editScheduleModal"
                            data-id="{{ $s->id }}"
                            data-application-id="{{ $s->application_id }}"
                            data-type="{{ $s->type }}"
                            data-scheduled-at="{{ \Carbon\Carbon::parse($s->scheduled_at)->format('Y-m-d\TH:i') }}"
                            data-location="{{ $s->location }}"
                            data-job-posting-id="{{ $s->application->job_posting_id }}"
                            data-panelist-ids="{{ json_encode($s->panelists->pluck('id')) }}"
                            data-status="{{ $s->status }}"
                            data-remarks="{{ $s->remarks }}"
                        >
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form action="{{ route('interviews.destroy', $s->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this schedule? This cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="newScheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content sched-modal">
            <form action="{{ route('interviews.store') }}" method="POST">
                @csrf
                <div class="modal-header sched-modal-header">
                    <h6 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>Schedule interview / exam</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small">Application</label>
                        <select name="application_id" class="form-select form-select-sm" required>
                            <option value="" disabled selected>Select candidate / application</option>
                            @foreach ($applications as $application)
                                <option value="{{ $application->id }}" data-job-posting-id="{{ $application->job_posting_id }}">
                                    {{ $application->candidate->full_name }} — {{ $application->jobPosting->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Type</label>
                        <div class="sched-type-group" role="group">
                            <input type="checkbox" class="btn-check sched-type-check" name="type_display" value="open_ranking" id="new_type_open_ranking" autocomplete="off" checked>
                            <label class="btn sched-type-btn" for="new_type_open_ranking">Open ranking</label>

                            <input type="checkbox" class="btn-check sched-type-check" name="type_display" value="interview" id="new_type_interview" autocomplete="off">
                            <label class="btn sched-type-btn" for="new_type_interview">Interview</label>

                            <input type="checkbox" class="btn-check sched-type-check" name="type_display" value="exam" id="new_type_exam" autocomplete="off">
                            <label class="btn sched-type-btn" for="new_type_exam">Exam</label>

                            <span class="sched-type-sep"></span>

                            <input type="checkbox" class="btn-check sched-type-selectall" id="new_type_selectall" autocomplete="off">
                            <label class="btn sched-type-btn sched-type-btn-all" for="new_type_selectall"><i class="bi bi-check2-all me-1"></i>Select all</label>
                        </div>
                        <input type="hidden" name="type" id="new_type" value="open_ranking">
                        <div class="sched-type-hint text-muted">Select one or more that apply.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Date &amp; time</label>
                        <div class="sched-datetime-confirm">
                            <input type="datetime-local" name="scheduled_at" class="form-control form-control-sm sched-datetime-input" required>
                            <button type="button" class="btn btn-sm sched-confirm-btn" title="Confirm date & time">
                                <i class="bi bi-check-lg"></i>
                            </button>
                        </div>
                        <div class="sched-datetime-hint text-muted">Press Enter or tap the check to confirm.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Location</label>
                        <input type="text" name="location" class="form-control form-control-sm">
                    </div>
                    {{-- Panelist checklist — populated via AJAX when application is selected --}}
                    <div class="mb-2" id="newPanelistSection">
                        <label class="form-label small">Vacancy for Screening / Interview</label>
                        <div id="newPanelistList" class="border rounded p-2 sched-panelist-box">
                            <span class="text-muted small" id="newPanelistPlaceholder">Select an application above to load panelists.</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer sched-modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm sched-submit-btn">Send invitation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editScheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content sched-modal">
            <form id="editScheduleForm" action="" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header sched-modal-header">
                    <h6 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit schedule</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small">Application</label>
                        <select name="application_id" id="edit_application_id" class="form-select form-select-sm" required>
                            @foreach ($applications as $application)
                                <option value="{{ $application->id }}">
                                    {{ $application->candidate->full_name }} — {{ $application->jobPosting->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Type</label>
                        <div class="sched-type-group" role="group">
                            <input type="checkbox" class="btn-check sched-type-check" name="type_display" value="open_ranking" id="edit_type_open_ranking" autocomplete="off">
                            <label class="btn sched-type-btn" for="edit_type_open_ranking">Open ranking</label>

                            <input type="checkbox" class="btn-check sched-type-check" name="type_display" value="interview" id="edit_type_interview" autocomplete="off">
                            <label class="btn sched-type-btn" for="edit_type_interview">Interview</label>

                            <input type="checkbox" class="btn-check sched-type-check" name="type_display" value="exam" id="edit_type_exam" autocomplete="off">
                            <label class="btn sched-type-btn" for="edit_type_exam">Exam</label>

                            <span class="sched-type-sep"></span>

                            <input type="checkbox" class="btn-check sched-type-selectall" id="edit_type_selectall" autocomplete="off">
                            <label class="btn sched-type-btn sched-type-btn-all" for="edit_type_selectall"><i class="bi bi-check2-all me-1"></i>Select all</label>
                        </div>
                        <input type="hidden" name="type" id="edit_type" value="open_ranking">
                        <div class="sched-type-hint text-muted">Select one or more that apply.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Date &amp; time</label>
                        <div class="sched-datetime-confirm">
                            <input type="datetime-local" name="scheduled_at" id="edit_scheduled_at" class="form-control form-control-sm sched-datetime-input" required>
                            <button type="button" class="btn btn-sm sched-confirm-btn" title="Confirm date & time">
                                <i class="bi bi-check-lg"></i>
                            </button>
                        </div>
                        <div class="sched-datetime-hint text-muted">Press Enter or tap the check to confirm.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Location</label>
                        <input type="text" name="location" id="edit_location" class="form-control form-control-sm">
                    </div>
                    {{-- Panelist checklist — populated when modal opens --}}
                    <div class="mb-2" id="editPanelistSection">
                        <label class="form-label small">Vacancy for Screening / Interview</label>
                        <div id="editPanelistList" class="border rounded p-2 sched-panelist-box">
                            <span class="text-muted small" id="editPanelistPlaceholder">Loading panelists...</span>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Status</label>
                        <select name="status" id="edit_status" class="form-select form-select-sm">
                            <option value="scheduled">Scheduled</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="no_show">No show</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Remarks</label>
                        <textarea name="remarks" id="edit_remarks" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer sched-modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm sched-submit-btn">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    /* ── Table ─────────────────────────────────────────────────────────── */
    .sched-table-card {
        border: 1px solid rgba(0, 0, 0, .06);
        box-shadow: 0 1px 3px rgba(0, 0, 0, .04);
    }
    .sched-table thead th {
        font-size: .7rem;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #6b7280;
        background: #f8f9fb;
        border-bottom: 1px solid #e9ecef;
    }
    .sched-table tbody tr {
        transition: background-color .12s ease;
    }
    .sched-table tbody tr:hover {
        background-color: rgba(0, 92, 82, .04);
    }

    /* ── Type badges — colored per type ───────────────────────────────── */
    .sched-type-badge {
        font-weight: 500;
        border: 1px solid transparent;
    }
    .sched-type-badge[data-type="open_ranking"] {
        background: #fff4d6; color: #8a6d1d; border-color: #f0dfa4;
    }
    .sched-type-badge[data-type="interview"] {
        background: #e3f1ee; color: var(--hr-primary, #005c52); border-color: #bfe0d9;
    }
    .sched-type-badge[data-type="exam"] {
        background: #eae6f7; color: #4b2f8f; border-color: #d6cdef;
    }

    /* ── New schedule button ──────────────────────────────────────────── */
    .sched-new-btn {
        background-color: var(--hr-primary, #005c52);
        color: #fff;
        border: none;
        transition: filter .15s ease, transform .1s ease;
    }
    .sched-new-btn:hover { filter: brightness(1.08); color: #fff; }
    .sched-new-btn:active { transform: scale(.98); }

    /* ── Modal shell ───────────────────────────────────────────────────── */
    .sched-modal {
        border: none;
        border-radius: .6rem;
        overflow: hidden;
    }
    .sched-modal-header {
        background: linear-gradient(135deg, var(--hr-primary, #005c52), #00463f);
        color: #fff;
        border-bottom: none;
    }
    .sched-modal-header .modal-title {
        color: #fff;
        font-weight: 600;
    }
    .sched-modal-footer {
        border-top: 1px solid #eef0f2;
        background: #fbfbfc;
    }
    .sched-submit-btn {
        background-color: var(--hr-primary, #005c52);
        color: #fff;
        border: none;
    }
    .sched-submit-btn:hover { filter: brightness(1.08); color: #fff; }

    /* ── Type pills (checkbox-styled, single-select) ─────────────────────── */
    .sched-type-group {
        display: flex;
        gap: .5rem;
        flex-wrap: wrap;
    }
    .sched-type-btn {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        font-size: .78rem;
        padding: .3rem .75rem;
        border-radius: 999px;
        border: 1px solid #d7dbe0;
        background: #fff;
        color: #495057;
        cursor: pointer;
        transition: all .15s ease;
    }
    .sched-type-btn::before {
        content: "";
        width: .85em;
        height: .85em;
        border-radius: .2em;
        border: 1.5px solid #adb5bd;
        display: inline-block;
        background: #fff;
        transition: all .15s ease;
    }
    .sched-type-check:checked + .sched-type-btn {
        background: var(--hr-primary, #005c52);
        border-color: var(--hr-primary, #005c52);
        color: #fff;
    }
    .sched-type-check:checked + .sched-type-btn::before {
        background: #fff;
        border-color: #fff;
        box-shadow: inset 0 0 0 2px var(--hr-primary, #005c52);
    }
    .sched-type-check:focus-visible + .sched-type-btn {
        outline: 2px solid var(--hr-primary, #005c52);
        outline-offset: 1px;
    }
    .sched-type-sep {
        width: 1px;
        align-self: stretch;
        background: #e1e4e8;
        margin: 0 .15rem;
    }
    .sched-type-btn-all {
        border-style: dashed;
        color: #6b7280;
        font-weight: 500;
    }
    .sched-type-selectall:checked + .sched-type-btn-all,
    .sched-type-selectall:indeterminate + .sched-type-btn-all {
        background: #eef1f3;
        border-style: solid;
        border-color: #c7ccd1;
        color: #374151;
    }

    /* ── Date & time confirm control ──────────────────────────────────── */
    .sched-datetime-confirm {
        display: flex;
        gap: .4rem;
        align-items: stretch;
    }
    .sched-datetime-input { flex: 1; }
    .sched-confirm-btn {
        border: 1px solid #d7dbe0;
        background: #fff;
        color: #adb5bd;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2.1rem;
        flex-shrink: 0;
        transition: all .15s ease;
    }
    .sched-datetime-confirm.is-confirmed .sched-confirm-btn {
        background: var(--hr-primary, #005c52);
        border-color: var(--hr-primary, #005c52);
        color: #fff;
    }
    .sched-datetime-confirm.is-confirmed .sched-datetime-input {
        border-color: var(--hr-primary, #005c52);
    }
    .sched-type-hint {
        font-size: .7rem;
        margin-top: .35rem;
    }
    .sched-datetime-hint {
        font-size: .7rem;
        margin-top: .25rem;
    }

    /* ── Panelist checklist box ───────────────────────────────────────── */
    .sched-panelist-box {
        min-height: 48px;
        background: #f8f9fa;
    }
</style>

@push('scripts')
<script>
    document.getElementById('editScheduleModal').addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        if (!button) return;

        const id = button.getAttribute('data-id');
        const form = document.getElementById('editScheduleForm');
        form.action = '/interviews/' + id;

        document.getElementById('edit_application_id').value = button.getAttribute('data-application-id');

        const editType = button.getAttribute('data-type'); // may be "open_ranking" or "open_ranking,interview"
        setTypePills('editScheduleModal', editType);

        document.getElementById('edit_scheduled_at').value = button.getAttribute('data-scheduled-at');
        markDatetimeConfirmed('editScheduleModal', !!button.getAttribute('data-scheduled-at'));
        document.getElementById('edit_location').value = button.getAttribute('data-location') || '';
        document.getElementById('edit_status').value = button.getAttribute('data-status');
        document.getElementById('edit_remarks').value = button.getAttribute('data-remarks') || '';

        // Load panelists for this schedule's job posting
        const editJobPostingId = button.getAttribute('data-job-posting-id');
        const editSelectedIds  = JSON.parse(button.getAttribute('data-panelist-ids') || '[]');
        loadPanelists('editPanelistList', 'editPanelistPlaceholder', editJobPostingId, editSelectedIds);
    });

    // ── Panelist checklist helpers ────────────────────────────────────────────

    /**
     * Fetch panelists for a job posting and render checkboxes into a container.
     * @param {string} listId        - ID of the container div
     * @param {string} placeholderId - ID of the placeholder span
     * @param {string|number} jobPostingId
     * @param {number[]} selectedIds - IDs to pre-check (for edit modal)
     */
    function loadPanelists(listId, placeholderId, jobPostingId, selectedIds) {
        const list = document.getElementById(listId);
        const placeholder = document.getElementById(placeholderId);

        if (!jobPostingId) {
            list.innerHTML = '<span class="text-muted small" id="' + placeholderId + '">Select an application above to load panelists.</span>';
            return;
        }

        list.innerHTML = '<span class="text-muted small">Loading...</span>';

        fetch('/interviews/panelists-for-posting/' + jobPostingId)
            .then(function (res) {
                if (!res.ok) throw new Error('Server error ' + res.status);
                return res.json();
            })
            .then(function (panelists) {
                if (!panelists.length) {
                    list.innerHTML = '<span class="text-muted small">No panelists assigned to this vacancy. Assign them on the Job Posting edit page first.</span>';
                    return;
                }

                list.innerHTML = panelists.map(function (p) {
                    const checked    = selectedIds.includes(p.id) ? 'checked' : '';
                    const available  = p.is_available
                        ? '<span class="badge text-bg-success ms-2" style="font-size:0.65rem;">Available</span>'
                        : '<span class="badge text-bg-secondary ms-2" style="font-size:0.65rem;">Unavailable</span>';
                    return '<div class="form-check mb-1">' +
                        '<input class="form-check-input" type="checkbox" name="panelist_ids[]"' +
                        ' value="' + p.id + '" id="panCheck_' + listId + '_' + p.id + '" ' + checked + '>' +
                        '<label class="form-check-label small" for="panCheck_' + listId + '_' + p.id + '">' +
                        p.name + available +
                        '</label>' +
                        '</div>';
                }).join('');
            })
            .catch(function () {
                list.innerHTML = '<span class="text-danger small">Failed to load panelists.</span>';
            });
    }

    // New schedule modal — load panelists when application changes
    document.querySelector('#newScheduleModal select[name="application_id"]').addEventListener('change', function () {
        const selected = this.options[this.selectedIndex];
        // We need the job_posting_id from the selected application
        // Pass it via a data attribute on each <option> — see the patched view
        const jobPostingId = selected.getAttribute('data-job-posting-id');
        loadPanelists('newPanelistList', 'newPanelistPlaceholder', jobPostingId, []);
    });

    // ── Type pills (multi-select, styled as checkboxes) ──────────────────────
    // Any combination of Open ranking / Interview / Exam can be checked at
    // once. The hidden input keeps a comma-separated list of whatever is
    // checked, e.g. "open_ranking,interview", so the form still submits a
    // single "type" field. At least one option must stay checked.
    function setTypePills(modalId, values) {
        const modal = document.getElementById(modalId);
        const list = Array.isArray(values) ? values : String(values || '').split(',').map(function (v) { return v.trim(); }).filter(Boolean);
        modal.querySelectorAll('.sched-type-check').forEach(function (input) {
            input.checked = list.includes(input.value);
        });
        syncTypeHidden(modal);
        syncSelectAll(modal);
    }

    function syncTypeHidden(modal) {
        const checked = Array.from(modal.querySelectorAll('.sched-type-check:checked')).map(function (i) { return i.value; });
        modal.querySelector('input[type="hidden"][id$="_type"]').value = checked.join(',') || 'open_ranking';
    }

    // Keeps the "Select all" checkbox reflecting the current state of the
    // individual type checkboxes: checked when all are checked, indeterminate
    // (dash) when some but not all are checked, unchecked when none are.
    function syncSelectAll(modal) {
        const selectAll = modal.querySelector('.sched-type-selectall');
        if (!selectAll) return;
        const boxes = modal.querySelectorAll('.sched-type-check');
        const checkedCount = modal.querySelectorAll('.sched-type-check:checked').length;
        selectAll.checked = checkedCount === boxes.length;
        selectAll.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
    }

    document.querySelectorAll('.sched-type-check').forEach(function (input) {
        input.addEventListener('change', function () {
            const modal = this.closest('.modal');
            const anyChecked = modal.querySelectorAll('.sched-type-check:checked').length > 0;
            if (!anyChecked) {
                // Prevent unchecking the last remaining type — re-check it
                this.checked = true;
            }
            syncTypeHidden(modal);
            syncSelectAll(modal);
        });
    });

    document.querySelectorAll('.sched-type-selectall').forEach(function (selectAll) {
        selectAll.addEventListener('change', function () {
            const modal = this.closest('.modal');
            const boxes = modal.querySelectorAll('.sched-type-check');
            if (this.checked) {
                boxes.forEach(function (b) { b.checked = true; });
            } else {
                // Keep at least one checked — leave the first one on
                boxes.forEach(function (b, i) { b.checked = (i === 0); });
                this.checked = false;
            }
            syncTypeHidden(modal);
            syncSelectAll(modal);
        });
    });

    // ── Date & time confirm (Enter key or check button) ──────────────────────
    function markDatetimeConfirmed(modalId, confirmed) {
        const modal = document.getElementById(modalId);
        const wrap = modal.querySelector('.sched-datetime-confirm');
        if (!wrap) return;
        wrap.classList.toggle('is-confirmed', !!confirmed);
    }

    document.querySelectorAll('.sched-datetime-confirm').forEach(function (wrap) {
        const input = wrap.querySelector('.sched-datetime-input');
        const confirmBtn = wrap.querySelector('.sched-confirm-btn');
        const modalId = wrap.closest('.modal').id;

        function confirmValue() {
            if (input.value) {
                markDatetimeConfirmed(modalId, true);
                input.blur();
            }
        }

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                confirmValue();
            }
        });
        input.addEventListener('input', function () {
            markDatetimeConfirmed(modalId, false);
        });
        confirmBtn.addEventListener('click', confirmValue);
    });

    // Reset the "new schedule" modal each time it opens
    document.getElementById('newScheduleModal').addEventListener('show.bs.modal', function () {
        setTypePills('newScheduleModal', 'open_ranking');
        markDatetimeConfirmed('newScheduleModal', false);
    });
</script>
@endpush
@endsection