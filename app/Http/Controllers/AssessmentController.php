<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Models\AssessmentCriterion;
use App\Models\CandidateAssessment;
use App\Models\Application;
use App\Models\CarDistrictSignatory;
use App\Models\CarHrmpsbSignatory;
use App\Notifications\RankingResultNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

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

        // The official CAR template hard-wraps "Accomplishments" as
        // "Accomplishm" / "ents" across two lines in its narrow column.
        // That survives whitespace-collapsing above as two separate
        // tokens with a space between them, so "outstanding
        // accomplishments" would never match as written. Rejoin this
        // known fragment before matching.
        $normalized = str_replace('accomplishm ents', 'accomplishments', $normalized);
        // The RTP-SG forms wrap the same word at a different point --
        // "Accomplishme" / "nts" -- so both split variants are rejoined.
        $normalized = str_replace('accomplishme nts', 'accomplishments', $normalized);

        // Known official DepEd Cavite CAR templates each redistribute the
        // same 100 points differently across the same criterion names
        // (e.g. Performance is 25 pts on the school-admin form but 20 pts
        // on both RTP-SG forms), and several of them scramble multi-word
        // labels across narrow table columns in the extracted text. Both
        // problems are avoided by recognizing each known template up
        // front via a short label that sits outside the table and
        // survives extraction intact, then using that template's
        // pre-verified exact weights directly. Anything unrecognized
        // falls through to the generic word scanner below.
        $knownTemplates = [
            'rtp-sg 11 to 15' => [
                'Education' => 10, 'Training' => 10, 'Experience' => 10, 'Performance' => 20,
                'Outstanding Accomplishments' => 10, 'Application of Education' => 10,
                'Application of Learning and Development' => 10, 'Potential' => 20,
            ],
            'rtp-sg 16 to 23' => [
                'Education' => 10, 'Training' => 10, 'Experience' => 10, 'Performance' => 20,
                'Outstanding Accomplishments' => 5, 'Application of Education' => 15,
                'Application of Learning and Development' => 10, 'Potential' => 20,
            ],
            'annex i-2' => [
                'Education' => 10, 'Training' => 10, 'Experience' => 10, 'Performance' => 30,
                'PPST COIs (Classroom Observation/Demo Teaching)' => 25,
                'PPST NCOIs (Portfolio Annotation and BEI)' => 15,
            ],
            'school administration for secondary level' => [
                'Education' => 10, 'Training' => 10, 'Experience' => 10, 'Performance' => 25,
                'Outstanding Accomplishments' => 10, 'Application of Education' => 10,
                'Application of Learning and Development' => 10, 'Potential' => 15,
            ],
        ];

        foreach ($knownTemplates as $fingerprint => $criteria) {
            if (str_contains($normalized, $fingerprint)) {
                return $criteria;
            }
        }

        $result = [];

        $multiWord = [
            ['patterns' => ['application of learning and development', 'application of l&d', 'application of l & d'], 'name' => 'Application of Learning and Development', 'weight' => 10],
            ['patterns' => ['application of education'], 'name' => 'Application of Education', 'weight' => 10],
            ['patterns' => ['outstanding accomplishments', 'outstanding accomplishment'], 'name' => 'Outstanding Accomplishments', 'weight' => 10],
            ['patterns' => ['ppst cois'], 'name' => 'PPST COIs (Classroom Observation/Demo Teaching)', 'weight' => 25],
            ['patterns' => ['ppst ncois'], 'name' => 'PPST NCOIs (Portfolio Annotation and BEI)', 'weight' => 15],
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

        // The teaching-position CAR (PPST-based, e.g. Annex I-2) weights
        // Performance at 30 instead of the school-admin form's 25, since
        // its 100-point total is split differently between criteria.
        $isTeachingProfile = isset($result['PPST COIs (Classroom Observation/Demo Teaching)'])
            || isset($result['PPST NCOIs (Portfolio Annotation and BEI)']);

        $singleWord = [
            'performance' => ['Performance', $isTeachingProfile ? 30 : 25],
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

    /**
     * Windows' shell has no /dev/null -- shell_exec() there runs through
     * cmd.exe, which doesn't understand that path and can make the whole
     * redirected command misbehave. NUL is the Windows equivalent.
     */
    private function nullDevice(): string
    {
        return PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    }

    private function extractTextFromPdf(string $path): string
    {
        // `-layout` reconstructs the page by visual column position, which
        // is fine for simple documents but scrambles word order on narrow
        // multi-column tables (e.g. the official CAR form splits
        // "application of education" across columns so the words end up
        // nowhere near each other). Plain content-stream order doesn't have
        // that problem on such documents, so both are extracted and
        // concatenated -- this is only used for keyword matching, not
        // structure, so duplicated/reordered text is harmless.
        $layoutText = @shell_exec('pdftotext -layout ' . escapeshellarg($path) . ' - 2>' . $this->nullDevice());
        $plainText  = @shell_exec('pdftotext ' . escapeshellarg($path) . ' - 2>' . $this->nullDevice());
        $combined   = trim((string) $layoutText) . "\n" . trim((string) $plainText);

        if (trim($combined) !== '' && strlen(trim($combined)) > 20) {
            return $combined;
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
        shell_exec('pdftoppm -png -r 200 ' . escapeshellarg($path) . ' ' . escapeshellarg($prefix) . ' 2>' . $this->nullDevice());

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
        return (string) @shell_exec('tesseract ' . escapeshellarg($path) . ' stdout 2>' . $this->nullDevice());
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

        // Disqualified/rejected applicants are never scored or ranked (see
        // JobPostingController::show, $rankableApplications) -- exclude
        // them here too, or the template would let HR type in CAR scores
        // for someone who already failed qualification checking.
        $applications = Application::with('candidate')
            ->where('job_posting_id', $jobPostingId)
            ->whereNotIn('status', ['not_qualified', 'rejected'])
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
     * Export the current CAR-EQA rankings for this posting as a filled-in
     * official-format Excel document.
     *
     * Layout is chosen from the posting's salary grade: SG 11-15 gets the
     * District Sub-Committee layout, SG 16 and up gets the HRMPSB layout
     * (extra Background Investigation / Appointment / Probation columns).
     * Anything outside that range falls back to the simpler District
     * layout -- there's no official variant for it yet.
     *
     * Signatories come from CarDistrictSignatory / CarHrmpsbSignatory
     * (managed under Signatories in the sidebar) and are matched by
     * keywords in their `position` field: "chairman" gets its own slot,
     * "co-chairman" its own row, "appointing authority" (HRMPSB only) is
     * placed at the bottom, everyone else renders as a plain member.
     */
    public function downloadCarDocument(Request $request)
    {
        $request->validate([
            'job_posting_id' => 'required|exists:job_postings,id',
        ]);

        $jobPostingId = $request->query('job_posting_id') ?? $request->input('job_posting_id');
        $posting = JobPosting::findOrFail($jobPostingId);
        $criteria = AssessmentCriterion::where('job_posting_id', $jobPostingId)->orderBy('id')->get();

        if ($criteria->isEmpty()) {
            return back()->with('error', 'Add assessment criteria for this posting before exporting the CAR.');
        }

        $applications = Application::with(['candidate', 'assessments'])
            ->where('job_posting_id', $jobPostingId)
            ->whereHas('assessments')
            ->get();

        if ($applications->isEmpty()) {
            return back()->with('error', 'No scored applicants to export for this posting.');
        }

        $ranked = $applications->map(function ($app) use ($criteria) {
            $scores = [];
            $total = 0;
            foreach ($criteria as $c) {
                $assessment = $app->assessments->firstWhere('assessment_criteria_id', $c->id);
                $score = $assessment ? (float) $assessment->score : null;
                $scores[$c->id] = $score;
                $total += (float) $score;
            }
            return ['app' => $app, 'scores' => $scores, 'total' => $total];
        })->sortByDesc('total')->values();

        $grade = (int) preg_replace('/\D/', '', (string) $posting->salary_grade);
        $variant = $grade >= 16 ? 'hrmpsb' : 'district';

        $signatories = $variant === 'hrmpsb'
            ? CarHrmpsbSignatory::orderBy('id')->get()
            : CarDistrictSignatory::orderBy('id')->get();

        $chairman = $signatories->first(fn ($s) => stripos($s->position, 'chairman') !== false && stripos($s->position, 'co-chairman') === false);
        $coChairmen = $signatories->filter(fn ($s) => stripos($s->position, 'co-chairman') !== false)->values();
        $appointingAuthority = $signatories->first(fn ($s) => stripos($s->position, 'appointing authority') !== false);
        $members = $signatories->reject(function ($s) use ($chairman, $coChairmen, $appointingAuthority) {
            return ($chairman && $s->is($chairman))
                || $coChairmen->contains(fn ($cc) => $cc->is($s))
                || ($appointingAuthority && $s->is($appointingAuthority));
        })->values();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('CAR');

        // -- Letterhead --
        $sheet->setCellValue('A1', 'Republic of the Philippines');
        $sheet->setCellValue('A2', 'Department of Education');
        $sheet->setCellValue('A3', 'REGION IV-A');
        $sheet->setCellValue('A4', 'SCHOOLS DIVISION OFFICE OF CAVITE PROVINCE');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(13);
        foreach ([1, 2, 3, 4] as $r) {
            $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $row = 6;
        $sheet->setCellValue("A{$row}", 'COMPARATIVE ASSESSMENT RESULT (CAR)');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row += 2;

        $sheet->setCellValue("A{$row}", 'Position:');
        $sheet->setCellValue("B{$row}", $posting->title);
        $row++;
        $sheet->setCellValue("A{$row}", 'Office/Bureau/Service/Unit where the vacancy exists:');
        $sheet->setCellValue("D{$row}", $posting->place_of_assignment ?? '');
        $row += 2;

        // -- Table header (two rows: group header + per-criterion sub-header) --
        $tableHeaderRow = $row;
        $sheet->setCellValue("A{$tableHeaderRow}", 'No.');
        $sheet->setCellValue("B{$tableHeaderRow}", 'Name of Applicant');
        $sheet->setCellValue("C{$tableHeaderRow}", 'Application Code');
        $sheet->mergeCells("A{$tableHeaderRow}:A" . ($tableHeaderRow + 1));
        $sheet->mergeCells("B{$tableHeaderRow}:B" . ($tableHeaderRow + 1));
        $sheet->mergeCells("C{$tableHeaderRow}:C" . ($tableHeaderRow + 1));

        $criteriaStartCol = 4;
        $col = $criteriaStartCol;
        foreach ($criteria as $c) {
            $label = rtrim(rtrim(number_format($c->weight_percentage, 2), '0'), '.');
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . ($tableHeaderRow + 1), "{$c->name} ({$label} pts)");
            $col++;
        }
        $totalCol = $col;
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($totalCol) . ($tableHeaderRow + 1), 'Total');
        $sheet->mergeCells(Coordinate::stringFromColumnIndex($criteriaStartCol) . "{$tableHeaderRow}:" . Coordinate::stringFromColumnIndex($totalCol) . $tableHeaderRow);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($criteriaStartCol) . $tableHeaderRow, 'COMPARATIVE ASSESSMENT RESULTS');
        // NOTE: Total's header cell already sits inside the merge above --
        // it must NOT also be merged vertically on its own, or PhpSpreadsheet
        // ends up with two overlapping merged ranges sharing that cell,
        // which makes Excel blank out the whole group-header row on render.
        $col = $totalCol + 1;

        $remarksCol = $col;
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($remarksCol) . $tableHeaderRow, 'Remarks');
        $sheet->mergeCells(Coordinate::stringFromColumnIndex($remarksCol) . "{$tableHeaderRow}:" . Coordinate::stringFromColumnIndex($remarksCol) . ($tableHeaderRow + 1));
        $col++;

        if ($variant === 'hrmpsb') {
            $bgYesCol = $col;
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($bgYesCol) . $tableHeaderRow, 'For Background Investigation (Y/N)');
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($bgYesCol) . ($tableHeaderRow + 1), 'Yes');
            $col++;
            $bgNoCol = $col;
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($bgNoCol) . ($tableHeaderRow + 1), 'No');
            $sheet->mergeCells(Coordinate::stringFromColumnIndex($bgYesCol) . "{$tableHeaderRow}:" . Coordinate::stringFromColumnIndex($bgNoCol) . $tableHeaderRow);
            $col++;

            $apptCol = $col;
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($apptCol) . $tableHeaderRow, 'For Appointment');
            $sheet->mergeCells(Coordinate::stringFromColumnIndex($apptCol) . "{$tableHeaderRow}:" . Coordinate::stringFromColumnIndex($apptCol) . ($tableHeaderRow + 1));
            $col++;

            $probCol = $col;
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($probCol) . $tableHeaderRow, 'For Probation');
            $sheet->mergeCells(Coordinate::stringFromColumnIndex($probCol) . "{$tableHeaderRow}:" . Coordinate::stringFromColumnIndex($probCol) . ($tableHeaderRow + 1));
            $col++;
        }

        $lastCol = $col - 1;
        $lastColLetter = Coordinate::stringFromColumnIndex($lastCol);
        $headerRange = "A{$tableHeaderRow}:{$lastColLetter}" . ($tableHeaderRow + 1);
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // -- Data rows --
        $dataStartRow = $tableHeaderRow + 2;
        $r = $dataStartRow;
        foreach ($ranked as $i => $item) {
            $app = $item['app'];
            $sheet->setCellValue("A{$r}", $i + 1);
            $sheet->setCellValue("B{$r}", $app->candidate->full_name ?? 'Unknown');
            $sheet->setCellValue("C{$r}", $app->transaction_number ?? '');

            $c = $criteriaStartCol;
            foreach ($criteria as $crit) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($c) . $r, $item['scores'][$crit->id] ?? '');
                $c++;
            }
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($totalCol) . $r, $item['total']);
            // Remarks, and (HRMPSB) Background Investigation/Appointment/Probation
            // are left blank -- these are filled in by hand after deliberation,
            // same as on the blank official template.
            $sheet->getStyle("A{$r}:{$lastColLetter}{$r}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $r++;
        }

        // -- Signature block --
        $r += 2;
        $sheet->setCellValue("A{$r}", $variant === 'hrmpsb' ? 'Prepared by the HRMPSB' : 'Prepared by the District Sub-Committee');
        $r++;
        $sheet->setCellValue("A{$r}", '(All members should affix signature)');
        $r += 3;

        $sigCol = 1;
        $placeSignatory = function ($signatory) use ($sheet, &$sigCol, &$r, $lastCol) {
            $letter = Coordinate::stringFromColumnIndex($sigCol);
            $sheet->setCellValue("{$letter}{$r}", strtoupper($signatory->name));
            $sheet->getStyle("{$letter}{$r}")->getFont()->setBold(true);
            $sheet->setCellValue($letter . ($r + 1), $signatory->position);
            $sigCol += 3;
            if ($sigCol > $lastCol) {
                $sigCol = 1;
                $r += 3;
            }
        };

        if ($chairman) {
            $placeSignatory($chairman);
        }
        foreach ($coChairmen as $cc) {
            $placeSignatory($cc);
        }
        $r += 3;
        $sigCol = 1;
        foreach ($members as $m) {
            $placeSignatory($m);
        }

        if ($variant === 'hrmpsb' && $appointingAuthority) {
            $r += 3;
            $sheet->setCellValue("A{$r}", 'Appointment conferred by:');
            $r += 2;
            $sheet->setCellValue("A{$r}", strtoupper($appointingAuthority->name));
            $sheet->getStyle("A{$r}")->getFont()->setBold(true);
            $sheet->setCellValue('A' . ($r + 1), $appointingAuthority->position);
        }

        $sheet->getColumnDimension('A')->setWidth(5);   // No.
        $sheet->getColumnDimension('B')->setWidth(26);  // Name of Applicant
        $sheet->getColumnDimension('C')->setWidth(16);  // Application Code
        foreach (range($criteriaStartCol, $totalCol) as $c) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(13); // criteria + Total (header text wraps)
        }
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($remarksCol))->setWidth(18);
        if ($variant === 'hrmpsb') {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($bgYesCol))->setWidth(7);
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($bgNoCol))->setWidth(7);
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($apptCol))->setWidth(14);
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($probCol))->setWidth(14);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'CAR-' . Str::slug($posting->title) . '.xlsx';

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