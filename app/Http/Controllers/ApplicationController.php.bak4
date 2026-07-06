<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\InterviewSchedule;
use App\Notifications\QualificationResultNotification;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function index(Request $request)
    {
        $query = Application::with(['candidate', 'jobPosting'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $applications = $query->get();

        return view('applications.index', compact('applications'));
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