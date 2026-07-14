@extends('layouts.app')

@section('title', 'Talent pool')
@section('page-title', 'Talent pool')

@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if (session('info'))
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        {{ session('info') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0 small">Candidates kept on file for future openings</p>
    <div class="d-flex gap-2">
        <input type="text" id="talentSearch" class="form-control form-control-sm" style="width: 240px;" placeholder="Search by name or skill...">
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addTalentModal">
            <i class="bi bi-plus-lg"></i> Add to pool
        </button>
    </div>
</div>

<div class="row g-3" id="talentPoolGrid">
    @forelse ($pool as $p)
    @php
        $skills = $p->skills ? array_filter(array_map('trim', explode(',', $p->skills))) : [];
    @endphp
    <div class="col-md-4 talent-card"
         data-name="{{ strtolower($p->full_name) }}"
         data-email="{{ strtolower($p->email ?? '') }}"
         data-skills="{{ strtolower($p->skills ?? '') }}"
         data-position="{{ strtolower($p->position_applied ?? '') }}">
        <div class="card h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="fw-medium">{{ $p->full_name }}</div>
                        <div class="text-muted small">{{ $p->email }}</div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-bookmark-star text-warning"></i>
                        <button type="button" class="btn btn-link btn-sm p-0 text-secondary"
                            data-bs-toggle="modal" data-bs-target="#editTalentModal"
                            data-id="{{ $p->id }}"
                            data-skills="{{ $p->skills }}"
                            data-notes="{{ $p->notes }}"
                            data-added_at="{{ $p->added_at?->format('Y-m-d') }}"
                            title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                    </div>
                </div>

                @if($p->position_applied)
                    <div class="text-muted small mb-2">
                        <i class="bi bi-briefcase"></i> Applied for: {{ $p->position_applied }}
                    </div>
                @endif

                <div class="mb-2">
                    @forelse ($skills as $skill)
                        <span class="badge text-bg-light text-dark border me-1">{{ $skill }}</span>
                    @empty
                        <span class="text-muted small">No skills listed</span>
                    @endforelse
                </div>

                <p class="small text-muted mb-2">{{ $p->notes ?: 'No notes.' }}</p>

                {{-- Add to Pipeline --}}
                <form action="{{ route('pipelines.store') }}" method="POST" class="mb-2">
                    @csrf
                    <input type="hidden" name="talent_pool_id" value="{{ $p->id }}">
                    <select name="job_posting_id" class="form-select form-select-sm mb-1" required>
                        <option value="" disabled selected>Select job posting...</option>
                        @foreach($openJobPostings as $job)
                            <option value="{{ $job->id }}">{{ $job->title }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-sm btn-outline-success w-100">
                        <i class="bi bi-diagram-3 me-1"></i> Add to Pipeline
                    </button>
                </form>

                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-muted small">
                        Added {{ $p->added_at ? $p->added_at->format('M d, Y') : '-' }}
                    </div>
                    <form action="{{ route('talent-pool.destroy', $p->id) }}" method="POST"
                          onsubmit="return confirm('Remove this candidate from the talent pool?');" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-link btn-sm p-0 text-danger" title="Remove">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="text-center text-muted py-5">
            No candidates in the talent pool yet. Click "Add to pool" to add one.
        </div>
    </div>
    @endforelse
</div>

<!-- Add to pool modal -->
<div class="modal fade" id="addTalentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('talent-pool.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add candidate to talent pool</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if ($availableCandidates->isEmpty())
                        <p class="text-muted small mb-0">All candidates are already in the talent pool.</p>
                    @else
                        <div class="mb-3">
                            <label class="form-label">Candidate</label>
                            <select name="candidate_id" class="form-select" required>
                                <option value="" disabled selected>Select candidate</option>
                                @foreach ($availableCandidates as $c)
                                    <option value="{{ $c->id }}">{{ $c->full_name }} ({{ $c->email }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Skills</label>
                            <input type="text" name="skills" class="form-control" placeholder="e.g. IT, Networking">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Added on</label>
                            <input type="date" name="added_at" class="form-control" value="{{ now()->toDateString() }}">
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    @if ($availableCandidates->isNotEmpty())
                        <button type="submit" class="btn btn-primary">Add to pool</button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit talent pool entry modal -->
<div class="modal fade" id="editTalentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editTalentForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit talent pool entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Skills</label>
                        <input type="text" name="skills" id="edit_skills" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" id="edit_notes" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Added on</label>
                        <input type="date" name="added_at" id="edit_added_at" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('editTalentModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const id = button.getAttribute('data-id');
    const skills = button.getAttribute('data-skills') || '';
    const notes = button.getAttribute('data-notes') || '';
    const addedAt = button.getAttribute('data-added_at') || '';

    const form = document.getElementById('editTalentForm');
    form.action = '/talent-pool/' + id;
    document.getElementById('edit_skills').value = skills;
    document.getElementById('edit_notes').value = notes;
    document.getElementById('edit_added_at').value = addedAt;
});

document.getElementById('talentSearch').addEventListener('input', function () {
    const query = this.value.trim().toLowerCase();
    document.querySelectorAll('.talent-card').forEach(function (card) {
        const haystack = card.dataset.name + ' ' + card.dataset.email + ' ' + card.dataset.skills + ' ' + card.dataset.position;
        card.style.display = haystack.includes(query) ? '' : 'none';
    });
});
</script>

@endsection