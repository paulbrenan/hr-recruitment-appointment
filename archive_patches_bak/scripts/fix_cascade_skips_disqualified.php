<?php
/**
 * fix_cascade_skips_disqualified.php
 *
 * The REAL root cause of disqualified applicants still showing up ranked:
 * cascadeStatusToApplications() bulk-updates status for EVERY application
 * on the posting whenever the posting advances stage (open -> interview
 * -> ranking -> closed), with only ONE exclusion: already-hired
 * applicants. It had no exclusion for 'not_qualified' -- so the moment
 * the posting moved to "ranking", every applicant's status column got
 * overwritten to 'ranked', including disqualified ones. Their
 * not_qualified status was being silently destroyed, not just displayed
 * wrong -- fix_exclude_disqualified_from_ranking_v2.php's display filter
 * couldn't help because by step 4 the status literally isn't
 * 'not_qualified' anymore.
 *
 * Fix: cascade now also skips applicants whose status is 'not_qualified'
 * or already 'rejected', for every mapped stage -- not just the
 * 'rejected' case for hired applicants.
 *
 * REQUIRES fix_exclude_disqualified_from_ranking_v2.php to already be
 * applied (or apply it after this one) -- that patch filters the
 * ranking DISPLAY; this patch stops the status from being destroyed in
 * the first place. Both are needed together.
 *
 * HOW TO RUN:
 *   php fix_cascade_skips_disqualified.php   (from project root)
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

echo "\n=== fix_cascade_skips_disqualified.php ===\n\n";

$controllerPath = ROOT . '/app/Http/Controllers/JobPostingController.php';

echo "[1] Patching cascadeStatusToApplications() to never overwrite disqualified applicants...\n";

apply_patch(
    $controllerPath,
    '    /**
     * Map job posting pipeline stage → application status, then bulk-update.
     *
     * Mapping:
     *   open                → submitted
     *   interview_scheduled → interview_scheduled
     *   ranking             → ranked
     *   closed               → rejected  (for all non-hired applicants)
     *
     * Special rule: if any applicant on this posting is already \'hired\',
     * closing the posting will NOT override their status (hired stays hired).
     */
    private function cascadeStatusToApplications(JobPosting $posting, string $postingStatus): void
    {
        $map = [
            \'open\'                => \'submitted\',
            \'interview_scheduled\' => \'interview_scheduled\',
            \'ranking\'             => \'ranked\',
            \'closed\'              => \'rejected\',
        ];

        if (!isset($map[$postingStatus])) {
            return;
        }

        $applicationStatus = $map[$postingStatus];

        $query = Application::where(\'job_posting_id\', $posting->id);

        // Never override an applicant who has already been hired
        if ($applicationStatus === \'rejected\') {
            $query->where(\'status\', \'!=\', \'hired\');
        }

        $query->update([\'status\' => $applicationStatus]);
    }',
    '    /**
     * Map job posting pipeline stage → application status, then bulk-update.
     *
     * Mapping:
     *   open                → submitted
     *   interview_scheduled → interview_scheduled
     *   ranking             → ranked
     *   closed               → rejected  (for all non-hired applicants)
     *
     * Special rules:
     *   - If any applicant on this posting is already \'hired\', closing
     *     the posting will NOT override their status (hired stays hired).
     *   - Applicants already marked \'not_qualified\' (disqualified at
     *     Qualification Checking) or already \'rejected\' are NEVER
     *     touched by this cascade, at any stage. Without this, advancing
     *     the posting to \'ranking\' would silently overwrite a
     *     disqualified applicant\'s status to \'ranked\', erasing the
     *     disqualification and making them appear rankable again.
     */
    private function cascadeStatusToApplications(JobPosting $posting, string $postingStatus): void
    {
        $map = [
            \'open\'                => \'submitted\',
            \'interview_scheduled\' => \'interview_scheduled\',
            \'ranking\'             => \'ranked\',
            \'closed\'              => \'rejected\',
        ];

        if (!isset($map[$postingStatus])) {
            return;
        }

        $applicationStatus = $map[$postingStatus];

        $query = Application::where(\'job_posting_id\', $posting->id)
            ->whereNotIn(\'status\', [\'not_qualified\', \'rejected\']);

        // Never override an applicant who has already been hired
        if ($applicationStatus === \'rejected\') {
            $query->where(\'status\', \'!=\', \'hired\');
        }

        $query->update([\'status\' => $applicationStatus]);
    }',
    'JobPostingController: cascadeStatusToApplications() never overwrites not_qualified/rejected'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - Advancing a posting through the pipeline (open -> interview ->\n";
echo "    ranking -> closed) no longer overwrites the status of any\n";
echo "    applicant who is already 'not_qualified' or 'rejected'. Their\n";
echo "    disqualification now sticks permanently, through every later\n";
echo "    stage change.\n\n";
echo "IMPORTANT -- existing bad data: any applicant who was disqualified\n";
echo "BEFORE this fix and already got silently flipped to 'ranked' (or\n";
echo "'interview_scheduled') by a past stage advance will still show the\n";
echo "wrong status -- this patch only stops it from happening again going\n";
echo "forward. If you have specific applicants in that state now, tell me\n";
echo "which application ID(s) and I'll write a one-off script to correct\n";
echo "them back to 'not_qualified' using their saved qualification_check\n";
echo "record (which still has the true result).\n\n";
echo "DELETE this script after running.\n";
