<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\JobPosting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PortalController extends Controller
{
    private function candidate()
    {
        return Auth::guard('candidate')->user();
    }

    /**
     * Portal home / open job listings.
     */
    public function index()
    {
        $postings = JobPosting::where('status', 'open')
            ->orderByDesc('posted_at')
            ->get();

        // IDs the candidate has already applied to (to disable Apply button)
        $appliedIds = Application::where('candidate_id', $this->candidate()->id)
            ->pluck('job_posting_id')
            ->toArray();

        return view('portal.jobs.index', compact('postings', 'appliedIds'));
    }

    /**
     * Single job detail + apply form.
     */
    public function showJob(int $id)
    {
        $posting = JobPosting::where('status', 'open')->findOrFail($id);

        $alreadyApplied = Application::where('candidate_id', $this->candidate()->id)
            ->where('job_posting_id', $id)
            ->exists();

        return view('portal.jobs.show', compact('posting', 'alreadyApplied'));
    }

    /**
     * Submit application.
     */
    public function apply(Request $request, int $id)
    {
        $posting = JobPosting::where('status', 'open')->findOrFail($id);

        $candidate = $this->candidate();

        // Prevent duplicate
        $exists = Application::where('candidate_id', $candidate->id)
            ->where('job_posting_id', $id)
            ->exists();

        if ($exists) {
            return redirect()
                ->route('portal.jobs.show', $id)
                ->with('error', 'You have already applied for this position.');
        }

        Application::create([
            'transaction_number' => Application::generateTransactionNumber(),
            'candidate_id'       => $candidate->id,
            'job_posting_id'     => $posting->id,
            'status'             => 'submitted',
            'applied_at'         => now(),
            'notes'              => $request->input('cover_note'),
        ]);

        return redirect()
            ->route('portal.my-applications')
            ->with('success', 'Application submitted successfully for ' . $posting->title . '.');
    }

    /**
     * Candidate's submitted applications with status tracking.
     */
    public function myApplications()
    {
        $applications = Application::with('jobPosting')
            ->where('candidate_id', $this->candidate()->id)
            ->latest('applied_at')
            ->get();

        return view('portal.my-applications', compact('applications'));
    }
}