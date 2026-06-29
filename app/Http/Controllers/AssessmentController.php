<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Models\AssessmentCriterion;
use App\Models\CandidateAssessment;
use App\Models\Application;
use App\Notifications\RankingResultNotification;
use Illuminate\Http\Request;

class AssessmentController extends Controller
{
    public function index(Request $request)
    {
        $postings = JobPosting::orderBy('title')->get();

        $selectedPostingId = $request->query('job_posting');

        if (!$selectedPostingId && $postings->isNotEmpty()) {
            $selectedPostingId = $postings->first()->id;
        }

        $criteria = AssessmentCriterion::where('job_posting_id', $selectedPostingId)
            ->orderBy('id')
            ->get();

        $usedWeight = $criteria->sum('weight_percentage');
        $remainingWeight = max(0, 100 - $usedWeight);

        $applications = Application::with(['candidate', 'assessments'])
            ->where('job_posting_id', $selectedPostingId)
            ->get();

        $selectedPosting = JobPosting::find($selectedPostingId);

        $rankedCandidates = $applications->map(function ($app) use ($criteria) {
            $scores = [];
            $total = 0;

            foreach ($criteria as $c) {
                $assessment = $app->assessments->firstWhere('assessment_criteria_id', $c->id);
                $score = $assessment ? (float) $assessment->score : null;
                $scores[$c->id] = $score;
                if ($score !== null) {
                    $total += $score;
                }
            }

            return (object) [
                'application_id' => $app->id,
                'candidate'      => $app->candidate,
                'candidate_name' => $app->candidate?->full_name ?? 'Unknown',
                'scores'         => $scores,
                'total_score'    => $total,
                'notification_sent' => $app->status === 'ranking_sent',
            ];
        })->sortByDesc('total_score')->values();

        // Attach rank and passed flag
        $total_count = $rankedCandidates->count();
        $rankedCandidates = $rankedCandidates->map(function ($cand, $i) use ($total_count) {
            $cand->rank   = $i + 1;
            $cand->passed = $cand->total_score >= 75;
            $cand->total  = $total_count;
            return $cand;
        });

        return view('assessments.index', compact('criteria', 'rankedCandidates', 'postings', 'selectedPostingId', 'selectedPosting', 'usedWeight', 'remainingWeight'));
    }

    public function storeCriterion(Request $request)
    {
        $validated = $request->validate([
            'job_posting_id' => 'required|exists:job_postings,id',
            'name' => 'required|string|max:255',
            'weight_percentage' => 'required|numeric|min:0.01|max:100',
            'description' => 'nullable|string',
        ]);

        $existingWeight = AssessmentCriterion::where('job_posting_id', $validated['job_posting_id'])
            ->sum('weight_percentage');

        $newTotal = $existingWeight + $validated['weight_percentage'];

        if ($newTotal > 100) {
            $remaining = max(0, 100 - $existingWeight);

            return back()
                ->withErrors(['weight_percentage' => "Total weight for this posting would be {$newTotal}%, which exceeds 100%. Only {$remaining}% remains available."])
                ->withInput()
                ->with('openAddCriterionModal', true);
        }

        AssessmentCriterion::create($validated);

        return redirect()
            ->route('assessments.index', ['job_posting' => $validated['job_posting_id']])
            ->with('success', 'Assessment criterion added.');
    }

    public function destroyCriterion($id)
    {
        $criterion = AssessmentCriterion::findOrFail($id);
        $jobPostingId = $criterion->job_posting_id;
        $criterion->delete();

        return redirect()
            ->route('assessments.index', ['job_posting' => $jobPostingId])
            ->with('success', 'Assessment criterion removed.');
    }

    public function saveScores(Request $request)
    {
        $validated = $request->validate([
            'application_id' => 'required|exists:applications,id',
            'job_posting_id' => 'required|exists:job_postings,id',
            'scores' => 'required|array',
            'scores.*' => 'nullable|numeric|min:0',
            'evaluator_remarks' => 'nullable|string',
            'evaluated_by' => 'nullable|string|max:255',
        ]);

        $criteria = AssessmentCriterion::whereIn('id', array_keys($validated['scores']))
            ->get()
            ->keyBy('id');

        foreach ($validated['scores'] as $criterionId => $score) {
            if ($score === null || $score === '') {
                continue;
            }

            $criterion = $criteria->get($criterionId);
            $maxScore = $criterion ? (float) $criterion->weight_percentage : 100;

            if ((float) $score > $maxScore) {
                return back()
                    ->withErrors(["scores.$criterionId" => "Score for \"{$criterion->name}\" cannot exceed its weight of {$maxScore}."])
                    ->withInput();
            }

            CandidateAssessment::updateOrCreate(
                [
                    'application_id' => $validated['application_id'],
                    'assessment_criteria_id' => $criterionId,
                ],
                [
                    'score' => $score,
                    'evaluator_remarks' => $validated['evaluator_remarks'] ?? null,
                    'evaluated_by' => $validated['evaluated_by'] ?? null,
                ]
            );
        }

        // Auto-send ranking notification after scores are saved
        $this->autoSendNotification($validated['application_id'], $validated['job_posting_id']);

        return redirect()
            ->route('assessments.index', ['job_posting' => $validated['job_posting_id']])
            ->with('success', 'Scores saved and ranking notification sent to the applicant.');
    }

    /**
     * Automatically compute and send ranking notification after scores are saved.
     */
    private function autoSendNotification(int $applicationId, int $jobPostingId): void
    {
        try {
            $posting  = JobPosting::with('assessmentCriteria')->findOrFail($jobPostingId);
            $criteria = AssessmentCriterion::where('job_posting_id', $jobPostingId)->get();

            // Get ALL applications to compute correct rank
            $allApps = Application::with(['candidate', 'assessments'])
                ->where('job_posting_id', $jobPostingId)
                ->whereHas('assessments')
                ->get();

            // Compute totals for all applicants
            $ranked = $allApps->map(function ($app) use ($criteria) {
                $total = 0;
                foreach ($criteria as $c) {
                    $assessment = $app->assessments->firstWhere('assessment_criteria_id', $c->id);
                    if ($assessment) $total += (float) $assessment->score;
                }
                return ['app' => $app, 'total' => $total];
            })->sortByDesc('total')->values();

            $totalCount = $ranked->count();

            // Find this specific applicant in the ranked list
            foreach ($ranked as $i => $item) {
                if ($item['app']->id != $applicationId) continue;

                $app = $item['app'];
                if (! $app->candidate) break;

                $rankedData = [
                    'application_id' => $app->id,
                    'candidate'      => $app->candidate,
                    'weighted_score' => round($item['total'], 2),
                    'rank'           => $i + 1,
                    'total'          => $totalCount,
                    'passed'         => $item['total'] >= 75,
                ];

                $app->candidate->notify(new RankingResultNotification($rankedData, $posting));
                $app->update(['status' => 'ranking_sent']);
                break;
            }
        } catch (\Exception $e) {
            // Silently fail — don't block the score save if notification fails
            \Log::error('Auto ranking notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Send ranking notification to a single applicant.
     */
    public function sendOne(Request $request, Application $application)
    {
        $request->validate([
            'job_posting_id' => 'required|exists:job_postings,id',
        ]);

        $posting  = JobPosting::with('assessmentCriteria')->findOrFail($request->job_posting_id);
        $criteria = AssessmentCriterion::where('job_posting_id', $posting->id)->get();

        $app = Application::with(['candidate', 'assessments'])
            ->findOrFail($application->id);

        $total = 0;
        foreach ($criteria as $c) {
            $assessment = $app->assessments->firstWhere('assessment_criteria_id', $c->id);
            if ($assessment) $total += (float) $assessment->score;
        }

        $allApps = Application::with(['candidate', 'assessments'])
            ->where('job_posting_id', $posting->id)
            ->get();

        $totals = $allApps->map(fn($a) => $criteria->sum(fn($c) =>
            (float) ($a->assessments->firstWhere('assessment_criteria_id', $c->id)?->score ?? 0)
        ))->sort()->values()->reverse()->values();

        $rank = $totals->search(fn($s) => $s === $total) + 1;

        $ranked = [
            'application_id' => $app->id,
            'candidate'      => $app->candidate,
            'weighted_score' => round($total, 2),
            'rank'           => $rank,
            'total'          => $allApps->count(),
            'passed'         => $total >= 75,
        ];

        $app->candidate->notify(new RankingResultNotification($ranked, $posting));
        $app->update(['status' => 'ranking_sent']);

        return redirect()
            ->route('assessments.index', ['job_posting' => $request->job_posting_id])
            ->with('success', "Notification sent to {$app->candidate->full_name}.");
    }

    /**
     * Send ranking notifications to ALL applicants of a posting.
     */
    public function sendAll(Request $request)
    {
        $request->validate([
            'job_posting_id' => 'required|exists:job_postings,id',
        ]);

        $posting  = JobPosting::with('assessmentCriteria')->findOrFail($request->job_posting_id);
        $criteria = AssessmentCriterion::where('job_posting_id', $posting->id)->get();

        $applications = Application::with(['candidate', 'assessments'])
            ->where('job_posting_id', $posting->id)
            ->whereHas('assessments')
            ->get();

        if ($applications->isEmpty()) {
            return back()->with('error', 'No assessed applicants found for this posting.');
        }

        // Compute totals and sort for ranking
        $ranked = $applications->map(function ($app) use ($criteria) {
            $total = 0;
            foreach ($criteria as $c) {
                $assessment = $app->assessments->firstWhere('assessment_criteria_id', $c->id);
                if ($assessment) $total += (float) $assessment->score;
            }
            return ['app' => $app, 'total' => $total];
        })->sortByDesc('total')->values();

        $totalCount = $ranked->count();
        $sent = 0;

        foreach ($ranked as $i => $item) {
            $app = $item['app'];
            if (! $app->candidate) continue;

            $rankedData = [
                'application_id' => $app->id,
                'candidate'      => $app->candidate,
                'weighted_score' => round($item['total'], 2),
                'rank'           => $i + 1,
                'total'          => $totalCount,
                'passed'         => $item['total'] >= 75,
            ];

            $app->candidate->notify(new RankingResultNotification($rankedData, $posting));
            $app->update(['status' => 'ranking_sent']);
            $sent++;
        }

        return redirect()
            ->route('assessments.index', ['job_posting' => $request->job_posting_id])
            ->with('success', "Ranking notifications sent to {$sent} applicant(s).");
    }
}