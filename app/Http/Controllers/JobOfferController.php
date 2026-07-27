<?php

namespace App\Http\Controllers;

use App\Models\JobOffer;
use App\Models\Application;
use App\Models\JobPosting;
use App\Models\SalaryGrade;
use App\Notifications\OfferLetterNotification;
use Illuminate\Http\Request;

class JobOfferController extends Controller
{
    // SG 1 Step 1 — from the currently-confirmed imported circular, falling
    // back to config/salary_grades.php if no circular has been imported yet.
    private function minCompensation(): int
    {
        return (int) (SalaryGrade::currentTableArray()[1][0] ?? config('salary_grades.table.1.0', 14634));
    }

    public function index()
    {
        $offers = JobOffer::with(['application.candidate', 'application.jobPosting'])
            ->orderByDesc('created_at')
            ->get();

        // 'ranked' applicants get auto-promoted to 'hired' when the
        // posting closes -- check for that instead, so the actual
        // hired applicant shows up as eligible for an offer.
        $eligibleApplications = Application::with(['candidate', 'jobPosting'])
            ->whereIn('status', ['shortlisted', 'assessed', 'hired'])
            ->whereDoesntHave('jobOffer')
            ->orderByDesc('applied_at')
            ->get();

        $minCompensation = $this->minCompensation();
        $sgTable = SalaryGrade::currentTableArray();

        return view('offers.index', compact('offers', 'eligibleApplications', 'minCompensation', 'sgTable'));
    }

    public function store(Request $request)
    {
        // SG is now inherited from the job posting -- no more manual
        // SG/Step selects. Step defaults to 1; HR can still override the
        // resulting peso amount directly via compensation_override for
        // edge cases (e.g. negotiated pay) instead of picking a step.
        $validated = $request->validate([
            'job_posting_id'         => 'required|exists:job_postings,id',
            'application_ids'        => 'required|array|min:1',
            'application_ids.*'      => 'exists:applications,id|distinct',
            'sg_override'            => 'nullable|integer|min:1|max:33',
            'compensation_override'  => 'nullable|numeric|min:0',
            'response_deadline'      => 'nullable|date|after_or_equal:today',
            'benefits'                => 'nullable|string',
            'terms'                   => 'nullable|string',
        ]);

        $posting = JobPosting::findOrFail($validated['job_posting_id']);

        // Re-enforce the vacancy cap server-side. The checkbox UI already
        // disables extra boxes past this limit, but that's client-side
        // only -- never trust it alone.
        $alreadyOffered = JobOffer::whereHas('application', fn ($q) => $q->where('job_posting_id', $posting->id))
            ->whereIn('status', ['draft', 'sent', 'accepted'])
            ->count();
        $limit = max(0, ((int) $posting->vacancies ?: 1) - $alreadyOffered);

        $applicationIds = array_slice($validated['application_ids'], 0, $limit);
        if (empty($applicationIds)) {
            return back()->with('error', 'No open offer slots remain for this posting\'s vacancy count.');
        }

        // sg_override, if given, replaces the job's inherited SG as the
        // basis for the Step 1 default -- a typed compensation_override
        // still wins over both when present.
        // $posting->salary_grade is stored as free text (e.g. "SG-19" or
        // "19") -- casting a "SG-19" string straight to (int) silently
        // yields 0, which fell through to the SG-1 Step-1 default below
        // and produced wildly wrong compensation on offers. Strip any
        // non-digit prefix first, same normalization JobPostingController
        // already applies elsewhere.
        $rawGrade = $validated['sg_override'] ?? $posting->salary_grade;
        $grade   = (int) preg_replace('/[^0-9]/', '', (string) $rawGrade);
        $sgTable = SalaryGrade::currentTableArray();
        $defaultCompensation = $sgTable[$grade][0] ?? $this->minCompensation(); // SG {grade} Step 1
        $compensation = $validated['compensation_override'] ?? $defaultCompensation;

        $created = 0;
        foreach ($applicationIds as $applicationId) {
            // Skip an application that already picked up an offer between
            // this page loading and the form being submitted (the
            // eligible list already excludes these, but a stale page is
            // still possible with two HR staff on the same posting).
            if (JobOffer::where('application_id', $applicationId)->exists()) {
                continue;
            }

            JobOffer::create([
                'application_id'    => $applicationId,
                'compensation'      => $compensation,
                'response_deadline' => $validated['response_deadline'] ?? null,
                'benefits'          => $validated['benefits'] ?? null,
                'terms'             => $validated['terms'] ?? null,
                'status'            => 'draft',
            ]);
            $created++;
        }

        if ($created === 0) {
            return back()->with('error', 'Selected candidate(s) already have an offer -- nothing new was generated.');
        }

        $overrideNote = isset($validated['compensation_override'])
            ? ', manually overridden (SG ' . $grade . ')'
            : ', SG ' . $grade . ' Step 1' . (isset($validated['sg_override']) ? ' (override)' : '');

        return back()->with('success', "Generated {$created} draft offer(s) at ₱" . number_format($compensation, 2) . $overrideNote . '.');
    }

    public function send($id)
    {
        $offer = JobOffer::findOrFail($id);

        if ($offer->status !== 'draft') {
            return back()->with('error', 'Only draft offers can be sent.');
        }

        $offer->update([
            'status' => 'sent',
            'offer_sent_at' => now()->toDateString(),
        ]);

        $offer->application->update(['status' => 'offer_sent']);

        // Reload with relations so the notification has candidate/jobPosting
        // available without extra queries inside the Notification class.
        $offer->load(['application.candidate', 'application.jobPosting']);

        // Deliver the formal offer letter to the candidate. Wrapped like
        // every other notification call in this app -- status is already
        // 'sent' above, so a mail failure here must not turn into an
        // uncaught 500 that leaves the page looking completely broken
        // (the Accept/Decline buttons are gated on status === 'sent' and
        // would still be reachable on the next page load either way, but
        // an uncaught exception here means the user never SEES that).
        try {
            $offer->application->candidate->notify(new OfferLetterNotification($offer));

            // Stamp separately from offer_sent_at (which tracks the business
            // status) so this column reflects actual email dispatch, matching
            // the reminder_sent_at guard pattern used in interview schedules.
            $offer->update(['email_sent_at' => now()]);

            return back()->with('success', 'Offer sent to candidate. Offer letter emailed.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Offer letter email failed for offer ' . $offer->id . ': ' . $e->getMessage());

            return back()->with('error', 'Offer marked as sent, but the offer letter email failed to send. Check the mail configuration and try resending.');
        }
    }

    public function respond(Request $request, $id)
    {
        $validated = $request->validate([
            'response' => 'required|in:accepted,declined',
        ]);

        $offer = JobOffer::findOrFail($id);

        if ($offer->status !== 'sent') {
            return back()->with('error', 'Only sent offers can be marked accepted or declined.');
        }

        $offer->update(['status' => $validated['response']]);

        $offer->application->update([
            'status' => $validated['response'] === 'accepted' ? 'offer_accepted' : 'offer_declined',
        ]);

        return back()->with('success', 'Offer marked as ' . $validated['response'] . '.');
    }

    public function destroy($id)
    {
        $offer = JobOffer::findOrFail($id);
        $offer->delete();

        return back()->with('success', 'Offer deleted.');
    }
}