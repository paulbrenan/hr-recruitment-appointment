@extends('layouts.app')

@section('title', 'Offer management')
@section('page-title', 'Offer management')

@section('content')
@if (session('success'))
<div class="alert alert-success alert-dismissible fade show small py-2" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if (session('error'))
<div class="alert alert-danger alert-dismissible fade show small py-2" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<p class="text-muted small mb-3">Generate, send, and track job offers for selected candidates</p>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Candidate</th>
                    <th>Position</th>
                    <th>Compensation</th>
                    <th>Sent</th>
                    <th>Email delivery</th>
                    <th>Response by</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($offers as $o)
                <tr>
                    <td class="fw-medium">{{ $o->application->candidate->full_name ?? 'Unknown' }}</td>
                    <td>{{ $o->application->jobPosting->title ?? '-' }}</td>
                    <td>&#8369;{{ number_format($o->compensation, 2) }}</td>
                    <td>{{ $o->offer_sent_at ? \Carbon\Carbon::parse($o->offer_sent_at)->format('M d, Y') : '-' }}</td>
                    <td>
                        @if ($o->email_sent_at)
                            <span class="badge text-bg-success">Sent</span>
                            <div class="text-muted" style="font-size: 0.72rem;">{{ \Carbon\Carbon::parse($o->email_sent_at)->format('M d, Y g:i A') }}</div>
                        @else
                            <span class="badge text-bg-secondary">Not sent</span>
                        @endif
                    </td>
                    <td>{{ $o->response_deadline ? \Carbon\Carbon::parse($o->response_deadline)->format('M d, Y') : '-' }}</td>
                    <td>
                        @php
                            $colors = ['draft' => 'secondary', 'sent' => 'primary', 'accepted' => 'success', 'declined' => 'danger', 'expired' => 'dark'];
                        @endphp
                        <span class="badge badge-status text-bg-{{ $colors[$o->status] ?? 'secondary' }}">{{ ucfirst($o->status) }}</span>
                    </td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            @if ($o->status === 'draft')
                            <form method="POST" action="{{ route('offers.send', $o->id) }}" class="d-inline">
                                @csrf
                                @method('PUT')
                                <button type="submit" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">Send</button>
                            </form>
                            @elseif ($o->status === 'sent')
                            <form method="POST" action="{{ route('offers.respond', $o->id) }}" class="d-inline"
                                  onsubmit="return confirm('Mark this offer as accepted?')">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="response" value="accepted">
                                <button type="submit" class="btn btn-sm btn-outline-success">Accept</button>
                            </form>
                            <form method="POST" action="{{ route('offers.respond', $o->id) }}" class="d-inline"
                                  onsubmit="return confirm('Mark this offer as declined?')">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="response" value="declined">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Decline</button>
                            </form>
                            @else
                            <span class="text-muted small">No actions</span>
                            @endif
                            <form method="POST" action="{{ route('offers.destroy', $o->id) }}" class="d-inline" onsubmit="return confirm('Delete this offer? This cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">No offers yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body p-4">
        <h6 class="mb-3">Generate new offer</h6>
        @if ($eligibleApplications->isEmpty())
        <p class="text-muted small mb-0">No candidates are currently eligible for an offer. Candidates become eligible once shortlisted, assessed, or ranked, and don't already have an offer.</p>
        @else
        @if ($errors->has('salary_grade') || $errors->has('salary_step'))
        <div class="alert alert-danger small py-2">{{ $errors->first('salary_grade') ?: $errors->first('salary_step') }}</div>
        @endif
        <form method="POST" action="{{ route('offers.store') }}" class="row g-2">
            @csrf
            <div class="col-md-3">
                <select name="application_id" class="form-select form-select-sm" required>
                    <option value="">Select candidate</option>
                    @foreach ($eligibleApplications as $app)
                        <option value="{{ $app->id }}" {{ old('application_id') == $app->id ? 'selected' : '' }}>{{ $app->candidate->full_name ?? 'Unknown' }} &mdash; {{ $app->jobPosting->title ?? '-' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="salary_grade" class="form-select form-select-sm" id="sgSelect" required>
                    <option value="">SG</option>
                    @for ($sg = 1; $sg <= 33; $sg++)
                        <option value="{{ $sg }}" {{ old('salary_grade') == $sg ? 'selected' : '' }}>SG {{ $sg }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-md-2">
                <select name="salary_step" class="form-select form-select-sm" id="stepSelect" required>
                    <option value="">Step</option>
                    @for ($step = 1; $step <= 8; $step++)
                        <option value="{{ $step }}" {{ old('salary_step') == $step ? 'selected' : '' }}>Step {{ $step }}</option>
                    @endfor
                </select>
                <div class="form-text" style="font-size: 0.72rem;" id="sgAmountHint">&nbsp;</div>
            </div>
            <div class="col-md-2">
                <input type="date" name="response_deadline" class="form-control form-control-sm" min="{{ now()->toDateString() }}" value="{{ old('response_deadline') }}">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm w-100" style="background-color: var(--hr-primary); color: #fff;">Generate</button>
            </div>
        </form>
        @endif
    </div>
</div>

{{-- Respond modal removed — accept/decline now use inline forms per row --}}
@endsection

@push('scripts')
<script>
    // ── SG/step → compensation live preview ──────────────────────────────────
    const sgTable = @json(\App\Models\SalaryGrade::currentTableArray());

    function updateAmountHint() {
        const sg   = parseInt(document.getElementById('sgSelect').value);
        const step = parseInt(document.getElementById('stepSelect').value);
        const hint = document.getElementById('sgAmountHint');
        if (sg && step && sgTable[sg] && sgTable[sg][step - 1]) {
            const amount = sgTable[sg][step - 1];
            hint.textContent = '₱' + amount.toLocaleString('en-PH');
            hint.style.color = 'var(--hr-primary)';
        } else {
            hint.textContent = '\u00a0';
        }
    }

    document.getElementById('sgSelect').addEventListener('change', updateAmountHint);
    document.getElementById('stepSelect').addEventListener('change', updateAmountHint);
    updateAmountHint(); // run on load in case old() values are present

    // Respond modal removed — no JS needed for accept/decline
</script>
@endpushp