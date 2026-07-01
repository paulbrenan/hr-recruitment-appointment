<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\InterviewSchedule;
use App\Models\JobOffer;
use App\Models\JobPosting;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * All statuses in the applications.status enum, in display order.
     * Used to build the status doughnut chart so every status appears
     * even when its count is zero, keeping the legend/colors consistent
     * across visits.
     */
    private const STATUS_ORDER = [
        'submitted',
        'screening',
        'shortlisted',
        'interview_scheduled',
        'assessed',
        'ranked',
        'offer_sent',
        'offer_accepted',
        'offer_declined',
        'hired',
        'rejected',
    ];

    public function index()
    {
        $stats = [
            'open_postings' => JobPosting::where('status', 'open')->count(),
            'total_applications' => Application::count(),
            // Pending = offer sent, awaiting the candidate's response.
            'pending_offers' => JobOffer::where('status', 'sent')->count(),
            // Scheduled interviews/exams falling within the current
            // calendar week (Monday–Sunday).
            'interviews_this_week' => InterviewSchedule::where('status', 'scheduled')
                ->whereBetween('scheduled_at', [
                    Carbon::now()->startOfWeek(),
                    Carbon::now()->endOfWeek(),
                ])
                ->count(),
        ];

        // --- Monthly chart: last 6 months, relative to today ---

        $months = collect(range(5, 0))->map(function ($monthsAgo) {
            return Carbon::now()->subMonths($monthsAgo)->startOfMonth();
        });

        $monthlyLabels = $months->map(fn (Carbon $m) => $m->format('M'))->all();

        $applicationsByMonth = Application::selectRaw("DATE_FORMAT(applied_at, '%Y-%m') as ym, COUNT(*) as total")
            ->whereNotNull('applied_at')
            ->where('applied_at', '>=', $months->first())
            ->groupBy('ym')
            ->pluck('total', 'ym');

        // Only count currently-open postings here, keyed by posted_at
        // (falling back to created_at for rows where posted_at was never
        // set) so this chart's bars always sum to the same total shown in
        // the "Open postings" stat card above, rather than mixing in
        // postings that have since been filled/closed.
        $postingsByMonth = JobPosting::where('status', 'open')
            ->selectRaw("DATE_FORMAT(COALESCE(posted_at, created_at), '%Y-%m') as ym, COUNT(*) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $monthlyApplicationsData = $months->map(function (Carbon $m) use ($applicationsByMonth) {
            return (int) ($applicationsByMonth[$m->format('Y-m')] ?? 0);
        })->all();

        $firstYm = $months->first()->format('Y-m');
        $lastYm = $months->last()->format('Y-m');
        $lastIndex = $months->count() - 1;

        $monthlyPostingsData = $months->map(function (Carbon $m, $i) use ($postingsByMonth, $firstYm, $lastYm, $lastIndex) {
            $ym = $m->format('Y-m');

            if ($i === 0) {
                // Roll any open postings older than the 6-month window
                // (or with no date at all, via the COALESCE above) into
                // the first bar, so no open posting is silently dropped
                // from the total and the bars keep summing to the full
                // open-postings count.
                return (int) $postingsByMonth
                    ->filter(fn ($total, $key) => $key <= $firstYm)
                    ->sum();
            }

            if ($i === $lastIndex) {
                // Same idea at the other end: a handful of postings have
                // posted_at dates ahead of the current month (typos,
                // OCR-extracted dates, etc). Roll those into the last bar
                // too, rather than letting them fall outside the window
                // and silently disappear from the total.
                return (int) $postingsByMonth
                    ->filter(fn ($total, $key) => $key >= $lastYm)
                    ->sum();
            }

            return (int) ($postingsByMonth[$ym] ?? 0);
        })->all();

        // --- Status doughnut chart: real counts, all statuses included ---

        $statusCounts = Application::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $statusLabels = array_map(
            fn ($status) => str_replace('_', ' ', ucfirst($status)),
            self::STATUS_ORDER
        );

        $statusData = array_map(
            fn ($status) => (int) ($statusCounts[$status] ?? 0),
            self::STATUS_ORDER
        );

        // --- Recent applications: real, eager-loaded ---

        $recentApplications = Application::with(['candidate', 'jobPosting'])
            ->latest()
            ->take(3)
            ->get();

        $upcomingSchedules = InterviewSchedule::with(['application.candidate'])
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>=', Carbon::now())
            ->orderBy('scheduled_at')
            ->take(5)
            ->get();

        return view('dashboard.index', compact(
            'stats',
            'monthlyLabels',
            'monthlyApplicationsData',
            'monthlyPostingsData',
            'statusLabels',
            'statusData',
            'recentApplications',
            'upcomingSchedules'
        ));
    }
}