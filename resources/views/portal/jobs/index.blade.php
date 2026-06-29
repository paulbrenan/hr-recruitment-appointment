@extends('layouts.portal')

@section('title', 'Open Positions')

@section('content')
<div class="mb-4">
    <h4 class="fw-bold mb-0">Open Positions</h4>
    <p class="text-muted small mb-0">Click a position to view details and apply.</p>
</div>

@if (session('success'))
    <div class="alert alert-success small">{{ session('success') }}</div>
@endif

@if ($postings->isEmpty())
    <div class="text-center py-5 text-muted">
        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
        No open positions at this time. Check back later.
    </div>
@else
    <div class="row g-3">
        @foreach ($postings as $posting)
        <div class="col-12">
            <div class="card border shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold mb-1">{{ $posting->title }}</h6>
                            <div class="text-muted small">
                                @if ($posting->place_of_assignment)
                                    <i class="bi bi-geo-alt me-1"></i>{{ $posting->place_of_assignment }}
                                @endif
                                @if ($posting->salary_grade)
                                    &nbsp;&bull;&nbsp;<i class="bi bi-cash me-1"></i>Salary Grade {{ $posting->salary_grade }}
                                @endif
                                @if ($posting->employment_type)
                                    &nbsp;&bull;&nbsp;{{ $posting->employment_type }}
                                @endif
                            </div>
                            @if ($posting->closes_at)
                                <div class="small mt-1">
                                    <i class="bi bi-calendar-x text-danger me-1"></i>
                                    Closes {{ \Carbon\Carbon::parse($posting->closes_at)->format('M d, Y') }}
                                </div>
                            @endif
                        </div>
                        <div class="flex-shrink-0">
                            @if (in_array($posting->id, $appliedIds))
                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Applied</span>
                            @else
                                <a href="{{ route('portal.jobs.show', $posting->id) }}" class="btn btn-hr-primary btn-sm">
                                    View & Apply
                                </a>
                            @endif
                        </div>
                    </div>
                    @if ($posting->description)
                        <p class="small text-muted mt-2 mb-0" style="line-height:1.5;">
                            {{ Str::limit($posting->description, 160) }}
                        </p>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
@endif
@endsection