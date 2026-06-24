@extends('layouts.app')

@section('title', 'Assessment & ranking')
@section('page-title', 'Candidate assessment & ranking')

@section('content')
@if (session('success'))
<div class="alert alert-success alert-dismissible fade show small py-2" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<form method="GET" action="{{ route('assessments.index') }}" class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0 small">Comparative ranking based on weighted assessment criteria</p>
    <select name="job_posting" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
        @forelse ($postings as $p)
            <option value="{{ $p->id }}" {{ (string) $selectedPostingId === (string) $p->id ? 'selected' : '' }}>{{ $p->title }}</option>
        @empty
            <option>No job postings yet</option>
        @endforelse
    </select>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Candidate</th>
                    @foreach ($criteria as $c)
                        <th>{{ $c->name }} <span class="text-muted">({{ rtrim(rtrim(number_format($c->weight_percentage, 2), '0'), '.') }}%)</span></th>
                    @endforeach
                    <th>Total score</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rankedCandidates as $i => $cand)
                <tr>
                    <td>
                        @if ($i === 0 && $cand->total_score > 0)
                            <span class="badge text-bg-warning">#1</span>
                        @else
                            <span class="text-muted">#{{ $i + 1 }}</span>
                        @endif
                    </td>
                    <td class="fw-medium">{{ $cand->candidate_name }}</td>
                    @foreach ($criteria as $c)
                        <td>{{ $cand->scores[$c->id] ?? '-' }}</td>
                    @endforeach
                    <td class="fw-semibold">{{ $cand->total_score }}</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                            data-bs-toggle="modal" data-bs-target="#editScoresModal"
                            data-application-id="{{ $cand->application_id }}"
                            data-candidate-name="{{ $cand->candidate_name }}"
                            data-scores="{{ json_encode($cand->scores) }}">
                            <i class="bi bi-pencil"></i> Edit scores
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ count($criteria) + 4 }}" class="text-center text-muted py-4">
                        No applications for this posting yet.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
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
                <div class="border rounded p-2 small d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fw-medium">{{ $c->name }}</div>
                        <div class="text-muted">{{ rtrim(rtrim(number_format($c->weight_percentage, 2), '0'), '.') }}% weight</div>
                    </div>
                    <form method="POST" action="{{ route('assessments.criteria.destroy', $c->id) }}" onsubmit="return confirm('Remove this criterion? Any scores recorded under it will also be removed.');">
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
        @if ($remainingWeight <= 0)
        <button class="btn btn-sm btn-outline-secondary mt-3" disabled title="No weight remaining — remove or reduce an existing criterion first.">
            <i class="bi bi-plus-lg me-1"></i> Add criterion
        </button>
        @else
        <button class="btn btn-sm btn-outline-secondary mt-3" data-bs-toggle="modal" data-bs-target="#addCriterionModal">
            <i class="bi bi-plus-lg me-1"></i> Add criterion
        </button>
        @endif
    </div>
</div>

{{-- Add criterion modal --}}
<div class="modal fade" id="addCriterionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('assessments.criteria.store') }}">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title">Add assessment criterion</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                        <label class="form-label small">Weight (%) <span class="text-muted">&mdash; {{ $remainingWeight }}% remaining</span></label>
                        <input type="number" name="weight_percentage" class="form-control form-control-sm" min="0.01" max="{{ $remainingWeight }}" step="0.01" required value="{{ old('weight_percentage', $remainingWeight > 0 ? $remainingWeight : '') }}">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Description (optional)</label>
                        <textarea name="description" class="form-control form-control-sm" rows="2">{{ old('description') }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">Add criterion</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit scores modal --}}
<div class="modal fade" id="editScoresModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('assessments.scores.save') }}" id="editScoresForm">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title">Edit scores &mdash; <span id="editScoresCandidateName"></span></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                    @foreach ($criteria as $c)
                    <div class="mb-2">
                        <label class="form-label small">{{ $c->name }} <span class="text-muted">(max {{ rtrim(rtrim(number_format($c->weight_percentage, 2), '0'), '.') }})</span></label>
                        <input type="number" name="scores[{{ $c->id }}]" class="form-control form-control-sm score-input" data-criterion-id="{{ $c->id }}" min="0" max="{{ $c->weight_percentage }}" step="0.01">
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">Save scores</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    @if ($errors->has('weight_percentage'))
    document.addEventListener('DOMContentLoaded', function () {
        new bootstrap.Modal(document.getElementById('addCriterionModal')).show();
    });
    @endif

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
    });
</script>
@endpush