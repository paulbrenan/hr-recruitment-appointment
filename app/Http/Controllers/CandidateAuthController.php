<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
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
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:candidates,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $candidate = Candidate::create([
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
        ]);

        Auth::guard('candidate')->login($candidate);

        $request->session()->regenerate();

        return redirect()->route('portal.dashboard');
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