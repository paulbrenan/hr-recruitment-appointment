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
    // index() removed -- the old standalone Assessment & Ranking page is
    // gone. Its data (criteria, ranked candidates, weights) is now built
    // directly inside the job-postings pipeline's Assessment & Results
    // step -- see JobPostingController / the pipeline view instead.

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

        return back()->with('success', 'Assessment criterion added.');
    }

    public function destroyCriterion($id)
    {
        $criterion = AssessmentCriterion::findOrFail($id);
        $jobPostingId = $criterion->job_posting_id;
        $criterion->delete();

        return back()->with('success', 'Assessment criterion removed.');
    }

    /**
     * Delete every assessment criterion for a given posting at once.
     * Used by the "Delete all" button on the job-postings.show pipeline
     * view. Redirects back to whichever page the request came from.
     */
    public function destroyAllCriteria(Request $request)
    {
        $validated = $request->validate([
            'job_posting_id' => 'required|exists:job_postings,id',
        ]);

        $count = AssessmentCriterion::where('job_posting_id', $validated['job_posting_id'])->count();
        AssessmentCriterion::where('job_posting_id', $validated['job_posting_id'])->delete();

        return back()->with('success', "Deleted all {$count} assessment criteria for this posting.");
    }

    /**
     * Scan an uploaded PDF/DOCX/XLSX/image for recognized assessment
     * criteria names and create whichever ones are found, with their
     * standard CSC merit-selection weight. Existing criteria and anything
     * that would push the posting over 100% weight are skipped.
     */
    public function importCriteriaScan(Request $request)
    {
        $validated = $request->validate([
            'job_posting_id' => 'required|exists:job_postings,id',
            'criteria_file'  => 'required|file|mimes:pdf,docx,xlsx,xls,jpg,jpeg,png|max:20480',
        ]);

        $file = $request->file('criteria_file');
        $ext  = strtolower($file->getClientOriginalExtension());
        $tmpPath = $file->getRealPath();

        try {
            $text = match ($ext) {
                'xlsx', 'xls'        => $this->extractTextFromSpreadsheet($tmpPath),
                'docx'               => $this->extractTextFromDocx($tmpPath),
                'pdf'                => $this->extractTextFromPdf($tmpPath),
                'jpg', 'jpeg', 'png' => $this->extractTextFromImage($tmpPath),
                default              => '',
            };
        } catch (\Throwable $e) {
            Log::warning('Criteria scan extraction failed: ' . $e->getMessage());
            return back()->with('error', 'Could not read that file. Try a clearer scan or a digital copy.');
        }

        if (trim((string) $text) === '') {
            return back()->with('error', 'No readable text found in that file.');
        }

        $matches = $this->matchCriteriaCatalog($text);

        if (empty($matches)) {
            return back()->with('error', 'No recognized criteria names found in that file (Education, Training, Experience, Performance, Outstanding Accomplishments, Application of Education, Application of Learning and Development, Potential).');
        }

        $existingNames = AssessmentCriterion::where('job_posting_id', $validated['job_posting_id'])
            ->pluck('name')
            ->map(fn($n) => strtolower(trim($n)))
            ->toArray();

        $existingWeight = (float) AssessmentCriterion::where('job_posting_id', $validated['job_posting_id'])
            ->sum('weight_percentage');

        $created = 0;
        $skippedExisting = 0;
        $skippedWeight = 0;

        foreach ($matches as $name => $weight) {
            if (in_array(strtolower($name), $existingNames, true)) {
                $skippedExisting++;
                continue;
            }
            if ($existingWeight + $weight > 100) {
                $skippedWeight++;
                continue;
            }

            AssessmentCriterion::create([
                'job_posting_id'    => $validated['job_posting_id'],
                'name'              => $name,
                'weight_percentage' => $weight,
            ]);

            $existingWeight += $weight;
            $created++;
        }

        $msg = "Scanned file: added {$created} criterion/criteria.";
        if ($skippedExisting > 0) $msg .= " Skipped {$skippedExisting} already added.";
        if ($skippedWeight   > 0) $msg .= " Skipped {$skippedWeight} that would exceed 100% weight.";

        return back()->with($created > 0 ? 'success' : 'error', $msg);
    }

    /**
     * Matches known CSC merit-selection criteria names inside extracted
     * text and returns [canonical name => standard weight]. Multi-word
     * phrases are checked first and stripped from the working buffer so
     * e.g. "Application of Education" doesn't also register as a
     * standalone "Education" match.
     */
    private function matchCriteriaCatalog(string $text): array
    {
        $normalized = preg_replace('/\s+/', ' ', strtolower($text));
        $result = [];

        $multiWord = [
            ['patterns' => ['application of learning and development', 'application of l&d', 'application of l & d'], 'name' => 'Application of Learning and Development', 'weight' => 10],
            ['patterns' => ['application of education'], 'name' => 'Application of Education', 'weight' => 10],
            ['patterns' => ['outstanding accomplishments', 'outstanding accomplishment'], 'name' => 'Outstanding Accomplishments', 'weight' => 10],
        ];

        foreach ($multiWord as $def) {
            foreach ($def['patterns'] as $p) {
                if (str_contains($normalized, $p)) {
                    $result[$def['name']] = $def['weight'];
                    $normalized = str_replace($p, ' ', $normalized);
                    break;
                }
            }
        }

        $singleWord = [
            'performance' => ['Performance', 25],
            'experience'  => ['Experience', 10],
            'training'    => ['Training', 10],
            'potential'   => ['Potential', 15],
            'education'   => ['Education', 10],
        ];

        foreach ($singleWord as $needle => [$name, $weight]) {
            if (preg_match('/\b' . preg_quote($needle, '/') . '\b/', $normalized)) {
                $result[$name] = $weight;
            }
        }

        return $result;
    }

    private function extractTextFromSpreadsheet(string $path): string
    {
        $spreadsheet = IOFactory::load($path);
        $text = '';
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            foreach ($sheet->toArray() as $row) {
                $text .= implode(' ', array_map(fn($c) => (string) $c, $row)) . "\n";
            }
        }
        return $text;
    }

    private function extractTextFromDocx(string $path): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (!$xml) {
            return '';
        }
        $text = preg_replace('/<[^>]+>/', ' ', $xml);
        return html_entity_decode((string) $text);
    }

    private function extractTextFromPdf(string $path): string
    {
        $text = @shell_exec('pdftotext -layout ' . escapeshellarg($path) . ' - 2>/dev/null');
        if ($text && strlen(trim($text)) > 20) {
            return $text;
        }
        // Likely a scanned/photographed PDF -- OCR fallback, same tools
        // the job posting PDF import already relies on.
        return $this->ocrPdf($path);
    }

    private function ocrPdf(string $path): string
    {
        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'criteria_ocr_' . uniqid();
        mkdir($tmpDir, 0755, true);
        $prefix = $tmpDir . DIRECTORY_SEPARATOR . 'page';
        shell_exec('pdftoppm -png -r 200 ' . escapeshellarg($path) . ' ' . escapeshellarg($prefix) . ' 2>/dev/null');

        $text = '';
        foreach (glob($prefix . '*.png') as $img) {
            $text .= $this->extractTextFromImage($img) . "\n";
        }

        foreach (glob($tmpDir . '/*') as $f) {
            @unlink($f);
        }
        @rmdir($tmpDir);

        return $text;
    }

    private function extractTextFromImage(string $path): string
    {
        return (string) @shell_exec('tesseract ' . escapeshellarg($path) . ' stdout 2>/dev/null');
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

        return back()->with((!empty($unmatchedCodes) || !empty($outOfRange)) ? 'error' : 'success', $message);
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

        return back()->with('success', 'Scores saved and ranking notification sent to the applicant.');
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

        return back()->with('success', "Notification sent to {$app->candidate->full_name}.");
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

        return back()->with('success', "Ranking notifications sent to {$sent} applicant(s).");
    }
}