<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\JobPosting;
use App\Notifications\QualificationResultNotification;
use App\Notifications\QualifiedScheduleNotification;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
     * Always uses the official CAR template file (resources/templates/
     * car-school-admin-elem.xlsx — the real CSC/DepEd form, letterhead and
     * all) and fills in "Application Code" (col D) and "Name of Applicant"
     * (col C) starting at row 16 on every sheet in the file, for whichever
     * applications are currently filtered (status and/or job posting, same
     * filters as index() above). Score columns (E–M) stay blank for the
     * committee to fill in by hand and re-upload via "Import scores from
     * Excel" on the Assessment & ranking page.
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

        $templatePath = resource_path('templates/car-school-admin-elem.xlsx');

        if (!file_exists($templatePath)) {
            return back()->with('error', 'CAR template file is missing. It should be at resources/templates/car-school-admin-elem.xlsx.');
        }

        $spreadsheet = IOFactory::load($templatePath);

        // First data row in the template; rows 16–25 are pre-numbered 1–10.
        // If there are more than 10 applicants, extend past row 25 by
        // copying that row's style so borders/formatting continue.
        $firstDataRow = 16;
        $lastTemplateRow = 25;

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $row = $firstDataRow;
            foreach ($applications as $app) {
                if ($row > $lastTemplateRow) {
                    $sheet->duplicateStyle(
                        $sheet->getStyle('B' . $lastTemplateRow . ':M' . $lastTemplateRow),
                        'B' . $row . ':M' . $row
                    );
                    $sheet->setCellValue('B' . $row, $row - $firstDataRow + 1);
                }

                $sheet->setCellValue('C' . $row, $app->candidate?->full_name ?? 'Unknown');
                $sheet->setCellValue('D' . $row, $app->transaction_number);
                $row++;
            }
        }

        $postingTitle = $jobPostingId
            ? (optional(JobPosting::find($jobPostingId))->title ?? 'posting')
            : 'all-postings';
        $filename = 'CAR-' . \Illuminate\Support\Str::slug($postingTitle) . '.xlsx';

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

        return view('applications.show', compact('application'));
    }

    public function updateStatus(Request $request, $id)
    {
        $application = Application::findOrFail($id);

        $validated = $request->validate([
            // Full status list kept here even though "shortlisted" and
            // "assessed" are hidden from the UI dropdown, so existing
            // records that still carry those statuses can be saved without
            // validation errors (e.g. saving notes without touching status).
            'status' => ['required', 'in:submitted,shortlisted,interview_scheduled,assessed,ranked,qualified,not_qualified,offer_sent,offer_accepted,offer_declined,hired,rejected'],
            'notes' => ['nullable', 'string'],
        ]);

        $application->update($validated);

        // When an applicant is marked hired:
        //   1. Reject other applicants competing for the SAME place of
        //      assignment only -- a different place under the same
        //      posting is unaffected and stays open.
        //   2. Close the job posting itself only once EVERY place of
        //      assignment (or, for a legacy posting with no location
        //      rows, the single vacancies count) has no open slots left.
        if ($validated['status'] === 'hired') {
            $posting = JobPosting::with('locations')->find($application->job_posting_id);
            if ($posting) {
                Application::where('job_posting_id', $posting->id)
                    ->where('id', '!=', $application->id)
                    ->where('status', '!=', 'hired')
                    ->when(
                        $application->job_posting_location_id !== null,
                        fn ($q) => $q->where('job_posting_location_id', $application->job_posting_location_id),
                        fn ($q) => $q->whereNull('job_posting_location_id')
                    )
                    ->update(['status' => 'rejected']);

                if (!$posting->fresh('locations')->hasAnyOpenVacancy()) {
                    $posting->update(['status' => 'closed']);
                }
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
            'qualification_result' => $passed ? 'qualified' : 'not_qualified',
            'qualification_checked_at' => now(),
            'status' => $passed ? 'qualified' : 'not_qualified',
        ]);

        return redirect()
            ->route('job-postings.show', ['id' => $application->job_posting_id, 'step' => 2])
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

    /**
     * Bulk version of sendQualificationNotice(): emails every applicant in
     * one job posting who currently has the given qualification_result
     * ('qualified' or 'not_qualified') their result notice + PDF, in one
     * click from the group header button. Applicants without a saved
     * qualification_check are skipped (nothing to notify them of yet).
     * Re-sending is allowed -- this always sends, even to applicants who
     * were already notified once, same as the per-applicant "Resend result"
     * button.
     */
    public function sendAllQualificationNotices(Request $request, $jobPostingId)
    {
        $validated = $request->validate([
            'result' => ['required', 'in:qualified,not_qualified'],
        ]);

        $applications = Application::with(['candidate', 'jobPosting'])
            ->where('job_posting_id', $jobPostingId)
            ->where('qualification_result', $validated['result'])
            ->whereNotNull('qualification_check')
            ->get();

        $sent = 0;
        $first = true;
        foreach ($applications as $application) {
            // Throttle: mail sandboxes (e.g. Mailtrap testing plan) reject
            // rapid consecutive sends with "550 Too many emails per
            // second". A short pause between sends keeps us under that
            // limit without meaningfully slowing down the request.
            if (!$first) {
                // Mailtrap's Sandbox rate limit is a rolling 10-second
                // window (not literally "per second" despite the error
                // text) -- 12s clears it with margin.
                sleep(12);
            }
            $first = false;

            try {
                $application->candidate->notify(new QualificationResultNotification($application));
                $application->update(['qualification_notified_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Bulk qualification notice failed for application ' . $application->id . ': ' . $e->getMessage());
            }
        }

        $label = $validated['result'] === 'qualified' ? 'qualified' : 'disqualified';

        return redirect()
            ->route('job-postings.show', ['id' => $jobPostingId, 'step' => 2])
            ->with('success', "Emailed qualification result to {$sent} {$label} applicant(s).");
    }

    /**
     * Step 3 "Send all emails" bulk action, triggered from the Interview /
     * exam schedules panel. For every applicant on this posting who has a
     * saved qualification check and hasn't been notified by this button
     * before (schedule_notice_sent_at is null):
     *
     *   - qualified + has a schedule  -> QualifiedScheduleNotification
     *                                    (qualified letter + schedule details, one email)
     *   - not qualified               -> QualificationResultNotification
     *                                    (disqualified template)
     *   - qualified but NO schedule   -> QualificationResultNotification, forced to
     *                                    the disqualified template via $overridePassed.
     *                                    qualification_result on the record itself is
     *                                    left untouched -- only the outgoing email
     *                                    template is affected.
     *
     * Already-notified applicants (schedule_notice_sent_at not null) are
     * skipped, so re-clicking the button only emails newly-added or
     * newly-scheduled applicants.
     */
    public function sendAllScheduleNotices($jobPostingId)
    {
        $applications = Application::with(['candidate', 'jobPosting', 'interviewSchedules' => function ($q) {
                $q->orderByDesc('scheduled_at');
            }])
            ->where('job_posting_id', $jobPostingId)
            ->whereNotNull('qualification_result')
            ->whereNotNull('qualification_check')
            ->whereNull('schedule_notice_sent_at')
            ->get();

        if ($applications->isEmpty()) {
            return redirect()
                ->route('job-postings.show', ['id' => $jobPostingId, 'step' => 3])
                ->with('error', 'No applicants left to notify — everyone eligible has already been emailed.');
        }

        $sent = 0;
        $first = true;

        foreach ($applications as $application) {
            // Same Mailtrap-sandbox throttle used by sendAllQualificationNotices().
            if (!$first) {
                sleep(12);
            }
            $first = false;

            $schedule = $application->interviewSchedules->first();
            $isQualified = $application->qualification_result === 'qualified';

            try {
                if ($isQualified && $schedule) {
                    $application->candidate->notify(new QualifiedScheduleNotification($application, $schedule));
                } else {
                    // Not qualified, or qualified but never scheduled -> disqualification letter.
                    $forcePassedFalse = ($isQualified && !$schedule) ? false : null;
                    $application->candidate->notify(new QualificationResultNotification($application, $forcePassedFalse));
                }

                $application->update(['schedule_notice_sent_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Bulk schedule notice failed for application ' . $application->id . ': ' . $e->getMessage());
            }
        }

        return redirect()
            ->route('job-postings.show', ['id' => $jobPostingId, 'step' => 3])
            ->with('success', "Emailed {$sent} applicant(s).");
    }
}