<?php
/**
 * patch_qualcheck_autofill_and_redirect.php
 *
 * 1. AUTO-FILL: the "Check qualifications" modal now pre-fills each actual-
 *    value field from the candidate's self-reported education/experience/
 *    training/eligibility (same fields/precedence as the standalone
 *    /applications/{id} page: saved check value wins if present, otherwise
 *    self-reported is used as an editable starting point). Still fully
 *    editable — this only changes what's pre-filled, not what's saved.
 *
 * 2. REDIRECT FIX: ApplicationController::saveQualificationCheck() was
 *    redirecting to applications.show, bouncing HR off the pipeline
 *    dashboard entirely after saving — which is also why the "Email result"
 *    button never appeared (the page they landed on wasn't the dashboard).
 *    Now redirects back to job-postings.show with ?step=2, so HR lands back
 *    on Qualification Checking (not Overview) with the fresh data —
 *    including the now-visible Email result button.
 *
 * 3. JobPostingController::show() now accepts an optional ?step= query
 *    param to pick which panel to land on, clamped to $currentStep so a
 *    crafted URL can't skip ahead of what the posting's status unlocks.
 *
 * 4. Added an "Edit posting" button to the Overview panel header (next to
 *    the status badge), alongside the existing one in the sidebar.
 *
 * HOW TO RUN:
 *   php patch_qualcheck_autofill_and_redirect.php   (from project root)
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

echo "\n=== patch_qualcheck_autofill_and_redirect.php ===\n\n";

// ─── 1. ApplicationController::saveQualificationCheck() — redirect fix ───

echo "[1] ApplicationController.php\n";

$appControllerPath = ROOT . '/app/Http/Controllers/ApplicationController.php';

apply_patch(
    $appControllerPath,
    "        return redirect()
            ->route('applications.show', \$application->id)
            ->with('success', 'Qualification check saved. Result: ' . (\$passed ? 'Qualified' : 'Disqualified') . '.');
    }",
    "        return redirect()
            ->route('job-postings.show', ['id' => \$application->job_posting_id, 'step' => 2])
            ->with('success', 'Qualification check saved. Result: ' . (\$passed ? 'Qualified' : 'Disqualified') . '.');
    }",
    'Redirect back to job posting Step 2 instead of applications.show'
);

// ─── 2. JobPostingController::show() — honor ?step= override ─────────────

echo "\n[2] JobPostingController.php\n";

$jpControllerPath = ROOT . '/app/Http/Controllers/JobPostingController.php';

apply_patch(
    $jpControllerPath,
    'public function show($id)
    {',
    'public function show($id, \Illuminate\Http\Request $request)
    {',
    'show(): accept Request for optional ?step= override'
);

apply_patch(
    $jpControllerPath,
    "        \$currentStep = \$stepMap[\$posting->status] ?? 1;
        \$activeStep  = \$posting->status === 'open' ? 1 : \$currentStep;",
    "        \$currentStep = \$stepMap[\$posting->status] ?? 1;

        // Allow returning to a specific panel after an action elsewhere
        // (e.g. saving a qualification check redirects back here with
        // ?step=2 so HR lands back on Qualification Checking instead of
        // Overview). Clamped to \$currentStep so a crafted URL can't skip
        // ahead of what the posting's status actually unlocks.
        \$requestedStep = (int) \$request->query('step', 0);
        \$activeStep = \$requestedStep > 0
            ? min(\$requestedStep, \$currentStep)
            : (\$posting->status === 'open' ? 1 : \$currentStep);",
    'show(): $activeStep honors ?step= query param, clamped to $currentStep'
);

// ─── 3. show.blade.php — self-reported fallback + Edit posting button ────

echo "\n[3] show.blade.php\n";

$showPath = ROOT . '/resources/views/job-postings/show.blade.php';

apply_patch(
    $showPath,
    "                    @php
                        \$qColors = ['qualified'=>'success','not_qualified'=>'danger','hired'=>'dark','ranking_sent'=>'primary','interview_scheduled'=>'info','submitted'=>'secondary','rejected'=>'secondary'];
                        \$qColor = \$qColors[\$app->status] ?? 'secondary';
                        \$appCheck = \$app->qualification_check ?? [];
                    @endphp",
    "                    @php
                        \$qColors = ['qualified'=>'success','not_qualified'=>'danger','hired'=>'dark','ranking_sent'=>'primary','interview_scheduled'=>'info','submitted'=>'secondary','rejected'=>'secondary'];
                        \$qColor = \$qColors[\$app->status] ?? 'secondary';
                        \$appCheck = \$app->qualification_check ?? [];
                        // Candidate's self-reported qualifications — used only
                        // as the modal's starting point when no qualification
                        // check has been saved yet for that criterion; a saved
                        // \"actual\" value always takes precedence (see the
                        // qualCheckModal JS below). Same fields/precedence as
                        // the standalone /applications/{id} page.
                        \$appSelfReported = [
                            'education'   => \$app->candidate->education ?? null,
                            'experience'  => \$app->candidate->years_experience ?? null,
                            'training'    => \$app->candidate->training_hours ?? null,
                            'eligibility' => \$app->candidate->eligibility ?? null,
                        ];
                    @endphp",
    'Panel-2 loop: compute $appSelfReported per applicant'
);

apply_patch(
    $showPath,
    '                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="modal" data-bs-target="#qualCheckModal"
                                    data-application-id="{{ $app->id }}"
                                    data-candidate-name="{{ addslashes($app->candidate->full_name) }}"
                                    data-check="{{ json_encode($appCheck) }}">
                                <i class="bi bi-clipboard-check me-1"></i> Check qualifications
                            </button>',
    '                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="modal" data-bs-target="#qualCheckModal"
                                    data-application-id="{{ $app->id }}"
                                    data-candidate-name="{{ addslashes($app->candidate->full_name) }}"
                                    data-check="{{ json_encode($appCheck) }}"
                                    data-self-reported="{{ json_encode($appSelfReported) }}">
                                <i class="bi bi-clipboard-check me-1"></i> Check qualifications
                            </button>',
    'Check-qualifications button: pass data-self-reported'
);

apply_patch(
    $showPath,
    "    const check = JSON.parse(btn.dataset.check || '{}');
    const criteria = check.criteria || {};

    document.querySelectorAll('.qual-actual-input').forEach(input => {
        const key = input.dataset.criterion;
        input.value = criteria[key]?.actual ?? '';
    });",
    "    const check = JSON.parse(btn.dataset.check || '{}');
    const criteria = check.criteria || {};
    const selfReported = JSON.parse(btn.dataset.selfReported || '{}');

    document.querySelectorAll('.qual-actual-input').forEach(input => {
        const key = input.dataset.criterion;
        // Saved \"actual\" value takes precedence; otherwise fall back to the
        // candidate's self-reported value as an editable starting point.
        input.value = criteria[key]?.actual ?? selfReported[key] ?? '';
    });",
    'qualCheckModal JS: fall back to self-reported when no saved value'
);

apply_patch(
    $showPath,
    "                        <span class=\"badge text-bg-{{ \$statusColors[\$posting->status] ?? 'secondary' }}\">
                            {{ \$statusLabels[\$posting->status] ?? ucfirst(\$posting->status) }}
                        </span>
                    </div>
                    <hr class=\"mt-0\">",
    "                        <div class=\"d-flex align-items-center gap-2\">
                            <span class=\"badge text-bg-{{ \$statusColors[\$posting->status] ?? 'secondary' }}\">
                                {{ \$statusLabels[\$posting->status] ?? ucfirst(\$posting->status) }}
                            </span>
                            <a href=\"{{ route('job-postings.edit', \$posting->id) }}\"
                               class=\"btn btn-sm btn-outline-secondary\">
                                <i class=\"bi bi-pencil me-1\"></i> Edit posting
                            </a>
                        </div>
                    </div>
                    <hr class=\"mt-0\">",
    'Overview header: add Edit posting button'
);

echo "\n✅ Done.\n\n";
echo "ASSUMPTION TO VERIFY: Candidate model has columns education,\n";
echo "years_experience, training_hours, eligibility (matches the standalone\n";
echo "/applications/{id} page's \$selfReported block). If any of those column\n";
echo "names differ, the corresponding field will just stay blank (no error),\n";
echo "so nothing will break — but let me know and I'll correct the names.\n\n";
echo "DELETE this script after running.\n";
