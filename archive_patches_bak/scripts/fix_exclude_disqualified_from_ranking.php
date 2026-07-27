<?php
/**
 * fix_exclude_disqualified_from_ranking.php
 *
 * JobPostingController::show() builds $rankedCandidates (used by the
 * Assessment & Results panel / step 4) directly from $applications --
 * every application on the posting, with no filter. That let applicants
 * marked 'not_qualified' at the Qualification Checking step (step 2)
 * still appear scoreable and ranked at step 4, right alongside qualified
 * candidates.
 *
 * Fix: $rankedCandidates is now built from a filtered subset that
 * excludes 'not_qualified' (and 'rejected', which can also be set at
 * step 2/3 via other flows). $applications itself is left untouched --
 * it's still used elsewhere in the view (step 2's qualification list,
 * step 3's scheduling count) where disqualified applicants SHOULD still
 * show up, just correctly labeled "Disqualified".
 *
 * HOW TO RUN:
 *   php fix_exclude_disqualified_from_ranking.php   (from project root)
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

echo "\n=== fix_exclude_disqualified_from_ranking.php ===\n\n";

$controllerPath = ROOT . '/app/Http/Controllers/JobPostingController.php';

echo "[1] Patching show() to exclude disqualified/rejected applicants from ranking...\n";

apply_patch(
    $controllerPath,
    '        \$rankedCandidates = \$applications->map(function (\$app) use (\$criteria) {
            \$scores = [];
            \$total  = 0;
            foreach (\$criteria as \$c) {
                \$assessment = \$app->assessments->firstWhere(\'assessment_criteria_id\', \$c->id);
                \$score = \$assessment ? (float) \$assessment->score : null;
                \$scores[\$c->id] = \$score;
                if (\$score !== null) \$total += \$score;
            }
            return (object) [
                \'application_id\'    => \$app->id,
                \'candidate\'         => \$app->candidate,
                \'candidate_name\'    => \$app->candidate?->full_name ?? \'Unknown\',
                \'scores\'            => \$scores,
                \'total_score\'       => \$total,
                \'notification_sent\' => \$app->status === \'ranking_sent\',
            ];
        })->sortByDesc(\'total_score\')->values()->map(function (\$cand, \$i) use (\$applications) {
            \$cand->rank   = \$i + 1;
            \$cand->passed = \$cand->total_score >= 75;
            \$cand->total  = \$applications->count();
            return \$cand;
        });',
    '        // Disqualified (and rejected) applicants must never appear in
        // ranking/assessment -- only candidates who passed Qualification
        // Checking (step 2) belong here. Built from a filtered subset,
        // NOT \$applications itself, so \$applications stays the full list
        // for the qualification-checking view (step 2) where disqualified
        // applicants should still show up, correctly labeled.
        \$rankableApplications = \$applications->whereNotIn(\'status\', [\'not_qualified\', \'rejected\'])->values();

        \$rankedCandidates = \$rankableApplications->map(function (\$app) use (\$criteria) {
            \$scores = [];
            \$total  = 0;
            foreach (\$criteria as \$c) {
                \$assessment = \$app->assessments->firstWhere(\'assessment_criteria_id\', \$c->id);
                \$score = \$assessment ? (float) \$assessment->score : null;
                \$scores[\$c->id] = \$score;
                if (\$score !== null) \$total += \$score;
            }
            return (object) [
                \'application_id\'    => \$app->id,
                \'candidate\'         => \$app->candidate,
                \'candidate_name\'    => \$app->candidate?->full_name ?? \'Unknown\',
                \'scores\'            => \$scores,
                \'total_score\'       => \$total,
                \'notification_sent\' => \$app->status === \'ranking_sent\',
            ];
        })->sortByDesc(\'total_score\')->values()->map(function (\$cand, \$i) use (\$rankableApplications) {
            \$cand->rank   = \$i + 1;
            \$cand->passed = \$cand->total_score >= 75;
            \$cand->total  = \$rankableApplications->count();
            return \$cand;
        });',
    'JobPostingController::show() -- rankedCandidates excludes not_qualified/rejected'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - Applicants marked 'not_qualified' (or 'rejected') at\n";
echo "    Qualification Checking no longer appear in the Candidate ranking\n";
echo "    table or become scoreable at the Assessment & Results step.\n";
echo "  - The Qualification Checking panel itself (step 2) is untouched --\n";
echo "    disqualified applicants still show up there, correctly grouped\n";
echo "    under \"Disqualified\".\n\n";
echo "STILL WORTH CHECKING (not changed by this patch, flagging for you):\n";
echo "  - AssessmentController::saveScores() and autoSendNotification()\n";
echo "    don't check applicant status either -- if HR opens the score\n";
echo "    edit modal directly for a disqualified applicant (e.g. via a\n";
echo "    stale browser tab/old link) and submits, it would still save.\n";
echo "    Want a server-side guard added there too so it's not just a UI\n";
echo "    omission?\n\n";
echo "DELETE this script after running.\n";
