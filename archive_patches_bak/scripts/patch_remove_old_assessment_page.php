<?php
/**
 * patch_remove_old_assessment_page.php
 *
 * Removes the OLD standalone "Assessment & Ranking" page, now that it's
 * fully replaced by the pipeline's "Assessment & Results" step (and its
 * nav link was already removed earlier). This deletes:
 *   - the route: GET /assessments -> assessments.index
 *   - the AssessmentController::index() method that powered it
 *   - the view file: resources/views/assessments/index.blade.php
 *
 * Everything else under assessments.* (criteria.store, criteria.destroy,
 * template, import, scores.save, send-all, etc.) is left untouched --
 * those are the actual backend actions the pipeline's Assessment &
 * Results tab submits to, and are still in active use.
 *
 * IMPORTANT: Run patch_fix_assessment_redirect_bug.php BEFORE this one.
 * That patch removes the last remaining redirects to assessments.index;
 * if you delete the route first, those redirects would 404 in the
 * meantime.
 *
 * Run once from the project root:
 *   php patch_remove_old_assessment_page.php
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

$controllerFile = __DIR__ . '/app/Http/Controllers/AssessmentController.php';
$routesFile     = __DIR__ . '/routes/web.php';
$viewFile       = __DIR__ . '/resources/views/assessments/index.blade.php';

// ── 1. Remove the route ─────────────────────────────────────────────────

apply_patch(
    $routesFile,
    <<<'OLD'
Route::get('/assessments', [AssessmentController::class, 'index'])->name('assessments.index');
OLD,
    <<<'NEW'
// GET /assessments (assessments.index) removed -- replaced by the
// job-postings pipeline's "Assessment & Results" step.
NEW,
    'routes/web.php: remove GET /assessments (assessments.index)'
);

// ── 2. Remove the controller method ─────────────────────────────────────

apply_patch(
    $controllerFile,
    <<<'OLD'
    public function index(Request $request)
    {
        // All postings with their locations eager-loaded
        $allPostings = JobPosting::with('locations')->orderBy('title')->get();

        // Unique titles for the first dropdown
        $postings = $allPostings->unique('title')->values();

        // Which title is selected? Default to the first unique title.
        $selectedTitle = $request->query('title');
        if (!$selectedTitle && $postings->isNotEmpty()) {
            $selectedTitle = $postings->first()->title;
        }

        // All postings matching the selected title (one per place of assignment)
        $locationPostings = $allPostings->where('title', $selectedTitle)->values();

        // Which specific posting (place of assignment) is selected?
        $selectedPostingId = $request->query('job_posting');

        // Auto-select the first location posting if none chosen yet
        if (!$selectedPostingId && $locationPostings->isNotEmpty()) {
            $selectedPostingId = $locationPostings->first()->id;
        }

        $criteria = AssessmentCriterion::where('job_posting_id', $selectedPostingId)
            ->orderBy('id')
            ->get();

        $usedWeight = $criteria->sum('weight_percentage');
        $remainingWeight = max(0, 100 - $usedWeight);

        $applications = Application::with(['candidate', 'assessments'])
            ->where('job_posting_id', $selectedPostingId)
            ->get();

        $selectedPosting = JobPosting::with('locations')->find($selectedPostingId);

        $rankedCandidates = $applications->map(function ($app) use ($criteria) {
            $scores = [];
            $total = 0;

            foreach ($criteria as $c) {
                $assessment = $app->assessments->firstWhere('assessment_criteria_id', $c->id);
                $score = $assessment ? (float) $assessment->score : null;
                $scores[$c->id] = $score;
                if ($score !== null) {
                    $total += $score;
                }
            }

            // The official CAR form's "Application Code" is the applicant-facing
            // identifier that stays visible when the name is concealed for public
            // posting (RA No. 10163 / Data Privacy Act) — the app already generates
            // one per application, so reuse it rather than adding a new field.
            $remarks = optional($app->assessments->first())->evaluator_remarks;

            return (object) [
                'application_id'   => $app->id,
                'application_code' => $app->transaction_number,
                'candidate'        => $app->candidate,
                'candidate_name'   => $app->candidate?->full_name ?? 'Unknown',
                'scores'           => $scores,
                'total_score'      => $total,
                'remarks'          => $remarks,
                'notification_sent' => $app->status === 'ranking_sent',
            ];
        })->sortByDesc('total_score')->values();

        // Attach rank and passed flag
        $total_count = $rankedCandidates->count();
        $rankedCandidates = $rankedCandidates->map(function ($cand, $i) use ($total_count) {
            $cand->rank   = $i + 1;
            $cand->passed = $cand->total_score >= 75;
            $cand->total  = $total_count;
            return $cand;
        });

        return view('assessments.index', compact('criteria', 'rankedCandidates', 'postings', 'selectedPostingId', 'selectedPosting', 'usedWeight', 'remainingWeight', 'locationPostings', 'selectedTitle'));
    }

    public function storeCriterion(Request $request)
OLD,
    <<<'NEW'
    // index() removed -- the old standalone Assessment & Ranking page is
    // gone. Its data (criteria, ranked candidates, weights) is now built
    // directly inside the job-postings pipeline's Assessment & Results
    // step -- see JobPostingController / the pipeline view instead.

    public function storeCriterion(Request $request)
NEW,
    'AssessmentController.php: remove index() method'
);

// ── 3. Delete the old view file ─────────────────────────────────────────

if (file_exists($viewFile)) {
    copy($viewFile, $viewFile . '.bak');
    unlink($viewFile);
    echo "[OK] Deleted resources/views/assessments/index.blade.php (backed up to .bak)\n";
} else {
    echo "[SKIP] resources/views/assessments/index.blade.php not found -- may already be removed.\n";
}

echo "\nDone. GET /assessments no longer exists. All assessment actions now live only\n";
echo "inside the job-postings pipeline's Assessment & Results step.\n";
