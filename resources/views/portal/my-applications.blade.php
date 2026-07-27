@extends('layouts.portal')

@section('title', 'My Applications')

@section('content')
<div class="mb-4">
    <h4 class="fw-bold mb-0">My Applications</h4>
    <p class="text-muted small mb-0">Track the status of your submitted applications.</p>
</div>

@if (session('success'))
    <div class="alert alert-success small">{{ session('success') }}</div>
@endif

@if ($applications->isEmpty())
    <div class="text-center py-5 text-muted">
        <i class="bi bi-file-earmark-x fs-1 d-block mb-2"></i>
        You haven't submitted any applications yet.
        <div class="mt-3">
            <a href="{{ route('portal.jobs.index') }}" class="btn btn-hr-primary btn-sm">Browse open positions</a>
        </div>
    </div>
@else
    @php
        $statusColors = [
            'submitted'            => 'secondary',
            'screening'            => 'info',
            'shortlisted'          => 'primary',
            'interview_scheduled'  => 'warning',
            'assessed'             => 'warning',
            'ranked'               => 'primary',
            'ranking_sent'         => 'primary',
            'offer_sent'           => 'success',
            'offer_accepted'       => 'success',
            'offer_declined'       => 'danger',
            'hired'                => 'success',
            'rejected'             => 'danger',
        ];
        $statusSteps = [
            'submitted'           => 1,
            'screening'           => 2,
            'shortlisted'         => 3,
            'interview_scheduled' => 4,
            'assessed'            => 5,
            'ranked'              => 5,
            'ranking_sent'        => 5,
            'offer_sent'          => 6,
            'offer_accepted'      => 7,
            'offer_declined'      => 7,
            'hired'               => 7,
            'rejected'            => 7,
        ];
    @endphp

    <div class="d-flex flex-column gap-3">
        @foreach ($applications as $app)
        @php
            $color = $statusColors[$app->status] ?? 'secondary';
            $step  = $statusSteps[$app->status] ?? 1;
            $label = ucwords(str_replace('_', ' ', $app->status));
        @endphp
        <div class="card shadow-sm border">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                    <div>
                        <h6 class="fw-bold mb-0">{{ $app->jobPosting->title ?? 'Position' }}</h6>
                        @if ($app->jobPosting->place_of_assignment)
                            <small class="text-muted">
                                <i class="bi bi-geo-alt me-1"></i>{{ $app->jobPosting->place_of_assignment }}
                            </small>
                        @endif
                    </div>
                    <span class="badge bg-{{ $color }} text-white">{{ $label }}</span>
                </div>

                {{-- Progress bar --}}
                @php $pct = round(($step / 7) * 100); @endphp
                <div class="progress mb-2" style="height:6px;">
                    <div class="progress-bar bg-{{ in_array($app->status, ['rejected','offer_declined']) ? 'danger' : 'success' }}"
                         style="width: {{ $pct }}%"></div>
                </div>
                <div class="d-flex justify-content-between" style="font-size:0.7rem;color:#888;">
                    <span>Submitted</span>
                    <span>Screening</span>
                    <span>Shortlisted</span>
                    <span>Interview</span>
                    <span>Assessment</span>
                    <span>Offer</span>
                    <span>Final</span>
                </div>

                <div class="text-muted mt-2" style="font-size:0.75rem;">
                    Application Code:
                    @if ($app->transaction_number)
                        <span class="font-monospace">{{ $app->transaction_number }}</span>
                    @else
                        <span class="fst-italic">Pending verification by Records</span>
                    @endif
                </div>

                @if ($app->applied_at)
                    <div class="text-muted mt-1" style="font-size:0.75rem;">
                        Applied {{ \Carbon\Carbon::parse($app->applied_at)->format('M d, Y') }}
                    </div>
                @endif

                @if ($app->notes)
                    <div class="mt-2 small text-muted fst-italic">"{{ Str::limit($app->notes, 100) }}"</div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
@endif
@endsection