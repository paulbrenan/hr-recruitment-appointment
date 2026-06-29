@extends('layouts.app')

@section('title', 'Application details')
@section('page-title', 'Application details')

@section('content')
@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
<div class="row g-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body p-4 text-center">
                <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px;">
                    <i class="bi bi-person fs-3 text-muted"></i>
                </div>
                <h6 class="mb-0">{{ $application->candidate->full_name }}</h6>
                <p class="text-muted small mb-3">{{ $application->candidate->email }}</p>
                <p class="text-muted small mb-1"><i class="bi bi-telephone me-1"></i> {{ $application->candidate->phone ?? '—' }}</p>
                <hr>
                <p class="small text-muted mb-1">Applying for</p>
                <p class="fw-medium mb-0">{{ $application->jobPosting->title }}</p>
                <hr>
                @if($application->status === 'rejected')
       @if($application->talentPool)
           <span class="badge bg-success">Already in Talent Pool</span>
       @else
           <form action="{{ route('talent-pool.store-from-application', $application->id) }}" method="POST">
               @csrf
               <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                   <i class="bi bi-bookmark-plus me-1"></i> Add to Talent Pool
               </button>
           </form>
       @endif
       <hr>
   @endif
                <form action="{{ route('applications.destroy', $application->id) }}" method="POST" onsubmit="return confirm('Delete this application? This will also delete any linked documents, interview schedules, assessments, job offers, and appointments. This cannot be undone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                        <i class="bi bi-trash me-1"></i> Delete application
                    </button>
                </form>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body p-4">
                <h6 class="mb-3">Documents</h6>
                @foreach ($documents as $doc)
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="small"><i class="bi bi-file-earmark-text me-2"></i>{{ $doc->document_type }}</span>
                    <a href="#" class="small">Download</a>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-body p-4">
                <h6 class="mb-3">Application status</h6>
                @if ($errors->any())
                    <div class="alert alert-danger small">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <form action="{{ route('applications.updateStatus', $application->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <select name="status" class="form-select">
                        @foreach (['submitted', 'screening', 'shortlisted', 'interview_scheduled', 'assessed', 'ranked', 'offer_sent', 'offer_accepted', 'offer_declined', 'hired', 'rejected'] as $status)
                            <option value="{{ $status }}" {{ old('status', $application->status) === $status ? 'selected' : '' }}>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                        @endforeach
                    </select>
                    <textarea name="notes" class="form-control mt-2" rows="2" placeholder="Add notes about this application...">{{ old('notes', $application->notes) }}</textarea>
                    <button type="submit" class="btn btn-sm mt-2" style="background-color: var(--hr-primary); color: #fff;">Update status</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-4">
                <h6 class="mb-3">Interview / exam schedule</h6>
                @forelse ($schedules as $s)
                <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                    <div>
                        <div class="fw-medium small">{{ str_replace('_', ' ', ucfirst($s->type)) }}</div>
                        <div class="text-muted small">{{ \Carbon\Carbon::parse($s->scheduled_at)->format('M d, Y h:i A') }} &middot; {{ $s->location }}</div>
                    </div>
                    <span class="badge text-bg-secondary">{{ ucfirst($s->status) }}</span>
                </div>
                @empty
                <p class="text-muted small mb-0">No schedule set yet.</p>
                @endforelse
                <button class="btn btn-sm btn-outline-secondary mt-3">
                    <i class="bi bi-plus-lg me-1"></i> Schedule interview/exam
                </button>
            </div>
        </div>
    </div>
</div>
@endsection