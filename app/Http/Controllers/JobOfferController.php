<?php

namespace App\Http\Controllers;

use App\Models\JobOffer;
use App\Models\Application;
use App\Notifications\OfferLetterNotification;
use Illuminate\Http\Request;

class JobOfferController extends Controller
{
    // SG 1 Step 1 — derived from config/salary_grades.php at runtime
    private function minCompensation(): int
    {
        return config('salary_grades.table.1.0', 14634); // index 0 = step 1
    }

    public function index()
    {
        $offers = JobOffer::with(['application.candidate', 'application.jobPosting'])
            ->orderByDesc('created_at')
            ->get();

        $eligibleApplications = Application::with(['candidate', 'jobPosting'])
            ->whereIn('status', ['shortlisted', 'assessed', 'ranked'])
            ->whereDoesntHave('jobOffer')
            ->orderByDesc('applied_at')
            ->get();

        $minCompensation = $this->minCompensation();

        return view('offers.index', compact('offers', 'eligibleApplications', 'minCompensation'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'application_id'  => 'required|exists:applications,id|unique:job_offers,application_id',
            'salary_grade'    => 'required|integer|min:1|max:33',
            'salary_step'     => 'required|integer|min:1|max:8',
            'response_deadline' => 'nullable|date|after_or_equal:today',
            'benefits'        => 'nullable|string',
            'terms'           => 'nullable|string',
        ]);

        // Resolve the exact compensation from the official SG table
        $grade        = (int) $validated['salary_grade'];
        $step         = (int) $validated['salary_step'];
        $sgTable      = config('salary_grades.table');
        $compensation = $sgTable[$grade][$step - 1] ?? $this->minCompensation();

        JobOffer::create([
            'application_id'    => $validated['application_id'],
            'compensation'      => $compensation,
            'response_deadline' => $validated['response_deadline'] ?? null,
            'benefits'          => $validated['benefits'] ?? null,
            'terms'             => $validated['terms'] ?? null,
            'status'            => 'draft',
        ]);

        return redirect()->route('offers.index')->with('success', "Offer generated as draft — SG {$grade} Step {$step} (₱" . number_format($compensation, 2) . ').');
    }

    public function send($id)
    {
        $offer = JobOffer::findOrFail($id);

        if ($offer->status !== 'draft') {
            return redirect()->route('offers.index')->with('error', 'Only draft offers can be sent.');
        }

        $offer->update([
            'status' => 'sent',
            'offer_sent_at' => now()->toDateString(),
        ]);

        $offer->application->update(['status' => 'offer_sent']);

        // Reload with relations so the notification has candidate/jobPosting
        // available without extra queries inside the Notification class.
        $offer->load(['application.candidate', 'application.jobPosting']);

        // Deliver the formal offer letter to the candidate.
        $offer->application->candidate->notify(new OfferLetterNotification($offer));

        // Stamp separately from offer_sent_at (which tracks the business
        // status) so this column reflects actual email dispatch, matching
        // the reminder_sent_at guard pattern used in interview schedules.
        $offer->update(['email_sent_at' => now()]);

        return redirect()->route('offers.index')->with('success', 'Offer sent to candidate. Offer letter emailed.');
    }

    public function respond(Request $request, $id)
    {
        $validated = $request->validate([
            'response' => 'required|in:accepted,declined',
        ]);

        $offer = JobOffer::findOrFail($id);

        if ($offer->status !== 'sent') {
            return redirect()->route('offers.index')->with('error', 'Only sent offers can be marked accepted or declined.');
        }

        $offer->update(['status' => $validated['response']]);

        $offer->application->update([
            'status' => $validated['response'] === 'accepted' ? 'offer_accepted' : 'offer_declined',
        ]);

        return redirect()->route('offers.index')->with('success', 'Offer marked as ' . $validated['response'] . '.');
    }

    public function destroy($id)
    {
        $offer = JobOffer::findOrFail($id);
        $offer->delete();

        return redirect()->route('offers.index')->with('success', 'Offer deleted.');
    }
}
