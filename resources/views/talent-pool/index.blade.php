@extends('layouts.app')

@section('title', 'Talent pool')
@section('page-title', 'Talent pool')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0 small">Candidates kept on file for future openings</p>
    <input type="text" class="form-control form-control-sm" style="width: 240px;" placeholder="Search by name or tag...">
</div>

<div class="row g-3">
    @foreach ($pool as $p)
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="fw-medium">{{ $p->candidate_name }}</div>
                        <div class="text-muted small">{{ $p->candidate_email }}</div>
                    </div>
                    <i class="bi bi-bookmark-star text-warning"></i>
                </div>
                <div class="mb-2">
                    @foreach (explode(',', $p->tags) as $tag)
                        <span class="badge text-bg-light text-dark border me-1">{{ trim($tag) }}</span>
                    @endforeach
                </div>
                <p class="small text-muted mb-2">{{ $p->notes }}</p>
                <div class="text-muted small">Added {{ \Carbon\Carbon::parse($p->added_at)->format('M d, Y') }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endsection