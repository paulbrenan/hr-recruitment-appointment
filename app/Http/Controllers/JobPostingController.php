<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\JobPosting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JobPostingController extends Controller
{
    /**
     * Standard DepEd mandatory requirements (A-J), used to pre-fill the
     * Mandatory requirements list when creating a NEW posting. Fully
     * editable/removable by HR on the form -- this is just a starting point.
     */
    private const DEFAULT_MANDATORY_REQUIREMENTS = [
        'Letter of intent addressed to the Schools Division Superintendent',
        'Duly Accomplished Personal Data Sheet (CSC Form No. 212, Revised 2025) with latest passport size picture and Work Experience Sheet, if applicable',
        'Photocopy of valid and updated PRC License/ID, if applicable',
        'Photocopy of Certificate of Eligibility/Rating, if applicable',
        'Photocopy of scholastic/academic record such as but not limited to Transcript of Records (TOR) and Diploma, including completion of graduate and post graduate units/degrees, if available',
        'Photocopy of Certificates of Training, if applicable',
        'Photocopy of Certificate of Employment, Contract of Service, or duly signed Service Record, whichever is/are applicable',
        'Photocopy of the latest appointment, if applicable',
        'Photocopy of Performance Rating in the last rating period(s) covering one (1) year performance in the current/latest position, if applicable',
        'Checklist of Requirements and Omnibus Sworn Statement on the Certification on the Authenticity and Veracity (CAV) of the documents submitted and Data Privacy Consent Form, signed by authorized official (e.g., Brgy. Captain)',
    ];

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
            'qualification_education' => ['nullable', 'string'],
            'qualification_training' => ['nullable', 'string'],
            'qualification_experience' => ['nullable', 'string'],
            'qualification_eligibility' => ['nullable', 'string'],
            'mandatory_requirements' => ['nullable', 'string'],
            'additional_requirements' => ['nullable', 'string'],
            'place_of_assignment' => ['nullable', 'string', 'max:255'],
            'employment_type' => ['nullable', 'string', 'max:255'],
            'salary_grade' => [
                'nullable',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    if (empty($value)) {
                        return;
                    }
                    $numeric = preg_replace('/^sg-?/i', '', trim($value));
                    if (!ctype_digit($numeric) || (int) $numeric < 1 || (int) $numeric > 33) {
                        $fail('The salary grade must be a valid Salary Grade from 1 to 33 (e.g. "21" or "SG-21").');
                    }
                },
            ],
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
        $posting->mandatory_requirements = implode("\n", self::DEFAULT_MANDATORY_REQUIREMENTS);
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

        $applications = Application::with('candidate')
            ->where('job_posting_id', $id)
            ->latest('applied_at')
            ->get();

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