<?php
/**
 * patch_offer_checkbox_ranking.php
 *
 * Reworks Step 5 (Offer Management) "Generate new offer" from a
 * single-candidate dropdown + manual SG/Step selects into a ranked,
 * checkbox-driven candidate list -- matching the visual pattern already
 * used in Step 4's "Candidate ranking" table.
 *
 * Changes:
 *   1. JobPostingController::show() -- $eligibleOfferApplications is now
 *      built from $rankedCandidates (not a bare $applications filter), so
 *      it carries rank number + full candidate record (education,
 *      years_experience, eligibility). Also computes $offerVacancyLimit
 *      = posting vacancies minus offers already active (draft/sent/
 *      accepted), passed to the view.
 *   2. show.blade.php Step 5 panel -- dropdown + SG/Step selects replaced
 *      with a checkbox table (Rank / Candidate / Education / Experience /
 *      Eligibility), capped client-side at $offerVacancyLimit (extra
 *      checkboxes disable once the cap is hit). SG is inherited from the
 *      job silently; an optional "override compensation" field lets HR
 *      type a specific peso amount instead of the SG-Step-1 default.
 *   3. JobOfferController::store() -- now accepts application_ids[] +
 *      job_posting_id + optional compensation_override, instead of a
 *      single application_id + required salary_grade/salary_step.
 *      Compensation defaults to the job's SG at Step 1, or the override
 *      if given. Re-enforces the vacancy cap server-side (never trust
 *      the client-side checkbox disabling alone) and silently skips any
 *      application that already picked up an offer between page load and
 *      submit.
 *
 * Run once from the project root:
 *   php patch_offer_checkbox_ranking.php
 * Then delete this file.
 */

function apply_patch($path, $old, $new, $label) {
    if (!file_exists($path)) {
        fwrite(STDERR, "[ABORT] File not found: $path ($label)\n");
        exit(1);
    }
    $contents = file_get_contents($path);
    if (strpos($contents, $old) === false) {
        fwrite(STDERR, "[ABORT] Expected content not found for: $label\n");
        fwrite(STDERR, "        File may already be patched or is a different version. No changes made.\n");
        exit(1);
    }
    copy($path, $path . '.bak');
    $updated = str_replace($old, $new, $contents, $count);
    if ($count !== 1) {
        fwrite(STDERR, "[ABORT] Expected exactly 1 match for '$label', found $count. Restoring backup.\n");
        copy($path . '.bak', $path);
        exit(1);
    }
    file_put_contents($path, $updated);
    echo "[OK] $label\n";
}

$showView    = __DIR__ . '/resources/views/job-postings/show.blade.php';
$postingCtrl = __DIR__ . '/app/Http/Controllers/JobPostingController.php';
$offerCtrl   = __DIR__ . '/app/Http/Controllers/JobOfferController.php';

// ── 1. JobPostingController.php: rebuild $eligibleOfferApplications from
//       $rankedCandidates, add $offerVacancyLimit ───────────────────────

$c1old = <<<'OLD'
        $eligibleOfferApplications = $applications
            ->whereIn('status', ['shortlisted', 'assessed', 'hired'])
            ->reject(fn ($app) => $app->jobOffer !== null)
            ->values();
OLD;

$c1new = <<<'NEW'
        // Built from $rankedCandidates (not $applications) so the offer
        // list already carries rank number, total_score, and the full
        // candidate record (education/years_experience/eligibility) --
        // needed now that Step 5 shows rank + those fields instead of a
        // bare candidate-name dropdown.
        $eligibleOfferApplications = $rankedCandidates
            ->filter(function ($cand) use ($applications) {
                $app = $applications->firstWhere('id', $cand->application_id);
                return $app
                    && in_array($app->status, ['shortlisted', 'assessed', 'hired'])
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
NEW;

apply_patch($postingCtrl, $c1old, $c1new, 'show(): rebuild eligibleOfferApplications from rankedCandidates + compute offerVacancyLimit');

$c2old = <<<'OLD'
        return view('job-postings.show', compact(
            'posting', 'locations', 'panelists', 'applications',
            'schedules', 'criteria', 'usedWeight', 'remainingWeight',
            'rankedCandidates', 'currentStep', 'activeStep',
            'offers', 'eligibleOfferApplications', 'minCompensation'
        ));
OLD;

$c2new = <<<'NEW'
        return view('job-postings.show', compact(
            'posting', 'locations', 'panelists', 'applications',
            'schedules', 'criteria', 'usedWeight', 'remainingWeight',
            'rankedCandidates', 'currentStep', 'activeStep',
            'offers', 'eligibleOfferApplications', 'minCompensation',
            'offerVacancyLimit'
        ));
NEW;

apply_patch($postingCtrl, $c2old, $c2new, "show(): pass offerVacancyLimit to the view");

// ── 2. show.blade.php: replace the dropdown+SG/Step form with a ranked
//       checkbox table ──────────────────────────────────────────────────

$v1old = <<<'OLD'
                    <h6 class="mb-3">Generate new offer</h6>
                    @if ($eligibleOfferApplications->isEmpty())
                        <p class="text-muted small mb-0">No candidates on this posting are currently eligible for an offer. Candidates become eligible once shortlisted, assessed, or hired, and don't already have an offer.</p>
                    @else
                    @if ($errors->has('salary_grade') || $errors->has('salary_step'))
                    <div class="alert alert-danger small py-2">{{ $errors->first('salary_grade') ?: $errors->first('salary_step') }}</div>
                    @endif
                    <form method="POST" action="{{ route('offers.store') }}" class="row g-2">
                        @csrf
                        <div class="col-md-3">
                            <select name="application_id" class="form-select form-select-sm" required>
                                <option value="">Select candidate</option>
                                @foreach ($eligibleOfferApplications as $app)
                                    <option value="{{ $app->id }}" {{ old('application_id') == $app->id ? 'selected' : '' }}>{{ $app->candidate->full_name ?? 'Unknown' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="salary_grade" class="form-select form-select-sm" id="offerSgSelect" required>
                                <option value="">SG</option>
                                @for ($sgOpt = 1; $sgOpt <= 33; $sgOpt++)
                                    <option value="{{ $sgOpt }}" {{ old('salary_grade') == $sgOpt ? 'selected' : '' }}>SG {{ $sgOpt }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="salary_step" class="form-select form-select-sm" id="offerStepSelect" required>
                                <option value="">Step</option>
                                @for ($stepOpt = 1; $stepOpt <= 8; $stepOpt++)
                                    <option value="{{ $stepOpt }}" {{ old('salary_step') == $stepOpt ? 'selected' : '' }}>Step {{ $stepOpt }}</option>
                                @endfor
                            </select>
                            <div class="form-text" style="font-size:0.72rem;" id="offerSgAmountHint">&nbsp;</div>
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="response_deadline" class="form-control form-control-sm" min="{{ now()->toDateString() }}" value="{{ old('response_deadline') }}">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-sm w-100" style="background-color:var(--hr-primary);color:#fff;">Generate offer</button>
                        </div>
                    </form>
                    @endif
OLD;

$v1new = <<<'NEW'
                    <h6 class="mb-3">Generate new offer{{ $offerVacancyLimit > 1 ? 's' : '' }}</h6>
                    @if ($eligibleOfferApplications->isEmpty())
                        <p class="text-muted small mb-0">No candidates on this posting are currently eligible for an offer. Candidates become eligible once shortlisted, assessed, or hired, and don't already have an offer.</p>
                    @elseif ($offerVacancyLimit < 1)
                        <p class="text-muted small mb-0">All {{ $posting->vacancies }} vacanc{{ $posting->vacancies == 1 ? 'y' : 'ies' }} for this posting already have an active offer.</p>
                    @else
                    @if ($errors->has('application_ids') || $errors->has('compensation_override'))
                    <div class="alert alert-danger small py-2">{{ $errors->first('application_ids') ?: $errors->first('compensation_override') }}</div>
                    @endif
                    <p class="text-muted small mb-2">
                        Select up to <strong>{{ $offerVacancyLimit }}</strong> candidate{{ $offerVacancyLimit == 1 ? '' : 's' }} (this posting's remaining vacancy slots). Compensation defaults to SG {{ $posting->salary_grade }} Step 1 &mdash; override below if needed.
                    </p>
                    <form method="POST" action="{{ route('offers.store') }}" id="generateOfferForm">
                        @csrf
                        <input type="hidden" name="job_posting_id" value="{{ $posting->id }}">
                        <div class="table-responsive mb-3">
                        <table class="table align-middle mb-0" style="font-size:0.875rem;">
                            <thead>
                                <tr>
                                    <th style="width:2.5rem;"></th>
                                    <th>Rank</th>
                                    <th>Candidate</th>
                                    <th>Education</th>
                                    <th>Experience</th>
                                    <th>Eligibility</th>
                                </tr>
                            </thead>
                            <tbody id="offerCandidateRows">
                                @foreach ($eligibleOfferApplications as $cand)
                                <tr>
                                    <td>
                                        <input class="form-check-input offer-candidate-checkbox" type="checkbox"
                                               name="application_ids[]" value="{{ $cand->application_id }}"
                                               {{ in_array($cand->application_id, old('application_ids', [])) ? 'checked' : '' }}>
                                    </td>
                                    <td>
                                        @if ($cand->rank === 1)
                                            <span class="badge text-bg-warning">#1</span>
                                        @else
                                            <span class="text-muted">#{{ $cand->rank }}</span>
                                        @endif
                                    </td>
                                    <td class="fw-medium">{{ $cand->candidate_name }}</td>
                                    <td>{{ $cand->candidate->education ?? '—' }}</td>
                                    <td>{{ $cand->candidate->years_experience ?? '—' }}{{ $cand->candidate->years_experience ? ' yrs' : '' }}</td>
                                    <td>{{ $cand->candidate->eligibility ?? '—' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1">Override compensation (optional)</label>
                                <input type="number" step="0.01" min="0" name="compensation_override" class="form-control form-control-sm"
                                       placeholder="Default: SG {{ $posting->salary_grade }} Step 1" value="{{ old('compensation_override') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1">Response deadline</label>
                                <input type="date" name="response_deadline" class="form-control form-control-sm" min="{{ now()->toDateString() }}" value="{{ old('response_deadline') }}">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-sm w-100" style="background-color:var(--hr-primary);color:#fff;">
                                    Generate offer<span id="offerSelectedCountLabel"></span>
                                </button>
                            </div>
                        </div>
                    </form>
                    <script>
                    (function () {
                        const limit = {{ (int) $offerVacancyLimit }};
                        const boxes = document.querySelectorAll('.offer-candidate-checkbox');
                        const countLabel = document.getElementById('offerSelectedCountLabel');

                        function refresh() {
                            const checked = document.querySelectorAll('.offer-candidate-checkbox:checked');
                            if (countLabel) countLabel.textContent = checked.length ? ' (' + checked.length + ')' : '';
                            const atLimit = checked.length >= limit;
                            boxes.forEach(function (b) {
                                if (!b.checked) b.disabled = atLimit;
                            });
                        }

                        boxes.forEach(function (b) { b.addEventListener('change', refresh); });
                        refresh();
                    })();
                    </script>
                    @endif
NEW;

apply_patch($showView, $v1old, $v1new, 'Step 5: replace dropdown+SG/Step form with ranked checkbox table (vacancy-capped)');

// ── 3. JobOfferController.php: bulk store() with inherited SG, no
//       required Step, vacancy cap re-enforced server-side ─────────────

$o1old = <<<'OLD'
use App\Models\JobOffer;
use App\Models\Application;
use App\Notifications\OfferLetterNotification;
use Illuminate\Http\Request;
OLD;

$o1new = <<<'NEW'
use App\Models\JobOffer;
use App\Models\Application;
use App\Models\JobPosting;
use App\Notifications\OfferLetterNotification;
use Illuminate\Http\Request;
NEW;

apply_patch($offerCtrl, $o1old, $o1new, 'Import JobPosting model (needed to look up inherited SG + vacancy cap)');

$o2old = <<<'OLD'
    public function store(Request $request)
    {
        $validated = $request->validate([
            'application_id'  => 'required|exists:applications,id|unique:job_offers,application_id',
            'salary_grade'    => 'required|integer|min:1|max:33',
            'salary_step'     => 'required|integer|min:1|max:8',
            'response_deadline' => 'nullable|date|after_or_equal:today',
            'benefits'        => 'nullable|string',
            'terms'           => 'nullable|string',
        ]);

        // Resolve the exact compensation from the official SG table
        $grade        = (int) $validated['salary_grade'];
        $step         = (int) $validated['salary_step'];
        $sgTable      = config('salary_grades.table');
        $compensation = $sgTable[$grade][$step - 1] ?? $this->minCompensation();

        JobOffer::create([
            'application_id'    => $validated['application_id'],
            'compensation'      => $compensation,
            'response_deadline' => $validated['response_deadline'] ?? null,
            'benefits'          => $validated['benefits'] ?? null,
            'terms'             => $validated['terms'] ?? null,
            'status'            => 'draft',
        ]);

        return back()->with('success', "Offer generated as draft — SG {$grade} Step {$step} (₱" . number_format($compensation, 2) . ').');
    }
OLD;

$o2new = <<<'NEW'
    public function store(Request $request)
    {
        // SG is now inherited from the job posting -- no more manual
        // SG/Step selects. Step defaults to 1; HR can still override the
        // resulting peso amount directly via compensation_override for
        // edge cases (e.g. negotiated pay) instead of picking a step.
        $validated = $request->validate([
            'job_posting_id'         => 'required|exists:job_postings,id',
            'application_ids'        => 'required|array|min:1',
            'application_ids.*'      => 'exists:applications,id|distinct',
            'compensation_override'  => 'nullable|numeric|min:0',
            'response_deadline'      => 'nullable|date|after_or_equal:today',
            'benefits'                => 'nullable|string',
            'terms'                   => 'nullable|string',
        ]);

        $posting = JobPosting::findOrFail($validated['job_posting_id']);

        // Re-enforce the vacancy cap server-side. The checkbox UI already
        // disables extra boxes past this limit, but that's client-side
        // only -- never trust it alone.
        $alreadyOffered = JobOffer::whereHas('application', fn ($q) => $q->where('job_posting_id', $posting->id))
            ->whereIn('status', ['draft', 'sent', 'accepted'])
            ->count();
        $limit = max(0, ((int) $posting->vacancies ?: 1) - $alreadyOffered);

        $applicationIds = array_slice($validated['application_ids'], 0, $limit);
        if (empty($applicationIds)) {
            return back()->with('error', 'No open offer slots remain for this posting\'s vacancy count.');
        }

        $grade   = (int) $posting->salary_grade;
        $sgTable = config('salary_grades.table');
        $defaultCompensation = $sgTable[$grade][0] ?? $this->minCompensation(); // SG {grade} Step 1
        $compensation = $validated['compensation_override'] ?? $defaultCompensation;

        $created = 0;
        foreach ($applicationIds as $applicationId) {
            // Skip an application that already picked up an offer between
            // this page loading and the form being submitted (the
            // eligible list already excludes these, but a stale page is
            // still possible with two HR staff on the same posting).
            if (JobOffer::where('application_id', $applicationId)->exists()) {
                continue;
            }

            JobOffer::create([
                'application_id'    => $applicationId,
                'compensation'      => $compensation,
                'response_deadline' => $validated['response_deadline'] ?? null,
                'benefits'          => $validated['benefits'] ?? null,
                'terms'             => $validated['terms'] ?? null,
                'status'            => 'draft',
            ]);
            $created++;
        }

        if ($created === 0) {
            return back()->with('error', 'Selected candidate(s) already have an offer -- nothing new was generated.');
        }

        $overrideNote = isset($validated['compensation_override']) ? ', manually overridden' : ', SG ' . $grade . ' Step 1';

        return back()->with('success', "Generated {$created} draft offer(s) at ₱" . number_format($compensation, 2) . $overrideNote . '.');
    }
NEW;

apply_patch($offerCtrl, $o2old, $o2new, 'store(): bulk checkbox-driven offer creation, SG inherited from job, vacancy cap enforced server-side');

echo "\nDone. Step 5 now shows a ranked, checkbox-capped candidate table (Education/\n";
echo "Experience instead of SG/Step selects). SG is inherited from the job at Step 1\n";
echo "by default; HR can override the peso amount directly if needed.\n";
