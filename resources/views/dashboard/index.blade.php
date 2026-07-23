@extends('layouts.app')

@section('page-title', 'Records')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<p class="text-muted small mb-3">Recruitment pipeline overview as of {{ \Carbon\Carbon::now()->format('M d, Y') }}</p>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <a href="{{ route('job-postings.index') }}" class="stat-card-link">
            <div class="card stat-card p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small">Open postings</div>
                        <div class="fs-4 fw-semibold stat-number" data-count="{{ $stats['open_postings'] }}">0</div>
                    </div>
                    <div class="stat-icon stat-icon-navy"><i class="bi bi-briefcase"></i></div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('applications.index') }}" class="stat-card-link">
            <div class="card stat-card p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small">Total applications</div>
                        <div class="fs-4 fw-semibold stat-number" data-count="{{ $stats['total_applications'] }}">0</div>
                    </div>
                    <div class="stat-icon stat-icon-gold"><i class="bi bi-person-lines-fill"></i></div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('offers.index') }}" class="stat-card-link">
            <div class="card stat-card p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small">Pending offers</div>
                        <div class="fs-4 fw-semibold stat-number" data-count="{{ $stats['pending_offers'] }}">0</div>
                    </div>
                    <div class="stat-icon stat-icon-red"><i class="bi bi-envelope-paper"></i></div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('job-postings.index') }}" class="stat-card-link">
            <div class="card stat-card p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small">Interviews this week</div>
                        <div class="fs-4 fw-semibold stat-number" data-count="{{ $stats['interviews_this_week'] }}">0</div>
                    </div>
                    <div class="stat-icon stat-icon-navy"><i class="bi bi-calendar-event"></i></div>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-7">
        <div class="card h-100 fade-card">
            <div class="card-body p-3">
                <h6 class="mb-3">Recruitment activity (last 6 months)</h6>
                <canvas id="applicationsChart" height="110"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card h-100 fade-card">
            <div class="card-body p-3">
                <h6 class="mb-3">Applications by status</h6>
                <canvas id="statusChart" height="160"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card h-100 fade-card">
            <div class="card-body p-3">
                <h6 class="mb-3">Recent applications</h6>
                @forelse ($recentApplications as $app)
                <a href="{{ route('applications.show', $app->id) }}" class="row-link">
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2 dash-row">
                        <div>
                            <div class="fw-medium small">{{ $app->candidate->full_name }}</div>
                            <div class="text-muted small">{{ $app->jobPosting->title }} &middot; {{ $app->applied_at ? \Carbon\Carbon::parse($app->applied_at)->diffForHumans() : 'date not set' }}</div>
                        </div>
                        <span class="badge status-badge status-{{ $app->status }}">{{ str_replace('_', ' ', ucfirst($app->status)) }}</span>
                    </div>
                </a>
                @empty
                <p class="text-muted small mb-0">No applications yet.</p>
                @endforelse
                <a href="{{ route('applications.index') }}" class="small d-block mt-2 view-all-link">View all applications <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100 fade-card">
            <div class="card-body p-3">
                <h6 class="mb-3">Upcoming interviews &amp; exams</h6>
                @forelse ($upcomingSchedules as $s)
                <a href="{{ route('job-postings.show', $s->application->job_posting_id) }}" class="row-link">
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2 dash-row">
                        <div>
                            <div class="fw-medium small">{{ $s->application->candidate->full_name }}</div>
                            <div class="text-muted small">{{ str_replace('_', ' ', ucfirst($s->type)) }} &middot; {{ \Carbon\Carbon::parse($s->scheduled_at)->format('M d, h:i A') }}</div>
                        </div>
                        <i class="bi bi-arrow-right text-muted"></i>
                    </div>
                </a>
                @empty
                <p class="text-muted small mb-0">No upcoming interviews or exams scheduled.</p>
                @endforelse
                <a href="{{ route('job-postings.index') }}" class="small d-block mt-2 view-all-link">View full schedule <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>
</div>

<style>
    .stat-card-link { text-decoration: none; display: block; }

    .stat-card {
        transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        cursor: pointer;
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 28px rgba(0, 48, 135, 0.12);
        border-color: rgba(0, 48, 135, 0.25);
    }

    .stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.05rem;
        flex-shrink: 0;
    }
    .stat-icon-navy { background: rgba(0, 48, 135, 0.1); color: var(--hr-primary, #003087); }
    .stat-icon-gold { background: rgba(255, 215, 0, 0.18); color: #a97e00; }
    .stat-icon-red  { background: rgba(206, 17, 38, 0.1); color: #ce1126; }

    .fade-card {
        animation: dash-fade-in 0.4s ease both;
    }
    .row.g-3.mb-3 .fade-card, .row.g-3 .fade-card:nth-of-type(1) { animation-delay: 0.05s; }

    @keyframes dash-fade-in {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .row-link { text-decoration: none; color: inherit; display: block; }
    .dash-row { transition: background-color 0.15s ease, padding-left 0.15s ease; border-radius: 6px; }
    .row-link:hover .dash-row {
        background-color: #f5f8fc;
        padding-left: 8px;
        padding-right: 8px;
        margin-right: -8px;
    }

    .view-all-link { color: var(--hr-primary, #003087); font-weight: 600; text-decoration: none; }
    .view-all-link:hover { text-decoration: underline; }

    .status-badge {
        font-weight: 600;
        font-size: 0.7rem;
        padding: 0.35em 0.65em;
        border: 1px solid transparent;
    }
    .status-submitted           { background: #eef1f5; color: #495057; border-color: #dfe3e8; }
    .status-screening           { background: rgba(13, 110, 253, 0.1); color: #0d6efd; border-color: rgba(13, 110, 253, 0.2); }
    .status-shortlisted         { background: rgba(13, 202, 240, 0.12); color: #0aa2c0; border-color: rgba(13, 202, 240, 0.25); }
    .status-interview_scheduled { background: rgba(111, 66, 193, 0.1); color: #6f42c1; border-color: rgba(111, 66, 193, 0.2); }
    .status-assessed            { background: rgba(253, 126, 20, 0.12); color: #c8630f; border-color: rgba(253, 126, 20, 0.25); }
    .status-ranked              { background: rgba(32, 201, 151, 0.12); color: #178a6c; border-color: rgba(32, 201, 151, 0.25); }
    .status-offer_sent          { background: rgba(255, 193, 7, 0.18); color: #8a6500; border-color: rgba(255, 193, 7, 0.3); }
    .status-offer_accepted      { background: rgba(25, 135, 84, 0.12); color: #157347; border-color: rgba(25, 135, 84, 0.25); }
    .status-offer_declined      { background: rgba(206, 17, 38, 0.1); color: #ce1126; border-color: rgba(206, 17, 38, 0.2); }
    .status-hired               { background: rgba(0, 48, 135, 0.12); color: #003087; border-color: rgba(0, 48, 135, 0.25); }
    .status-rejected            { background: #eef1f5; color: #6c757d; border-color: #dfe3e8; }
</style>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    // ── Animated count-up for the stat cards ────────────────────────────────
    document.querySelectorAll('.stat-number').forEach(function (el) {
        const target = parseInt(el.dataset.count, 10) || 0;
        const duration = 700;
        const start = performance.now();

        function tick(now) {
            const progress = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.round(eased * target);
            if (progress < 1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
    });

    // ── Recruitment activity (line chart) ───────────────────────────────────
    const applicationsCtx = document.getElementById('applicationsChart');

    const appGradient = applicationsCtx.getContext('2d').createLinearGradient(0, 0, 0, 220);
    appGradient.addColorStop(0, 'rgba(0, 48, 135, 0.25)');
    appGradient.addColorStop(1, 'rgba(0, 48, 135, 0)');

    const postingGradient = applicationsCtx.getContext('2d').createLinearGradient(0, 0, 0, 220);
    postingGradient.addColorStop(0, 'rgba(255, 193, 7, 0.3)');
    postingGradient.addColorStop(1, 'rgba(255, 193, 7, 0)');

    new Chart(applicationsCtx, {
        type: 'line',
        data: {
            labels: @json($monthlyLabels),
            datasets: [
                {
                    label: 'Applications',
                    data: @json($monthlyApplicationsData),
                    borderColor: '#003087',
                    backgroundColor: appGradient,
                    pointBackgroundColor: '#003087',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    borderWidth: 2.5,
                    tension: 0.35,
                    fill: true,
                },
                {
                    label: 'Job postings opened',
                    data: @json($monthlyPostingsData),
                    borderColor: '#ce1126',
                    backgroundColor: postingGradient,
                    pointBackgroundColor: '#ce1126',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    borderWidth: 2.5,
                    tension: 0.35,
                    fill: true,
                }
            ]
        },
        options: {
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: true, position: 'bottom', labels: { boxWidth: 12, font: { size: 11 }, usePointStyle: true, pointStyle: 'circle' } },
                tooltip: { backgroundColor: '#0a1a33', padding: 10, cornerRadius: 8, titleFont: { size: 12 }, bodyFont: { size: 12 } }
            },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#f0f2f5' } },
                x: { grid: { display: false } }
            },
            animation: { duration: 900, easing: 'easeOutQuart' }
        }
    });

    // ── Applications by status (doughnut) ───────────────────────────────────
    const statusCtx = document.getElementById('statusChart');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: @json($statusLabels),
            datasets: [{
                data: @json($statusData),
                backgroundColor: [
                    '#6c757d', // submitted
                    '#0d6efd', // screening
                    '#0dcaf0', // shortlisted
                    '#6f42c1', // interview scheduled
                    '#fd7e14', // assessed
                    '#20c997', // ranked
                    '#ffc107', // offer sent
                    '#198754', // offer accepted
                    '#ce1126', // offer declined
                    '#003087', // hired
                    '#adb5bd', // rejected
                ],
                borderWidth: 2,
                borderColor: '#fff',
                hoverOffset: 8,
            }]
        },
        options: {
            cutout: '68%',
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 }, usePointStyle: true, pointStyle: 'circle' } },
                tooltip: { backgroundColor: '#0a1a33', padding: 10, cornerRadius: 8 }
            },
            animation: { duration: 900, easing: 'easeOutQuart' }
        }
    });
</script>
@endpush