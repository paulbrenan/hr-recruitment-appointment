<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Models\AssessmentCriterion;
use App\Models\CandidateAssessment;
use App\Models\Application;
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
                'candidate_name' => $app->candidate?->full_name ?? 'Unknown',
                'scores' => $scores,
                'total_score' => $total,
            ];
        })->sortByDesc('total_score')->values();

        return view('assessments.index', compact('criteria', 'rankedCandidates', 'postings', 'selectedPostingId', 'usedWeight', 'remainingWeight'));
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

        return redirect()
            ->route('assessments.index', ['job_posting' => $validated['job_posting_id']])
            ->with('success', 'Scores saved.');
    }
}