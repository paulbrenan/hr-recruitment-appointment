<?php
/**
 * patch_remove_old_scheduling_page.php
 *
 * Removes the OLD standalone "Scheduling" page (its nav link was already
 * removed earlier). This deletes:
 *   - the route: GET /interviews -> interviews.index
 *   - the InterviewScheduleController::index() method that powered it
 *   - the view file: resources/views/interviews/index.blade.php
 *
 * Everything else under interviews.* (store, store-for-posting, update,
 * destroy, panelists-for-posting) is left untouched -- store-for-posting
 * and destroy are actively used by the pipeline's "Open Ranking &
 * Scheduling" step.
 *
 * IMPORTANT: store(), update(), and destroy() all currently redirect to
 * route('interviews.index') on success. destroy() in particular is
 * called directly from the pipeline (the trash icon on each schedule
 * row) -- if the route were deleted first, that redirect would throw a
 * RouteNotFoundException. So this patch fixes all three redirects to use
 * back() BEFORE removing the route, in the same run.
 *
 * Run once from the project root:
 *   php patch_remove_old_scheduling_page.php
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

$controllerFile = __DIR__ . '/app/Http/Controllers/InterviewScheduleController.php';
$routesFile     = __DIR__ . '/routes/web.php';
$viewFile       = __DIR__ . '/resources/views/interviews/index.blade.php';

// ── 1. Fix the three redirects that pointed at the page we're about to
//       delete. Must happen before the route is removed. ────────────────

apply_patch(
    $controllerFile,
    <<<'OLD'
        return redirect()
            ->route('interviews.index')
            ->with('success', 'Schedule created successfully. Invitation email sent.');
OLD,
    <<<'NEW'
        return back()->with('success', 'Schedule created successfully. Invitation email sent.');
NEW,
    'store(): redirect back instead of to old interviews.index page'
);

apply_patch(
    $controllerFile,
    <<<'OLD'
        return redirect()
            ->route('interviews.index')
            ->with('success', 'Schedule updated successfully.');
OLD,
    <<<'NEW'
        return back()->with('success', 'Schedule updated successfully.');
NEW,
    'update(): redirect back instead of to old interviews.index page'
);

apply_patch(
    $controllerFile,
    <<<'OLD'
        return redirect()
            ->route('interviews.index')
            ->with('success', 'Schedule deleted successfully.');
OLD,
    <<<'NEW'
        return back()->with('success', 'Schedule deleted successfully.');
NEW,
    'destroy(): redirect back instead of to old interviews.index page (this one is live -- pipeline calls it)'
);

// ── 2. Remove the route ─────────────────────────────────────────────────

apply_patch(
    $routesFile,
    <<<'OLD'
Route::get('/interviews', [InterviewScheduleController::class, 'index'])->name('interviews.index');
OLD,
    <<<'NEW'
// GET /interviews (interviews.index) removed -- replaced by the
// job-postings pipeline's "Open Ranking & Scheduling" step.
NEW,
    'routes/web.php: remove GET /interviews (interviews.index)'
);

// ── 3. Remove the controller method ─────────────────────────────────────

apply_patch(
    $controllerFile,
    <<<'OLD'
    public function index()
    {
        // Farthest future date at the top, nearest dates below it,
        // descending all the way down to the oldest past schedule.
        $schedules = InterviewSchedule::with(['application.candidate', 'application.jobPosting', 'panelists'])
            ->orderBy('scheduled_at', 'desc')
            ->get();
        $applications    = Application::with(['candidate', 'jobPosting'])->get();
        $allPanelists    = Panelist::orderBy('name')->get();
        return view('interviews.index', compact('schedules', 'applications', 'allPanelists'));
    }
    public function store(Request $request)
OLD,
    <<<'NEW'
    // index() removed -- the old standalone Scheduling page is gone.
    // Schedules are now created and managed directly inside the
    // job-postings pipeline's "Open Ranking & Scheduling" step.

    public function store(Request $request)
NEW,
    'InterviewScheduleController.php: remove index() method'
);

// ── 4. Delete the old view file ─────────────────────────────────────────

if (file_exists($viewFile)) {
    copy($viewFile, $viewFile . '.bak');
    unlink($viewFile);
    echo "[OK] Deleted resources/views/interviews/index.blade.php (backed up to .bak)\n";
} else {
    echo "[SKIP] resources/views/interviews/index.blade.php not found -- may already be removed.\n";
}

echo "\nDone. GET /interviews no longer exists. Scheduling now lives only inside the\n";
echo "job-postings pipeline's Open Ranking & Scheduling step.\n";
