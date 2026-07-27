<?php
/**
 * patch_fix_assessment_redirect_bug.php
 *
 * Fixes: clicking "Add criterion" (and every other assessment action --
 * delete criterion, import scores, save scores, send one/send all
 * notifications) from the job-posting pipeline's Assessment & Results
 * step kicks you back to the OLD standalone Assessment & Ranking page
 * instead of staying on the pipeline.
 *
 * Root cause: every one of these controller methods hard-redirects to
 * route('assessments.index', ...) regardless of where the request came
 * from. destroyAllCriteria() already does this correctly with back(),
 * which returns to whichever page submitted the form (the pipeline, in
 * normal use). This patch makes the other six methods match that
 * pattern.
 *
 * Run once from the project root:
 *   php patch_fix_assessment_redirect_bug.php
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

$file = __DIR__ . '/app/Http/Controllers/AssessmentController.php';

// 1. storeCriterion() — the "Add criterion" action
apply_patch(
    $file,
    <<<'OLD'
        return redirect()
            ->route('assessments.index', ['job_posting' => $validated['job_posting_id']])
            ->with('success', 'Assessment criterion added.');
OLD,
    <<<'NEW'
        return back()->with('success', 'Assessment criterion added.');
NEW,
    'storeCriterion(): redirect back instead of to old assessments.index page'
);

// 2. destroyCriterion()
apply_patch(
    $file,
    <<<'OLD'
        return redirect()
            ->route('assessments.index', ['job_posting' => $jobPostingId])
            ->with('success', 'Assessment criterion removed.');
OLD,
    <<<'NEW'
        return back()->with('success', 'Assessment criterion removed.');
NEW,
    'destroyCriterion(): redirect back instead of to old assessments.index page'
);

// 3. importScores()
apply_patch(
    $file,
    <<<'OLD'
        return redirect()
            ->route('assessments.index', ['job_posting' => $jobPostingId])
            ->with((!empty($unmatchedCodes) || !empty($outOfRange)) ? 'error' : 'success', $message);
OLD,
    <<<'NEW'
        return back()->with((!empty($unmatchedCodes) || !empty($outOfRange)) ? 'error' : 'success', $message);
NEW,
    'importScores(): redirect back instead of to old assessments.index page'
);

// 4. saveScores()
apply_patch(
    $file,
    <<<'OLD'
        return redirect()
            ->route('assessments.index', ['job_posting' => $validated['job_posting_id']])
            ->with('success', 'Scores saved and ranking notification sent to the applicant.');
OLD,
    <<<'NEW'
        return back()->with('success', 'Scores saved and ranking notification sent to the applicant.');
NEW,
    'saveScores(): redirect back instead of to old assessments.index page'
);

// 5. sendOne()
apply_patch(
    $file,
    <<<'OLD'
        return redirect()
            ->route('assessments.index', ['job_posting' => $request->job_posting_id])
            ->with('success', "Notification sent to {$app->candidate->full_name}.");
OLD,
    <<<'NEW'
        return back()->with('success', "Notification sent to {$app->candidate->full_name}.");
NEW,
    'sendOne(): redirect back instead of to old assessments.index page'
);

// 6. sendAll()
apply_patch(
    $file,
    <<<'OLD'
        return redirect()
            ->route('assessments.index', ['job_posting' => $request->job_posting_id])
            ->with('success', "Ranking notifications sent to {$sent} applicant(s).");
OLD,
    <<<'NEW'
        return back()->with('success', "Ranking notifications sent to {$sent} applicant(s).");
NEW,
    'sendAll(): redirect back instead of to old assessments.index page'
);

echo "\nDone. Adding/deleting a criterion, importing/saving scores, and sending notifications\n";
echo "from the pipeline's Assessment & Results step should now stay on the pipeline page.\n";
