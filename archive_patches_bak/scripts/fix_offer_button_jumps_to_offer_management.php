<?php
/**
 * fix_offer_button_jumps_to_offer_management.php
 *
 * Adds an "Offer" button to the Candidate ranking table's action
 * column, next to the existing Edit Scores pencil. This doesn't submit
 * anything -- it just switches the pipeline view to the Offer
 * Management step (step 5), where HR can then generate/send the actual
 * offer using the existing per-candidate or bulk flow there. Uses the
 * same switchStep(n) JS function the pipeline's own step tracker
 * already uses.
 *
 * HOW TO RUN:
 *   php fix_offer_button_jumps_to_offer_management.php   (from project root)
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

echo "\n=== fix_offer_button_jumps_to_offer_management.php ===\n\n";

$showPath = ROOT . '/resources/views/job-postings/show.blade.php';

echo "[1] Adding Offer button (jumps to Offer Management step) to ranking table...\n";

apply_patch(
    $showPath,
    '                                <td>
                                    @if ($posting->status !== \'closed\')
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="modal" data-bs-target="#editScoresModal"
                                            data-application-id="{{ $cand->application_id }}"
                                            data-candidate-name="{{ $cand->candidate_name }}"
                                            data-scores="{{ json_encode($cand->scores) }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    @endif
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
    'show.blade.php: Offer button in ranking table jumps to Offer Management step'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - Candidate ranking table: each row now has an \"Offer\" button\n";
echo "    beside Edit Scores. Clicking it switches the pipeline view to\n";
echo "    the Offer Management step (step 5) -- it doesn't submit\n";
echo "    anything or pre-select the candidate there, just jumps HR to\n";
echo "    where they'd generate the offer.\n\n";
echo "DELETE this script after running.\n";
