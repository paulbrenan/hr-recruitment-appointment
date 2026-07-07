<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Candidate;
use App\Mail\ApplicationSubmitted;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CandidateAuthController extends Controller
{
    public function showRegister()
    {
        return view('portal.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            // Personal
            'first_name'       => ['required', 'string', 'max:255'],
            'middle_name'      => ['nullable', 'string', 'max:255'],
            'last_name'        => ['required', 'string', 'max:255'],
'job_posting_id'   => ['required', 'regex:/^\\d+:\\d+$/'],
            'address'          => ['required', 'string', 'max:500'],
            'age'              => ['required', 'integer', 'min:18', 'max:70'],
            'sex'              => ['required', 'in:Male,Female'],
            'civil_status'     => ['required', 'in:Single,Married,Legally Separated,Widowed'],
            'religion'         => ['required', 'string', 'max:100'],
            'disability'       => ['required', 'string', 'max:255'],
            'ethnic_group'     => ['required', 'string', 'max:100'],
            'phone'            => ['required', 'string', 'max:50'],
            'email'            => ['required', 'email', 'max:255', 'unique:candidates,email'],
            // Qualifications
            'education'        => ['required', 'string', 'max:500'],
            'training_hours'   => ['required', 'string', 'max:100'],
            'years_experience' => ['required', 'string', 'max:100'],
            'eligibility'      => ['required', 'string', 'max:255'],
        ]);

        // Resolve the job posting BEFORE creating any account/records, so
        // an invalid or no-longer-open position stops registration cleanly
        // instead of silently creating a candidate with no application and
        // telling them "submitted successfully" anyway. Resolved by ID
        // (not title) so two postings sharing a title but differing in
        // place of assignment can never be confused with one another.
        [$postingIdRaw, $locationIdRaw] = array_pad(explode(':', $validated['job_posting_id'], 2), 2, '0');
        $jobPosting = \App\Models\JobPosting::find((int) $postingIdRaw);

        if (!$jobPosting || $jobPosting->status !== 'open') {
            return back()
                ->withInput()
                ->withErrors(['job_posting_id' => 'Sorry, this position is no longer available. Please choose another open position.']);
        }

// Resolve and verify the chosen location actually belongs to this
        // posting (never trust the submitted ID on its own -- a tampered
        // value could reference an unrelated posting's location).
        $jobPostingLocation = null;
        $locationId = (int) $locationIdRaw;
        if ($locationId > 0) {
            $jobPostingLocation = $jobPosting->locations()->find($locationId);
            if (!$jobPostingLocation) {
                return back()
                    ->withInput()
                    ->withErrors(['job_posting_id' => 'Sorry, that place of assignment is no longer available. Please choose another option.']);
            }
        }
        }

        $candidate = Candidate::create([
            'first_name'       => $validated['first_name'],
            'middle_name'      => $validated['middle_name'] ?? null,
            'last_name'        => $validated['last_name'],
            'position_applied' => $jobPosting->title . ($jobPostingLocation ? ' - ' . $jobPostingLocation->place_of_assignment : ($jobPosting->place_of_assignment ? ' - ' . $jobPosting->place_of_assignment : '')),
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
'job_posting_location_id' => $jobPostingLocation->id ?? null,
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