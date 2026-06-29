<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\JobPosting;
use App\Notifications\RankingResultNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class RankingController extends Controller
{
    /**
     * Show ranking results for a specific job posting.
     */
    public function index(Request $request)
    {
        $jobPostings = JobPosting::orderByDesc('posted_at')->get();

        $selectedPosting = null;
        $rankings = collect();

        if ($request->filled('job_posting_id')) {
            $selectedPosting = JobPosting::with('assessmentCriteria')->findOrFail($request->job_posting_id);
            $rankings = $this->computeRankings($selectedPosting);
        }

        return view('rankings.index', compact('jobPostings', 'selectedPosting', 'rankings'));
    }

    /**
     * Send ranking notification to a single applicant.
     */
    public function sendOne(Request $request, Application $application)
    {
        $request->validate([
            'job_posting_id' => 'required|exists:job_postings,id',
        ]);

        $posting = JobPosting::with('assessmentCriteria')->findOrFail($request->job_posting_id);
        $rankings = $this->computeRankings($posting);

        $ranked = $rankings->firstWhere('application_id', $application->id);

        if (! $ranked) {
            return back()->with('error', 'Applicant has no assessment scores for this posting.');
        }

        $candidate = $application->candidate;

        $candidate->notify(new RankingResultNotification($ranked, $posting));

        $application->update(['status' => 'ranking_sent']);

        return back()->with('success', "Notification sent to {$candidate->full_name}.");
    }

    /**
     * Send ranking notifications to all applicants of a job posting.
     */
    public function sendAll(Request $request)
    {
        $request->validate([
            'job_posting_id' => 'required|exists:job_postings,id',
        ]);

        $posting = JobPosting::with('assessmentCriteria')->findOrFail($request->job_posting_id);
        $rankings = $this->computeRankings($posting);

        if ($rankings->isEmpty()) {
            return back()->with('error', 'No assessment scores found for this posting.');
        }

        $sent = 0;

        foreach ($rankings as $ranked) {
            $application = Application::with('candidate')->find($ranked['application_id']);

            if (! $application || ! $application->candidate) {
                continue;
            }

            $application->candidate->notify(new RankingResultNotification($ranked, $posting));
            $application->update(['status' => 'ranking_sent']);
            $sent++;
        }

        return back()->with('success', "Ranking notifications sent to {$sent} applicant(s).");
    }

    /**
     * Compute weighted rankings for all assessed applicants of a posting.
     *
     * Score formula:
     *   weighted_score = SUM( (criterion.weight_percentage / 100) * assessment.score )
     *
     * Returns a collection sorted by weighted_score DESC, with rank assigned.
     */
    private function computeRankings(JobPosting $posting): \Illuminate\Support\Collection
    {
        // Load all applications for this posting that have at least one assessment
        $applications = Application::with([
            'candidate',
            'assessments.criterion',
        ])
            ->where('job_posting_id', $posting->id)
            ->whereHas('assessments')
            ->get();

        $rows = $applications->map(function (Application $app) {
            $weightedScore = 0;

            foreach ($app->assessments as $assessment) {
                $weight = $assessment->criterion?->weight_percentage ?? 0;
                $score  = $assessment->score ?? 0;
                $weightedScore += ($weight / 100) * $score;
            }

            return [
                'application_id' => $app->id,
                'candidate'      => $app->candidate,
                'weighted_score' => round($weightedScore, 2),
                'status'         => $app->status,
                'notification_sent' => $app->status === 'ranking_sent',
            ];
        });

        // Sort descending and assign rank
        return $rows->sortByDesc('weighted_score')
            ->values()
            ->map(function ($row, $index) use ($rows) {
                $row['rank']   = $index + 1;
                $row['passed'] = $row['weighted_score'] >= 75; // passing threshold
                $row['total']  = $rows->count();
                return $row;
            });
    }
}
