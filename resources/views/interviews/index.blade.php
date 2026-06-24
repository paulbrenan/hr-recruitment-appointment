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
    <button class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;" data-bs-toggle="modal" data-bs-target="#newScheduleModal">
        <i class="bi bi-plus-lg me-1"></i> New schedule
    </button>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
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
                        <span class="badge text-bg-light text-dark border">{{ str_replace('_', ' ', ucfirst($s->type)) }}</span>
                    </td>
                    <td>{{ $s->scheduled_at ? \Carbon\Carbon::parse($s->scheduled_at)->format('M d, Y h:i A') : '—' }}</td>
                    <td>{{ $s->location ?? '—' }}</td>
                    <td>{{ $s->interviewer_name ?? '—' }}</td>
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
                            data-interviewer-name="{{ $s->interviewer_name }}"
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
        <div class="modal-content">
            <form action="{{ route('interviews.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title">Schedule interview / exam</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small">Application</label>
                        <select name="application_id" class="form-select form-select-sm" required>
                            <option value="" disabled selected>Select candidate / application</option>
                            @foreach ($applications as $application)
                                <option value="{{ $application->id }}">
                                    {{ $application->candidate->full_name }} — {{ $application->jobPosting->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Type</label>
                        <select name="type" class="form-select form-select-sm">
                            <option value="open_ranking">Open ranking</option>
                            <option value="interview">Interview</option>
                            <option value="exam">Exam</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Date &amp; time</label>
                        <input type="datetime-local" name="scheduled_at" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Location</label>
                        <input type="text" name="location" class="form-control form-control-sm">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Interviewer / evaluator</label>
                        <input type="text" name="interviewer_name" class="form-control form-control-sm">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">Send invitation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editScheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editScheduleForm" action="" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h6 class="modal-title">Edit schedule</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                    <div class="mb-2">
                        <label class="form-label small">Type</label>
                        <select name="type" id="edit_type" class="form-select form-select-sm">
                            <option value="open_ranking">Open ranking</option>
                            <option value="interview">Interview</option>
                            <option value="exam">Exam</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Date &amp; time</label>
                        <input type="datetime-local" name="scheduled_at" id="edit_scheduled_at" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Location</label>
                        <input type="text" name="location" id="edit_location" class="form-control form-control-sm">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Interviewer / evaluator</label>
                        <input type="text" name="interviewer_name" id="edit_interviewer_name" class="form-control form-control-sm">
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.getElementById('editScheduleModal').addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        if (!button) return;

        const id = button.getAttribute('data-id');
        const form = document.getElementById('editScheduleForm');
        form.action = '/interviews/' + id;

        document.getElementById('edit_application_id').value = button.getAttribute('data-application-id');
        document.getElementById('edit_type').value = button.getAttribute('data-type');
        document.getElementById('edit_scheduled_at').value = button.getAttribute('data-scheduled-at');
        document.getElementById('edit_location').value = button.getAttribute('data-location') || '';
        document.getElementById('edit_interviewer_name').value = button.getAttribute('data-interviewer-name') || '';
        document.getElementById('edit_status').value = button.getAttribute('data-status');
        document.getElementById('edit_remarks').value = button.getAttribute('data-remarks') || '';
    });
</script>
@endpush
@endsection