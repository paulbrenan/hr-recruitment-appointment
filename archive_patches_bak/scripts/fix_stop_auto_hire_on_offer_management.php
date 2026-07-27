<?php
/**
 * fix_stop_auto_hire_on_offer_management.php
 *
 * Reaching Offer Management (posting status -> 'closed') used to
 * auto-hire the top-ranked passing candidate(s) per vacancy slot before
 * HR ever saw the checkbox list (autoHireTopRankedCandidates(), called
 * from advance()). That made sense back when 'closed' was a true
 * terminal state, but now it pre-empts the manual, checkbox-driven offer
 * flow -- HR should be the one deciding who gets an offer, and hiring
 * should follow from an accepted offer, not happen automatically on
 * arrival.
 *
 * This patch:
 *   1. Removes the autoHireTopRankedCandidates() call from advance() for
 *      the 'closed' transition. Candidates now simply move to 'ranked'
 *      (via cascadeStatusToApplications(), already fixed by
 *      fix_offer_management_ranked_not_rejected.php) and stay there
 *      until HR generates and sends an offer.
 *   2. Removes the now-unused autoHireTopRankedCandidates() method
 *      entirely rather than leaving dead code behind.
 *
 * Run once from the project root:
 *   php fix_stop_auto_hire_on_offer_management.php
 * Then delete this file.
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

$postingCtrl = __DIR__ . '/app/Http/Controllers/JobPostingController.php';

// ── 1. advance(): stop calling autoHireTopRankedCandidates() ────────────

$p1old = <<<'OLD'
        if ($nextStatus) {
            $oldStatus = $posting->status;
            $posting->update(['status' => $nextStatus]);

            if ($nextStatus === 'closed') {
                $this->autoHireTopRankedCandidates($posting);
            }

            $this->cascadeStatusToApplications($posting, $nextStatus);
        }
OLD;

$p1new = <<<'NEW'
        if ($nextStatus) {
            $oldStatus = $posting->status;
            $posting->update(['status' => $nextStatus]);

            // No more auto-hiring on arrival at Offer Management -- HR
            // picks who gets an offer via the Step 5 checkbox list, and
            // hiring now follows from an accepted offer instead.
            $this->cascadeStatusToApplications($posting, $nextStatus);
        }
NEW;

apply_patch($postingCtrl, $p1old, $p1new, "advance(): stop auto-hiring top-ranked candidates on transition to Offer Management");

// ── 2. Remove the now-unused autoHireTopRankedCandidates() method ───────

$p2old = <<<'OLD'
    /**
     * When a posting closes, hire the top-ranked PASSING candidate(s)
     * (total_score >= 75, same threshold as the "passed" flag shown on
     * the ranking table) for each place of assignment, up to that
     * location's open vacancy count -- before the close cascade rejects
     * everyone else. For legacy postings with no location rows, uses the
     * single `vacancies` column against applicants with no location set.
     * Applicants who never got scored (or scored below 75) are left
     * alone here; the cascade right after this will reject them.
     */
    private function autoHireTopRankedCandidates(JobPosting $posting): void
    {
        $criteria = $posting->assessmentCriteria()->get();

        if ($criteria->isEmpty()) {
            return;
        }

        $candidates = Application::where('job_posting_id', $posting->id)
            ->whereNotIn('status', ['not_qualified', 'rejected', 'hired'])
            ->with('assessments')
            ->get()
            ->map(function ($app) use ($criteria) {
                $total = 0;
                foreach ($criteria as $c) {
                    $assessment = $app->assessments->firstWhere('assessment_criteria_id', $c->id);
                    if ($assessment) {
                        $total += (float) $assessment->score;
                    }
                }
                $app->setAttribute('auto_hire_total_score', $total);
                return $app;
            })
            ->filter(fn ($app) => $app->auto_hire_total_score >= 75)
            ->sortByDesc('auto_hire_total_score')
            ->values();

        if ($candidates->isEmpty()) {
            return;
        }

        $locations = $posting->locations;

        if ($locations->isNotEmpty()) {
            foreach ($locations as $loc) {
                $alreadyHired = Application::where('job_posting_id', $posting->id)
                    ->where('job_posting_location_id', $loc->id)
                    ->where('status', 'hired')
                    ->count();
                $openSlots = max(0, $loc->vacancies - $alreadyHired);
                if ($openSlots < 1) {
                    continue;
                }

                $pool = $candidates->where('job_posting_location_id', $loc->id)->values();
                foreach ($pool->take($openSlots) as $winner) {
                    $winner->update(['status' => 'hired']);
                }
            }
        } else {
            $alreadyHired = Application::where('job_posting_id', $posting->id)
                ->where('status', 'hired')
                ->count();
            $openSlots = max(0, ((int) $posting->vacancies ?: 1) - $alreadyHired);
            if ($openSlots < 1) {
                return;
            }

            $pool = $candidates->whereNull('job_posting_location_id')->values();
            foreach ($pool->take($openSlots) as $winner) {
                $winner->update(['status' => 'hired']);
            }
        }
    }

OLD;

$p2new = <<<'NEW'
NEW;

apply_patch($postingCtrl, $p2old, $p2new, "Remove now-unused autoHireTopRankedCandidates() method");

echo "\nDone. Posting -> Offer Management no longer auto-hires anyone. Candidates land\n";
echo "on Step 5 as 'ranked' and stay that way until HR generates/accepts an offer.\n";
