<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\JobPosting;
use App\Models\JobPostingLocation;
use App\Mail\ApplicationSubmitted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CandidateAuthController extends Controller
{
    public function showRegister()
    {
        // Place of Assignment is no longer chosen on the public form --
        // a posting simply disappears from the list once EITHER:
        //   - every location (or the legacy vacancies column) is filled
        //     (hasAnyOpenVacancy() sums across all locations already), or
        //   - its closes_at due date has passed.
        // Locations are still eager-loaded (needed by hasAnyOpenVacancy()),
        // just without the per-location hired_count/order-by that only
        // existed to feed the old Place dropdown.
        $openPostings = JobPosting::where('status', '!=', 'closed')
            ->where(function ($query) {
                $query->whereNull('closes_at')
                      ->orWhereDate('closes_at', '>=', now()->toDateString());
            })
            ->with('locations')
            ->orderBy('title')
            ->get()
            ->filter->hasAnyOpenVacancy()
            ->values();

        return view('portal.register', compact('openPostings'));
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            // Personal
            'first_name'       => ['required', 'string', 'max:255'],
            'middle_name'      => ['nullable', 'string', 'max:255'],
            'last_name'        => ['required', 'string', 'max:255'],
'job_posting_id'          => ['required', 'integer', 'exists:job_postings,id'],
            'job_posting_location_id' => ['nullable', 'integer'],
            'address'          => ['required', 'string', 'max:500'],
            'age'              => ['required', 'integer', 'min:18', 'max:70'],
            'sex'              => ['required', 'in:Male,Female'],
            'civil_status'     => ['required', 'in:Single,Married,Legally Separated,Widowed'],
            'religion'         => ['required', 'string', 'max:100'],
            'disability'       => ['required', 'string', 'max:255'],
            'ethnic_group'     => ['required', 'string', 'max:100'],
            'phone'            => ['required', 'string', 'max:50'],
            // Only block the email if this candidate already has a submitted
            // application. A candidate record without an application means a
            // previous registration attempt failed partway through — allow retry.
            'email'            => [
                'required', 'email', 'max:255',
                \Illuminate\Validation\Rule::unique('candidates', 'email')->where(function ($query) {
                    return $query->whereExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('applications')
                            ->whereColumn('applications.candidate_id', 'candidates.id');
                    });
                }),
            ],
            // Qualifications
            'education'        => ['required', 'string', 'max:500'],
            'training_hours'   => ['required', 'string', 'max:100'],
            'years_experience' => ['required', 'string', 'max:100'],
            'eligibility'      => ['required', 'string', 'max:255'],
        ]);

        // Resolve the job posting BEFORE creating any account/records, so
        // an invalid or no-longer-open position stops registration cleanly
        // instead of silently creating a candidate with no application and
        // telling them "submitted successfully" anyway.
        $jobPosting = \App\Models\JobPosting::find((int) $validated['job_posting_id']);

        if (!$jobPosting || $jobPosting->status !== 'open') {
            return back()
                ->withInput()
                ->withErrors(['job_posting_id' => 'Sorry, this position is no longer available. Please choose another open position.']);
        }

        // Place of Assignment is no longer picked on the public form --
        // just re-check the posting hasn't been filled or closed between
        // page load and submit (hasAnyOpenVacancy() sums across every
        // location, or falls back to the legacy vacancies column).
        if (!$jobPosting->hasAnyOpenVacancy()) {
            return back()
                ->withInput()
                ->withErrors(['job_posting_id' => 'Sorry, this position was just filled. Please choose another open position.']);
        }

        if ($jobPosting->closes_at && $jobPosting->closes_at->lt(now()->startOfDay())) {
            return back()
                ->withInput()
                ->withErrors(['job_posting_id' => 'Sorry, the application period for this position has closed. Please choose another open position.']);
        }

        // Clean up any orphaned candidate record for this email
        // (a previous registration that failed before creating the application).
        Candidate::where('email', $validated['email'])
            ->whereDoesntHave('applications')
            ->delete();

        $candidate = Candidate::create([
            'first_name'       => $validated['first_name'],
            'middle_name'      => $validated['middle_name'] ?? null,
            'last_name'        => $validated['last_name'],
            'position_applied' => $jobPosting->title . ($jobPosting->place_of_assignment ? ' - ' . $jobPosting->place_of_assignment : ''),
            'email'            => $validated['email'],
            'phone'            => $validated['phone'],
            'address'          => $validated['address'],
            'age'              => $validated['age'],
            'sex'              => $validated['sex'],
            'civil_status'     => $validated['civil_status'],
            'religion'         => $validated['religion'],
            'disability'       => $validated['disability'],
            'ethnic_group'     => $validated['ethnic_group'],
            'education'        => $validated['education'],
            'training_hours'   => $validated['training_hours'],
            'years_experience' => $validated['years_experience'],
            'eligibility'      => $validated['eligibility'],
        ]);

        // Generate transaction number
        $txn = Application::generateTransactionNumber();

        // Auto-create the application record so it appears in HR's list
        Application::create([
            'transaction_number' => $txn,
            'candidate_id'       => $candidate->id,
            'job_posting_id'     => $jobPosting->id,
            'status'             => 'submitted',
            'applied_at'         => now()->toDateString(),
            'notes'              => 'Submitted via Online Recruitment Form.',
        ]);

        // Send confirmation email (non-blocking — catches any mail failure)
        try {
            Mail::to($candidate->email)
                ->send(new ApplicationSubmitted($candidate, $txn, $jobPosting->title, $jobPosting));
        } catch (\Throwable $e) {
            Log::error('Recruitment confirmation email failed: ' . $e->getMessage());
        }

        return view('portal.submitted', [
            'candidate'         => $candidate,
            'transactionNumber' => $txn,
            'position'          => $jobPosting->title,
            'jobPosting'        => $jobPosting,
        ]);
    }

    public function dashboard()
    {
        return view('portal.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::guard('candidate')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}