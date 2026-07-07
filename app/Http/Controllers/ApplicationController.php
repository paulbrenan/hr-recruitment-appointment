<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\AssessmentCriterion;
use App\Models\InterviewSchedule;
use App\Models\JobPosting;
use App\Notifications\QualificationResultNotification;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ApplicationController extends Controller
{
    public function index(Request $request)
    {
        $query = Application::with(['candidate', 'jobPosting'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('job_posting')) {
            $query->where('job_posting_id', $request->input('job_posting'));
        }

        $applications = $query->get();

        // For the job-posting filter dropdown, and so it doubles as the
        // scope selector for the Excel export below.
        $jobPostings = JobPosting::orderBy('title')->get();

        return view('applications.index', compact('applications', 'jobPostings'));
    }

    /**
     * Export applicants to Excel from the Applications page.
     *
     * Two modes, chosen by whether a job posting is selected in the filter
     * bar (same filter logic as index() above):
     *
     *  - Scoped to a job posting: outputs Application Code + Candidate Name
     *    + one column per that posting's actual assessment criteria (e.g.
     *    "Education (10 pts)") — identical layout to what
     *    AssessmentController@importScores expects. HR can fill in scores
     *    and re-upload it directly on the Assessment & ranking page's
     *    "Import scores from Excel", with no separate template download
     *    step and no manual re-typing of tracking numbers/names.
     *  - No posting selected: a flat reference list (tracking number, name,
     *    posting, applied date, status) across whatever's currently
     *    filtered by status. No score columns here since criteria differ
     *    per posting.
     */
    public function exportExcel(Request $request)
    {
        $query = Application::with(['candidate', 'jobPosting'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $jobPostingId = $request->input('job_posting');

        if ($jobPostingId) {
            $query->where('job_posting_id', $jobPostingId);
        }

        $applications = $query->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Applicants');

        if ($jobPostingId) {
            $criteria = AssessmentCriterion::where('job_posting_id', $jobPostingId)
                ->orderBy('id')
                ->get();

            if ($criteria->isEmpty()) {
                return back()->with('error', 'Add assessment criteria for this posting (on the Assessment & ranking page) before exporting a scoring sheet.');
            }

            $col = 1;
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . '1', 'Application Code');
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . '1', 'Candidate Name');
            foreach ($criteria as $c) {
                $label = rtrim(rtrim(number_format($c->weight_percentage, 2), '0'), '.');
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . '1', "{$c->name} ({$label} pts)");
            }
            $lastColLetter = Coordinate::stringFromColumnIndex($col - 1);
            $sheet->getStyle("A1:{$lastColLetter}1")->getFont()->setBold(true);

            $row = 2;
            foreach ($applications as $app) {
                $sheet->setCellValue('A' . $row, $app->transaction_number);
                $sheet->setCellValue('B' . $row, $app->candidate?->full_name ?? 'Unknown');
                $row++;
            }

            foreach (range(1, $col - 1) as $c) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
            }

            $postingTitle = optional(JobPosting::find($jobPostingId))->title ?? 'posting';
            $filename = 'applicant-scoring-' . \Illuminate\Support\Str::slug($postingTitle) . '.xlsx';
        } else {
            $sheet->setCellValue('A1', 'Tracking Number');
            $sheet->setCellValue('B1', 'Candidate Name');
            $sheet->setCellValue('C1', 'Job Posting');
            $sheet->setCellValue('D1', 'Applied');
            $sheet->setCellValue('E1', 'Status');
            $sheet->getStyle('A1:E1')->getFont()->setBold(true);

            $row = 2;
            foreach ($applications as $app) {
                $sheet->setCellValue('A' . $row, $app->transaction_number);
                $sheet->setCellValue('B' . $row, $app->candidate?->full_name ?? 'Unknown');
                $sheet->setCellValue('C' . $row, $app->jobPosting?->title ?? '');
                $sheet->setCellValue('D' . $row, $app->applied_at ? \Carbon\Carbon::parse($app->applied_at)->format('M d, Y') : '');
                $sheet->setCellValue('E' . $row, str_replace('_', ' ', ucfirst($app->status)));
                $row++;
            }

            foreach (range(1, 5) as $c) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
            }

            $filename = 'applicant-tracking-' . now()->format('Y-m-d') . '.xlsx';
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function show($id)
    {
        $application = Application::with(['candidate', 'jobPosting'])->findOrFail($id);

        // Real interview schedules from the database
        $schedules = InterviewSchedule::where('application_id', $id)
            ->orderBy('scheduled_at')
            ->get();

        return view('applications.show', compact('application', 'schedules'));
    }

    public function updateStatus(Request $request, $id)
    {
        $application = Application::findOrFail($id);

        $validated = $request->validate([
            // Full status list kept here even though "shortlisted" and
            // "assessed" are hidden from the UI dropdown, so existing
            // records that still carry those statuses can be saved without
            // validation errors (e.g. saving notes without touching status).
            'status' => ['required', 'in:submitted,screening,shortlisted,interview_scheduled,assessed,ranked,offer_sent,offer_accepted,offer_declined,hired,rejected'],
            'notes' => ['nullable', 'string'],
        ]);

        $application->update($validated);

        // When an applicant is marked hired:
        //   1. Close the job posting
        //   2. Reject all other applicants on the same posting
        if ($validated['status'] === 'hired') {
            $posting = JobPosting::find($application->job_posting_id);
            if ($posting) {
                $posting->update(['status' => 'closed']);

                Application::where('job_posting_id', $posting->id)
                    ->where('id', '!=', $application->id)
                    ->where('status', '!=', 'hired')
                    ->update(['status' => 'rejected']);
            }
        }

        return redirect()
            ->route('applications.show', $application->id)
            ->with('success', 'Application status updated successfully.');
    }

    /**
     * Save HR's per-criterion qualification check, matching the official
     * CSC-approved QS notice format: for each of Education / Experience /
     * Training / Eligibility, HR records the candidate's actual
     * qualification text and marks it Qualified / Not qualified. The
     * overall result (used to pick which notice template to send) is
     * "qualified" only if every criterion passes.
     *
     * item_number and chair_name are typed fresh each time (per the current
     * workflow — not stored on the job posting), so they're saved into the
     * qualification_check JSON blob alongside the criteria for the record.
     */
    public function saveQualificationCheck(Request $request, $id)
    {
        $application = Application::findOrFail($id);

        $validated = $request->validate([
            'item_number' => ['nullable', 'string', 'max:255'],
            'chair_name' => ['nullable', 'string', 'max:255'],
            'evaluation_date' => ['nullable', 'date'],
            'education_actual' => ['nullable', 'string', 'max:500'],
            // Each *_passed decision is REQUIRED — leaving a row unmarked
            // must not silently count as "Not qualified". Previously these
            // were 'nullable', so a row HR forgot to click defaulted to
            // false via `?? false` below, which could disqualify an
            // otherwise-qualified candidate without any indication why.
            'education_passed' => ['required', 'boolean'],
            'experience_actual' => ['nullable', 'string', 'max:500'],
            'experience_passed' => ['required', 'boolean'],
            'training_actual' => ['nullable', 'string', 'max:500'],
            'training_passed' => ['required', 'boolean'],
            'eligibility_actual' => ['nullable', 'string', 'max:500'],
            'eligibility_passed' => ['required', 'boolean'],
            'check_notes' => ['nullable', 'string'],
        ], [
            '*_passed.required' => 'Please mark every criterion as Qualified or Not qualified before saving.',
        ]);

        $criteria = [
            'education' => [
                'actual' => $validated['education_actual'] ?? null,
                'passed' => (bool) ($validated['education_passed'] ?? false),
            ],
            'experience' => [
                'actual' => $validated['experience_actual'] ?? null,
                'passed' => (bool) ($validated['experience_passed'] ?? false),
            ],
            'training' => [
                'actual' => $validated['training_actual'] ?? null,
                'passed' => (bool) ($validated['training_passed'] ?? false),
            ],
            'eligibility' => [
                'actual' => $validated['eligibility_actual'] ?? null,
                'passed' => (bool) ($validated['eligibility_passed'] ?? false),
            ],
        ];

        $passed = collect($criteria)->every(fn ($c) => $c['passed']);

        $check = [
            'item_number' => $validated['item_number'] ?? null,
            'chair_name' => $validated['chair_name'] ?? null,
            'evaluation_date' => $validated['evaluation_date'] ?? now()->toDateString(),
            'criteria' => $criteria,
            'notes' => $validated['check_notes'] ?? null,
        ];

        $application->update([
            'qualification_check' => $check,
            'qualification_result' => $passed ? 'qualified' : 'disqualified',
            'qualification_checked_at' => now(),
        ]);

        return redirect()
            ->route('applications.show', $application->id)
            ->with('success', 'Qualification check saved. Result: ' . ($passed ? 'Qualified' : 'Disqualified') . '.');
    }

    /**
     * Email the candidate their qualification result: an HTML summary in
     * the email body, plus the official CSC-format notice as a PDF
     * attachment (Qualified or Disqualified template, chosen automatically
     * based on qualification_result).
     */
    public function sendQualificationNotice($id)
    {
        $application = Application::with(['candidate', 'jobPosting'])->findOrFail($id);

        if (empty($application->qualification_result) || empty($application->qualification_check)) {
            return redirect()
                ->route('applications.show', $application->id)
                ->with('error', 'Run the qualification check before sending a notice.');
        }

        $application->candidate->notify(new QualificationResultNotification($application));

        $application->update(['qualification_notified_at' => now()]);

        return redirect()
            ->route('applications.show', $application->id)
            ->with('success', 'Qualification result notice emailed to the candidate, with the official notice PDF attached.');
    }
}