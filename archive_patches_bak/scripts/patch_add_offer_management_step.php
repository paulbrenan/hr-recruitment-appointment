<?php
/**
 * patch_add_offer_management_step.php
 *
 * Adds "Offer Management" as Step 5 in the job-postings pipeline, right
 * after Assessment & Results (Step 4). Scope of THIS patch: get the same
 * functionality the standalone /offers page had (generate/send/accept/
 * decline/delete an offer), but scoped to the current posting only, and
 * reachable from the pipeline instead of a separate page.
 *
 * NOT included here (separate, later tasks per the backlog):
 *   - "Change Step & SG to Education & Experience"
 *   - "Offer beside candidate" / ranking still shown alongside it
 *   - Checkbox limiting offers to the number of job vacancies
 * This patch is a straight move + scope-to-posting, nothing more.
 *
 * Step mapping change: 'closed' now maps to step 5 instead of step 4.
 * This fits naturally -- advancing a posting to "closed" already
 * auto-hires the top-ranked candidate(s) (existing behavior), so by the
 * time you land on the newly-closed posting you're exactly where you'd
 * want to generate/send that candidate's offer.
 *
 * Also fixes JobOfferController's store()/send()/respond()/destroy() --
 * all four currently hard-redirect to route('offers.index') (the OLD
 * standalone page), which is the same bug we already fixed for
 * AssessmentController and InterviewScheduleController. Submitting from
 * the new Step 5 panel would otherwise bounce you off the pipeline.
 *
 * The old standalone /offers page and its nav link are left alone here
 * -- same as Assessment/Scheduling, remove those in a follow-up patch
 * once you've confirmed Step 5 works the way you want.
 *
 * Run once from the project root:
 *   php patch_add_offer_management_step.php
 * Then delete this file — it is a one-shot installer, not idempotent.
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

// ── 1. $steps array: add Step 5 ──────────────────────────────────────────

$s1old = <<<'OLD'
    $steps = [
        1 => ['label' => 'Overview',                'icon' => 'bi-info-circle'],
        2 => ['label' => 'Qualification Checking',  'icon' => 'bi-clipboard-check'],
        3 => ['label' => 'Open Ranking & Scheduling','icon' => 'bi-calendar-event'],
        4 => ['label' => 'Assessment & Results',     'icon' => 'bi-bar-chart-line'],
    ];
OLD;

$s1new = <<<'NEW'
    $steps = [
        1 => ['label' => 'Overview',                'icon' => 'bi-info-circle'],
        2 => ['label' => 'Qualification Checking',  'icon' => 'bi-clipboard-check'],
        3 => ['label' => 'Open Ranking & Scheduling','icon' => 'bi-calendar-event'],
        4 => ['label' => 'Assessment & Results',     'icon' => 'bi-bar-chart-line'],
        5 => ['label' => 'Offer Management',         'icon' => 'bi-envelope-paper'],
    ];
NEW;

apply_patch($showView, $s1old, $s1new, '$steps array: add Step 5 (Offer Management)');

// ── 2. JobPostingController.php: import JobOffer, remap 'closed' to step
//       5, and fetch offer data scoped to this posting ─────────────────

$s2old = <<<'OLD'
use App\Models\Application;
use App\Models\AssessmentCriterion;
use App\Models\InterviewSchedule;
use App\Models\JobPosting;
OLD;

$s2new = <<<'NEW'
use App\Models\Application;
use App\Models\AssessmentCriterion;
use App\Models\InterviewSchedule;
use App\Models\JobOffer;
use App\Models\JobPosting;
NEW;

apply_patch($postingCtrl, $s2old, $s2new, 'Import JobOffer model');

$s3old = <<<'OLD'
        $stepMap = [
            'open'                => 2,
            'interview_scheduled' => 3,
            'ranking'             => 4,
            'closed'              => 4,
        ];
OLD;

$s3new = <<<'NEW'
        $stepMap = [
            'open'                => 2,
            'interview_scheduled' => 3,
            'ranking'             => 4,
            'closed'              => 5,
        ];
NEW;

apply_patch($postingCtrl, $s3old, $s3new, 'stepMap: closed now maps to Step 5 (Offer Management) instead of Step 4');

$s4old = <<<'OLD'
        return view('job-postings.show', compact(
            'posting', 'locations', 'panelists', 'applications',
            'schedules', 'criteria', 'usedWeight', 'remainingWeight',
            'rankedCandidates', 'currentStep', 'activeStep'
        ));
OLD;

$s4new = <<<'NEW'
        // Step 5 -- offers, scoped to this posting only (the old
        // standalone page showed offers for every posting; here we only
        // want this posting's).
        $offers = JobOffer::whereHas('application', function ($q) use ($id) {
                $q->where('job_posting_id', $id);
            })
            ->with(['application.candidate', 'application.jobPosting'])
            ->orderByDesc('created_at')
            ->get();

        $eligibleOfferApplications = $applications
            ->whereIn('status', ['shortlisted', 'assessed', 'hired'])
            ->reject(fn ($app) => $app->jobOffer !== null)
            ->values();

        $minCompensation = config('salary_grades.table.1.0', 14634); // SG 1 Step 1

        return view('job-postings.show', compact(
            'posting', 'locations', 'panelists', 'applications',
            'schedules', 'criteria', 'usedWeight', 'remainingWeight',
            'rankedCandidates', 'currentStep', 'activeStep',
            'offers', 'eligibleOfferApplications', 'minCompensation'
        ));
NEW;

apply_patch($postingCtrl, $s4old, $s4new, 'show(): fetch offers/eligible applications/minCompensation scoped to this posting');

// ── 3. show.blade.php: insert the Step 5 panel right after Step 4 ───────

$s5old = <<<'OLD'
                    @if ($posting->status !== 'closed')
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-2" data-bs-toggle="modal" data-bs-target="#importCriteriaModal">
                        <i class="bi bi-upload me-1"></i> Scan file for criteria
                    </button>
                    @endif
                </div>
            </div>
        </div>

    </div>{{-- col-md-9 --}}
OLD;

$s5new = <<<'NEW'
                    @if ($posting->status !== 'closed')
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-2" data-bs-toggle="modal" data-bs-target="#importCriteriaModal">
                        <i class="bi bi-upload me-1"></i> Scan file for criteria
                    </button>
                    @endif
                </div>
            </div>
        </div>

        {{-- ══ STEP 5 ══════════════════════════════════════════════════════ --}}
        <div class="step-panel d-none" id="panel-5">
            <div class="card mb-3">
                <div class="card-body p-4">
                    <h6 class="mb-3">Job offers</h6>
                    @if ($offers->isEmpty())
                        <p class="text-muted small mb-0 text-center py-3">No offers yet.</p>
                    @else
                    <div class="table-responsive mb-4">
                        <table class="table align-middle mb-0" style="font-size:0.875rem;">
                            <thead>
                                <tr>
                                    <th>Candidate</th>
                                    <th>Compensation</th>
                                    <th>Sent</th>
                                    <th>Email delivery</th>
                                    <th>Response by</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($offers as $o)
                                @php
                                    $offerColors = ['draft' => 'secondary', 'sent' => 'primary', 'accepted' => 'success', 'declined' => 'danger', 'expired' => 'dark'];
                                @endphp
                                <tr>
                                    <td class="fw-medium">{{ $o->application->candidate->full_name ?? 'Unknown' }}</td>
                                    <td>&#8369;{{ number_format($o->compensation, 2) }}</td>
                                    <td>{{ $o->offer_sent_at ? \Carbon\Carbon::parse($o->offer_sent_at)->format('M d, Y') : '—' }}</td>
                                    <td>
                                        @if ($o->email_sent_at)
                                            <span class="badge text-bg-success">Sent</span>
                                            <div class="text-muted" style="font-size:0.72rem;">{{ \Carbon\Carbon::parse($o->email_sent_at)->format('M d, Y g:i A') }}</div>
                                        @else
                                            <span class="badge text-bg-secondary">Not sent</span>
                                        @endif
                                    </td>
                                    <td>{{ $o->response_deadline ? \Carbon\Carbon::parse($o->response_deadline)->format('M d, Y') : '—' }}</td>
                                    <td>
                                        <span class="badge badge-status text-bg-{{ $offerColors[$o->status] ?? 'secondary' }}">{{ ucfirst($o->status) }}</span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex gap-1 justify-content-end">
                                            @if ($o->status === 'draft')
                                            <form method="POST" action="{{ route('offers.send', $o->id) }}" class="d-inline">
                                                @csrf @method('PUT')
                                                <button type="submit" class="btn btn-sm" style="background-color:var(--hr-primary);color:#fff;">Send</button>
                                            </form>
                                            @elseif ($o->status === 'sent')
                                            <form method="POST" action="{{ route('offers.respond', $o->id) }}" class="d-inline"
                                                  onsubmit="return confirm('Mark this offer as accepted?')">
                                                @csrf @method('PUT')
                                                <input type="hidden" name="response" value="accepted">
                                                <button type="submit" class="btn btn-sm btn-outline-success">Accept</button>
                                            </form>
                                            <form method="POST" action="{{ route('offers.respond', $o->id) }}" class="d-inline"
                                                  onsubmit="return confirm('Mark this offer as declined?')">
                                                @csrf @method('PUT')
                                                <input type="hidden" name="response" value="declined">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Decline</button>
                                            </form>
                                            @else
                                            <span class="text-muted small">No actions</span>
                                            @endif
                                            <form method="POST" action="{{ route('offers.destroy', $o->id) }}" class="d-inline" onsubmit="return confirm('Delete this offer? This cannot be undone.');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif

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
                </div>
            </div>
        </div>

    </div>{{-- col-md-9 --}}
NEW;

apply_patch($showView, $s5old, $s5new, 'show.blade.php: insert Step 5 (Offer Management) panel after Step 4');

// ── 4. Offer SG/Step -> compensation live preview script ────────────────

$s6old = <<<'OLD'
function advanceStep() {
OLD;

$s6new = <<<'NEW'
// ── Step 5: SG/step -> compensation live preview ────────────────────────
(function () {
    const sgTable = @json(config('salary_grades.table'));
    const sgSel   = document.getElementById('offerSgSelect');
    const stepSel = document.getElementById('offerStepSelect');
    const hint    = document.getElementById('offerSgAmountHint');
    if (!sgSel || !stepSel || !hint) return;

    function updateOfferAmountHint() {
        const sg   = parseInt(sgSel.value, 10);
        const step = parseInt(stepSel.value, 10);
        if (sg && step && sgTable[sg] && sgTable[sg][step - 1]) {
            hint.textContent = '₱' + Number(sgTable[sg][step - 1]).toLocaleString('en-PH');
            hint.style.color = 'var(--hr-primary)';
        } else {
            hint.textContent = '\u00a0';
        }
    }

    sgSel.addEventListener('change', updateOfferAmountHint);
    stepSel.addEventListener('change', updateOfferAmountHint);
    updateOfferAmountHint();
})();

function advanceStep() {
NEW;

apply_patch($showView, $s6old, $s6new, 'Add SG/Step compensation live preview script for the Step 5 offer form');

// ── 5. JobOfferController.php: fix redirects so submitting from the
//       pipeline stays on the pipeline instead of bouncing to the old
//       standalone page. ─────────────────────────────────────────────────

$s7old = <<<'OLD'
        return redirect()->route('offers.index')->with('success', "Offer generated as draft — SG {$grade} Step {$step} (₱" . number_format($compensation, 2) . ').');
OLD;

$s7new = <<<'NEW'
        return back()->with('success', "Offer generated as draft — SG {$grade} Step {$step} (₱" . number_format($compensation, 2) . ').');
NEW;

apply_patch($offerCtrl, $s7old, $s7new, 'store(): redirect back instead of to old offers.index page');

$s8old = <<<'OLD'
        if ($offer->status !== 'draft') {
            return redirect()->route('offers.index')->with('error', 'Only draft offers can be sent.');
        }
OLD;

$s8new = <<<'NEW'
        if ($offer->status !== 'draft') {
            return back()->with('error', 'Only draft offers can be sent.');
        }
NEW;

apply_patch($offerCtrl, $s8old, $s8new, 'send(): redirect back instead of to old offers.index page (guard clause)');

$s9old = <<<'OLD'
            return redirect()->route('offers.index')->with('success', 'Offer sent to candidate. Offer letter emailed.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Offer letter email failed for offer ' . $offer->id . ': ' . $e->getMessage());

            return redirect()->route('offers.index')->with('error', 'Offer marked as sent, but the offer letter email failed to send. Check the mail configuration and try resending.');
OLD;

$s9new = <<<'NEW'
            return back()->with('success', 'Offer sent to candidate. Offer letter emailed.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Offer letter email failed for offer ' . $offer->id . ': ' . $e->getMessage());

            return back()->with('error', 'Offer marked as sent, but the offer letter email failed to send. Check the mail configuration and try resending.');
NEW;

apply_patch($offerCtrl, $s9old, $s9new, 'send(): redirect back instead of to old offers.index page (success/failure paths)');

$s10old = <<<'OLD'
        if ($offer->status !== 'sent') {
            return redirect()->route('offers.index')->with('error', 'Only sent offers can be marked accepted or declined.');
        }

        $offer->update(['status' => $validated['response']]);

        $offer->application->update([
            'status' => $validated['response'] === 'accepted' ? 'offer_accepted' : 'offer_declined',
        ]);

        return redirect()->route('offers.index')->with('success', 'Offer marked as ' . $validated['response'] . '.');
OLD;

$s10new = <<<'NEW'
        if ($offer->status !== 'sent') {
            return back()->with('error', 'Only sent offers can be marked accepted or declined.');
        }

        $offer->update(['status' => $validated['response']]);

        $offer->application->update([
            'status' => $validated['response'] === 'accepted' ? 'offer_accepted' : 'offer_declined',
        ]);

        return back()->with('success', 'Offer marked as ' . $validated['response'] . '.');
NEW;

apply_patch($offerCtrl, $s10old, $s10new, 'respond(): redirect back instead of to old offers.index page');

$s11old = <<<'OLD'
        $offer->delete();

        return redirect()->route('offers.index')->with('success', 'Offer deleted.');
OLD;

$s11new = <<<'NEW'
        $offer->delete();

        return back()->with('success', 'Offer deleted.');
NEW;

apply_patch($offerCtrl, $s11old, $s11new, 'destroy(): redirect back instead of to old offers.index page');

echo "\nDone. Offer Management now lives in the pipeline as Step 5, after Assessment &\n";
echo "Results. The old standalone /offers page still exists (not removed by this patch)\n";
echo "-- remove it and its nav link separately once you've confirmed Step 5 works.\n";
