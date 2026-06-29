<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Candidate;
use App\Mail\ApplicationSubmitted;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

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
            'position_applied' => ['required', 'string', 'max:255'],
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
            // Account
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        $candidate = Candidate::create([
            'first_name'       => $validated['first_name'],
            'middle_name'      => $validated['middle_name'] ?? null,
            'last_name'        => $validated['last_name'],
            'email'            => $validated['email'],
            'password'         => $validated['password'],
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

        // Generate transaction number and create the application record
        $txn = Application::generateTransactionNumber();

        // We won't create an Application row here — the candidate can apply
        // to specific open postings from the portal. We store the txn on the
        // candidate profile temporarily for the confirmation page/email.

        Auth::guard('candidate')->login($candidate);
        $request->session()->regenerate();

        // Send confirmation email (non-blocking — catches any mail failure)
        try {
            Mail::to($candidate->email)
                ->send(new ApplicationSubmitted($candidate, $txn, $validated['position_applied']));
        } catch (\Throwable $e) {
            \Log::error('Recruitment confirmation email failed: ' . $e->getMessage());
        }

        return view('portal.submitted', [
            'candidate'         => $candidate,
            'transactionNumber' => $txn,
            'position'          => $validated['position_applied'],
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