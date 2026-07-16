<?php
/**
 * fix_offer_eligibility_and_send_resilience.php
 *
 * [1] Offer eligibility checked 'ranked', not 'hired' -- but per your
 *     workflow, the top-ranked applicant gets auto-promoted to 'hired'
 *     status when the posting closes. That meant hired applicants (the
 *     ones who should actually get an offer) never showed up in the
 *     "Generate new offer" dropdown at all -- only applicants still
 *     sitting at 'ranked' did.
 *
 *     Fix: eligibility now checks 'hired' instead of 'ranked'.
 *
 * [2] send() called $offer->application->candidate->notify(new
 *     OfferLetterNotification($offer)) with NO try/catch -- every other
 *     notification call in this codebase is wrapped (see
 *     InterviewScheduleController, ApplicationController, etc.) specifically
 *     because an uncaught notification failure turns into a hard 500
 *     instead of "offer marked sent, but email didn't go out." Since
 *     status flips to 'sent' BEFORE the notify() call, a crash here means
 *     the DB is already correct but the user sees a broken page and never
 *     gets redirected to where the Accept/Decline buttons (which are
 *     already built, gated on status === 'sent') would appear.
 *
 *     Fix: wrapped in try/catch, same pattern as everywhere else --
 *     offer send still succeeds and redirects even if the email itself
 *     fails, with an honest message either way.
 *
 * HOW TO RUN:
 *   php fix_offer_eligibility_and_send_resilience.php   (from project root)
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

echo "\n=== fix_offer_eligibility_and_send_resilience.php ===\n\n";

$controllerPath = ROOT . '/app/Http/Controllers/JobOfferController.php';

echo "[1] Patching eligibility check: 'ranked' -> 'hired'...\n";

apply_patch(
    $controllerPath,
    "        \$eligibleApplications = Application::with(['candidate', 'jobPosting'])
            ->whereIn('status', ['shortlisted', 'assessed', 'ranked'])
            ->whereDoesntHave('jobOffer')
            ->orderByDesc('applied_at')
            ->get();",
    "        // 'ranked' applicants get auto-promoted to 'hired' when the\n" .
    "        // posting closes -- check for that instead, so the actual\n" .
    "        // hired applicant shows up as eligible for an offer.\n" .
    "        \$eligibleApplications = Application::with(['candidate', 'jobPosting'])\n" .
    "            ->whereIn('status', ['shortlisted', 'assessed', 'hired'])\n" .
    "            ->whereDoesntHave('jobOffer')\n" .
    "            ->orderByDesc('applied_at')\n" .
    "            ->get();",
    'JobOfferController::index() -- eligibility checks hired instead of ranked'
);

echo "\n[2] Wrapping the offer letter email in try/catch, matching the rest of the app...\n";

apply_patch(
    $controllerPath,
    "        // Reload with relations so the notification has candidate/jobPosting
        // available without extra queries inside the Notification class.
        \$offer->load(['application.candidate', 'application.jobPosting']);

        // Deliver the formal offer letter to the candidate.
        \$offer->application->candidate->notify(new OfferLetterNotification(\$offer));

        // Stamp separately from offer_sent_at (which tracks the business
        // status) so this column reflects actual email dispatch, matching
        // the reminder_sent_at guard pattern used in interview schedules.
        \$offer->update(['email_sent_at' => now()]);

        return redirect()->route('offers.index')->with('success', 'Offer sent to candidate. Offer letter emailed.');",
    "        // Reload with relations so the notification has candidate/jobPosting
        // available without extra queries inside the Notification class.
        \$offer->load(['application.candidate', 'application.jobPosting']);

        // Deliver the formal offer letter to the candidate. Wrapped like
        // every other notification call in this app -- status is already
        // 'sent' above, so a mail failure here must not turn into an
        // uncaught 500 that leaves the page looking completely broken
        // (the Accept/Decline buttons are gated on status === 'sent' and
        // would still be reachable on the next page load either way, but
        // an uncaught exception here means the user never SEES that).
        try {
            \$offer->application->candidate->notify(new OfferLetterNotification(\$offer));

            // Stamp separately from offer_sent_at (which tracks the business
            // status) so this column reflects actual email dispatch, matching
            // the reminder_sent_at guard pattern used in interview schedules.
            \$offer->update(['email_sent_at' => now()]);

            return redirect()->route('offers.index')->with('success', 'Offer sent to candidate. Offer letter emailed.');
        } catch (\Throwable \$e) {
            \Illuminate\Support\Facades\Log::error('Offer letter email failed for offer ' . \$offer->id . ': ' . \$e->getMessage());

            return redirect()->route('offers.index')->with('error', 'Offer marked as sent, but the offer letter email failed to send. Check the mail configuration and try resending.');
        }",
    'JobOfferController::send() -- offer letter email wrapped in try/catch'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - 'Generate new offer' dropdown now lists hired applicants\n";
echo "    (instead of ranked), matching your actual workflow.\n";
echo "  - Sending an offer no longer crashes the whole request if the\n";
echo "    email fails -- status still updates to 'sent', you get an\n";
echo "    honest error message instead of a broken page, and the\n";
echo "    Accept/Decline buttons (already built, unchanged) become\n";
echo "    reachable either way.\n\n";
echo "STILL NEEDED: send me app/Notifications/OfferLetterNotification.php\n";
echo "so I can check whether IT is the actual source of the failure (e.g.\n";
echo "missing class, broken view reference) and restyle it to match the\n";
echo "rest of the emails from today.\n\n";
echo "DELETE this script after running.\n";
