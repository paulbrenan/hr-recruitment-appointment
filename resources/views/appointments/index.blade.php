@extends('layouts.app')

@section('title', 'Appointment & onboarding')
@section('page-title', 'Appointment & onboarding')

@section('content')
<p class="text-muted small mb-3">Generate appointment papers for qualified applicants and a summary list for onboarding/induction</p>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
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

<div class="d-flex justify-content-end mb-3">
    <button type="button" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;" data-bs-toggle="modal" data-bs-target="#newAppointmentModal">
        <i class="bi bi-plus-lg me-1"></i> New appointment
    </button>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Candidate</th>
                    <th>Position</th>
                    <th>Item number</th>
                    <th>Appointment status</th>
                    <th>Appointment date</th>
                    <th>Onboarding date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($appointments as $a)
                <tr>
                    <td class="fw-medium">{{ $a->application->candidate->full_name }}</td>
                    <td>{{ $a->position_title }}</td>
                    <td>{{ $a->item_number ?? '—' }}</td>
                    <td>
                        <span class="badge text-bg-light text-dark border">{{ str_replace('_', ' ', ucfirst($a->appointment_status)) }}</span>
                    </td>
                    <td>{{ $a->appointment_date ? \Carbon\Carbon::parse($a->appointment_date)->format('M d, Y') : '—' }}</td>
                    <td>{{ $a->onboarding_date ? \Carbon\Carbon::parse($a->onboarding_date)->format('M d, Y') : '—' }}</td>
                    <td class="text-end">
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary"
                            data-bs-toggle="modal"
                            data-bs-target="#paperModal"
                            data-candidate-name="{{ $a->application->candidate->full_name }}"
                            data-position-title="{{ $a->position_title }}"
                            data-item-number="{{ $a->item_number ?? '—' }}"
                            data-appointment-status="{{ str_replace('_', ' ', ucfirst($a->appointment_status)) }}"
                            data-appointment-date="{{ $a->appointment_date ? \Carbon\Carbon::parse($a->appointment_date)->format('F d, Y') : '—' }}"
                        >
                            <i class="bi bi-file-earmark-pdf"></i> Paper
                        </button>
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary"
                            data-bs-toggle="modal"
                            data-bs-target="#editAppointmentModal"
                            data-id="{{ $a->id }}"
                            data-position-title="{{ $a->position_title }}"
                            data-item-number="{{ $a->item_number }}"
                            data-appointment-status="{{ $a->appointment_status }}"
                            data-appointment-date="{{ $a->appointment_date ? \Carbon\Carbon::parse($a->appointment_date)->format('Y-m-d') : '' }}"
                            data-onboarding-date="{{ $a->onboarding_date ? \Carbon\Carbon::parse($a->onboarding_date)->format('Y-m-d') : '' }}"
                        >
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form action="{{ route('appointments.destroy', $a->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this appointment record? This cannot be undone.')">
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

<div class="card mt-3">
    <div class="card-body p-4">
        <h6 class="mb-2">Newly-hired summary (for onboarding/induction)</h6>
        <p class="small text-muted mb-3">Generated list of employees ready for induction this period</p>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#summaryModal">
            <i class="bi bi-download me-1"></i> Generate summary list
        </button>
    </div>
</div>

{{-- New appointment modal --}}
<div class="modal fade" id="newAppointmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('appointments.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title">New appointment</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small">Application</label>
                        <select name="application_id" class="form-select form-select-sm" required>
                            <option value="" disabled selected>Select candidate / application</option>
                            @foreach ($eligibleApplications as $application)
                                <option value="{{ $application->id }}">
                                    {{ $application->candidate->full_name }} — {{ $application->jobPosting->title }}
                                </option>
                            @endforeach
                        </select>
                        @if ($eligibleApplications->isEmpty())
                            <div class="form-text text-muted">No applications with an accepted offer and no existing appointment are currently available.</div>
                        @endif
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Position title</label>
                        <input type="text" name="position_title" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Item number</label>
                        <input type="text" name="item_number" class="form-control form-control-sm" placeholder="e.g. OSEC-DECSB-HRA-001-2026">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Appointment status</label>
                        <select name="appointment_status" class="form-select form-select-sm">
                            <option value="permanent">Permanent</option>
                            <option value="temporary">Temporary</option>
                            <option value="provisional" selected>Provisional</option>
                            <option value="casual">Casual</option>
                            <option value="job_order">Job Order</option>
                            <option value="ojt">OJT</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Appointment date</label>
                        <input type="date" name="appointment_date" class="form-control form-control-sm">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Onboarding date</label>
                        <input type="date" name="onboarding_date" class="form-control form-control-sm">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">Create appointment</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit appointment modal --}}
<div class="modal fade" id="editAppointmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editAppointmentForm" action="" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h6 class="modal-title">Edit appointment</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small">Position title</label>
                        <input type="text" name="position_title" id="edit_position_title" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Item number</label>
                        <input type="text" name="item_number" id="edit_item_number" class="form-control form-control-sm" placeholder="e.g. OSEC-DECSB-HRA-001-2026">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Appointment status</label>
                        <select name="appointment_status" id="edit_appointment_status" class="form-select form-select-sm">
                            <option value="permanent">Permanent</option>
                            <option value="temporary">Temporary</option>
                            <option value="provisional">Provisional</option>
                            <option value="casual">Casual</option>
                            <option value="job_order">Job Order</option>
                            <option value="ojt">OJT</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Appointment date</label>
                        <input type="date" name="appointment_date" id="edit_appointment_date" class="form-control form-control-sm">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Onboarding date</label>
                        <input type="date" name="onboarding_date" id="edit_onboarding_date" class="form-control form-control-sm">
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

{{-- Printable "Paper" modal (Notice of Appointment style) --}}
<div class="modal fade" id="paperModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Notice of Appointment</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="paperPrintArea" class="p-3">
                    <h5 class="text-center mb-1">NOTICE OF APPOINTMENT</h5>
                    <p class="text-center text-muted small mb-4">This is to certify that</p>
                    <p class="text-center fw-bold fs-5 mb-1" id="paper_candidate_name"></p>
                    <p class="text-center mb-4">is hereby appointed to the position of</p>
                    <p class="text-center fw-bold mb-4" id="paper_position_title"></p>
                    <table class="table table-borderless table-sm w-auto mx-auto">
                        <tr>
                            <td class="text-muted small">Item number</td>
                            <td class="fw-medium" id="paper_item_number"></td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Appointment status</td>
                            <td class="fw-medium" id="paper_appointment_status"></td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Appointment date</td>
                            <td class="fw-medium" id="paper_appointment_date"></td>
                        </tr>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;" onclick="printElement('paperPrintArea')">
                    <i class="bi bi-printer me-1"></i> Print / Save as PDF
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Printable "Newly-hired summary" modal --}}
<div class="modal fade" id="summaryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Newly-Hired Summary</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="summaryPrintArea" class="p-3">
                    <h5 class="text-center mb-1">NEWLY-HIRED SUMMARY</h5>
                    <p class="text-center text-muted small mb-4">For onboarding / induction &middot; Generated {{ \Carbon\Carbon::now()->format('F d, Y') }}</p>
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>Candidate</th>
                                <th>Position</th>
                                <th>Item number</th>
                                <th>Status</th>
                                <th>Appointment date</th>
                                <th>Onboarding date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($appointments as $a)
                            <tr>
                                <td>{{ $a->application->candidate->full_name }}</td>
                                <td>{{ $a->position_title }}</td>
                                <td>{{ $a->item_number ?? '—' }}</td>
                                <td>{{ str_replace('_', ' ', ucfirst($a->appointment_status)) }}</td>
                                <td>{{ $a->appointment_date ? \Carbon\Carbon::parse($a->appointment_date)->format('M d, Y') : '—' }}</td>
                                <td>{{ $a->onboarding_date ? \Carbon\Carbon::parse($a->onboarding_date)->format('M d, Y') : '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;" onclick="printElement('summaryPrintArea')">
                    <i class="bi bi-printer me-1"></i> Print / Save as PDF
                </button>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    @media print {
        body * {
            visibility: hidden;
        }
        #printSection, #printSection * {
            visibility: visible;
        }
        #printSection {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    // Shared helper: clones the given element's content into a temporary
    // #printSection wrapper, triggers the browser print dialog (where the
    // user can choose "Save as PDF"), then cleans up afterward.
    function printElement(elementId) {
        const source = document.getElementById(elementId);
        if (!source) return;

        const existing = document.getElementById('printSection');
        if (existing) existing.remove();

        const printSection = document.createElement('div');
        printSection.id = 'printSection';
        printSection.innerHTML = source.innerHTML;
        document.body.appendChild(printSection);

        window.print();

        printSection.remove();
    }

    // Populate the "Paper" modal when opened
    document.getElementById('paperModal').addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        if (!button) return;

        document.getElementById('paper_candidate_name').textContent = button.getAttribute('data-candidate-name');
        document.getElementById('paper_position_title').textContent = button.getAttribute('data-position-title');
        document.getElementById('paper_item_number').textContent = button.getAttribute('data-item-number');
        document.getElementById('paper_appointment_status').textContent = button.getAttribute('data-appointment-status');
        document.getElementById('paper_appointment_date').textContent = button.getAttribute('data-appointment-date');
    });

    // Populate the "Edit appointment" modal when opened
    document.getElementById('editAppointmentModal').addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        if (!button) return;

        const id = button.getAttribute('data-id');
        document.getElementById('editAppointmentForm').action = '/appointments/' + id;

        document.getElementById('edit_position_title').value = button.getAttribute('data-position-title') || '';
        document.getElementById('edit_item_number').value = button.getAttribute('data-item-number') || '';
        document.getElementById('edit_appointment_status').value = button.getAttribute('data-appointment-status');
        document.getElementById('edit_appointment_date').value = button.getAttribute('data-appointment-date') || '';
        document.getElementById('edit_onboarding_date').value = button.getAttribute('data-onboarding-date') || '';
    });
</script>
@endpush
@endsection