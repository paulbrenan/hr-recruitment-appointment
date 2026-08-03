<?php
/**
 * patch_pick_applicants_show_all_qualified.php
 *
 * The Pick Applicants modal currently reuses the page's existing
 * $applications variable, filtered to qualification_result === 'qualified':
 *   $applications->where('qualification_result', 'qualified')
 *
 * If $applications (as loaded by the controller for the whole show page)
 * is itself scoped to exclude already-scheduled applicants -- e.g. if it
 * only loads certain statuses -- then already-scheduled qualified
 * applicants silently disappear from the picker, even though the
 * "Already scheduled" column was designed to show them.
 *
 * Fix: query independently, directly off the job posting's own
 * `applications` relation, so the picker always shows every qualified
 * applicant regardless of how the main page's $applications is scoped.
 *
 * Usage: php patch_pick_applicants_show_all_qualified.php
 */

function apply_patch(string $file, string $search, string $replace, string $label): void
{
    if (!file_exists($file)) {
        echo "[ABORT] File not found: {$file}\n";
        exit(1);
    }

    $contents = file_get_contents($file);

    if (strpos($contents, $search) === false) {
        echo "[ABORT] {$label}: expected text not found in {$file}. File may have drifted — aborting without changes.\n";
        exit(1);
    }

    if (substr_count($contents, $search) > 1) {
        echo "[ABORT] {$label}: expected text is not unique in {$file}. Aborting to avoid ambiguous edit.\n";
        exit(1);
    }

    $backup = $file . '.bak';
    if (!file_exists($backup)) {
        copy($file, $backup);
    }

    $new_contents = str_replace($search, $replace, $contents);
    file_put_contents($file, $new_contents);

    echo "[OK] {$label} applied to {$file}\n";
}

// Adjust this if the live path differs from the Laravel default.
$view = __DIR__ . '/resources/views/job-postings/show.blade.php';

apply_patch(
    $view,
    "                            @forelse (\$applications->where('qualification_result', 'qualified') as \$qa)",
    "                            @forelse (\$posting->applications()->where('qualification_result', 'qualified')->with(['candidate', 'interviewSchedules'])->get() as \$qa)",
    'Pick Applicants list queries independently so already-scheduled applicants always show'
);

echo "\nDone. Verify:\n";
echo " - Open the Pick Applicants modal -- every qualified applicant on this posting appears, including ones already scheduled (shown with the 'Already scheduled: Yes' indicator).\n";
echo " - If this errors with something like 'Call to undefined method applications()' on JobPosting, the relation may be named differently (e.g. jobApplications()) --\n";
echo "   share the JobPosting model and I'll adjust the relation name used here.\n";
