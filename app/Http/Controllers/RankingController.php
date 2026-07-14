<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\JobPosting;
use App\Notifications\RankingResultNotification;
use App\Services\RankingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class RankingController extends Controller
{
    public function __construct(private RankingService $rankingService)
    {
    }

    public function index(Request $request)
    {
        $jobPostings = JobPosting::orderByDesc('posted_at')->get();

        $selectedPosting = null;
        $rankings = collect();

        if ($request->filled('job_posting_id')) {
            $selectedPosting = JobPosting::with('assessmentCriteria')->findOrFail($request->job_posting_id);
            $rankings = $this->rankingService->computeRankings($selectedPosting);
        }

        return view('rankings.index', compact('jobPostings', 'selectedPosting', 'rankings'));
    }

    public function sendOne(Request $request, Application $application)
    {
        $request->validate([
            'job_posting_id' => 'required|exists:job_postings,id',
        ]);

        $posting = JobPosting::with('assessmentCriteria')->findOrFail($request->job_posting_id);
        $rankings = $this->rankingService->computeRankings($posting);

        $ranked = $rankings->firstWhere('application_id', $application->id);

        if (! $ranked) {
            return back()->with('error', 'Applicant has no assessment scores for this posting.');
        }

        $candidate = $application->candidate;

        $candidate->notify(new RankingResultNotification($ranked, $posting));

        $application->update(['status' => 'ranking_sent']);

        return back()->with('success', "Notification sent to {$candidate->full_name}.");
    }

    public function sendAll(Request $request)
    {
        $request->validate([
            'job_posting_id' => 'required|exists:job_postings,id',
        ]);

        $posting = JobPosting::with('assessmentCriteria')->findOrFail($request->job_posting_id);
        $rankings = $this->rankingService->computeRankings($posting);

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
}
