<?php
/**
 * patch_split_overview_qualchecking.php
 *
 * Splits the old Step 1 ("Overview & Qualification Checking") into two
 * separate steps:
 *   Step 1 — Overview                    (status: open)
 *   Step 2 — Qualification Checking      (status: open)
 *   Step 3 — Open Ranking & Scheduling   (status: interview_scheduled)  [was step 2]
 *   Step 4 — Assessment & Results        (status: ranking/closed)      [was step 3]
 *
 * Step 1 and Step 2 share the SAME posting status ("open") — they're two
 * views of one stage, not separate statuses. So the controller now tracks
 * two things instead of one:
 *   $currentStep — lock boundary (highest step unlocked so far, status-driven)
 *   $activeStep  — which panel shows by default on page load (1 for a fresh
 *                  "open" posting, otherwise same as $currentStep)
 * Clicking between step 1 and step 2 is unrestricted (both unlocked whenever
 * status is "open"); step 3/4 stay locked until the status actually advances.
 *
 * Qualification Checking reuses your EXISTING applications.qualification-check
 * route/controller unchanged — this only adds a view for it (a shared modal,
 * one per applicant, same Education/Experience/Training/Eligibility
 * Qualified/Not-qualified + actual-value pattern as the standalone
 * /applications/{id} page) instead of new backend logic.
 *
 * ASSUMPTION TO VERIFY: if ApplicationController::saveQualificationCheck()
 * redirects back to /applications instead of back to the job posting, saving
 * a qualification check will bounce HR off this dashboard. If that happens,
 * share that method and I'll patch the redirect target.
 *
 * HOW TO RUN:
 *   php patch_split_overview_qualchecking.php   (from project root)
 * DELETE this script after running.
 */

define('ROOT', __DIR__);

function backup(string $path): void {
    if (!file_exists($path)) return;
    $bak = $path . '.bak';
    $i = 2;
    while (file_exists($bak)) { $bak = $path . '.bak' . $i++; }
    copy($path, $bak);
    echo "  [bak] $bak\n";
}

function apply_patch(string $path, string $old, string $new, string $label): void {
    if (!file_exists($path)) { echo "\n❌ File not found: $path\n"; exit(1); }
    $content = file_get_contents($path);
    $count = substr_count($content, $old);
    if ($count === 0) {
        echo "\n❌ PATCH ABORTED — content not found in $path\nLabel: $label\n";
        exit(1);
    }
    if ($count > 1) {
        echo "\n❌ PATCH ABORTED — pattern found $count times (expected 1) in $path\nLabel: $label\n";
        exit(1);
    }
    backup($path);
    file_put_contents($path, str_replace($old, $new, $content));
    echo "  [ok ] $label\n";
}

echo "\n=== patch_split_overview_qualchecking.php ===\n\n";

// ─── 1. JobPostingController::show() — step map + activeStep ─────────────

echo "[1] Patching JobPostingController::show()...\n";

$controllerPath = ROOT . '/app/Http/Controllers/JobPostingController.php';

apply_patch(
    $controllerPath,
    "        // Derive current pipeline step from posting status
        \$stepMap     = ['open' => 1, 'interview_scheduled' => 2, 'ranking' => 3, 'closed' => 3];
        \$currentStep = \$stepMap[\$posting->status] ?? 1;

        return view('job-postings.show', compact(
            'posting', 'locations', 'panelists', 'applications',
            'schedules', 'criteria', 'usedWeight', 'remainingWeight',
            'rankedCandidates', 'currentStep'
        ));
    }",
    "        // Derive current pipeline step from posting status.
        // Overview (1) and Qualification Checking (2) both live under status
        // \"open\" — they're two views of the same stage, not separate statuses.
        // \$currentStep is the LOCK BOUNDARY (highest step unlocked so far);
        // \$activeStep is which panel is shown by default on page load.
        \$stepMap = [
            'open'                => 2,
            'interview_scheduled' => 3,
            'ranking'             => 4,
            'closed'              => 4,
        ];
        \$currentStep = \$stepMap[\$posting->status] ?? 1;
        \$activeStep  = \$posting->status === 'open' ? 1 : \$currentStep;

        return view('job-postings.show', compact(
            'posting', 'locations', 'panelists', 'applications',
            'schedules', 'criteria', 'usedWeight', 'remainingWeight',
            'rankedCandidates', 'currentStep', 'activeStep'
        ));
    }",
    'JobPostingController: 4-step map + activeStep'
);

// ─── 2. show.blade.php edits ───────────────────────────────────────────────

echo "\n[2] Patching show.blade.php...\n";

$showPath = ROOT . '/resources/views/job-postings/show.blade.php';

// 2a. $steps array — 4 steps now
apply_patch(
    $showPath,
    "    \$steps = [
        1 => ['label' => 'Overview & Qualification Checking', 'icon' => 'bi-clipboard-check'],
        2 => ['label' => 'Open Ranking & Scheduling',         'icon' => 'bi-calendar-event'],
        3 => ['label' => 'Assessment & Results',              'icon' => 'bi-bar-chart-line'],
    ];",
    "    \$steps = [
        1 => ['label' => 'Overview',                'icon' => 'bi-info-circle'],
        2 => ['label' => 'Qualification Checking',  'icon' => 'bi-clipboard-check'],
        3 => ['label' => 'Open Ranking & Scheduling','icon' => 'bi-calendar-event'],
        4 => ['label' => 'Assessment & Results',     'icon' => 'bi-bar-chart-line'],
    ];",
    'show.blade.php: 4-step $steps array'
);

// 2b. Divider condition (3 dividers between 4 steps, not 2 between 3)
apply_patch(
    $showPath,
    '@if ($num < 3)',
    '@if ($num < 4)',
    'show.blade.php: step divider condition'
);

// 2c. Remove old Applicants+Qualify/Disqualify card from panel-1, insert new
//     panel-2 (Qualification Checking) with per-applicant modal trigger.
apply_patch(
    $showPath,
    '            {{-- Applicants + qualification check --}}
            <div class="card">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">
                            Applicants
                            <span class="badge text-bg-light text-dark border ms-1">{{ $applications->count() }}</span>
                        </h6>
                    </div>

                    @forelse ($applications as $app)
                    @php
                        $qColors = [\'qualified\'=>\'success\',\'not_qualified\'=>\'danger\',\'hired\'=>\'dark\',\'ranking_sent\'=>\'primary\',\'interview_scheduled\'=>\'info\',\'submitted\'=>\'secondary\',\'rejected\'=>\'secondary\'];
                        $qColor = $qColors[$app->status] ?? \'secondary\';
                    @endphp
                    <div class="border rounded p-3 mb-2" style="font-size:0.875rem;">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                                <div class="fw-medium">{{ $app->candidate->full_name }}</div>
                                <div class="text-muted small">
                                    Applied {{ $app->applied_at ? \Carbon\Carbon::parse($app->applied_at)->format(\'M d, Y\') : \'—\' }}
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge text-bg-{{ $qColor }}">
                                    {{ str_replace(\'_\', \' \', ucfirst($app->status)) }}
                                </span>
                                @if (!in_array($app->status, [\'hired\', \'ranking_sent\']))
                                <form action="{{ route(\'applications.updateStatus\', $app->id) }}" method="POST" class="m-0">
                                    @csrf @method(\'PUT\')
                                    <input type="hidden" name="status" value="qualified">
                                    <input type="hidden" name="job_posting_id" value="{{ $posting->id }}">
                                    <button type="submit" class="btn btn-sm btn-outline-success py-0 px-2"
                                            style="font-size:0.72rem;"
                                            {{ $app->status === \'qualified\' ? \'disabled\' : \'\' }}>
                                        ✓ Qualify
                                    </button>
                                </form>
                                <form action="{{ route(\'applications.updateStatus\', $app->id) }}" method="POST" class="m-0">
                                    @csrf @method(\'PUT\')
                                    <input type="hidden" name="status" value="not_qualified">
                                    <input type="hidden" name="job_posting_id" value="{{ $posting->id }}">
                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2"
                                            style="font-size:0.72rem;"
                                            {{ $app->status === \'not_qualified\' ? \'disabled\' : \'\' }}>
                                        ✗ Disqualify
                                    </button>
                                </form>
                                @endif
                            </div>
                        </div>
                    </div>
                    @empty
                    <p class="text-muted small mb-0 text-center py-3">No applications yet for this posting.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ══ STEP 2 ══════════════════════════════════════════════════════ --}}
        <div class="step-panel d-none" id="panel-2">',
    '        </div>

        {{-- ══ STEP 2 — Qualification Checking ════════════════════════════ --}}
        <div class="step-panel d-none" id="panel-2">
            <div class="card">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">
                            Qualification checking
                            <span class="badge text-bg-light text-dark border ms-1">{{ $applications->count() }}</span>
                        </h6>
                    </div>

                    @forelse ($applications as $app)
                    @php
                        $qColors = [\'qualified\'=>\'success\',\'not_qualified\'=>\'danger\',\'hired\'=>\'dark\',\'ranking_sent\'=>\'primary\',\'interview_scheduled\'=>\'info\',\'submitted\'=>\'secondary\',\'rejected\'=>\'secondary\'];
                        $qColor = $qColors[$app->status] ?? \'secondary\';
                        $appCheck = $app->qualification_check ?? [];
                    @endphp
                    <div class="border rounded p-3 mb-2 d-flex justify-content-between align-items-center flex-wrap gap-2" style="font-size:0.875rem;">
                        <div>
                            <div class="fw-medium">{{ $app->candidate->full_name }}</div>
                            <div class="text-muted small">
                                Applied {{ $app->applied_at ? \Carbon\Carbon::parse($app->applied_at)->format(\'M d, Y\') : \'—\' }}
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge text-bg-{{ $qColor }}">
                                {{ str_replace(\'_\', \' \', ucfirst($app->status)) }}
                            </span>
                            @if (!in_array($app->status, [\'hired\', \'ranking_sent\']))
                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="modal" data-bs-target="#qualCheckModal"
                                    data-application-id="{{ $app->id }}"
                                    data-candidate-name="{{ addslashes($app->candidate->full_name) }}"
                                    data-check="{{ json_encode($appCheck) }}">
                                <i class="bi bi-clipboard-check me-1"></i> Check qualifications
                            </button>
                            @endif
                            @if ($app->qualification_result)
                            <form action="{{ route(\'applications.qualification-notice\', $app->id) }}" method="POST" class="m-0">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    {{ $app->qualification_notified_at ? \'Resend result\' : \'Email result\' }}
                                </button>
                            </form>
                            @endif
                        </div>
                    </div>
                    @empty
                    <p class="text-muted small mb-0 text-center py-3">No applications yet for this posting.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ══ STEP 3 ══════════════════════════════════════════════════════ --}}
        <div class="step-panel d-none" id="panel-3">',
    'show.blade.php: split panel-1, insert panel-2, renumber old panel-2 -> panel-3'
);

// 2d. Rename old panel-3 (Ranking/Assessment) -> panel-4
apply_patch(
    $showPath,
    '        {{-- ══ STEP 3 ══════════════════════════════════════════════════════ --}}
        <div class="step-panel d-none" id="panel-3">

            {{-- Ranking --}}',
    '        {{-- ══ STEP 4 ══════════════════════════════════════════════════════ --}}
        <div class="step-panel d-none" id="panel-4">

            {{-- Ranking --}}',
    'show.blade.php: renumber old panel-3 -> panel-4'
);

// 2e. New qualCheckModal markup — insert right before the Edit Scores modal
apply_patch(
    $showPath,
    '{{-- Edit Scores --}}
<div class="modal fade" id="editScoresModal" tabindex="-1">',
    '{{-- Qualification Check --}}
<div class="modal fade" id="qualCheckModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="qualCheckForm" action="">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title">Qualification check — <span id="qualCheckCandidateName"></span></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="job_posting_id" value="{{ $posting->id }}">
                    @php
                        $qualCriteriaFields = [
                            \'education\'   => [\'label\' => \'Education\',   \'required\' => $posting->qualification_education   ?? null],
                            \'experience\'  => [\'label\' => \'Experience\',  \'required\' => $posting->qualification_experience  ?? null],
                            \'training\'    => [\'label\' => \'Training\',    \'required\' => $posting->qualification_training    ?? null],
                            \'eligibility\' => [\'label\' => \'Eligibility\', \'required\' => $posting->qualification_eligibility ?? null],
                        ];
                    @endphp
                    @foreach ($qualCriteriaFields as $key => $meta)
                    <div class="border-bottom py-2">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <label class="fw-medium mb-0">{{ $meta[\'label\'] }}</label>
                            <div class="btn-group btn-group-sm" role="group">
                                <input type="radio" class="btn-check qual-passed-input" name="{{ $key }}_passed" value="1"
                                       id="qc_{{ $key }}_yes" data-criterion="{{ $key }}" autocomplete="off">
                                <label class="btn btn-outline-success" for="qc_{{ $key }}_yes" style="font-size:.7rem;padding:.15rem .5rem;">Qualified</label>

                                <input type="radio" class="btn-check qual-passed-input" name="{{ $key }}_passed" value="0"
                                       id="qc_{{ $key }}_no" data-criterion="{{ $key }}" autocomplete="off">
                                <label class="btn btn-outline-danger" for="qc_{{ $key }}_no" style="font-size:.7rem;padding:.15rem .5rem;">Not qualified</label>
                            </div>
                        </div>
                        @if ($meta[\'required\'])
                        <div class="text-muted mb-1" style="font-size:.75rem;">Required: {{ $meta[\'required\'] }}</div>
                        @endif
                        <input type="text" name="{{ $key }}_actual" class="form-control form-control-sm qual-actual-input"
                               data-criterion="{{ $key }}"
                               placeholder="Candidate\'s actual {{ strtolower($meta[\'label\']) }}...">
                    </div>
                    @endforeach
                    <textarea name="check_notes" id="qualCheckNotes" class="form-control form-control-sm mt-2" rows="2"
                              placeholder="Notes about this qualification check..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background-color:var(--hr-primary);color:#fff;">Save qualification check</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit Scores --}}
<div class="modal fade" id="editScoresModal" tabindex="-1">',
    'show.blade.php: add qualCheckModal markup'
);

// 2f. JS: add activeStep variable
apply_patch(
    $showPath,
    'const currentStep = {{ $currentStep }};',
    'const currentStep = {{ $currentStep }};
const activeStep  = {{ $activeStep }};',
    'show.blade.php: JS activeStep variable'
);

// 2g. JS: land on activeStep, not currentStep, on page load
apply_patch(
    $showPath,
    '// Show the active step on load
switchStep(currentStep);',
    '// Show the active step on load
switchStep(activeStep);',
    'show.blade.php: JS initial panel = activeStep'
);

// 2h. JS: advance-confirmation messages re-keyed to the new 2/3/4 scale
apply_patch(
    $showPath,
    "    const msgs = {
        1: 'Move this posting to Interview Scheduling? Status will update to \"Interview\".',
        2: 'Move this posting to Assessment & Results? Status will update to \"Ranking\".',
        3: 'Close this posting? All remaining applicants will be rejected.',
    };",
    "    const msgs = {
        2: 'Move this posting to Interview Scheduling? Status will update to \"Interview\".',
        3: 'Move this posting to Assessment & Results? Status will update to \"Ranking\".',
        4: 'Close this posting? All remaining applicants will be rejected.',
    };",
    'show.blade.php: JS advance messages re-keyed'
);

// 2i. JS: qualCheckModal populate handler — inserted after the editScoresModal handler
apply_patch(
    $showPath,
    "// ── Panelist checklist for schedule modal ───────────────────────────────────",
    "// ── Qualification check modal ───────────────────────────────────────────────
document.getElementById('qualCheckModal')?.addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    const appId = btn.dataset.applicationId;
    document.getElementById('qualCheckForm').action = '/applications/' + appId + '/qualification-check';
    document.getElementById('qualCheckCandidateName').textContent = btn.dataset.candidateName;

    const check = JSON.parse(btn.dataset.check || '{}');
    const criteria = check.criteria || {};

    document.querySelectorAll('.qual-actual-input').forEach(input => {
        const key = input.dataset.criterion;
        input.value = criteria[key]?.actual ?? '';
    });
    document.querySelectorAll('.qual-passed-input').forEach(input => { input.checked = false; });
    Object.keys(criteria).forEach(key => {
        const passed = criteria[key]?.passed;
        const targetId = passed === true ? 'qc_' + key + '_yes' : (passed === false ? 'qc_' + key + '_no' : null);
        if (targetId) document.getElementById(targetId)?.setAttribute('checked', 'checked'), document.getElementById(targetId).checked = true;
    });
    document.getElementById('qualCheckNotes').value = check.notes ?? '';
});

// ── Panelist checklist for schedule modal ───────────────────────────────────",
    'show.blade.php: JS qualCheckModal populate handler'
);

echo "\n✅ Done.\n\n";
echo "NEW STEP MAP:\n";
echo "  1 — Overview                    (status: open)\n";
echo "  2 — Qualification Checking      (status: open)\n";
echo "  3 — Open Ranking & Scheduling   (status: interview_scheduled)\n";
echo "  4 — Assessment & Results        (status: ranking / closed)\n\n";
echo "Reload /job-postings/{id} to check it — a fresh 'open' posting should\n";
echo "land on Overview, with Qualification Checking clickable (not locked)\n";
echo "right next to it.\n\n";
echo "DELETE this script after running.\n";
