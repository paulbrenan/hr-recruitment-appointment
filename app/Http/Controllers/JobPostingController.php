<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JobPostingController extends Controller
{
    /**
     * Validation rules shared by store() and update(), matching the
     * job_postings migration exactly.
     */
    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255', Rule::in(config('job_titles.titles', []))],
            'description' => ['nullable', 'string'],
            'duties_responsibilities' => ['nullable', 'string'],
            'qualification_standards' => ['nullable', 'string'],
            'place_of_assignment' => ['nullable', 'string', 'max:255'],
            'employment_type' => ['nullable', 'string', 'max:255'],
            'vacancies' => ['required', 'integer', 'min:1'],
            'posted_at' => ['nullable', 'date'],
            'closes_at' => [
                'nullable',
                'date',
                Rule::when(
                    fn ($input) => !empty($input['posted_at']),
                    ['after_or_equal:posted_at']
                ),
            ],
            'status' => ['required', 'in:draft,open,filled,closed'],
        ];
    }

    public function index()
    {
        $postings = JobPosting::latest()->get();

        return view('job-postings.index', compact('postings'));
    }

    public function create()
    {
        $posting = new JobPosting();
        $posting->exists = false;
        $jobTitles = config('job_titles.titles', []);

        return view('job-postings.form', compact('posting', 'jobTitles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        JobPosting::create($validated);

        return redirect()
            ->route('job-postings.index')
            ->with('success', 'Job posting created successfully.');
    }

    public function edit($id)
    {
        $posting = JobPosting::findOrFail($id);
        $posting->exists = true;
        $jobTitles = config('job_titles.titles', []);

        return view('job-postings.form', compact('posting', 'jobTitles'));
    }

    public function update(Request $request, $id)
    {
        $posting = JobPosting::findOrFail($id);

        $validated = $request->validate($this->rules());

        $posting->update($validated);

        return redirect()
            ->route('job-postings.index')
            ->with('success', 'Job posting updated successfully.');
    }

    public function show($id)
    {
        $posting = JobPosting::findOrFail($id);

        // NOTE: Applications module is not wired to real data yet.
        // This dummy list is intentionally left in place and should be
        // replaced once the Applications controller/migration is wired up.
        $applications = collect([
            (object) ['candidate_name' => 'Maria Santos', 'applied_at' => '2026-06-10', 'status' => 'shortlisted'],
            (object) ['candidate_name' => 'Juan Dela Cruz', 'applied_at' => '2026-06-12', 'status' => 'screening'],
        ]);

        return view('job-postings.show', compact('posting', 'applications'));
    }

    public function destroy($id)
    {
        $posting = JobPosting::findOrFail($id);
        $posting->delete();

        return redirect()
            ->route('job-postings.index')
            ->with('success', 'Job posting deleted successfully.');
    }
}