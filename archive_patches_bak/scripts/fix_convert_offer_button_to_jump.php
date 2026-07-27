<?php
/**
 * fix_convert_offer_button_to_jump.php
 *
 * fix_offer_button_and_email_column.php already ran, which added a
 * form-submitting "Offer" button (POSTs directly to offers.store) to
 * the Candidate ranking table. This converts that into a simple
 * jump-to-Offer-Management button instead -- no form, no submission,
 * just switchStep(5) to bring HR to the Offer Management step, where
 * the actual offer gets generated via the existing flow there.
 *
 * Do NOT run fix_offer_button_jumps_to_offer_management.php after this
 * -- that one assumes the ORIGINAL (pre-fix_offer_button_and_email_column)
 * ranking table markup and won't match anymore. This script is the
 * correct one to run given what's already live.
 *
 * HOW TO RUN:
 *   php fix_convert_offer_button_to_jump.php   (from project root)
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

echo "\n=== fix_convert_offer_button_to_jump.php ===\n\n";

$showPath = ROOT . '/resources/views/job-postings/show.blade.php';

echo "[1] Converting the ranking table's Offer form-button into a jump button...\n";

apply_patch(
    $showPath,
    '                                <td class="text-end">
                                    @php
                                        $rankAlreadyOffered = $offers->firstWhere(\'application_id\', $cand->application_id);
                                    @endphp
                                    <div class="d-flex gap-1 justify-content-end">
                                        @if ($posting->status !== \'closed\')
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                data-bs-toggle="modal" data-bs-target="#editScoresModal"
                                                data-application-id="{{ $cand->application_id }}"
                                                data-candidate-name="{{ $cand->candidate_name }}"
                                                data-scores="{{ json_encode($cand->scores) }}">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        @endif
                                        @if ($rankAlreadyOffered)
                                        <span class="badge text-bg-light text-dark border align-self-center" style="font-size:.68rem;">Offer sent</span>
                                        @else
                                        <form method="POST" action="{{ route(\'offers.store\') }}" class="d-inline"
                                              onsubmit="return confirm(\'Generate a draft offer for {{ addslashes($cand->candidate_name) }} at SG {{ $posting->salary_grade }} Step 1?\')">
                                            @csrf
                                            <input type="hidden" name="job_posting_id" value="{{ $posting->id }}">
                                            <input type="hidden" name="application_ids[]" value="{{ $cand->application_id }}">
                                            <button type="submit" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">
                                                <i class="bi bi-envelope-paper me-1"></i> Offer
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                </td>',
    '                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        @if ($posting->status !== \'closed\')
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                data-bs-toggle="modal" data-bs-target="#editScoresModal"
                                                data-application-id="{{ $cand->application_id }}"
                                                data-candidate-name="{{ $cand->candidate_name }}"
                                                data-scores="{{ json_encode($cand->scores) }}">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        @endif
                                        <button type="button" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;"
                                                onclick="switchStep(5)" title="Go to Offer Management">
                                            <i class="bi bi-envelope-paper me-1"></i> Offer
                                        </button>
                                    </div>
                                </td>',
    'show.blade.php: ranking table Offer button now jumps to step 5 instead of submitting a form'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - The Offer button in the Candidate ranking table no longer\n";
echo "    submits a form directly -- it now just switches the pipeline\n";
echo "    view to Offer Management (step 5), same as clicking that step\n";
echo "    in the tracker. No auto-offer-generation happens from this\n";
echo "    button anymore.\n\n";
echo "DELETE this script after running.\n";
