<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\TalentPool;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TalentPoolController extends Controller
{
    public function index()
    {
        $pool = TalentPool::with('candidate')
            ->orderByDesc('added_at')
            ->get();

        $availableCandidates = Candidate::whereNotIn('id', TalentPool::pluck('candidate_id')->filter())
            ->orderBy('first_name')
            ->get();

        return view('talent-pool.index', compact('pool', 'availableCandidates'));
    }

    public function show($id)
    {
        $talentPool = TalentPool::findOrFail($id);
        return view('talent-pool.show', compact('talentPool'));
    }

    public function edit($id)
    {
        $talentPool = TalentPool::findOrFail($id);
        return view('talent-pool.edit', compact('talentPool'));
    }

    // Manual add: HR picks any candidate from the dropdown, regardless of application status
    public function store(Request $request)
    {
        $validated = $request->validate([
            'candidate_id' => [
                'required',
                'exists:candidates,id',
                Rule::unique('talent_pools', 'candidate_id'),
            ],
            'skills' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'added_at' => ['nullable', 'date'],
        ], [
            'candidate_id.unique' => 'This candidate is already in the talent pool.',
        ]);

        $candidate = Candidate::findOrFail($validated['candidate_id']);

        $fullName = trim(implode(' ', array_filter([
            $candidate->first_name,
            $candidate->middle_name,
            $candidate->last_name,
        ])));

        TalentPool::create([
            'candidate_id' => $candidate->id,
            'full_name'    => $fullName ?: 'Unknown',
            'email'        => $candidate->email,
            'phone'        => $candidate->phone,
            'skills'       => $validated['skills'] ?? null,
            'notes'        => $validated['notes'] ?? null,
            'status'       => 'active',
            'added_at'     => $validated['added_at'] ?? now()->toDateString(),
        ]);

        return redirect()->route('talent-pool.index')->with('success', 'Candidate added to talent pool.');
    }

    // Auto add: triggered by the "Add to Talent Pool" button on a rejected application
    public function storeFromApplication(Request $request, $id)
    {
        $application = Application::with(['candidate', 'jobPosting'])->findOrFail($id);

        if (TalentPool::where('application_id', $application->id)->exists()) {
            return back()->with('info', 'This applicant is already in the talent pool.');
        }

        if ($application->candidate_id && TalentPool::where('candidate_id', $application->candidate_id)->exists()) {
            return back()->with('info', 'This candidate is already in the talent pool.');
        }

        $fullName = trim(implode(' ', array_filter([
            $application->candidate->first_name ?? null,
            $application->candidate->middle_name ?? null,
            $application->candidate->last_name ?? null,
        ])));

        TalentPool::create([
            'application_id'   => $application->id,
            'candidate_id'     => $application->candidate_id,
            'full_name'        => $fullName ?: 'Unknown',
            'email'            => $application->candidate->email ?? null,
            'phone'            => $application->candidate->phone ?? null,
            'position_applied' => $application->jobPosting->title ?? null,
            'notes'            => $application->notes,
            'status'           => 'active',
            'added_at'         => now(),
        ]);

        return back()->with('success', 'Applicant added to the talent pool.');
    }

    public function update(Request $request, $id)
    {
        $entry = TalentPool::findOrFail($id);

        $validated = $request->validate([
            'skills' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'added_at' => ['nullable', 'date'],
        ]);

        $entry->update($validated);

        return redirect()->route('talent-pool.index')->with('success', 'Talent pool entry updated.');
    }

    public function destroy($id)
    {
        $entry = TalentPool::findOrFail($id);
        $entry->delete();

        return redirect()->route('talent-pool.index')->with('success', 'Candidate removed from talent pool.');
    }
}