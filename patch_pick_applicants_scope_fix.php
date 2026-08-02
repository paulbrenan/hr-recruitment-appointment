<?php
/**
 * patch_pick_applicants_scope_fix.php
 *
 * THE ACTUAL BUG, finally found by syntax-checking and reading the file
 * directly rather than guessing further: the original
 * patch_schedule_pick_applicants.php inserted its "Pick applicants
 * modal" IIFE using the search string
 *   "const panelistsList = document.getElementById('si-panelists-list');"
 * assuming that was top-level page-load code. It's actually the first
 * line INSIDE `function showScheduleInfo(el) { ... }` -- a function that
 * only runs when someone clicks an existing schedule's Type badge to
 * view its details.
 *
 * Net effect: on a normal page load, the IIFE that wires up
 * "Pick applicants" / quick-select / "Use selected" never executes at
 * all -- pickBtn.addEventListener(...) is simply never called. Clicking
 * the button does nothing because nothing is listening. This is why the
 * button "just fills" (default browser :active style) with no console
 * error -- there was nothing broken to error about, the code just never
 * ran. The earlier "Blocked aria-hidden" warnings you saw were a
 * red herring: they only appeared because the code had accidentally
 * already run once, from a previous click on a schedule's Type badge
 * during testing.
 *
 * Fix: cut the misplaced IIFE out of showScheduleInfo() and move it to
 * top-level scope, right after that function's closing brace -- the
 * same place its sibling block (the Step 5 SG/step live-preview IIFE)
 * already lives, which runs correctly on every page load.
 *
 * Usage: php patch_pick_applicants_scope_fix.php
 */

function apply_patch(string $file, string $search, string $replace, string $label): void
{
    if (!file_exists($file)) {
        echo "[ABORT] File not found: {$file}\n";
        exit(1);
    }

    $contents = file_get_contents($file);

    if (strpos($contents, $search) === false) {
        echo "[ABORT] {$label}: expected text not found in {$file}. File may have drifted — aborting without changes.\n";
        exit(1);
    }

    if (substr_count($contents, $search) > 1) {
        echo "[ABORT] {$label}: expected text is not unique in {$file}. Aborting to avoid ambiguous edit.\n";
        exit(1);
    }

    $backup = $file . '.bak';
    if (!file_exists($backup)) {
        copy($file, $backup);
    }

    $new_contents = str_replace($search, $replace, $contents);
    file_put_contents($file, $new_contents);

    echo "[OK] {$label} applied to {$file}\n";
}

// Adjust this if the live path differs from the Laravel default.
$view = __DIR__ . '/resources/views/job-postings/show.blade.php';

$pickApplicantsBlock = <<<'JS'
    // ── Pick applicants modal (New Schedule -> subset of up to 150) ────────
    (function () {
        const MAX_PICK = 150;
        const newScheduleModalEl = document.getElementById('newScheduleModal');
        const pickModalEl = document.getElementById('pickApplicantsModal');
        if (!newScheduleModalEl || !pickModalEl) return;

        const newScheduleModal = bootstrap.Modal.getOrCreateInstance(newScheduleModalEl);
        const pickModal = bootstrap.Modal.getOrCreateInstance(pickModalEl);

        const pickBtn = document.getElementById('pickApplicantsBtn');
        const checkboxes = Array.from(pickModalEl.querySelectorAll('.pick-applicant-checkbox'));
        const countLabel = document.getElementById('pickCountLabel');
        const summarySpan = document.getElementById('pickApplicantsSummary');
        const infoBanner = document.getElementById('newScheduleInfoBanner');
        const hiddenInputsContainer = document.getElementById('newScheduleApplicantIdInputs');

        function checkedCount() {
            return checkboxes.filter(function (cb) { return cb.checked; }).length;
        }

        function updateCountLabel() {
            countLabel.textContent = checkedCount();
        }

        function enforceCap(justChecked) {
            if (checkedCount() > MAX_PICK) {
                justChecked.checked = false;
                alert('You can select at most ' + MAX_PICK + ' applicants at a time.');
            }
            updateCountLabel();
        }

        checkboxes.forEach(function (cb) {
            cb.addEventListener('change', function () {
                if (cb.checked) enforceCap(cb);
                else updateCountLabel();
            });
        });

        function selectFirstN(n) {
            checkboxes.forEach(function (cb, i) {
                cb.checked = i < n;
            });
            updateCountLabel();
        }

        const select100Btn = document.getElementById('pickSelect100');
        if (select100Btn) select100Btn.addEventListener('click', function () { selectFirstN(100); });

        const select150Btn = document.getElementById('pickSelect150');
        if (select150Btn) select150Btn.addEventListener('click', function () { selectFirstN(150); });

        const selectNInput = document.getElementById('pickSelectN');
        const selectNBtn = document.getElementById('pickSelectNBtn');
        if (selectNBtn && selectNInput) {
            selectNBtn.addEventListener('click', function () {
                const n = Math.max(0, Math.min(MAX_PICK, parseInt(selectNInput.value, 10) || 0));
                selectFirstN(n);
            });
        }

        const clearBtn = document.getElementById('pickClearBtn');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                checkboxes.forEach(function (cb) { cb.checked = false; });
                updateCountLabel();
            });
        }

        // Open: hide New Schedule, then show Pick Applicants once the
        // first one has fully hidden (avoids Bootstrap backdrop stacking
        // issues from having two modals open at once).
        if (pickBtn) {
            pickBtn.addEventListener('click', function () {
                newScheduleModalEl.addEventListener('hidden.bs.modal', function handler() {
                    newScheduleModalEl.removeEventListener('hidden.bs.modal', handler);
                    pickModal.show();
                });
                newScheduleModal.hide();
            });
        }

        // Confirm: write hidden application_ids[] inputs into the New
        // Schedule form, update its summary/banner text, then hand back.
        const useSelectedBtn = document.getElementById('pickUseSelectedBtn');
        if (useSelectedBtn) {
            useSelectedBtn.addEventListener('click', function () {
                const selected = checkboxes.filter(function (cb) { return cb.checked; });

                hiddenInputsContainer.innerHTML = '';
                selected.forEach(function (cb) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'application_ids[]';
                    input.value = cb.value;
                    hiddenInputsContainer.appendChild(input);
                });

                if (selected.length > 0) {
                    summarySpan.textContent = selected.length + ' applicant(s) picked';
                    infoBanner.innerHTML = '<i class="bi bi-info-circle me-1"></i> This will schedule the <strong>' + selected.length + ' picked applicant(s)</strong> only.';
                } else {
                    summarySpan.textContent = 'All qualified applicants (default)';
                    infoBanner.innerHTML = '<i class="bi bi-info-circle me-1"></i> This will schedule <strong>all qualified applicants</strong> on this posting at once.';
                }

                pickModalEl.addEventListener('hidden.bs.modal', function handler() {
                    pickModalEl.removeEventListener('hidden.bs.modal', handler);
                    newScheduleModal.show();
                });
                pickModal.hide();
            });
        }
    })();

JS;

$contents = file_get_contents($view);
if ($contents === false) {
    echo "[ABORT] File not found: {$view}\n";
    exit(1);
}

if (strpos($contents, $pickApplicantsBlock) === false) {
    echo "[ABORT] Could not find the Pick Applicants IIFE in its expected (misplaced) form. File may have drifted -- aborting without changes. Please re-upload the current file.\n";
    exit(1);
}

if (substr_count($contents, $pickApplicantsBlock) > 1) {
    echo "[ABORT] Found more than one copy of the Pick Applicants IIFE -- aborting to avoid ambiguous edit. The file likely has this block duplicated from a previous patch run; please clean that up manually first.\n";
    exit(1);
}

// 1. Remove it from inside showScheduleInfo().
apply_patch($view, $pickApplicantsBlock, '', 'Remove Pick Applicants IIFE from inside showScheduleInfo()');

// 2. Re-insert it at top level, right after showScheduleInfo()'s closing
//    brace -- same spot its sibling top-level IIFE (Step 5 SG/step
//    preview) already lives just above it.
apply_patch(
    $view,
    "    new bootstrap.Modal(document.getElementById('scheduleInfoModal')).show();\n}\n\nfunction advanceStep() {",
    "    new bootstrap.Modal(document.getElementById('scheduleInfoModal')).show();\n}\n\n" . $pickApplicantsBlock . "\nfunction advanceStep() {",
    'Re-insert Pick Applicants IIFE at top level, runs on page load'
);

echo "\nDone. Hard refresh and verify:\n";
echo " - Click 'New schedule', then 'Pick applicants' WITHOUT ever having opened a schedule's Type-badge info popup first.\n";
echo " - The Pick Applicants modal should now open immediately -- this was the actual bug, nothing to do with focus/aria-hidden.\n";
echo " - Select 100 / Select 150 / custom N / Use selected should all work as expected.\n";
