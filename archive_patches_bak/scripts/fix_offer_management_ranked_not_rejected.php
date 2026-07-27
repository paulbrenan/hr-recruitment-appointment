<?php
/**
 * fix_offer_management_ranked_not_rejected.php
 *
 * Root cause: cascadeStatusToApplications() maps posting status 'closed'
 * -> application status 'rejected'. That was correct back when 'closed'
 * was a true terminal state. Since patch_add_offer_management_step.php,
 * 'closed' means "arrived at Step 5 (Offer Management)" -- HR hasn't
 * picked anyone yet at that point, so mass-rejecting every non-hired
 * applicant the moment the posting gets there is wrong; they should stay
 * 'ranked' so they remain visible/selectable in the Offer Management
 * checkbox list.
 *
 * Two things needed together, or the fix is invisible:
 *   1. cascadeStatusToApplications(): 'closed' now maps to 'ranked'
 *      instead of 'rejected'.
 *   2. eligibleOfferApplications (in show()): its status whitelist was
 *      ['shortlisted', 'assessed', 'hired'] -- 'ranked' was never in it,
 *      in the ORIGINAL code, before any of these patches. So even after
 *      fix #1, the checkbox list would still render empty without this.
 *
 * Run once from the project root:
 *   php fix_offer_management_ranked_not_rejected.php
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

// ── 1. cascadeStatusToApplications(): closed -> ranked, not rejected ────

$p1old = <<<'OLD'
    /**
     * Map job posting pipeline stage → application status, then bulk-update.
     *
     * Mapping:
     *   open                → submitted
     *   interview_scheduled → interview_scheduled
     *   ranking             → ranked
     *   closed              → rejected  (for all non-hired applicants)
     *
     * Special rule: if any applicant on this posting is already 'hired',
     * closing the posting will NOT override their status (hired stays hired).
     */
    private function cascadeStatusToApplications(JobPosting $posting, string $postingStatus): void
    {
        $map = [
            'open'                => 'submitted',
            'interview_scheduled' => 'interview_scheduled',
            'ranking'             => 'ranked',
            'closed'              => 'rejected',
        ];

        if (!isset($map[$postingStatus])) {
            return;
        }

        $applicationStatus = $map[$postingStatus];

        // Applicants already disqualified ('not_qualified') or already
        // 'rejected' are NEVER touched by this cascade, at any stage --
        // otherwise advancing the posting silently overwrites their real
        // status (e.g. to 'ranked'), erasing the disqualification.
        $query = Application::where('job_posting_id', $posting->id)
            ->whereNotIn('status', ['not_qualified', 'rejected']);

        // Never override an applicant who has already been hired
        if ($applicationStatus === 'rejected') {
            $query->where('status', '!=', 'hired');
        }

        $query->update(['status' => $applicationStatus]);
    }
OLD;

$p1new = <<<'NEW'
    /**
     * Map job posting pipeline stage → application status, then bulk-update.
     *
     * Mapping:
     *   open                → submitted
     *   interview_scheduled → interview_scheduled
     *   ranking             → ranked
     *   closed              → ranked  (Step 5 / Offer Management -- HR
     *                                  hasn't picked anyone yet, so
     *                                  candidates stay ranked/selectable
     *                                  instead of being auto-rejected;
     *                                  real rejection now only happens
     *                                  via the manual hire/offer flow)
     *
     * Special rule: if any applicant on this posting is already 'hired',
     * this cascade will NOT override their status (hired stays hired).
     */
    private function cascadeStatusToApplications(JobPosting $posting, string $postingStatus): void
    {
        $map = [
            'open'                => 'submitted',
            'interview_scheduled' => 'interview_scheduled',
            'ranking'             => 'ranked',
            'closed'              => 'ranked',
        ];

        if (!isset($map[$postingStatus])) {
            return;
        }

        $applicationStatus = $map[$postingStatus];

        // Applicants already disqualified ('not_qualified') or already
        // 'rejected' are NEVER touched by this cascade, at any stage --
        // otherwise advancing the posting silently overwrites their real
        // status (e.g. to 'ranked'), erasing the disqualification/rejection.
        $query = Application::where('job_posting_id', $posting->id)
            ->whereNotIn('status', ['not_qualified', 'rejected']);

        // Never override an applicant who has already been hired
        $query->where('status', '!=', 'hired');

        $query->update(['status' => $applicationStatus]);
    }
NEW;

apply_patch($postingCtrl, $p1old, $p1new, "cascadeStatusToApplications(): 'closed' now keeps applicants 'ranked' instead of auto-rejecting them");

// ── 2. eligibleOfferApplications: whitelist must include 'ranked' or the
//       checkbox list stays empty even after fix #1 ─────────────────────

$p2old = <<<'OLD'
        $eligibleOfferApplications = $rankedCandidates
            ->filter(function ($cand) use ($applications) {
                $app = $applications->firstWhere('id', $cand->application_id);
                return $app
                    && in_array($app->status, ['shortlisted', 'assessed', 'hired'])
                    && $app->jobOffer === null;
            })
            ->values();
OLD;

$p2new = <<<'NEW'
        $eligibleOfferApplications = $rankedCandidates
            ->filter(function ($cand) use ($applications) {
                $app = $applications->firstWhere('id', $cand->application_id);
                return $app
                    && in_array($app->status, ['ranked', 'shortlisted', 'assessed', 'hired'])
                    && $app->jobOffer === null;
            })
            ->values();
NEW;

apply_patch($postingCtrl, $p2old, $p2new, "eligibleOfferApplications: whitelist 'ranked' status so ranked candidates actually show up as checkboxes");

echo "\nDone. Reaching Offer Management (Step 5) no longer mass-rejects candidates --\n";
echo "they stay 'ranked' and now correctly populate the checkbox list.\n";
