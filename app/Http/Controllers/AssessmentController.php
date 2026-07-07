<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Models\AssessmentCriterion;
use App\Models\CandidateAssessment;
use App\Models\Application;
use App\Notifications\RankingResultNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class AssessmentController extends Controller
{
    public function index(Request $request)
    {
        // All postings with their locations eager-loaded
        $allPostings = JobPosting::with('locations')->orderBy('title')->get();

        // Unique titles for the first dropdown
        $postings = $allPostings->unique('title')->values();

        // Which title is selected? Default to the first unique title.
        $selectedTitle = $request->query('title');
        if (!$selectedTitle && $postings->isNotEmpty()) {
            $selectedTitle = $postings->first()->title;
        }

        // All postings matching the selected title (one per place of assignment)
        $locationPostings = $allPostings->where('title', $selectedTitle)->values();

        // Which specific posting (place of assignment) is selected?
        $selectedPostingId = $request->query('job_posting');

        // Auto-select the first location posting if none chosen yet
        if (!$selectedPostingId && $locationPostings->isNotEmpty()) {
            $selectedPostingId = $locationPostings->first()->id;
        }

        $criteria = AssessmentCriterion::where('job_posting_id', $selectedPostingId)
            ->orderBy('id')
            ->get();

        $usedWeight = $criteria->sum('weight_percentage');
        $remainingWeight = max(0, 100 - $usedWeight);

        $applications = Application::with(['candidate', 'assessments'])
            ->where('job_posting_id', $selectedPostingId)
            ->get();

        $selectedPosting = JobPosting::with('locations')->find($selectedPostingId);

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

            // The official CAR form's "Application Code" is the applicant-facing
            // identifier that stays visible when the name is concealed for public
            // posting (RA No. 10163 / Data Privacy Act) — the app already generates
            // one per application, so reuse it rather than adding a new field.
            $remarks = optional($app->assessments->first())->evaluator_remarks;

            return (object) [
                'application_id'   => $app->id,
                'application_code' => $app->transaction_number,
                'candidate'        => $app->candidate,
                'candidate_name'   => $app->candidate?->full_name ?? 'Unknown',
                'scores'           => $scores,
                'total_score'      => $total,
                'remarks'          => $remarks,
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

        return view('assessments.index', compact('criteria', 'rankedCandidates', 'postings', 'selectedPostingId', 'selectedPosting', 'usedWeight', 'remainingWeight', 'locationPostings', 'selectedTitle'));
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

    /**
     * Generate a ready-to-fill Excel template for this posting: Application
     * Code + candidate name (reference only, not read back on import) for
     * every current applicant, then one column per this posting's actual
     * criteria — so HR only has to type in scores, not codes or headers.
     */
    public function downloadImportTemplate(Request $request)
    {
        $request->validate([
            'job_posting_id' => 'required|exists:job_postings,id',
        ]);

        $jobPostingId = $request->query('job_posting_id') ?? $request->input('job_posting_id');

        $criteria = AssessmentCriterion::where('job_posting_id', $jobPostingId)
            ->orderBy('id')
            ->get();

        if ($criteria->isEmpty()) {
            return back()->with('error', 'Add assessment criteria for this posting before downloading a template.');
        }

        $applications = Application::with('candidate')
            ->where('job_posting_id', $jobPostingId)
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Scores');

        // Header row
        $col = 1;
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . '1', 'Application Code');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . '1', 'Candidate Name');
        foreach ($criteria as $c) {
            $label = rtrim(rtrim(number_format($c->weight_percentage, 2), '0'), '.');
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . '1', "{$c->name} ({$label} pts)");
        }
        $lastColLetter = Coordinate::stringFromColumnIndex($col - 1);
        $sheet->getStyle("A1:{$lastColLetter}1")->getFont()->setBold(true);

        // One row per current applicant, code + name pre-filled, scores blank
        $row = 2;
        foreach ($applications as $app) {
            $sheet->setCellValue('A' . $row, $app->transaction_number);
            $sheet->setCellValue('B' . $row, $app->candidate?->full_name ?? 'Unknown');
            $row++;
        }

        foreach (range(1, $col - 1) as $c) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'car-import-template-' . $jobPostingId . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Bulk-import scores from the official CAR-format Excel file.
     *
     * Rows are matched to applicants by "Application Code" (the app's own
     * transaction_number), and columns are matched to this posting's
     * criteria by name (e.g. "Education (10 pts)" -> criterion "Education"),
     * so it works regardless of which/how many criteria the posting has.
     */
    public function importScores(Request $request)
    {
        $validated = $request->validate([
            'job_posting_id' => 'required|exists:job_postings,id',
            'import_file' => 'required|file|mimes:xlsx,xls',
        ]);

        $jobPostingId = $validated['job_posting_id'];
        $criteria = AssessmentCriterion::where('job_posting_id', $jobPostingId)->get();

        if ($criteria->isEmpty()) {
            return back()->with('error', 'Add assessment criteria for this posting before importing scores.');
        }

        try {
            $spreadsheet = IOFactory::load($request->file('import_file')->getRealPath());
        } catch (\Exception $e) {
            return back()->with('error', 'Could not read the uploaded file: ' . $e->getMessage());
        }

        $sheet = $spreadsheet->getActiveSheet();
        // Keyed by column letter (A, B, C...) and 1-indexed row number
        $rows = $sheet->toArray(null, true, true, true);

        // Locate the header row/column that says "Application Code" — this
        // anchors everything else, so we don't have to assume a fixed row
        // number (the official template has it on row 14, but be tolerant).
        $headerRow = null;
        $appCodeCol = null;
        foreach ($rows as $rowNum => $row) {
            foreach ($row as $col => $val) {
                if (is_string($val) && trim($val) === 'Application Code') {
                    $headerRow = $rowNum;
                    $appCodeCol = $col;
                    break 2;
                }
            }
        }

        if (!$headerRow) {
            return back()->with('error', 'Could not find an "Application Code" column in the uploaded file. Please use the official CAR template for this posting.');
        }

        // Match criterion columns against a small helper so we can try two
        // header layouts: our own template (Application Code + criteria all
        // on one row) and the official CAR template (criteria one row below
        // "Application Code", matching its two-row header).
        $mapColumns = function (array $headerRowValues) use ($criteria) {
            $map = [];
            foreach ($headerRowValues as $col => $val) {
                if (!is_string($val) || trim($val) === '') continue;
                $cleanName = trim(preg_replace('/\(.*?pts?\)/i', '', $val));

                foreach ($criteria as $c) {
                    if (strcasecmp(trim($c->name), $cleanName) === 0) {
                        $map[$col] = $c;
                        break;
                    }
                }
            }
            return $map;
        };

        $subHeaderRow = $headerRow;
        $columnCriterionMap = $mapColumns($rows[$headerRow]);

        if (empty($columnCriterionMap)) {
            // Fall back to the official template's two-row header.
            $subHeaderRow = $headerRow + 1;
            $columnCriterionMap = $mapColumns($rows[$subHeaderRow] ?? []);
        }

        if (empty($columnCriterionMap)) {
            return back()->with('error', 'None of the score columns in the uploaded file matched this posting\'s criteria names. Check that criterion names (e.g. "Education") match exactly.');
        }

        $dataStartRow = $subHeaderRow + 1;
        $matched = 0;
        $unmatchedCodes = [];
        $outOfRange = [];

        foreach ($rows as $rowNum => $row) {
            if ($rowNum < $dataStartRow) continue;

            $code = trim((string) ($row[$appCodeCol] ?? ''));
            if ($code === '') continue;

            $application = Application::where('job_posting_id', $jobPostingId)
                ->where('transaction_number', $code)
                ->first();

            if (!$application) {
                $unmatchedCodes[] = $code;
                continue;
            }

            foreach ($columnCriterionMap as $col => $criterion) {
                $rawScore = $row[$col] ?? null;
                if ($rawScore === null || $rawScore === '') continue;
                if (!is_numeric($rawScore)) continue;

                $score = (float) $rawScore;
                if ($score > (float) $criterion->weight_percentage) {
                    $outOfRange[] = "{$code} / {$criterion->name}";
                    continue;
                }

                CandidateAssessment::updateOrCreate(
                    [
                        'application_id' => $application->id,
                        'assessment_criteria_id' => $criterion->id,
                    ],
                    ['score' => $score]
                );
            }

            $matched++;
        }

        $message = "Imported scores for {$matched} applicant(s).";
        if (!empty($unmatchedCodes)) {
            $message .= ' Unmatched application codes: ' . implode(', ', array_unique($unmatchedCodes)) . '.';
        }
        if (!empty($outOfRange)) {
            $message .= ' Skipped out-of-range scores: ' . implode(', ', array_unique($outOfRange)) . '.';
        }

        return redirect()
            ->route('assessments.index', ['job_posting' => $jobPostingId])
            ->with((!empty($unmatchedCodes) || !empty($outOfRange)) ? 'error' : 'success', $message);
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
            Log::error('Auto ranking notification failed: ' . $e->getMessage());
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