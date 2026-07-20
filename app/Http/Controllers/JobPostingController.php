<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\AssessmentCriterion;
use App\Models\InterviewSchedule;
use App\Models\JobOffer;
use App\Models\JobPosting;
use App\Models\JobPostingLocation;
use App\Models\Panelist;
use App\Services\JobTitleRegistrar;
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

    // Full DepEd "Means of Verification" checklist. The widget only
    // stores a flat newline-delimited list, so numbering/lettering
    // ("1.", "a.", "-") is kept as part of each line's own text --
    // that's what keeps the category hierarchy visually readable once
    // rendered as a flat list.
    private const DEFAULT_ADDITIONAL_REQUIREMENTS = [
        'A. Means of Verification showing Outstanding Accomplishments, Application of Education, and Application of Learning and Development, reckoned from the date of last issuance of appointment (if any):',
        '1. Awards and Recognition',
        'a. Citation or Commendation',
        '- Letter of Citation or Commendation from previous employer',
        'b. Academic or Inter-School Awards',
        '- Academic or inter-school award; or',
        '- Ten Outstanding Students of the Philippines (TOSP) Award; or',
        '- Certification of any document that the applicant belongs to the Top 10 in the Board or Civil Service Eligibility Examination.',
        'c. Outstanding Employee Award',
        '- Any issuance, memorandum or document showing the Criteria for the Search; and',
        '- Certificate of Recognition/Merit',
        '2. Research and Innovation',
        'a. Proposal duly approved by the Head of Office or the designated Research Committee per DepEd Order No. 16, s. 2017',
        'b. Accomplishment Report verified by the Head of Office',
        'c. Certification of utilization of the innovation or research, within the school/office duly signed by the Head of Office',
        'd. Certification of adoption of the innovation or research by another school/office duly signed by the Head of Office',
        'e. Proof of citation by other researchers (whose study/research, whether published or unpublished, is likewise approved by authorized body) of the concept/s developed in the research',
        '3. Subject Matter Expert / Membership in National Technical Working Groups or Committees',
        'a. Issuance/Memorandum showing the membership in NTWG or Committees',
        'b. Certificate of Participation or Attendance',
        'c. Output/Adoption by the organization/DepEd',
        '4. Resource Speakership / Learning Facilitation',
        'a. Issuance/Memorandum/Invitation/Training Matrix',
        'b. Certificate of Recognition/Merit/Commendation/Appreciation',
        'c. Slide deck/s used and/or Session guide/s',
        '5. NEAP Accredited Learning Facilitator',
        'a. Certificate of Recognition as Learning Facilitator issued by NEAP Central or Regional Office',
        '6. Application of Education',
        'a. Action Plan approved by the Head of Office',
        'b. Accomplishment Report verified by the Head of Office',
        'c. Certification of the utilization/adoption signed by the Head of Office',
        '7. Application of Learning and Development',
        'a. Certificate of Training or Certification on any applicable L&D intervention acquired that is aligned with the Individual Development Plan (IDP); for external applicants, a certification from HR stating that the L&D intervention is aligned with the core tasks of the applicant in their current or previous position shall be required',
        'b. Action Plan/Re-entry Action Plan (REAP), Job Embedded Learning (JEL)/Impact Project applying the learnings from the L&D intervention done/attended, duly approved by the Head of Office',
        'c. Accomplishment Report together with a General Certification that the L&D intervention was used/adopted by the office at the local level',
        'd. Accomplishment Report together with a General Certification that the L&D intervention was used/adopted by a different office at the local/higher level',
        '8. Photocopy of the Performance Rating obtained from the relevant work experience if latest performance rating is not relevant to the position applying for',
    ];

    /**
     * Validation rules shared by store() and update(), matching the
     * job_postings migration exactly.
     */
    /**
     * Registers a brand-new title (typed in, not picked from the
     * dropdown) into config/job_titles.php on disk, so it passes
     * Rule::in() validation and becomes a real permanent option for
     * every future posting -- same registrar the PDF import pipeline
     * already uses for this exact purpose.
     */
    private function autoRegisterTitle(\Illuminate\Http\Request $request): void
    {
        $title = trim((string) $request->input('title', ''));

        if ($title === '') {
            return;
        }

        app(JobTitleRegistrar::class)->register($title);
    }

    private function rules(bool $forCreate = false): array
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
            // place_of_assignment is now managed via job_posting_locations table
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
            // vacancies is now per-location in job_posting_locations table
            'posted_at' => [
                'nullable',
                'date',
                // Only enforced when creating a brand new posting -- editing
                // an existing posting must not break just because its
                // original dates are now in the past.
                ...($forCreate ? ['after_or_equal:today'] : []),
            ],
            'closes_at' => [
                'nullable',
                'date',
                ...($forCreate ? ['after_or_equal:today'] : []),
                Rule::when(
                    fn ($input) => !empty($input['posted_at']),
                    ['after_or_equal:posted_at']
                ),
            ],
            'status' => ['required', 'in:open,interview_scheduled,ranking,closed'],
        ];
    }

    /**
     * Auto-close postings whose closing date has passed. Runs lazily on
     * every index() and show() load (no scheduler needed) -- any posting
     * still in the active pipeline (open / interview_scheduled / ranking)
     * with a closes_at strictly before today gets flipped to 'closed',
     * cascading to applications the same way the manual "Close Posting"
     * advance button does.
     */
    private function autoCloseExpiredPostings(): void
    {
        $expired = JobPosting::whereIn('status', ['open', 'interview_scheduled', 'ranking'])
            ->whereNotNull('closes_at')
            ->whereDate('closes_at', '<', now()->toDateString())
            ->get();

        foreach ($expired as $posting) {
            $posting->update(['status' => 'closed']);
            $this->autoHireTopRankedCandidates($posting);
            $this->cascadeStatusToApplications($posting, 'closed');
        }
    }

    public function index(Request $request)
    {
        $this->autoCloseExpiredPostings();

        // Archived postings are terminal/out-of-pipeline -- keep them out
        // of the default list, toggle-able via ?archived=1.
        $showArchived = $request->boolean('archived');

        // Sort by id, not created_at -- not every insert path (PDF
        // import, one-off scripts run directly against the DB, etc.)
        // reliably sets created_at, which let new postings show up
        // out of order. id is guaranteed to increase with every new
        // row regardless of how it was inserted.
        $postings = JobPosting::with('locations')
            ->when($showArchived, fn ($q) => $q->where('status', 'archived'))
            ->when(!$showArchived, fn ($q) => $q->where('status', '!=', 'archived'))
            ->orderByDesc('id')
            ->get();

        // Applicant counts for all listed postings in a single grouped
        // query, then attach as a dynamic property -- avoids an N+1
        // query per row on the list page.
        $applicantCounts = Application::whereIn('job_posting_id', $postings->pluck('id'))
            ->selectRaw('job_posting_id, count(*) as total')
            ->groupBy('job_posting_id')
            ->pluck('total', 'job_posting_id');

        $postings->each(function ($posting) use ($applicantCounts) {
            $posting->applicant_count = $applicantCounts->get($posting->id, 0);
        });

        return view('job-postings.index', compact('postings', 'showArchived'));
    }

    public function create()
    {
        $posting = new JobPosting();
        $posting->exists = false;
        $posting->mandatory_requirements  = implode("\n", self::DEFAULT_MANDATORY_REQUIREMENTS);
        $posting->additional_requirements = implode("\n", self::DEFAULT_ADDITIONAL_REQUIREMENTS);
        $jobTitles         = config('job_titles.titles', []);
        $panelists         = Panelist::orderBy('name')->get();
        $assignedPanelists = collect(); // empty for new posting
        $locations         = collect();

        return view('job-postings.form', compact('posting', 'jobTitles', 'panelists', 'assignedPanelists', 'locations'));
    }

    public function store(Request $request)
    {
        // A title typed in that isn't already in config/job_titles.php
        // would otherwise fail Rule::in() below. Register it first (same
        // registrar the PDF import pipeline already uses) so a genuinely
        // new title is accepted and permanently added to the dropdown,
        // instead of being rejected.
        $this->autoRegisterTitle($request);

        $validated = $request->validate($this->rules(forCreate: true));

        // Don't create a duplicate posting for a title that already
        // exists -- merge the submitted place(s) of assignment into the
        // existing posting's locations instead (same place again adds to
        // its vacancy count, a new place becomes a new location row; same
        // convention the PDF import pipeline uses).
        $existing = JobPosting::where('title', $validated['title'])->first();

        if ($existing) {
            $this->mergeLocationsInto($existing, $request);

            return redirect()
                ->route('job-postings.show', $existing->id)
                ->with('success', 'A posting for "' . $existing->title . '" already exists -- the place(s) of assignment you entered were added to it instead of creating a duplicate.');
        }

        $posting = JobPosting::create($validated);

        $this->syncLocations($posting, $request);
        $this->syncPanelists($posting, $request);

        return redirect()
            ->route('job-postings.index')
            ->with('success', 'Job posting created successfully.');
    }

    /**
     * Merge location_place[]/location_vacancies[] from a create-form
     * submission into an ALREADY-EXISTING posting, without touching its
     * current locations (unlike syncLocations(), which wholesale replaces
     * them -- that's correct for editing a posting's own form, but wrong
     * here since we're adding to a different, pre-existing posting).
     * Same place submitted again increments that location's vacancy count
     * rather than creating a second row for it.
     */
    private function mergeLocationsInto(JobPosting $posting, \Illuminate\Http\Request $request): void
    {
        $places    = $request->input('location_place', []);
        $vacancies = $request->input('location_vacancies', []);

        $existingLocations = $posting->locations()->get()->keyBy('place_of_assignment');

        foreach ($places as $i => $place) {
            $place = trim($place);
            if ($place === '') continue;

            $addVacancies = max(1, (int) ($vacancies[$i] ?? 1));

            $existingLocation = $existingLocations->get($place);
            if ($existingLocation) {
                $existingLocation->increment('vacancies', $addVacancies);
            } else {
                $newLocation = $posting->locations()->create([
                    'place_of_assignment' => $place,
                    'vacancies'           => $addVacancies,
                ]);
                $existingLocations->put($place, $newLocation);
            }
        }

        // Keep the legacy place_of_assignment/vacancies columns in sync,
        // same convention syncLocations() uses.
        $posting->refresh();
        $allLocations = $posting->locations()->get();
        $posting->updateQuietly([
            'place_of_assignment' => $allLocations->first()?->place_of_assignment,
            'vacancies'           => $allLocations->sum('vacancies') ?: 1,
        ]);
    }

    public function edit($id)
    {
        $posting = JobPosting::findOrFail($id);
        $posting->exists = true;
        $jobTitles  = config('job_titles.titles', []);
        $panelists         = Panelist::orderBy('name')->get();
        $assignedPanelists = $posting->panelists()->get()->keyBy('id');
        $locations         = $posting->locations()->get();

        return view('job-postings.form', compact('posting', 'jobTitles', 'panelists', 'assignedPanelists', 'locations'));
    }

    public function update(Request $request, $id)
    {
        $posting = JobPosting::findOrFail($id);

        $validated = $request->validate($this->rules());

        $oldStatus = $posting->status;
        $newStatus = $validated['status'];

        $posting->update($validated);

        // Cascade status to applications when the posting stage changes
        if ($oldStatus !== $newStatus) {
            $this->cascadeStatusToApplications($posting, $newStatus);
        }

        $this->syncLocations($posting, $request);
        $this->syncPanelists($posting, $request);

        return redirect()
            ->route('job-postings.index')
            ->with('success', 'Job posting updated successfully.');
    }


    /**
     * Map job posting pipeline stage → application status, then bulk-update.
     *
     * Mapping:
     *   open                → submitted
     *   interview_scheduled → interview_scheduled
     *   ranking             → ranked
     *   closed              → ranked  (Step 5 / Offer Management -- HR
     *                                  hasn't picked anyone yet, so
     *                                  candidates stay ranked/selectable
     *                                  instead of being auto-rejected;
     *                                  real rejection now only happens
     *                                  via the manual hire/offer flow)
     *
     * Special rule: if any applicant on this posting is already 'hired',
     * this cascade will NOT override their status (hired stays hired).
     */
    private function cascadeStatusToApplications(JobPosting $posting, string $postingStatus): void
    {
        $map = [
            'open'                => 'submitted',
            'interview_scheduled' => 'interview_scheduled',
            'ranking'             => 'ranked',
            'closed'              => 'ranked',
        ];

        if (!isset($map[$postingStatus])) {
            return;
        }

        $applicationStatus = $map[$postingStatus];

        // Applicants already disqualified ('not_qualified') or already
        // 'rejected' are NEVER touched by this cascade, at any stage --
        // otherwise advancing the posting silently overwrites their real
        // status (e.g. to 'ranked'), erasing the disqualification/rejection.
        $query = Application::where('job_posting_id', $posting->id)
            ->whereNotIn('status', ['not_qualified', 'rejected']);

        // Never override an applicant who has already been hired
        $query->where('status', '!=', 'hired');

        $query->update(['status' => $applicationStatus]);
    }

    /**
     * Sync place-of-assignment locations from the form submission.
     * Expects parallel arrays:
     *   location_place[]    — place of assignment strings
     *   location_vacancies[] — vacancy count per place
     * Empty/blank place rows are skipped.
     */
    private function syncLocations(JobPosting $posting, \Illuminate\Http\Request $request): void
    {
        $places    = $request->input('location_place', []);
        $vacancies = $request->input('location_vacancies', []);

        // Delete all existing location rows for this posting then re-insert
        $posting->locations()->delete();

        $rows = [];
        foreach ($places as $i => $place) {
            $place = trim($place);
            if ($place === '') continue;

            $rows[] = [
                'job_posting_id'     => $posting->id,
                'place_of_assignment' => $place,
                'vacancies'          => max(1, (int) ($vacancies[$i] ?? 1)),
                'created_at'         => now(),
                'updated_at'         => now(),
            ];
        }

        if (!empty($rows)) {
            JobPostingLocation::insert($rows);
        }

        // Keep the legacy place_of_assignment column in sync (first location)
        // so existing code that reads it doesn't break
        $first = $rows[0]['place_of_assignment'] ?? null;
        $totalVacancies = array_sum(array_column($rows, 'vacancies')) ?: 1;
        $posting->updateQuietly([
            'place_of_assignment' => $first,
            'vacancies'           => $totalVacancies,
        ]);
    }

    /**
     * Sync panelist assignments from the form submission. Checking a
     * panelist means HR wants them on this posting's panel -- there is no
     * separate "available" toggle; every assigned panelist is marked
     * available automatically.
     * Expects:
     *   panelist_ids[]        — checked panelist IDs to assign
     *   new_panelist_names[]  — names of brand-new panelists to create and assign
     */
    private function syncPanelists(JobPosting $posting, \Illuminate\Http\Request $request): void
    {
        // Create any newly added panelists (name is required, email is
        // optional but needed for that panelist to actually receive
        // schedule invitation emails -- see InterviewScheduleController).
        $newNames  = $request->input('new_panelist_names', []);
        $newEmails = $request->input('new_panelist_emails', []);
        foreach ($newNames as $i => $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $email = trim($newEmails[$i] ?? '');
            $new = Panelist::create([
                'name'  => $name,
                'email' => $email !== '' ? $email : null,
            ]);
            // Add to assigned list so they get synced below
            $request->merge([
                'panelist_ids' => array_merge($request->input('panelist_ids', []), [$new->id]),
            ]);
        }

        $assignedIds = array_map('intval', $request->input('panelist_ids', []));

        // Build pivot data: every assigned panelist is available, always
        $syncData = [];
        foreach ($assignedIds as $panelistId) {
            $syncData[$panelistId] = ['is_available' => true];
        }

        // sync() removes unassigned, adds new, updates existing
        $posting->panelists()->sync($syncData);
    }

    /**
     * Mark one applicant as Hired and reject other applicants competing
     * for that SAME place of assignment. Sibling places under the same
     * posting are left untouched. The posting itself is only closed once
     * every place of assignment (or, for legacy postings, the single
     * vacancies count) has no open slots left. Called from
     * ApplicationController or a dedicated route -- not directly from
     * the form.
     */
    public function hireApplicant(Request $request, $postingId, $applicationId)
    {
        $posting     = JobPosting::findOrFail($postingId);
        $application = Application::where('job_posting_id', $postingId)
                                  ->findOrFail($applicationId);

        // Hire the selected applicant
        $application->update(['status' => 'hired']);

        // Reject other applicants competing for the SAME place of
        // assignment only. Applicants at a different place under this
        // same posting are unaffected -- their slot is still open.
        Application::where('job_posting_id', $postingId)
                   ->where('id', '!=', $applicationId)
                   ->where('status', '!=', 'hired')
                   ->when(
                       $application->job_posting_location_id !== null,
                       fn ($q) => $q->where('job_posting_location_id', $application->job_posting_location_id),
                       fn ($q) => $q->whereNull('job_posting_location_id')
                   )
                   ->update(['status' => 'rejected']);

        // Close the posting only once EVERY place of assignment (or, for
        // a legacy posting with no location rows, the single vacancies
        // count) has no open slots left.
        $posting = $posting->fresh('locations');
        $stillOpenElsewhere = $posting->hasAnyOpenVacancy();

        if (!$stillOpenElsewhere) {
            $posting->update(['status' => 'closed']);
        }

        $message = $stillOpenElsewhere
            ? 'Applicant marked as hired. That place of assignment is now filled; other places under this posting remain open.'
            : 'Applicant marked as hired. All vacancies for this posting are now filled and it has been closed.';

        return redirect()
            ->back()
            ->with('success', $message);
    }

    public function show($id, \Illuminate\Http\Request $request)
    {
        $this->autoCloseExpiredPostings();

        $posting   = JobPosting::with(['locations', 'panelists', 'assessmentCriteria'])->findOrFail($id);
        $locations = $posting->locations;
        $panelists = $posting->panelists;

        $applications = Application::with(['candidate', 'assessments'])
            ->where('job_posting_id', $id)
            ->latest('applied_at')
            ->get();

        // Step 2 — interview schedules for this posting's applications
        $applicationIds = $applications->pluck('id');
        $schedules = InterviewSchedule::with(['application.candidate', 'panelists'])
            ->whereIn('application_id', $applicationIds)
            ->orderBy('scheduled_at', 'desc')
            ->get();

        // Step 3 — assessment criteria + ranking
        $criteria        = $posting->assessmentCriteria()->orderBy('id')->get();
        $usedWeight      = $criteria->sum('weight_percentage');
        $remainingWeight = max(0, 100 - $usedWeight);

        // Disqualified (and rejected) applicants must never appear in
        // ranking/assessment -- only candidates who passed Qualification
        // Checking (step 2) belong here. Built from a filtered subset,
        // NOT $applications itself, so $applications stays the full list
        // for the qualification-checking view (step 2) where disqualified
        // applicants should still show up, correctly labeled.
        $rankableApplications = $applications->whereNotIn('status', ['not_qualified', 'rejected'])->values();

        $rankedCandidates = $rankableApplications->map(function ($app) use ($criteria) {
            $scores = [];
            $total  = 0;
            foreach ($criteria as $c) {
                $assessment = $app->assessments->firstWhere('assessment_criteria_id', $c->id);
                $score = $assessment ? (float) $assessment->score : null;
                $scores[$c->id] = $score;
                if ($score !== null) $total += $score;
            }
            return (object) [
                'application_id'    => $app->id,
                'candidate'         => $app->candidate,
                'candidate_name'    => $app->candidate?->full_name ?? 'Unknown',
                'scores'            => $scores,
                'total_score'       => $total,
                'notification_sent' => $app->status === 'ranking_sent',
            ];
        })->sortByDesc('total_score')->values()->map(function ($cand, $i) use ($rankableApplications) {
            $cand->rank   = $i + 1;
            $cand->passed = $cand->total_score >= 75;
            $cand->total  = $rankableApplications->count();
            return $cand;
        });

        // Derive current pipeline step from posting status.
        // Overview (1) and Qualification Checking (2) both live under status
        // "open" — they're two views of the same stage, not separate statuses.
        // $currentStep is the LOCK BOUNDARY (highest step unlocked so far);
        // $activeStep is which panel is shown by default on page load.
        $stepMap = [
            'open'                => 2,
            'interview_scheduled' => 3,
            'ranking'             => 4,
            'closed'              => 5,
        ];
        $currentStep = $stepMap[$posting->status] ?? 1;

        // Allow returning to a specific panel after an action elsewhere
        // (e.g. saving a qualification check redirects back here with
        // ?step=2 so HR lands back on Qualification Checking instead of
        // Overview). Clamped to $currentStep so a crafted URL can't skip
        // ahead of what the posting's status actually unlocks.
        $requestedStep = (int) $request->query('step', 0);
        $activeStep = $requestedStep > 0
            ? min($requestedStep, $currentStep)
            : ($posting->status === 'open' ? 1 : $currentStep);

        // Step 5 -- offers, scoped to this posting only (the old
        // standalone page showed offers for every posting; here we only
        // want this posting's).
        $offers = JobOffer::whereHas('application', function ($q) use ($id) {
                $q->where('job_posting_id', $id);
            })
            ->with(['application.candidate', 'application.jobPosting'])
            ->orderByDesc('created_at')
            ->get();

        // Built from $rankedCandidates (not $applications) so the offer
        // list already carries rank number, total_score, and the full
        // candidate record (education/years_experience/eligibility) --
        // needed now that Step 5 shows rank + those fields instead of a
        // bare candidate-name dropdown.
        $eligibleOfferApplications = $rankedCandidates
            ->filter(function ($cand) use ($applications) {
                $app = $applications->firstWhere('id', $cand->application_id);
                return $app
                    && in_array($app->status, ['ranked', 'shortlisted', 'assessed', 'hired'])
                    && $app->jobOffer === null;
            })
            ->values();

        // Remaining open offer slots for this posting. SG is now
        // inherited from the job (no more manual SG/Step selection), so
        // the only thing capping how many offers HR can generate at once
        // is how many vacancy slots aren't already spoken for by an
        // active (draft/sent/accepted) offer.
        $alreadyOfferedCount = $offers->whereIn('status', ['draft', 'sent', 'accepted'])->count();
        $offerVacancyLimit = max(0, ((int) $posting->vacancies ?: 1) - $alreadyOfferedCount);

        $minCompensation = config('salary_grades.table.1.0', 14634); // SG 1 Step 1

        return view('job-postings.show', compact(
            'posting', 'locations', 'panelists', 'applications',
            'schedules', 'criteria', 'usedWeight', 'remainingWeight',
            'rankedCandidates', 'currentStep', 'activeStep',
            'offers', 'eligibleOfferApplications', 'minCompensation',
            'offerVacancyLimit'
        ));
    }

    /**
     * POST /job-postings/{id}/advance
     * Advances the posting to the next pipeline step.
     */
    public function advance(Request $request, $id)
    {
        $posting = JobPosting::findOrFail($id);

        $transitions = [
            'open'                => 'interview_scheduled',
            'interview_scheduled' => 'ranking',
            'ranking'             => 'closed',
        ];

        $nextStatus = $transitions[$posting->status] ?? null;

        if ($nextStatus) {
            $oldStatus = $posting->status;
            $posting->update(['status' => $nextStatus]);

            // No more auto-hiring on arrival at Offer Management -- HR
            // picks who gets an offer via the Step 5 checkbox list, and
            // hiring now follows from an accepted offer instead.
            $this->cascadeStatusToApplications($posting, $nextStatus);
        }

        if ($request->expectsJson()) {
            return response()->json(['status' => $posting->fresh()->status, 'ok' => true]);
        }

        return redirect()->route('job-postings.show', $posting->id)
            ->with('success', 'Posting advanced to next stage.');
    }

    /**
     * POST /job-postings/{id}/archive
     * Archives a closed posting. Only valid from 'closed' -- archiving is
     * a terminal, one-way move out of the active pipeline, not a pipeline
     * stage itself.
     */
    public function archive(Request $request, $id)
    {
        $posting = JobPosting::findOrFail($id);

        if ($posting->status !== 'closed') {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => 'Only closed postings can be archived.'], 422);
            }

            return back()->with('error', 'Only closed postings can be archived.');
        }

        $posting->update(['status' => 'archived']);

        if ($request->expectsJson()) {
            return response()->json(['status' => $posting->fresh()->status, 'ok' => true]);
        }

        return redirect()->route('job-postings.index')
            ->with('success', 'Posting archived.');
    }

    /**
     * DELETE /job-postings/{posting}/panelists/{panelist}
     * Removes a panelist from this posting's panel (pivot only, not global pool).
     */
    public function detachPanelist($postingId, $panelistId)
    {
        $posting = JobPosting::findOrFail($postingId);
        $posting->panelists()->detach($panelistId);

        return back()->with('success', 'Panelist removed from this posting.');
    }

    /**
     * Export qualification check results for all applicants on this posting
     * to an Excel file. Only available once all applicants have been checked.
     */
    public function exportQualifications($id)
    {
        $posting = JobPosting::with('locations')->findOrFail($id);

        $applications = Application::with('candidate')
            ->where('job_posting_id', $id)
            ->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Qualification Check');

        // Headers
        $headers = ['Candidate Name', 'Email', 'Place of Assignment', 'Education', 'Training', 'Experience', 'Eligibility', 'Overall Result', 'Notes'];
        foreach ($headers as $i => $h) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($col . '1', $h);
        }
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);

        $row = 2;
        foreach ($applications as $app) {
            $check    = $app->qualification_check ?? [];
            $criteria = $check['criteria'] ?? [];
            $location = $posting->locations->firstWhere('id', $app->job_posting_location_id);

            $sheet->setCellValue('A' . $row, $app->candidate?->full_name ?? '—');
            $sheet->setCellValue('B' . $row, $app->candidate?->email ?? '—');
            $sheet->setCellValue('C' . $row, $location?->place_of_assignment ?? '—');
            $sheet->setCellValue('D' . $row, ($criteria['education']['passed'] ?? null) === true ? 'Qualified' : (($criteria['education']['passed'] ?? null) === false ? 'Not Qualified' : '—'));
            $sheet->setCellValue('E' . $row, ($criteria['training']['passed'] ?? null) === true ? 'Qualified' : (($criteria['training']['passed'] ?? null) === false ? 'Not Qualified' : '—'));
            $sheet->setCellValue('F' . $row, ($criteria['experience']['passed'] ?? null) === true ? 'Qualified' : (($criteria['experience']['passed'] ?? null) === false ? 'Not Qualified' : '—'));
            $sheet->setCellValue('G' . $row, ($criteria['eligibility']['passed'] ?? null) === true ? 'Qualified' : (($criteria['eligibility']['passed'] ?? null) === false ? 'Not Qualified' : '—'));
            $sheet->setCellValue('H' . $row, ucfirst(str_replace('_', ' ', $app->qualification_result ?? '—')));
            $sheet->setCellValue('I' . $row, $check['notes'] ?? '');
            $row++;
        }

        foreach (range(1, 9) as $c) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        $writer   = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = 'qualification-check-' . $id . '-' . now()->format('Ymd') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
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