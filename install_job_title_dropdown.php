<?php
// install_job_title_dropdown.php
// Run once from your Laravel project root: php install_job_title_dropdown.php
// Replaces the free-text Job title input with a searchable type-to-filter dropdown
// populated from the official DepEd position title list (68 entries). Adds a config
// file as the single source of truth for the list, and restricts the title field
// to only accept values from it (both client-side and server-side).
// Backs up files it overwrites to .bak before writing. Safe to delete after running.

$files = [];

$files['config/job_titles.php'] = <<<'PHPCODE'
<?php

// config/job_titles.php
// Canonical list of position titles for job postings, sourced from the
// official DepEd "Position Applying For" list. Used to populate the
// searchable title dropdown on the Job Posting create/edit form.

return [
    'titles' => [
        'Contract of Service (COS)',
        'Accountant I',
        'Accountant III',
        'Administrative Aide I',
        'Administrative Aide III',
        'Administrative Aide IV',
        'Administrative Aide VI',
        'Administrative Assistant I',
        'Administrative Assistant II',
        'Administrative Assistant II (Disbursing Officer)',
        'Administrative Assistant II (Verifier)',
        'Administrative Assistant III',
        'Administrative Assistant III (Senior Bookkeeper)',
        'Administrative Officer I',
        'Administrative Officer II',
        'Administrative Officer IV',
        'Administrative Officer V',
        'Assistant School Principal II',
        'Attorney III',
        'Chief Education Program Supervisor',
        'Dental Aide',
        'Dentist II',
        'Driver',
        'Education Program Specialist',
        'Education Program Supervisor',
        'Engineer III',
        'Farmworker I',
        'Guidance Coordinator I',
        'Guidance Coordinator II',
        'Guidance Coordinator III',
        'Guidance Counselor I',
        'Guidance Counselor II',
        'Guidance Counselor III',
        'Handicraft Worker',
        'Head Teacher I',
        'Head Teacher II',
        'Head Teacher III',
        'Head Teacher IV',
        'Head Teacher V',
        'Head Teacher VI',
        'Information Technology Officer I',
        'Legal Assistant I',
        'Medical Officer III',
        'Nurse II',
        'Planning Officer III',
        'Project Development Officer I',
        'Project Development Officer II',
        'Public Schools District Supervisor',
        'Registrar I',
        'School Librarian I',
        'School Librarian II',
        'School Principal I',
        'School Principal II',
        'School Principal III',
        'School Principal IV',
        'Security Guard I',
        'Security Guard II',
        'Senior Education Program Specialist',
        'Teacher I',
        'Teacher II',
        'Teacher III',
        'Master Teacher I',
        'Master Teacher II',
        'Special Science Teacher I',
        'Special Education Teacher I',
        'Special Education Teacher II',
        'Special Education Teacher III',
        'Watchman I',
    ],
];
PHPCODE;

$files['app/Http/Controllers/JobPostingController.php'] = <<<'PHPCODE2'
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
PHPCODE2;

$files['resources/views/job-postings/form.blade.php'] = <<<'BLADE'
@extends('layouts.app')

@section('title', $posting->exists ?? false ? 'Edit posting' : 'New posting')
@section('page-title', ($posting->exists ?? false) ? 'Edit job posting' : 'New job posting')

@section('content')
<div class="card">
    <div class="card-body p-4">
        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Please fix the following:</strong>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form action="{{ ($posting->exists ?? false) ? route('job-postings.update', $posting->id) : route('job-postings.store') }}" method="POST">
            @if ($posting->exists ?? false)
                @method('PUT')
            @endif
            @csrf
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label small fw-medium">Job title</label>
                    <div class="position-relative" id="titleSearchWrapper">
                        <input
                            type="text"
                            class="form-control"
                            id="titleSearchInput"
                            autocomplete="off"
                            placeholder="Type to search position titles..."
                            value="{{ old('title', $posting->title ?? '') }}"
                        >
                        <input type="hidden" name="title" id="titleHiddenInput" value="{{ old('title', $posting->title ?? '') }}">
                        <div
                            id="titleSearchResults"
                            class="list-group position-absolute w-100 shadow-sm"
                            style="z-index: 1050; max-height: 260px; overflow-y: auto; display: none; top: 100%;"
                        ></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-medium">Vacancies</label>
                    <input type="number" class="form-control" name="vacancies" value="{{ old('vacancies', $posting->vacancies ?? 1) }}" min="1">
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-medium">Place of assignment</label>
                    <input type="text" class="form-control" name="place_of_assignment" value="{{ old('place_of_assignment', $posting->place_of_assignment ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-medium">Employment type</label>
                    <select class="form-select" name="employment_type">
                        @foreach (['Regular', 'Provisional', 'Casual', 'Job Order', 'On-the-Job Trainee'] as $type)
                            <option value="{{ $type }}" {{ ($posting->employment_type ?? '') === $type ? 'selected' : '' }}>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-medium">Job description</label>
                    <textarea class="form-control" name="description" rows="3">{{ $posting->description ?? '' }}</textarea>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-medium">Duties and responsibilities</label>
                    <textarea class="form-control" name="duties_responsibilities" rows="3">{{ $posting->duties_responsibilities ?? '' }}</textarea>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-medium">Qualification standards</label>
                    <textarea class="form-control" name="qualification_standards" rows="3">{{ $posting->qualification_standards ?? '' }}</textarea>
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-medium">Posted date</label>
                    <input type="date" class="form-control" name="posted_at" value="{{ old('posted_at', optional($posting->posted_at ?? null)->format('Y-m-d')) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-medium">Closes</label>
                    <input type="date" class="form-control" name="closes_at" value="{{ old('closes_at', optional($posting->closes_at ?? null)->format('Y-m-d')) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-medium">Status</label>
                    <select class="form-select" name="status">
                        @foreach (['draft', 'open', 'filled', 'closed'] as $status)
                            <option value="{{ $status }}" {{ ($posting->status ?? 'draft') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn" style="background-color: var(--hr-primary); color: #fff;">Save posting</button>
                <a href="{{ route('job-postings.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const titles = @json($jobTitles ?? []);
        const searchInput = document.getElementById('titleSearchInput');
        const hiddenInput = document.getElementById('titleHiddenInput');
        const resultsBox = document.getElementById('titleSearchResults');
        const wrapper = document.getElementById('titleSearchWrapper');

        function renderResults(filter) {
            const query = filter.trim().toLowerCase();
            const matches = query === ''
                ? titles
                : titles.filter(t => t.toLowerCase().includes(query));

            resultsBox.innerHTML = '';

            if (matches.length === 0) {
                resultsBox.style.display = 'none';
                return;
            }

            matches.slice(0, 50).forEach(function (title) {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action small';
                item.textContent = title;
                item.addEventListener('click', function () {
                    searchInput.value = title;
                    hiddenInput.value = title;
                    resultsBox.style.display = 'none';
                    searchInput.classList.remove('is-invalid');
                });
                resultsBox.appendChild(item);
            });

            resultsBox.style.display = 'block';
        }

        searchInput.addEventListener('input', function () {
            hiddenInput.value = '';
            renderResults(searchInput.value);
        });

        searchInput.addEventListener('focus', function () {
            renderResults(searchInput.value);
        });

        document.addEventListener('click', function (event) {
            if (!wrapper.contains(event.target)) {
                resultsBox.style.display = 'none';
            }
        });

        // Block submission if the typed text doesn't match an exact, valid title.
        searchInput.closest('form').addEventListener('submit', function (event) {
            if (!titles.includes(searchInput.value.trim())) {
                event.preventDefault();
                searchInput.classList.add('is-invalid');
                searchInput.focus();
                renderResults(searchInput.value);

                let feedback = wrapper.querySelector('.invalid-feedback');
                if (!feedback) {
                    feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback d-block';
                    feedback.textContent = 'Please select a valid position title from the list.';
                    wrapper.appendChild(feedback);
                }
            } else {
                hiddenInput.value = searchInput.value.trim();
            }
        });
    })();
</script>
@endpush
@endsection
BLADE;

$base = __DIR__;
$created = 0;

foreach ($files as $relativePath => $content) {
    $fullPath = $base . '/' . $relativePath;
    $dir = dirname($fullPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    if (file_exists($fullPath)) {
        copy($fullPath, $fullPath . '.bak');
        echo "BACKED UP: $relativePath -> " . basename($fullPath) . ".bak\n";
    }
    file_put_contents($fullPath, $content);
    echo "WROTE: $relativePath\n";
    $created++;
}

echo "\nDone. $created file(s) written.\n";
echo "Run 'php artisan config:clear' to make sure the new config file is picked up.\n";
echo "Then visit /job-postings/create and try the searchable title field.\n";
