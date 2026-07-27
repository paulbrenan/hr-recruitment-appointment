<?php
/**
 * patch_fix_step_done_persists.php
 *
 * Fixes: navigating back to view a completed step (e.g. Overview, after
 * you've already progressed to Qualification Checking/Assessment) makes
 * its checkmark disappear and revert to a plain number.
 *
 * Root cause: $isDone was computed from $activeStep (whichever panel is
 * currently being VIEWED) instead of $currentStep (how far the posting
 * has actually PROGRESSED, i.e. the lock boundary). So going back to view
 * step 1 set $activeStep = 1, and "$activeStep > $num" (1 > 1) became
 * false -- the checkmark was lost even though step 1 was genuinely done.
 * The same bug existed in the client-side updateStepTracker(n), which
 * computed isDone from n (the step being navigated TO) instead of the
 * real currentStep boundary.
 *
 * Fix: $isDone (server) and isDone (client) now derive from currentStep,
 * so completed steps keep their checkmark regardless of which step you're
 * currently viewing. isActive still tracks the viewed step, so a
 * completed step you're currently looking at shows both the checkmark
 * AND the active highlight/slide-out -- they're no longer mutually
 * exclusive.
 *
 * IMPORTANT: This patch assumes patch_fix_qualcheck_highlight_and_slide.php
 * has already been run on this file (it targets the step-row/step-circle/
 * updateStepTracker markup that patch introduced). Run that one first if
 * you haven't already.
 *
 * Run once from the project root:
 *   php patch_fix_step_done_persists.php
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
        fwrite(STDERR, "        File may already be patched, or patch_fix_qualcheck_highlight_and_slide.php\n");
        fwrite(STDERR, "        hasn't been run yet. No changes made.\n");
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

$file = __DIR__ . '/resources/views/job-postings/show.blade.php';

// ── 1. Server-side: base $isDone on $currentStep (real progress), not
//       $activeStep (which panel you're viewing). ──────────────────────

$old1 = <<<'OLD'
                        // viewed ($activeStep), not the status-driven lock
                        // boundary ($currentStep) — otherwise step 1 shows as
                        // "done" the moment status is open, even while still
                        // sitting on step 1 itself.
                        $isDone   = $activeStep > $num;
                        $isActive = $activeStep === $num;
                        $isLocked = $currentStep < $num;
OLD;

$new1 = <<<'NEW'
                        // $isDone reflects real progress ($currentStep, the
                        // lock boundary) so a step's checkmark persists no
                        // matter which panel you're currently viewing.
                        // $isActive reflects which panel you're viewing
                        // ($activeStep) and is independent of $isDone -- you
                        // can be actively viewing a step that's already done.
                        $isDone   = $currentStep > $num;
                        $isActive = $activeStep === $num;
                        $isLocked = $currentStep < $num;
NEW;

apply_patch($file, $old1, $new1, 'Server render: $isDone now derives from $currentStep, not $activeStep');

// ── 2. Connector line color: match the same real-progress logic instead
//       of the currently-viewed step. ───────────────────────────────────

$old2 = <<<'OLD'
                                background:{{ $activeStep > $num ? '#198754' : '#dee2e6' }};"></div>
OLD;

$new2 = <<<'NEW'
                                background:{{ $currentStep > $num ? '#198754' : '#dee2e6' }};"></div>
NEW;

apply_patch($file, $old2, $new2, 'Connector line: color from $currentStep instead of $activeStep');

// ── 3. Client-side: updateStepTracker(n) must use the same real-progress
//       boundary (currentStep) for isDone, not n (whichever step you just
//       clicked to view). ───────────────────────────────────────────────

$old3 = <<<'OLD'
    document.querySelectorAll('.step-row').forEach(row => {
        const step     = parseInt(row.dataset.step, 10);
        const isActive = step === n;
        const isDone   = n > step;
OLD;

$new3 = <<<'NEW'
    document.querySelectorAll('.step-row').forEach(row => {
        const step     = parseInt(row.dataset.step, 10);
        const isActive = step === n;
        const isDone   = currentStep > step; // real progress, not the step being viewed
NEW;

apply_patch($file, $old3, $new3, 'updateStepTracker(): isDone now derives from currentStep, not the viewed step');

$old4 = <<<'OLD'
    document.querySelectorAll('.step-connector').forEach(conn => {
        const step = parseInt(conn.id.replace('step-connector-', ''), 10);
        conn.style.background = n > step ? '#198754' : '#dee2e6';
    });
OLD;

$new4 = <<<'NEW'
    document.querySelectorAll('.step-connector').forEach(conn => {
        const step = parseInt(conn.id.replace('step-connector-', ''), 10);
        conn.style.background = currentStep > step ? '#198754' : '#dee2e6';
    });
NEW;

apply_patch($file, $old4, $new4, 'updateStepTracker(): connector color from currentStep instead of viewed step');

echo "\nDone. Progress to Qualification Checking (or further), then click back to Overview --\n";
echo "the completed step's checkmark should now stay while still showing the active highlight.\n";
