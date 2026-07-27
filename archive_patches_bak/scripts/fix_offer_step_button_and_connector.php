<?php
/**
 * fix_offer_step_button_and_connector.php
 *
 * Follow-up to patch_add_offer_management_step.php. That patch correctly
 * remapped 'closed' -> Step 5 (Offer Management) and added the Step 5
 * panel, but left two things stale:
 *
 *   1. The advance button at status=ranking still reads "Close Posting"
 *      and its confirm() dialog still says "Close this posting?" --
 *      but clicking it now lands you on Step 5 (Offer Management), not
 *      a closed/terminal state. Label and confirm copy were never
 *      updated to reflect the new step mapping.
 *
 *   2. The step-tracker connector line loop only draws connectors for
 *      $num < 4 (i.e. 1-2, 2-3, 3-4), a leftover from when there were
 *      only 4 steps. Step 5 was appended without extending this, so
 *      there's no connecting line between Step 4 and Step 5 in the
 *      tracker -- visually orphaning Offer Management from the rest of
 *      the vertical tracker.
 *
 * Run once from the project root:
 *   php fix_offer_step_button_and_connector.php
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

$showView = __DIR__ . '/resources/views/job-postings/show.blade.php';

// ── 1. Button label: "Close Posting" -> "Move to Offer Management" ──────

$b1old = <<<'OLD'
                        @if ($posting->status === 'interview_scheduled')
                            <i class="bi bi-arrow-right me-1"></i> Move to Assessment
                        @elseif ($posting->status === 'ranking')
                            <i class="bi bi-check-lg me-1"></i> Close Posting
                        @endif
OLD;

$b1new = <<<'NEW'
                        @if ($posting->status === 'interview_scheduled')
                            <i class="bi bi-arrow-right me-1"></i> Move to Assessment
                        @elseif ($posting->status === 'ranking')
                            <i class="bi bi-arrow-right me-1"></i> Move to Offer Management
                        @endif
NEW;

apply_patch($showView, $b1old, $b1new, 'Advance button: relabel "Close Posting" -> "Move to Offer Management" at ranking status');

// ── 2. Confirm dialog copy for the same transition ───────────────────────

$b2old = <<<'OLD'
        4: 'Close this posting? The top-ranked passing candidate(s) for each place of assignment will be hired automatically; remaining applicants will be rejected.',
OLD;

$b2new = <<<'NEW'
        4: 'Move this posting to Offer Management? The top-ranked passing candidate(s) for each place of assignment will be hired automatically; remaining applicants will be rejected.',
NEW;

apply_patch($showView, $b2old, $b2new, 'advanceStep() confirm() copy: "Close this posting?" -> "Move this posting to Offer Management?"');

// ── 3. Tracker connector: extend from 3 connectors (1-2,2-3,3-4) to 4
//       (adds 4-5) so Offer Management visually connects to the tracker ──

$b3old = <<<'OLD'
                    @if ($num < 4)
                    <div class="step-connector" id="step-connector-{{ $num }}"
                         style="width:3px;height:14px;margin-left:calc(0.5rem + 10px);
                                background:{{ $currentStep > $num ? '#198754' : '#dee2e6' }};"></div>
                    @endif
OLD;

$b3new = <<<'NEW'
                    @if ($num < count($steps))
                    <div class="step-connector" id="step-connector-{{ $num }}"
                         style="width:3px;height:14px;margin-left:calc(0.5rem + 10px);
                                background:{{ $currentStep > $num ? '#198754' : '#dee2e6' }};"></div>
                    @endif
NEW;

apply_patch($showView, $b3old, $b3new, 'Step tracker: extend connector loop so Step 4 -> Step 5 (Offer Management) has a connecting line');

echo "\nDone. 'Close Posting' now reads 'Move to Offer Management' at the ranking\n";
echo "stage, the confirm() dialog matches, and the tracker connector line now\n";
echo "runs all the way to Step 5.\n";
