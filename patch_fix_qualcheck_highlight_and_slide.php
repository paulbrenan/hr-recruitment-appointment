<?php
/**
 * patch_fix_qualcheck_highlight_and_slide.php
 *
 * Fixes:
 *  1. Qualification Checking (step 2) never highlights/turns green/active
 *     in the sidebar tracker. Root cause: switchStep(n) only swaps which
 *     .step-panel is visible — it never re-renders the tracker DOM, which
 *     was rendered once server-side from $activeStep at page load. Clicking
 *     between step 1 and 2 (both status "open") changes the panel but the
 *     tracker sidebar stays frozen on whatever it showed on load.
 *  2. Adds a "slide out" effect on the active step row (translateX) so the
 *     active step is visually distinct beyond just color.
 *
 * Run once from the project root:
 *   php patch_fix_qualcheck_highlight_and_slide.php
 * Then delete this file — it is a one-shot installer, not idempotent.
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

$file = __DIR__ . '/resources/views/job-postings/show.blade.php';

// ── 1. Give each step row/circle/label stable IDs + data-step so JS can
//       target them individually, and add the translateX slide-out on the
//       active row. ─────────────────────────────────────────────────────

$old1 = <<<'OLD'
                    <div class="d-flex align-items-start gap-2 py-2 px-2 rounded
                            {{ $isActive ? 'bg-primary bg-opacity-10' : '' }}"
                         style="cursor:{{ $isLocked ? 'default' : 'pointer' }};
                                border-left:3px solid {{ $isActive ? 'var(--hr-primary,#0d6efd)' : ($isDone ? '#198754' : '#dee2e6') }};"
                         onclick="{{ $isLocked ? '' : 'switchStep(' . $num . ')' }}">
                        <div class="flex-shrink-0 d-flex align-items-center justify-content-center mt-1"
                             style="width:20px;height:20px;border-radius:50%;
                                    background:{{ $isDone ? '#198754' : ($isActive ? 'var(--hr-primary,#0d6efd)' : '#dee2e6') }};">
                            @if ($isDone)
                                <i class="bi bi-check text-white" style="font-size:0.7rem;"></i>
                            @else
                                <span style="font-size:0.6rem;font-weight:600;color:{{ $isActive ? '#fff' : '#6c757d' }};">{{ $num }}</span>
                            @endif
                        </div>
                        <div class="small fw-medium {{ $isActive ? 'text-primary' : ($isDone ? 'text-success' : 'text-muted') }}">
                            {{ $step['label'] }}
                        </div>
                    </div>
                    @if ($num < 4)
                    <div style="width:3px;height:14px;margin-left:calc(0.5rem + 10px);
                                background:{{ $activeStep > $num ? '#198754' : '#dee2e6' }};"></div>
                    @endif
OLD;

$new1 = <<<'NEW'
                    <div class="step-row d-flex align-items-start gap-2 py-2 px-2 rounded
                            {{ $isActive ? 'bg-primary bg-opacity-10' : '' }}"
                         id="step-row-{{ $num }}"
                         data-step="{{ $num }}"
                         style="cursor:{{ $isLocked ? 'default' : 'pointer' }};
                                border-left:3px solid {{ $isActive ? 'var(--hr-primary,#0d6efd)' : ($isDone ? '#198754' : '#dee2e6') }};
                                transform:{{ $isActive ? 'translateX(6px)' : 'translateX(0)' }};
                                transition:transform .15s ease, background-color .15s ease;"
                         onclick="{{ $isLocked ? '' : 'switchStep(' . $num . ')' }}">
                        <div class="step-circle flex-shrink-0 d-flex align-items-center justify-content-center mt-1"
                             id="step-circle-{{ $num }}"
                             style="width:20px;height:20px;border-radius:50%;
                                    background:{{ $isDone ? '#198754' : ($isActive ? 'var(--hr-primary,#0d6efd)' : '#dee2e6') }};">
                            <span id="step-circle-inner-{{ $num }}">
                            @if ($isDone)
                                <i class="bi bi-check text-white" style="font-size:0.7rem;"></i>
                            @else
                                <span style="font-size:0.6rem;font-weight:600;color:{{ $isActive ? '#fff' : '#6c757d' }};">{{ $num }}</span>
                            @endif
                            </span>
                        </div>
                        <div class="small fw-medium" id="step-label-{{ $num }}"
                             style="color:{{ $isActive ? 'var(--hr-primary,#0d6efd)' : ($isDone ? '#198754' : '#6c757d') }};">
                            {{ $step['label'] }}
                        </div>
                    </div>
                    @if ($num < 4)
                    <div class="step-connector" id="step-connector-{{ $num }}"
                         style="width:3px;height:14px;margin-left:calc(0.5rem + 10px);
                                background:{{ $activeStep > $num ? '#198754' : '#dee2e6' }};"></div>
                    @endif
NEW;

apply_patch($file, $old1, $new1, 'Tracker rows: add IDs/data-step + slide-out transform on active row');

// ── 2. switchStep() must re-render the tracker on every click, not just
//       swap panels. Add updateStepTracker(n) and call it. ───────────────

$old2 = <<<'OLD'
function switchStep(n) {
    if (n > currentStep) return; // can't jump ahead
    document.querySelectorAll('.step-panel').forEach(p => p.classList.add('d-none'));
    document.getElementById('panel-' + n)?.classList.remove('d-none');
    // Keep the sidebar's Overview-vs-Qualification-Checking button slot in
    // sync with whichever panel is actually visible (see the two
    // .advance-slot divs in the sidebar above -- only relevant while
    // status is "open", harmless no-op otherwise since none exist).
    document.querySelectorAll('.advance-slot').forEach(el => {
        el.classList.toggle('d-none', el.dataset.forStep !== String(n));
    });
}
OLD;

$new2 = <<<'NEW'
function updateStepTracker(n) {
    // Re-render the sidebar tracker to match whichever step is now active.
    // Previously this DOM was only ever rendered once server-side from
    // $activeStep at page load, so clicking between steps (e.g. Overview
    // <-> Qualification Checking, which share status "open") changed the
    // panel but left the tracker frozen -- Qualification Checking never
    // appeared highlighted/green/active.
    document.querySelectorAll('.step-row').forEach(row => {
        const step     = parseInt(row.dataset.step, 10);
        const isActive = step === n;
        const isDone   = n > step;

        row.classList.toggle('bg-primary', isActive);
        row.classList.toggle('bg-opacity-10', isActive);
        row.style.borderLeft = '3px solid ' + (isActive ? 'var(--hr-primary,#0d6efd)' : (isDone ? '#198754' : '#dee2e6'));
        row.style.transform  = isActive ? 'translateX(6px)' : 'translateX(0)';

        const circle = document.getElementById('step-circle-' + step);
        if (circle) {
            circle.style.background = isDone ? '#198754' : (isActive ? 'var(--hr-primary,#0d6efd)' : '#dee2e6');
        }

        const inner = document.getElementById('step-circle-inner-' + step);
        if (inner) {
            inner.innerHTML = isDone
                ? '<i class="bi bi-check text-white" style="font-size:0.7rem;"></i>'
                : '<span style="font-size:0.6rem;font-weight:600;color:' + (isActive ? '#fff' : '#6c757d') + ';">' + step + '</span>';
        }

        const label = document.getElementById('step-label-' + step);
        if (label) {
            label.style.color = isActive ? 'var(--hr-primary,#0d6efd)' : (isDone ? '#198754' : '#6c757d');
        }
    });

    document.querySelectorAll('.step-connector').forEach(conn => {
        const step = parseInt(conn.id.replace('step-connector-', ''), 10);
        conn.style.background = n > step ? '#198754' : '#dee2e6';
    });
}

function switchStep(n) {
    if (n > currentStep) return; // can't jump ahead
    document.querySelectorAll('.step-panel').forEach(p => p.classList.add('d-none'));
    document.getElementById('panel-' + n)?.classList.remove('d-none');
    // Keep the sidebar's Overview-vs-Qualification-Checking button slot in
    // sync with whichever panel is actually visible (see the two
    // .advance-slot divs in the sidebar above -- only relevant while
    // status is "open", harmless no-op otherwise since none exist).
    document.querySelectorAll('.advance-slot').forEach(el => {
        el.classList.toggle('d-none', el.dataset.forStep !== String(n));
    });
    updateStepTracker(n);
}
NEW;

apply_patch($file, $old2, $new2, 'switchStep(): re-render tracker via updateStepTracker(n) on every click');

echo "\nDone. Reload the pipeline page and click between Overview and Qualification Checking\n";
echo "to confirm the tracker highlight + slide-out now follow the active step.\n";
