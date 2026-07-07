@extends('layouts.app')

@section('title', 'Assessment & ranking')
@section('page-title', 'Candidate assessment & ranking')

@section('content')
<style>
    /* ── Page header strip ────────────────────────────────────────────── */
    .car-header-strip {
        background: linear-gradient(135deg, var(--hr-primary, #005c52), #00463f);
        border-radius: .6rem;
        padding: 1rem 1.25rem;
        color: #fff;
        margin-bottom: 1rem;
    }
    .car-header-strip p { color: rgba(255,255,255,.85); }
    .car-header-strip .form-select {
        border: none;
    }
    .car-send-all-btn {
        background: #fff;
        color: var(--hr-primary, #005c52);
        border: none;
        font-weight: 500;
        transition: filter .15s ease, transform .1s ease;
    }
    .car-send-all-btn:hover { filter: brightness(.96); color: var(--hr-primary, #005c52); }
    .car-send-all-btn:active { transform: scale(.98); }

    /* ── Ranking table ─────────────────────────────────────────────────── */
    .car-table-card { border: 1px solid rgba(0,0,0,.06); box-shadow: 0 1px 3px rgba(0,0,0,.04); }
    .car-table thead th {
        font-size: .7rem;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #6b7280;
        background: #f8f9fb;
        border-bottom: 1px solid #e9ecef;
        white-space: nowrap;
    }
    .car-table tbody tr { transition: background-color .12s ease; }
    .car-table tbody tr:hover { background-color: rgba(0, 92, 82, .04); }

    .car-rank-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.9rem;
        height: 1.9rem;
        border-radius: 50%;
        font-weight: 700;
        font-size: .8rem;
    }
    .car-rank-badge.rank-1 { background: #fff2cc; color: #9a7d0a; }
    .car-rank-badge.rank-2 { background: #e9ecef; color: #495057; }
    .car-rank-badge.rank-3 { background: #f3ddc9; color: #8a5a2b; }

    .car-total-score {
        font-weight: 700;
        color: var(--hr-primary, #005c52);
    }

    /* ── Criteria cards ───────────────────────────────────────────────── */
    .car-criterion-card {
        border: 1px solid #e9ecef;
        border-radius: .5rem;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .car-criterion-card:hover {
        border-color: var(--hr-primary, #005c52);
        box-shadow: 0 1px 4px rgba(0,92,82,.1);
    }
    .car-weight-bar {
        height: 6px;
        border-radius: 999px;
        background: #eef0f2;
        overflow: hidden;
        margin-top: .5rem;
    }
    .car-weight-bar-fill {
        height: 100%;
        background: var(--hr-primary, #005c52);
        transition: width .2s ease;
    }

    /* ── Edit scores modal: live total ───────────────────────────────── */
    .car-modal { border: none; border-radius: .6rem; overflow: hidden; }
    .car-modal-header {
        background: linear-gradient(135deg, var(--hr-primary, #005c52), #00463f);
        color: #fff;
        border-bottom: none;
    }
    .car-modal-header .modal-title { color: #fff; font-weight: 600; }
    .car-modal-footer { border-top: 1px solid #eef0f2; background: #fbfbfc; }
    .car-submit-btn { background-color: var(--hr-primary, #005c52); color: #fff; border: none; }
    .car-submit-btn:hover { filter: brightness(1.08); color: #fff; }

    .car-score-total-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: .5rem .75rem;
        border-radius: .4rem;
        background: #f8f9fb;
        border: 1px solid #e9ecef;
        font-size: .85rem;
        margin-bottom: .75rem;
        transition: background-color .15s ease, border-color .15s ease;
    }
    .car-score-total-row.is-over {
        background: #fdeceb;
        border-color: #f3c2bd;
        color: #b02a1f;
    }
    .car-score-total-value { font-weight: 700; }

    .score-input.is-over { border-color: #dc3545; }

    /* ── Comparative Assessment Result (CAR) document view ─────────────── */
    .car-doc-card { border: 1px dashed #c7ccd1; background: #fbfbfc; }
    .car-doc-btn {
        background: #fff;
        border: 1px solid var(--hr-primary, #005c52);
        color: var(--hr-primary, #005c52);
    }
    .car-doc-btn:hover { background: var(--hr-primary, #005c52); color: #fff; }

    #carDocumentModal .modal-dialog { max-width: 1100px; }
    .car-doc-title {
        text-align: center;
        font-weight: 700;
        letter-spacing: .03em;
        margin-bottom: .25rem;
    }
    .car-doc-subtitle { text-align: center; color: #6b7280; font-size: .85rem; margin-bottom: 1rem; }
    .car-doc-meta { font-size: .8rem; margin-bottom: .75rem; }
    .car-doc-table { font-size: .78rem; }
    .car-doc-table th, .car-doc-table td { border: 1px solid #333 !important; vertical-align: middle; text-align: center; }
    .car-doc-table thead th { background: #f1f3f5; }

    /* Columns the official CSC form marks as concealed for public posting
       (name + remarks-to-probation) under RA No. 10163 (Data Privacy Act) */
    .car-doc-table.public-mode .car-confidential { display: none; }

    /* Blank columns meant to be completed by hand after printing
       (Background Investigation, Appointment, Probation) */
    .car-doc-fillable { min-width: 70px; }

    .car-public-toggle {
        display: flex;
        align-items: center;
        gap: .4rem;
        font-size: .8rem;
        margin-bottom: .75rem;
    }

    @media print {
        body * { visibility: hidden; }
        #carDocumentPrintArea, #carDocumentPrintArea * { visibility: visible; }
        #carDocumentPrintArea { position: absolute; top: 0; left: 0; width: 100%; }
        .no-print { display: none !important; }
    }
</style>

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

<div class="car-header-strip d-flex justify-content-between align-items-center mb-3">
    <p class="mb-0 small">Comparative ranking based on weighted assessment criteria</p>
    <div class="d-flex align-items-center gap-2">
        @if ($rankedCandidates->isNotEmpty())
        <form method="POST" action="{{ route('assessments.send-all') }}" class="m-0">
            @csrf
            <input type="hidden" name="job_posting_id" value="{{ $selectedPostingId }}">
            <input type="hidden" name="selected_title" value="{{ $selectedTitle }}">
            <button type="submit"
                onclick="return confirm('Send ranking notifications to all {{ $rankedCandidates->count() }} applicant(s)?')"
                class="btn btn-sm car-send-all-btn">
                <i class="bi bi-envelope me-1"></i> Send all notifications
            </button>
        </form>
        @endif
        @if ($criteria->isNotEmpty())
        <button type="button" class="btn btn-sm car-send-all-btn" data-bs-toggle="modal" data-bs-target="#importScoresModal">
            <i class="bi bi-upload me-1"></i> Import scores from Excel
        </button>
        @endif
        {{-- Two-level dropdown: Title → Place of Assignment --}}
        <form method="GET" action="{{ route('assessments.index') }}" class="m-0 d-flex align-items-center gap-2" id="postingFilterForm">
            {{-- Level 1: unique job titles --}}
            <select name="title" id="titleSelect" class="form-select form-select-sm" style="min-width: 220px; max-width: 280px;">
                @forelse ($postings as $p)
                    <option value="{{ $p->title }}" {{ $selectedTitle === $p->title ? 'selected' : '' }}>
                        {{ $p->title }}
                    </option>
                @empty
                    <option>No job postings yet</option>
                @endforelse
            </select>

            {{-- Level 2: place of assignment for the selected title --}}
            @if ($locationPostings->count() > 1)
            <select name="job_posting" id="locationSelect" class="form-select form-select-sm" style="min-width: 200px; max-width: 260px;">
                @foreach ($locationPostings as $lp)
                    @php
                        // Show the first location name, or fall back to legacy place_of_assignment
                        $loc = $lp->locations->first();
                        $label = $loc ? $loc->place_of_assignment : ($lp->place_of_assignment ?? '—');
                    @endphp
                    <option value="{{ $lp->id }}" {{ (string) $selectedPostingId === (string) $lp->id ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            @else
            {{-- Only one location — hidden field, no need to show dropdown --}}
            <input type="hidden" name="job_posting" value="{{ $selectedPostingId }}">
            @endif
        </form>
    </div>
</div>

<div class="card car-table-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0 car-table">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Candidate</th>
                    @foreach ($criteria as $c)
                        <th>{{ $c->name }} <span class="text-muted">({{ rtrim(rtrim(number_format($c->weight_percentage, 2), '0'), '.') }}%)</span></th>
                    @endforeach
                    <th>Total score</th>
                    <th>Notified</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rankedCandidates as $i => $cand)
                <tr>
                    <td>
                        @if ($i === 0 && $cand->total_score > 0)
                            <span class="car-rank-badge rank-1">#1</span>
                        @elseif ($i === 1 && $cand->total_score > 0)
                            <span class="car-rank-badge rank-2">#2</span>
                        @elseif ($i === 2 && $cand->total_score > 0)
                            <span class="car-rank-badge rank-3">#3</span>
                        @else
                            <span class="text-muted">#{{ $i + 1 }}</span>
                        @endif
                    </td>
                    <td class="fw-medium">{{ $cand->candidate_name }}</td>
                    @foreach ($criteria as $c)
                        <td>{{ $cand->scores[$c->id] ?? '-' }}</td>
                    @endforeach
                    <td class="car-total-score">{{ $cand->total_score }}</td>
                    <td>
                        @if ($cand->notification_sent)
                            <span class="text-success small"><i class="bi bi-check-lg"></i> Sent</span>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <div class="d-flex justify-content-end gap-1">
                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                data-bs-toggle="modal" data-bs-target="#editScoresModal"
                                data-application-id="{{ $cand->application_id }}"
                                data-candidate-name="{{ $cand->candidate_name }}"
                                data-scores="{{ json_encode($cand->scores) }}">
                                <i class="bi bi-pencil"></i> Edit scores
                            </button>
                            <form method="POST" action="{{ route('assessments.send-one', $cand->application_id) }}" class="m-0">
                                @csrf
                                <input type="hidden" name="job_posting_id" value="{{ $selectedPostingId }}">
                                <button type="submit"
                                    {{ $cand->notification_sent ? 'disabled' : '' }}
                                    onclick="return confirm('Send ranking notification to {{ $cand->candidate_name }}?')"
                                    class="btn btn-sm {{ $cand->notification_sent ? 'btn-outline-secondary disabled' : 'btn-outline-primary' }}">
                                    <i class="bi bi-envelope"></i> {{ $cand->notification_sent ? 'Sent' : 'Send' }}
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ count($criteria) + 5 }}" class="text-center text-muted py-4">
                        No applications for this posting yet.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($rankedCandidates->isNotEmpty())
<div class="card car-doc-card mt-3">
    <div class="card-body p-3 d-flex justify-content-between align-items-center">
        <div>
            <div class="fw-medium small">Comparative Assessment Result (CAR)</div>
            <div class="text-muted small">Official CSC-format ranking summary for {{ $selectedTitle }}, ready to print or attach to the appointment packet.</div>
        </div>
        <button type="button" class="btn btn-sm car-doc-btn" data-bs-toggle="modal" data-bs-target="#carDocumentModal">
            <i class="bi bi-file-earmark-text me-1"></i> View / Print CAR
        </button>
    </div>
</div>
@endif

<div class="modal fade" id="carDocumentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content car-modal">
            <div class="modal-header car-modal-header no-print">
                <h6 class="modal-title"><i class="bi bi-file-earmark-text me-2"></i>Comparative Assessment Result</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                @php
                    $selLocation = $selectedPosting?->locations->first();
                    $selLocationLabel = $selLocation?->place_of_assignment ?? $selectedPosting?->place_of_assignment ?? null;
                @endphp
                <div class="car-public-toggle no-print">
                    <input type="checkbox" class="form-check-input" id="carPublicToggle">
                    <label for="carPublicToggle">Public posting view — conceal name &amp; confidential columns (RA No. 10163)</label>
                </div>
                <div id="carDocumentPrintArea">
                    <div class="car-doc-title">Comparative Assessment Result (CAR)</div>
                    <div class="car-doc-subtitle">{{ $selectedTitle }}</div>
                    <div class="row car-doc-meta">
                        <div class="col-6">Position: <strong>{{ $selectedTitle }}</strong></div>
                        <div class="col-6">Plantilla Item Number: <strong>&nbsp;</strong></div>
                        <div class="col-6">Date of Final Deliberation: <strong>{{ now()->format('M d, Y') }}</strong></div>
                        <div class="col-6">Office/Bureau/Service/Unit: <strong>DepEd Division of Cavite Province{{ $selLocationLabel ? ' — ' . $selLocationLabel : '' }}</strong></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table car-doc-table mb-2" id="carDocTable">
                            <thead>
                                <tr>
                                    <th rowspan="2">Rank</th>
                                    <th rowspan="2">Application Code</th>
                                    <th rowspan="2" class="car-confidential">Name of Applicant</th>
                                    <th colspan="{{ count($criteria) + 1 }}">Comparative Assessment Results</th>
                                    <th rowspan="2" class="car-confidential">Remarks</th>
                                    <th colspan="2" class="car-confidential">For Background Investigation (Y/N)</th>
                                    <th rowspan="2" class="car-confidential">For Appointment</th>
                                    <th rowspan="2" class="car-confidential">For Probation</th>
                                </tr>
                                <tr>
                                    @foreach ($criteria as $c)
                                        <th>{{ $c->name }}<br>({{ rtrim(rtrim(number_format($c->weight_percentage, 2), '0'), '.') }} pts)</th>
                                    @endforeach
                                    <th>Total</th>
                                    <th class="car-confidential">Yes</th>
                                    <th class="car-confidential">No</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rankedCandidates as $i => $cand)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $cand->application_code }}</td>
                                    <td class="text-start car-confidential">{{ $cand->candidate_name }}</td>
                                    @foreach ($criteria as $c)
                                        <td>{{ $cand->scores[$c->id] ?? '—' }}</td>
                                    @endforeach
                                    <td class="fw-bold">{{ $cand->total_score }}</td>
                                    <td class="car-confidential">{{ $cand->remarks ?? '' }}</td>
                                    <td class="car-confidential car-doc-fillable"></td>
                                    <td class="car-confidential car-doc-fillable"></td>
                                    <td class="car-confidential car-doc-fillable"></td>
                                    <td class="car-confidential car-doc-fillable"></td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="{{ count($criteria) + 9 }}" class="text-muted py-3">No ranked applicants yet.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted" style="font-size:.7rem;">
                        Note: For official posting, applicant names, Remarks, Background Investigation, Appointment, and Probation
                        columns should be concealed per RA No. 10163 (Data Privacy Act); only the Application Code, per-criterion
                        results, and total scores are public information. Background Investigation, Appointment, and Probation are
                        completed by hand after printing.
                    </p>
                    <div class="row mt-4" style="font-size:.78rem;">
                        <div class="col-6">
                            Prepared by the HRMPSB<br>
                            <span class="text-muted">(All members should affix signature)</span>
                        </div>
                        <div class="col-6">
                            Appointment conferred by:<br>
                            <span class="text-muted">&nbsp;</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer car-modal-footer no-print">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-sm car-submit-btn" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">Assessment criteria for this posting</h6>
            <span class="badge {{ $remainingWeight > 0 ? 'text-bg-light text-dark border' : 'text-bg-success' }}">
                {{ $usedWeight }}% used &middot; {{ $remainingWeight }}% remaining
            </span>
        </div>
        <div class="row g-2">
            @forelse ($criteria as $c)
            <div class="col-md-4">
                <div class="car-criterion-card p-2 small d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="fw-medium">{{ $c->name }}</div>
                        <div class="text-muted">{{ rtrim(rtrim(number_format($c->weight_percentage, 2), '0'), '.') }}% weight</div>
                        <div class="car-weight-bar">
                            <div class="car-weight-bar-fill" style="width: {{ $c->weight_percentage }}%;"></div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('assessments.criteria.destroy', $c->id) }}" onsubmit="return confirm('Remove this criterion? Any scores recorded under it will also be removed.');" class="ms-2">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-lg"></i></button>
                    </form>
                </div>
            </div>
            @empty
            <div class="col-12">
                <p class="text-muted small mb-0">No criteria defined for this posting yet.</p>
            </div>
            @endforelse
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            @if ($remainingWeight <= 0)
            <button class="btn btn-sm btn-outline-secondary mt-3" disabled title="No weight remaining — remove or reduce an existing criterion first.">
                <i class="bi bi-plus-lg me-1"></i> Add criterion
            </button>
            @else
            <button class="btn btn-sm btn-outline-secondary mt-3" data-bs-toggle="modal" data-bs-target="#addCriterionModal">
                <i class="bi bi-plus-lg me-1"></i> Add criterion
            </button>
            @endif

            @if ($criteria->isEmpty())
            <button type="button" class="btn btn-sm car-doc-btn mt-3" id="cscStandardBtn" data-job-posting-id="{{ $selectedPostingId }}">
                <i class="bi bi-clipboard2-check me-1"></i> Use CSC standard criteria
            </button>
            @endif
        </div>
    </div>
</div>

{{-- Add criterion modal --}}
<div class="modal fade" id="addCriterionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content car-modal">
            <form method="POST" action="{{ route('assessments.criteria.store') }}">
                @csrf
                <div class="modal-header car-modal-header">
                    <h6 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add assessment criterion</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if ($errors->has('weight_percentage'))
                    <div class="alert alert-danger small py-2">{{ $errors->first('weight_percentage') }}</div>
                    @endif
                    <input type="hidden" name="job_posting_id" value="{{ $selectedPostingId }}">
                    <div class="mb-2">
                        <label class="form-label small">Criterion name</label>
                        <input type="text" name="name" class="form-control form-control-sm" required placeholder="e.g. Technical skills" value="{{ old('name') }}">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Weight (%) <span class="text-muted" id="criterionWeightRemainingLabel">&mdash; {{ $remainingWeight }}% remaining</span></label>
                        <input type="number" name="weight_percentage" id="criterionWeightInput" class="form-control form-control-sm" min="0.01" max="{{ $remainingWeight }}" step="0.01" required value="{{ old('weight_percentage', $remainingWeight > 0 ? $remainingWeight : '') }}">
                        <div class="car-weight-bar mt-2">
                            <div class="car-weight-bar-fill" id="criterionWeightPreviewFill" style="width: {{ $usedWeight }}%;"></div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Description (optional)</label>
                        <textarea name="description" class="form-control form-control-sm" rows="2">{{ old('description') }}</textarea>
                    </div>
                </div>
                <div class="modal-footer car-modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm car-submit-btn">Add criterion</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Import scores from Excel modal --}}
<div class="modal fade" id="importScoresModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content car-modal">
            <form method="POST" action="{{ route('assessments.scores.import') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header car-modal-header">
                    <h6 class="modal-title"><i class="bi bi-upload me-2"></i>Import scores from Excel</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if ($errors->any())
                    <div class="alert alert-danger small py-2">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                    @endif
                    <input type="hidden" name="job_posting_id" value="{{ $selectedPostingId }}">
                    <p class="small text-muted">
                        Upload the CAR spreadsheet for this posting. It must include an
                        <strong>"Application Code"</strong> column and score columns whose names match
                        this posting's criteria (e.g. "Education (10 pts)"). Applicants are matched by
                        Application Code — existing scores for a matched applicant/criterion will be
                        overwritten. Rows with an unrecognized code, or scores over a criterion's max,
                        are skipped and reported.
                    </p>
                    <a href="{{ route('assessments.scores.import-template') }}?job_posting_id={{ $selectedPostingId }}"
                       class="btn btn-sm btn-outline-secondary mb-3">
                        <i class="bi bi-download me-1"></i> Download template for this posting
                    </a>
                    <div class="mb-2">
                        <label class="form-label small">Excel file (.xlsx)</label>
                        <input type="file" name="import_file" class="form-control form-control-sm" accept=".xlsx,.xls" required>
                    </div>
                </div>
                <div class="modal-footer car-modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm car-submit-btn">
                        <i class="bi bi-upload me-1"></i> Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit scores modal --}}
<div class="modal fade" id="editScoresModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content car-modal">
            <form method="POST" action="{{ route('assessments.scores.save') }}" id="editScoresForm">
                @csrf
                <div class="modal-header car-modal-header">
                    <h6 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit scores &mdash; <span id="editScoresCandidateName"></span></h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if ($errors->any())
                    <div class="alert alert-danger small py-2">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                    @endif
                    <input type="hidden" name="application_id" id="editScoresApplicationId">
                    <input type="hidden" name="job_posting_id" value="{{ $selectedPostingId }}">
                    <div class="car-score-total-row" id="scoreTotalRow">
                        <span>Running total</span>
                        <span class="car-score-total-value"><span id="scoreTotalValue">0</span> / {{ $usedWeight }}</span>
                    </div>
                    @foreach ($criteria as $c)
                    <div class="mb-2">
                        <label class="form-label small">{{ $c->name }} <span class="text-muted">(max {{ rtrim(rtrim(number_format($c->weight_percentage, 2), '0'), '.') }})</span></label>
                        <input type="number" name="scores[{{ $c->id }}]" class="form-control form-control-sm score-input" data-criterion-id="{{ $c->id }}" data-max="{{ $c->weight_percentage }}" min="0" max="{{ $c->weight_percentage }}" step="0.01">
                    </div>
                    @endforeach
                    <div class="mb-2">
                        <label class="form-label small">Evaluator remarks (optional)</label>
                        <textarea name="evaluator_remarks" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Evaluated by (optional)</label>
                        <input type="text" name="evaluated_by" class="form-control form-control-sm">
                    </div>
                </div>
                <div class="modal-footer car-modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm car-submit-btn">Save scores</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // ── Posting filter dropdowns ──────────────────────────────────────────────
    (function () {
        const form        = document.getElementById('postingFilterForm');
        const titleSelect = document.getElementById('titleSelect');
        const locSelect   = document.getElementById('locationSelect');

        if (!form || !titleSelect) return;

        // Title changes → clear job_posting and resubmit (controller picks first location)
        titleSelect.addEventListener('change', function () {
            if (locSelect) locSelect.value = '';
            // Remove job_posting from form so controller auto-selects first location
            let hidden = form.querySelector('input[name="job_posting"]');
            if (hidden) hidden.remove();
            form.submit();
        });

        // Location changes → just submit (both title + job_posting are in the form)
        if (locSelect) {
            locSelect.addEventListener('change', function () {
                form.submit();
            });
        }
    })();

    @if ($errors->has('weight_percentage'))
    document.addEventListener('DOMContentLoaded', function () {
        new bootstrap.Modal(document.getElementById('addCriterionModal')).show();
    });
    @endif

    // ── CAR document: public posting view toggle ──────────────────────────────
    // Hides applicant names and the confidential columns (Remarks, Background
    // Investigation, Appointment, Probation) per RA No. 10163 (Data Privacy Act),
    // leaving only Rank, Application Code, per-criterion scores, and Total.
    (function () {
        const toggle = document.getElementById('carPublicToggle');
        const table = document.getElementById('carDocTable');
        if (!toggle || !table) return;

        toggle.addEventListener('change', function () {
            table.classList.toggle('public-mode', toggle.checked);
        });
    })();

    document.getElementById('editScoresModal').addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const applicationId = button.getAttribute('data-application-id');
        const candidateName = button.getAttribute('data-candidate-name');
        const scores = JSON.parse(button.getAttribute('data-scores') || '{}');

        document.getElementById('editScoresApplicationId').value = applicationId;
        document.getElementById('editScoresCandidateName').textContent = candidateName;

        document.querySelectorAll('.score-input').forEach(function (input) {
            const criterionId = input.getAttribute('data-criterion-id');
            const value = scores[criterionId];
            input.value = (value === null || value === undefined || value === '-') ? '' : value;
        });

        updateScoreTotal();
    });

    // ── Add criterion modal: live weight-remaining preview ───────────────────
    (function () {
        const weightInput = document.getElementById('criterionWeightInput');
        const fill = document.getElementById('criterionWeightPreviewFill');
        const label = document.getElementById('criterionWeightRemainingLabel');
        if (!weightInput) return;

        const usedWeight = {{ $usedWeight }};
        const remainingWeight = {{ $remainingWeight }};

        weightInput.addEventListener('input', function () {
            const entered = parseFloat(this.value) || 0;
            const previewUsed = Math.min(usedWeight + entered, 100);
            fill.style.width = previewUsed + '%';

            const stillRemaining = Math.max(remainingWeight - entered, 0);
            label.textContent = '\u2014 ' + stillRemaining.toFixed(2).replace(/\.?0+$/, '') + '% remaining';
        });
    })();

    // ── Edit scores modal: live running total vs. usable weight ──────────────
    function updateScoreTotal() {
        const totalValueEl = document.getElementById('scoreTotalValue');
        const totalRowEl = document.getElementById('scoreTotalRow');
        if (!totalValueEl) return;

        let total = 0;
        let anyOver = false;

        document.querySelectorAll('.score-input').forEach(function (input) {
            const val = parseFloat(input.value) || 0;
            const max = parseFloat(input.getAttribute('data-max')) || 0;
            total += val;

            const isOver = val > max;
            input.classList.toggle('is-over', isOver);
            if (isOver) anyOver = true;
        });

        totalValueEl.textContent = (Math.round(total * 100) / 100).toString();
        totalRowEl.classList.toggle('is-over', anyOver);
    }

    document.querySelectorAll('.score-input').forEach(function (input) {
        input.addEventListener('input', updateScoreTotal);
    });

    // ── "Use CSC standard criteria" quick-fill ────────────────────────────────
    // Creates the 8 official CAR categories (CSC MC No. 3, s. 2001 / DO 19,
    // s. 2022 comparative assessment format) for this posting, using the
    // same store route the "Add criterion" form already posts to. Only
    // shown when this posting has no criteria yet, so it can't push the
    // total over 100%.
    const cscStandardBtn = document.getElementById('cscStandardBtn');
    if (cscStandardBtn) {
        const cscCriteria = [
            { name: 'Education',                    weight_percentage: 10 },
            { name: 'Training',                     weight_percentage: 10 },
            { name: 'Experience',                   weight_percentage: 10 },
            { name: 'Performance',                  weight_percentage: 25 },
            { name: 'Outstanding Accomplishments',  weight_percentage: 10 },
            { name: 'Application of Education',     weight_percentage: 10 },
            { name: 'Application of L&D',           weight_percentage: 10 },
            { name: 'Potential',                    weight_percentage: 15 },
        ];

        cscStandardBtn.addEventListener('click', function () {
            if (!confirm('Add the 8 standard CSC comparative assessment criteria (Education, Training, Experience, Performance, Outstanding Accomplishments, Application of Education, Application of L&D, Potential) totaling 100%?')) {
                return;
            }

            const jobPostingId = this.getAttribute('data-job-posting-id');
            const csrfToken = document.querySelector('input[name="_token"]').value;
            const storeUrl = '{{ route('assessments.criteria.store') }}';
            const btn = this;

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Adding...';

            // Criteria are created one at a time, in order, since the store
            // route only accepts a single criterion per request.
            cscCriteria.reduce(function (chain, criterion) {
                return chain.then(function () {
                    return fetch(storeUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            job_posting_id: jobPostingId,
                            name: criterion.name,
                            weight_percentage: criterion.weight_percentage,
                        }),
                    }).then(function (res) {
                        if (!res.ok) throw new Error('Failed to add ' + criterion.name);
                    });
                });
            }, Promise.resolve())
            .then(function () {
                window.location.reload();
            })
            .catch(function (err) {
                alert('Something went wrong adding the standard criteria (' + err.message + '). Please check the criteria list and try adding any missing ones manually.');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-clipboard2-check me-1"></i> Use CSC standard criteria';
            });
        });
    }
</script>
@endpush