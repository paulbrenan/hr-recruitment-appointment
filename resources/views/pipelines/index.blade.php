@extends('layouts.app')

@section('title', 'Pipelines')
@section('page-title', 'Candidate Pipelines')

@section('content')

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('info'))
    <div class="alert alert-info alert-dismissible fade show">
        {{ session('info') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <p class="text-muted mb-0 small">Track pooled candidates through stages for specific job openings</p>
</div>

@php
    $stageColors = [
        'contacted'    => 'secondary',
        'interested'   => 'primary',
        'interviewing' => 'warning',
        'placed'       => 'success',
        'dropped'      => 'danger',
    ];
@endphp

<div class="row g-3">
    @foreach($stages as $stage)
    <div class="col-md">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <span class="badge bg-{{ $stageColors[$stage] }} text-capitalize">{{ $stage }}</span>
                <span class="text-muted small">{{ isset($pipelines[$stage]) ? $pipelines[$stage]->count() : 0 }}</span>
            </div>
            <div class="card-body p-2">
                @forelse($pipelines[$stage] ?? [] as $p)
                <div class="card mb-2 shadow-sm">
                    <div class="card-body p-3">
                        <div class="fw-medium small">{{ $p->talentPool->full_name }}</div>
                        <div class="text-muted small mb-2">{{ $p->jobPosting->title }}</div>

                        {{-- Stage update form --}}
                        <form action="{{ route('pipelines.update', $p->id) }}" method="POST" class="mb-2">
                            @csrf
                            @method('PUT')
                            <select name="stage" class="form-select form-select-sm mb-1" onchange="this.form.submit()">
                                @foreach($stages as $s)
                                    <option value="{{ $s }}" {{ $p->stage === $s ? 'selected' : '' }}>
                                        {{ ucfirst($s) }}
                                    </option>
                                @endforeach
                            </select>
                        </form>

                        {{-- Notes update form --}}
                        <form action="{{ route('pipelines.update', $p->id) }}" method="POST" class="mb-2">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="stage" value="{{ $p->stage }}">
                            <textarea name="notes" class="form-control form-control-sm mb-1" rows="2"
                                placeholder="Notes...">{{ $p->notes }}</textarea>
                            <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Save notes</button>
                        </form>

                        {{-- Remove --}}
                        <form action="{{ route('pipelines.destroy', $p->id) }}" method="POST"
                              onsubmit="return confirm('Remove from pipeline?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger w-100">
                                <i class="bi bi-x-lg me-1"></i> Remove
                            </button>
                        </form>
                    </div>
                </div>
                @empty
                <p class="text-muted small text-center py-3">No candidates</p>
                @endforelse
            </div>
        </div>
    </div>
    @endforeach
</div>

@endsection