<?php

namespace App\Http\Controllers;

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

        $availableCandidates = Candidate::whereNotIn('id', TalentPool::pluck('candidate_id'))
            ->orderBy('first_name')
            ->get();

        return view('talent-pool.index', compact('pool', 'availableCandidates'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'candidate_id' => [
                'required',
                'exists:candidates,id',
                Rule::unique('talent_pools', 'candidate_id'),
            ],
            'tags' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'added_at' => ['nullable', 'date'],
        ], [
            'candidate_id.unique' => 'This candidate is already in the talent pool.',
        ]);

        $validated['added_at'] = $validated['added_at'] ?? now()->toDateString();

        TalentPool::create($validated);

        return redirect()->route('talent-pool.index')->with('success', 'Candidate added to talent pool.');
    }

    public function update(Request $request, $id)
    {
        $entry = TalentPool::findOrFail($id);

        $validated = $request->validate([
            'tags' => ['nullable', 'string', 'max:255'],
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