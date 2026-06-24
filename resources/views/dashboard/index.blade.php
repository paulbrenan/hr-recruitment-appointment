@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<p class="text-muted small mb-3">Recruitment pipeline overview as of {{ \Carbon\Carbon::now()->format('M d, Y') }}</p>

<div class="row g-2 mb-3">
    <div class="col-md-3">
        <div class="card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted small">Open postings</div>
                    <div class="fs-4 fw-semibold">{{ $stats['open_postings'] }}</div>
                </div>
                <i class="bi bi-briefcase fs-5 text-muted"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted small">Total applications</div>
                    <div class="fs-4 fw-semibold">{{ $stats['total_applications'] }}</div>
                </div>
                <i class="bi bi-person-lines-fill fs-5 text-muted"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted small">Pending offers</div>
                    <div class="fs-4 fw-semibold">{{ $stats['pending_offers'] }}</div>
                </div>
                <i class="bi bi-envelope-paper fs-5 text-muted"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted small">Interviews this week</div>
                    <div class="fs-4 fw-semibold">{{ $stats['interviews_this_week'] }}</div>
                </div>
                <i class="bi bi-calendar-event fs-5 text-muted"></i>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-7">
        <div class="card h-100">
            <div class="card-body p-3">
                <h6 class="mb-3">Recruitment activity (last 6 months)</h6>
                <canvas id="applicationsChart" height="110"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card h-100">
            <div class="card-body p-3">
                <h6 class="mb-3">Applications by status</h6>
                <canvas id="statusChart" height="160"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body p-3">
                <h6 class="mb-3">Recent applications</h6>
                @foreach ($recentApplications as $app)
                <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                    <div>
                        <div class="fw-medium small">{{ $app->candidate->full_name }}</div>
                        <div class="text-muted small">{{ $app->jobPosting->title }} &middot; {{ $app->applied_at ? \Carbon\Carbon::parse($app->applied_at)->diffForHumans() : 'date not set' }}</div>
                    </div>
                    <span class="badge text-bg-light text-dark border">{{ str_replace('_', ' ', ucfirst($app->status)) }}</span>
                </div>
                @endforeach
                <a href="{{ route('applications.index') }}" class="small d-block mt-2">View all applications &rarr;</a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body p-3">
                <h6 class="mb-3">Upcoming interviews &amp; exams</h6>
                @forelse ($upcomingSchedules as $s)
                <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                    <div>
                        <div class="fw-medium small">{{ $s->application->candidate->full_name }}</div>
                        <div class="text-muted small">{{ str_replace('_', ' ', ucfirst($s->type)) }} &middot; {{ \Carbon\Carbon::parse($s->scheduled_at)->format('M d, h:i A') }}</div>
                    </div>
                    <i class="bi bi-arrow-right text-muted"></i>
                </div>
                @empty
                <p class="text-muted small mb-0">No upcoming interviews or exams scheduled.</p>
                @endforelse
                <a href="{{ route('interviews.index') }}" class="small d-block mt-2">View full schedule &rarr;</a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    const applicationsCtx = document.getElementById('applicationsChart');
    new Chart(applicationsCtx, {
        type: 'line',
        data: {
            labels: @json($monthlyLabels),
            datasets: [
                {
                    label: 'Applications',
                    data: @json($monthlyApplicationsData),
                    borderColor: '#3f7d8c',
                    backgroundColor: 'rgba(63, 125, 140, 0.1)',
                    tension: 0.3,
                    fill: true,
                },
                {
                    label: 'Job postings opened',
                    data: @json($monthlyPostingsData),
                    borderColor: '#dba514',
                    backgroundColor: 'rgba(219, 165, 20, 0.1)',
                    tension: 0.3,
                    fill: true,
                }
            ]
        },
        options: {
            plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    const statusCtx = document.getElementById('statusChart');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: @json($statusLabels),
            datasets: [{
                data: @json($statusData),
                backgroundColor: ['#6c757d', '#0dcaf0', '#2f4858', '#ffc107', '#198754', '#dc3545'],
            }]
        },
        options: {
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
        }
    });
</script>
@endpush