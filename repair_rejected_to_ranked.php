<?php
/**
 * repair_rejected_to_ranked.php
 *
 * ONE-TIME DATA REPAIR -- not a code patch. Fixes leftover damage from
 * the old cascadeStatusToApplications() bug (fixed in
 * fix_offer_management_ranked_not_rejected.php), which mass-rejected
 * every non-hired applicant the instant a posting reached status
 * 'closed' (Offer Management). The code fix stops it from happening
 * again, but deliberately never touches rows that are ALREADY
 * 'rejected' -- so anyone caught by the bug before the fix is stuck
 * rejected forever unless corrected here.
 *
 * Scope: every JobPosting currently at status = 'closed' (i.e. sitting
 * in Offer Management right now). For each one, applications with
 * status = 'rejected' are reset to 'ranked'. Postings that are
 * 'archived' or any other status are NOT touched -- this only targets
 * postings actively in the Offer Management step today, which is
 * exactly where the bug would have just fired.
 *
 * Applications with status 'not_qualified' or 'hired' are never touched
 * by this script (same as the real cascade never touches them either).
 *
 * SAFE BY DEFAULT: run with no arguments to DRY-RUN (prints exactly what
 * would change, changes nothing). Add --apply to actually update rows.
 *
 *   php repair_rejected_to_ranked.php            # dry run
 *   php repair_rejected_to_ranked.php --apply     # apply for real
 *
 * Delete this file once you've confirmed the affected postings look
 * right in the Offer Management checkbox list.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\JobPosting;
use App\Models\Application;

$apply = in_array('--apply', $argv, true);

$postings = JobPosting::where('status', 'closed')->get(['id', 'title']);

if ($postings->isEmpty()) {
    echo "No postings currently in Offer Management (status = 'closed'). Nothing to check.\n";
    exit(0);
}

echo $apply ? "APPLY MODE -- rows will be updated.\n\n" : "DRY RUN -- no changes will be made. Re-run with --apply to fix for real.\n\n";

$totalAffected = 0;

foreach ($postings as $posting) {
    $rejected = Application::where('job_posting_id', $posting->id)
        ->where('status', 'rejected')
        ->get(['id', 'candidate_id']);

    if ($rejected->isEmpty()) {
        continue;
    }

    echo "Posting #{$posting->id} \"{$posting->title}\": {$rejected->count()} application(s) stuck 'rejected' -> would reset to 'ranked'\n";
    echo "  application IDs: " . $rejected->pluck('id')->implode(', ') . "\n";

    $totalAffected += $rejected->count();

    if ($apply) {
        Application::where('job_posting_id', $posting->id)
            ->where('status', 'rejected')
            ->update(['status' => 'ranked']);
        echo "  -> done.\n";
    }

    echo "\n";
}

if ($totalAffected === 0) {
    echo "No affected applications found on any posting currently in Offer Management. Nothing to fix.\n";
} else {
    echo $apply
        ? "Applied. {$totalAffected} application(s) reset to 'ranked' across " . $postings->count() . " posting(s) checked.\n"
        : "{$totalAffected} application(s) would be reset. Re-run with --apply to actually make the change.\n";
}
