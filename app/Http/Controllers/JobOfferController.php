<?php

namespace App\Http\Controllers;

use App\Models\JobOffer;
use App\Models\Application;
use Illuminate\Http\Request;

class JobOfferController extends Controller
{
    private const MIN_COMPENSATION = 14634; // Salary Grade 1, Step 1 (EO No. 64, SG SA 2026 table)

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

        $minCompensation = self::MIN_COMPENSATION;

        return view('offers.index', compact('offers', 'eligibleApplications', 'minCompensation'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate(
            [
                'application_id' => 'required|exists:applications,id|unique:job_offers,application_id',
                'compensation' => 'required|numeric|min:' . self::MIN_COMPENSATION,
                'response_deadline' => 'nullable|date|after_or_equal:today',
                'benefits' => 'nullable|string',
                'terms' => 'nullable|string',
            ],
            [
                'compensation.min' => 'Compensation cannot be below \u20b1' . number_format(self::MIN_COMPENSATION, 0) . ' (Salary Grade 1, Step 1, the government minimum).',
            ]
        );

        JobOffer::create([
            'application_id' => $validated['application_id'],
            'compensation' => $validated['compensation'],
            'response_deadline' => $validated['response_deadline'] ?? null,
            'benefits' => $validated['benefits'] ?? null,
            'terms' => $validated['terms'] ?? null,
            'status' => 'draft',
        ]);

        return redirect()->route('offers.index')->with('success', 'Offer generated as draft.');
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

        return redirect()->route('offers.index')->with('success', 'Offer sent to candidate.');
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