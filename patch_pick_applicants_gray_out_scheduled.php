<?php
/**
 * patch_pick_applicants_gray_out_scheduled.php
 *
 * [1] Already-scheduled applicants stay visible in the Pick Applicants
 *     list (with the "Already scheduled: Yes" indicator, unchanged),
 *     but their row is now grayed out and their checkbox disabled --
 *     they can't be picked/checked, just seen for reference.
 *
 * [2] Guards the JS against re-including a disabled (already-scheduled)
 *     checkbox: selectFirstN() (used by Select 100/150/custom N) now
 *     skips disabled checkboxes when assigning .checked, and the
 *     "Use selected" handler's final filter explicitly excludes
 *     disabled ones too. Without this, JS can programmatically check a
 *     disabled checkbox even though a user can't click it -- silently
 *     defeating the whole point of [1].
 *
 * Usage: php patch_pick_applicants_gray_out_scheduled.php
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

// ─── 1. Gray out + disable already-scheduled rows ─────────────────────────

apply_patch(
    $view,
    '                            @forelse ($posting->applications()->where(\'qualification_result\', \'qualified\')->with([\'candidate\', \'interviewSchedules\'])->get() as $qa)
                            <tr>
                                <td>
                                    <input type="checkbox" class="form-check-input pick-applicant-checkbox"
                                           value="{{ $qa->id }}" data-name="{{ $qa->candidate->full_name ?? (\'Applicant #\' . $qa->id) }}">
                                </td>
                                <td class="small">{{ $qa->candidate->full_name ?? (\'Applicant #\' . $qa->id) }}</td>
                                <td class="small text-muted">
                                    @if ($qa->interviewSchedules->where(\'status\', \'!=\', \'cancelled\')->isNotEmpty())
                                        <i class="bi bi-check2-circle text-success"></i> Yes
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                            @empty',
    '                            @forelse ($posting->applications()->where(\'qualification_result\', \'qualified\')->with([\'candidate\', \'interviewSchedules\'])->get() as $qa)
                            @php
                                $qaAlreadyScheduled = $qa->interviewSchedules->where(\'status\', \'!=\', \'cancelled\')->isNotEmpty();
                            @endphp
                            <tr class="{{ $qaAlreadyScheduled ? \'text-muted\' : \'\' }}" style="{{ $qaAlreadyScheduled ? \'opacity:.55;background:#f8f9fa;\' : \'\' }}">
                                <td>
                                    <input type="checkbox" class="form-check-input pick-applicant-checkbox"
                                           value="{{ $qa->id }}" data-name="{{ $qa->candidate->full_name ?? (\'Applicant #\' . $qa->id) }}"
                                           {{ $qaAlreadyScheduled ? \'disabled title="Already scheduled"\' : \'\' }}>
                                </td>
                                <td class="small">{{ $qa->candidate->full_name ?? (\'Applicant #\' . $qa->id) }}</td>
                                <td class="small text-muted">
                                    @if ($qaAlreadyScheduled)
                                        <i class="bi bi-check2-circle text-success"></i> Yes
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                            @empty',
    'Pick Applicants: already-scheduled rows grayed out, checkbox disabled'
);

// ─── 2. Guard selectFirstN() against checking disabled boxes ───────────────

apply_patch(
    $view,
    "        function selectFirstN(n) {
            checkboxes.forEach(function (cb, i) {
                cb.checked = i < n;
            });
            updateCountLabel();
        }",
    "        function selectFirstN(n) {
            const selectable = checkboxes.filter(function (cb) { return !cb.disabled; });
            selectable.forEach(function (cb, i) {
                cb.checked = i < n;
            });
            updateCountLabel();
        }",
    'selectFirstN(): only assigns .checked among non-disabled (unscheduled) checkboxes'
);

// ─── 3. Guard "Use selected" filter against disabled checkboxes ───────────

apply_patch(
    $view,
    "            useSelectedBtn.addEventListener('click', function () {
                const selected = checkboxes.filter(function (cb) { return cb.checked; });",
    "            useSelectedBtn.addEventListener('click', function () {
                const selected = checkboxes.filter(function (cb) { return cb.checked && !cb.disabled; });",
    '"Use selected": final filter explicitly excludes disabled checkboxes too'
);

// ─── 4. Guard the Clear button too, for consistency ────────────────────────

apply_patch(
    $view,
    "        const clearBtn = document.getElementById('pickClearBtn');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                checkboxes.forEach(function (cb) { cb.checked = false; });
                updateCountLabel();
            });
        }",
    "        const clearBtn = document.getElementById('pickClearBtn');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                checkboxes.forEach(function (cb) { if (!cb.disabled) cb.checked = false; });
                updateCountLabel();
            });
        }",
    'Clear button: only touches non-disabled checkboxes (harmless either way, kept consistent)'
);

echo "\nDone. Verify:\n";
echo " - Open the Pick Applicants modal -- already-scheduled applicants still show ('Already scheduled: Yes'), row dimmed, checkbox disabled and unclickable.\n";
echo " - Select 100 / 150 / custom N only ever check unscheduled applicants, even though disabled rows sit among them in the list.\n";
echo " - 'Use selected' never includes an already-scheduled applicant even if something else in the page tried to force-check their box.\n";
