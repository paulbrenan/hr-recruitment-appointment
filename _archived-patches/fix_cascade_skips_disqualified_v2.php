<?php
/**
 * fix_cascade_skips_disqualified_v2.php
 *
 * Re-targeted version of fix_cascade_skips_disqualified.php -- that one's
 * old_str included the docblock comment above the method, and a single
 * space of drift in that comment's alignment (unrelated to the actual
 * bug) broke the exact-match. This version only touches the method BODY,
 * which is what actually needs to change, so comment formatting can't
 * break the match again.
 *
 * Root cause recap: cascadeStatusToApplications() bulk-updates status for
 * EVERY application on the posting whenever the posting advances stage,
 * with only ONE exclusion (already-hired). No exclusion for
 * 'not_qualified' -- so advancing to "ranking" silently overwrote
 * disqualified applicants' status to 'ranked' too.
 *
 * Fix: skip applicants already 'not_qualified' or 'rejected', at every
 * stage.
 *
 * HOW TO RUN:
 *   php fix_cascade_skips_disqualified_v2.php   (from project root)
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

echo "\n=== fix_cascade_skips_disqualified_v2.php ===\n\n";

$controllerPath = ROOT . '/app/Http/Controllers/JobPostingController.php';

echo "[1] Patching cascadeStatusToApplications() body to skip disqualified applicants...\n";

apply_patch(
    $controllerPath,
    '    private function cascadeStatusToApplications(JobPosting $posting, string $postingStatus): void
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
    '    private function cascadeStatusToApplications(JobPosting $posting, string $postingStatus): void
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

        // Applicants already disqualified (\'not_qualified\') or already
        // \'rejected\' are NEVER touched by this cascade, at any stage --
        // otherwise advancing the posting silently overwrites their real
        // status (e.g. to \'ranked\'), erasing the disqualification.
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
echo "  - Advancing a posting through the pipeline no longer overwrites\n";
echo "    the status of any applicant already 'not_qualified' or\n";
echo "    'rejected'. Disqualification now sticks through every later\n";
echo "    stage change.\n\n";
echo "REMINDER -- your CURRENT disqualified applicant that's already\n";
echo "showing as ranked has already had their status column overwritten\n";
echo "by past stage advances. This patch only stops it happening again --\n";
echo "it doesn't retroactively fix that record. Tell me the applicant's\n";
echo "name or application ID and I'll write a one-off correction script.\n\n";
echo "DELETE this script after running.\n";
