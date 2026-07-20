<?php
/**
 * fix_exclude_disqualified_from_ranking_v2.php
 *
 * Same fix as fix_exclude_disqualified_from_ranking.php, re-targeted:
 * that script's pattern no longer matched because show() picked up a
 * $request parameter (for the ?step= redirect) since it was written.
 * The $rankedCandidates block itself is unchanged -- only this file's
 * old_str needed updating to match.
 *
 * JobPostingController::show() builds $rankedCandidates directly from
 * $applications -- every application on the posting, no filter. That let
 * applicants marked 'not_qualified' at Qualification Checking (step 2)
 * still appear scoreable and ranked at Assessment & Results (step 4).
 *
 * Fix: $rankedCandidates is now built from a filtered subset that
 * excludes 'not_qualified' and 'rejected'. $applications itself is left
 * untouched -- it's still used elsewhere in the view (step 2's
 * qualification list, step 3's scheduling count) where disqualified
 * applicants SHOULD still show up, just correctly labeled.
 *
 * HOW TO RUN:
 *   php fix_exclude_disqualified_from_ranking_v2.php   (from project root)
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

echo "\n=== fix_exclude_disqualified_from_ranking_v2.php ===\n\n";

$controllerPath = ROOT . '/app/Http/Controllers/JobPostingController.php';

echo "[1] Patching show() to exclude disqualified/rejected applicants from ranking...\n";

apply_patch(
    $controllerPath,
    '        $rankedCandidates = $applications->map(function ($app) use ($criteria) {
            $scores = [];
            $total  = 0;
            foreach ($criteria as $c) {
                $assessment = $app->assessments->firstWhere(\'assessment_criteria_id\', $c->id);
                $score = $assessment ? (float) $assessment->score : null;
                $scores[$c->id] = $score;
                if ($score !== null) $total += $score;
            }
            return (object) [
                \'application_id\'    => $app->id,
                \'candidate\'         => $app->candidate,
                \'candidate_name\'    => $app->candidate?->full_name ?? \'Unknown\',
                \'scores\'            => $scores,
                \'total_score\'       => $total,
                \'notification_sent\' => $app->status === \'ranking_sent\',
            ];
        })->sortByDesc(\'total_score\')->values()->map(function ($cand, $i) use ($applications) {
            $cand->rank   = $i + 1;
            $cand->passed = $cand->total_score >= 75;
            $cand->total  = $applications->count();
            return $cand;
        });',
    '        // Disqualified (and rejected) applicants must never appear in
        // ranking/assessment -- only candidates who passed Qualification
        // Checking (step 2) belong here. Built from a filtered subset,
        // NOT $applications itself, so $applications stays the full list
        // for the qualification-checking view (step 2) where disqualified
        // applicants should still show up, correctly labeled.
        $rankableApplications = $applications->whereNotIn(\'status\', [\'not_qualified\', \'rejected\'])->values();

        $rankedCandidates = $rankableApplications->map(function ($app) use ($criteria) {
            $scores = [];
            $total  = 0;
            foreach ($criteria as $c) {
                $assessment = $app->assessments->firstWhere(\'assessment_criteria_id\', $c->id);
                $score = $assessment ? (float) $assessment->score : null;
                $scores[$c->id] = $score;
                if ($score !== null) $total += $score;
            }
            return (object) [
                \'application_id\'    => $app->id,
                \'candidate\'         => $app->candidate,
                \'candidate_name\'    => $app->candidate?->full_name ?? \'Unknown\',
                \'scores\'            => $scores,
                \'total_score\'       => $total,
                \'notification_sent\' => $app->status === \'ranking_sent\',
            ];
        })->sortByDesc(\'total_score\')->values()->map(function ($cand, $i) use ($rankableApplications) {
            $cand->rank   = $i + 1;
            $cand->passed = $cand->total_score >= 75;
            $cand->total  = $rankableApplications->count();
            return $cand;
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
echo "DELETE this script after running.\n";
