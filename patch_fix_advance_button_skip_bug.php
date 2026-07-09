<?php
/**
 * patch_fix_advance_button_skip_bug.php
 *
 * CONFIRMED BUG: Overview (step 1) and Qualification Checking (step 2)
 * both live under posting status "open" -- but the sidebar's single
 * Advance button didn't know which of the two panels was actually being
 * viewed. It always rendered as "Move to Scheduling" and always performed
 * that same open -> interview_scheduled status jump, regardless of
 * whether HR was looking at Overview or Qualification Checking. That let
 * HR click Advance straight from Overview and skip Qualification
 * Checking entirely -- exactly backwards from "make it a process."
 *
 * FIX: while status is "open", two button slots now exist in the
 * sidebar -- one for each panel -- and switchStep() (already the function
 * that swaps which panel is visible) now also swaps which button slot is
 * visible, so the correct one is always shown for whichever panel is
 * actually on screen, including when navigating client-side via the step
 * tracker (no page reload):
 *   - Viewing Overview (step 1): "Next: Qualification Checking" -- just
 *     switches to panel 2, no status change.
 *   - Viewing Qualification Checking (step 2): "Move to Scheduling" --
 *     the real status-advancing action, same as before.
 * Other statuses (interview_scheduled, ranking) are unaffected -- those
 * steps don't share a status with anything else, so there was never any
 * ambiguity there.
 *
 * HOW TO RUN:
 *   php patch_fix_advance_button_skip_bug.php   (project root)
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

echo "\n=== patch_fix_advance_button_skip_bug.php ===\n\n";

$showPath = ROOT . '/resources/views/job-postings/show.blade.php';

// ─── 1. Sidebar: split into two step-aware button slots ──────────────────

apply_patch(
    $showPath,
    '                {{-- Advance button --}}
                @if ($posting->status !== \'closed\')
                <div class="mt-3">
                    <button id="advanceBtn" class="btn btn-sm w-100"
                            style="background-color:var(--hr-primary);color:#fff;"
                            onclick="advanceStep()">
                        @if ($posting->status === \'open\')
                            <i class="bi bi-arrow-right me-1"></i> Move to Scheduling
                        @elseif ($posting->status === \'interview_scheduled\')
                            <i class="bi bi-arrow-right me-1"></i> Move to Assessment
                        @elseif ($posting->status === \'ranking\')
                            <i class="bi bi-check-lg me-1"></i> Close Posting
                        @endif
                    </button>
                </div>
                @endif',
    '                {{-- Advance button --}}
                @if ($posting->status === \'open\')
                    {{-- Overview (1) and Qualification Checking (2) share
                         status "open" -- only Qualification Checking can
                         trigger the real status advance. switchStep() below
                         toggles which of these two slots is visible to match
                         whichever panel is actually on screen, so this stays
                         correct even when navigating client-side via the
                         step tracker (no page reload happens there). --}}
                    <div class="mt-3 advance-slot" data-for-step="1">
                        <button type="button" class="btn btn-sm w-100 btn-outline-secondary" onclick="switchStep(2)">
                            <i class="bi bi-arrow-right me-1"></i> Next: Qualification Checking
                        </button>
                    </div>
                    <div class="mt-3 advance-slot d-none" data-for-step="2">
                        <button id="advanceBtn" class="btn btn-sm w-100"
                                style="background-color:var(--hr-primary);color:#fff;"
                                onclick="advanceStep()">
                            <i class="bi bi-arrow-right me-1"></i> Move to Scheduling
                        </button>
                    </div>
                @elseif ($posting->status !== \'closed\')
                <div class="mt-3">
                    <button id="advanceBtn" class="btn btn-sm w-100"
                            style="background-color:var(--hr-primary);color:#fff;"
                            onclick="advanceStep()">
                        @if ($posting->status === \'interview_scheduled\')
                            <i class="bi bi-arrow-right me-1"></i> Move to Assessment
                        @elseif ($posting->status === \'ranking\')
                            <i class="bi bi-check-lg me-1"></i> Close Posting
                        @endif
                    </button>
                </div>
                @endif',
    'Sidebar: step-aware Advance button (two slots for step 1 vs 2)'
);

// ─── 2. JS: switchStep() also toggles which advance-slot is shown ────────

apply_patch(
    $showPath,
    'function switchStep(n) {
    if (n > currentStep) return; // can\'t jump ahead
    document.querySelectorAll(\'.step-panel\').forEach(p => p.classList.add(\'d-none\'));
    document.getElementById(\'panel-\' + n)?.classList.remove(\'d-none\');
}',
    'function switchStep(n) {
    if (n > currentStep) return; // can\'t jump ahead
    document.querySelectorAll(\'.step-panel\').forEach(p => p.classList.add(\'d-none\'));
    document.getElementById(\'panel-\' + n)?.classList.remove(\'d-none\');
    // Keep the sidebar\'s Overview-vs-Qualification-Checking button slot in
    // sync with whichever panel is actually visible (see the two
    // .advance-slot divs in the sidebar above -- only relevant while
    // status is "open", harmless no-op otherwise since none exist).
    document.querySelectorAll(\'.advance-slot\').forEach(el => {
        el.classList.toggle(\'d-none\', el.dataset.forStep !== String(n));
    });
}',
    'JS: switchStep() syncs advance-slot visibility with active panel'
);

echo "\n✅ Done.\n\n";
echo "Reload a posting with status 'open' — Overview should now show 'Next:\n";
echo "Qualification Checking' instead of 'Move to Scheduling', and the real\n";
echo "advance button only appears once you're actually on Qualification\n";
echo "Checking.\n\n";
echo "DELETE this script after running.\n";
