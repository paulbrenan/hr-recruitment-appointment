<?php
/**
 * fix_remove_panelist_available_checkbox.php
 *
 * Assigning a panelist to a posting always meant HR wanted them
 * available -- the separate "Available" checkbox was redundant busywork
 * (you'd check a panelist, then have to check ANOTHER box right next to
 * it). This removes the Available checkbox entirely: checking a
 * panelist now automatically marks them available on save.
 *
 *   1. form.blade.php: removes the Available checkbox + label from each
 *      panelist row, and the JS that toggled/disabled it.
 *   2. JobPostingController::syncPanelists(): every assigned panelist_id
 *      is now synced with is_available => true, unconditionally --
 *      panelist_available[] is no longer read at all.
 *
 * NOTE: is_available stays as a real pivot column (used elsewhere, e.g.
 * panelistsForPosting() for the scheduling checklist) -- it's just always
 * true now instead of independently toggleable. If you ever want a
 * panelist temporarily marked unavailable without unassigning them, say
 * so and I'll add that back as a separate, simpler toggle.
 *
 * HOW TO RUN:
 *   php fix_remove_panelist_available_checkbox.php   (from project root)
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

echo "\n=== fix_remove_panelist_available_checkbox.php ===\n\n";

$formPath       = ROOT . '/resources/views/job-postings/form.blade.php';
$controllerPath = ROOT . '/app/Http/Controllers/JobPostingController.php';

// ─── 1. Remove Available checkbox markup ────────────────────────────────

echo "[1] Removing Available checkbox from panelist row markup...\n";

apply_patch(
    $formPath,
    '                                    @php
                                        $assigned  = isset($assignedPanelists[$panelist->id]);
                                        $available = $assigned && $assignedPanelists[$panelist->id]->pivot->is_available;
                                    @endphp',
    '                                    @php
                                        $assigned  = isset($assignedPanelists[$panelist->id]);
                                    @endphp',
    'form.blade.php: drop unused $available variable'
);

apply_patch(
    $formPath,
    '                                        {{-- Available checkbox — only active when assigned --}}
                                        <div class="d-flex align-items-center gap-1">
                                            <input
                                                type="checkbox"
                                                class="form-check-input panelist-avail-cb mt-0"
                                                name="panelist_available[]"
                                                value="{{ $panelist->id }}"
                                                id="avail{{ $panelist->id }}"
                                                {{ $available ? \'checked\' : \'\' }}
                                                {{ !$assigned ? \'disabled\' : \'\' }}
                                            >
                                            <label class="form-check-label small text-muted mb-0" for="avail{{ $panelist->id }}">Available</label>
                                        </div>
                                        {{-- Delete button (calls a JS confirm; uses a hidden form) --}}',
    '                                        {{-- Delete button (calls a JS confirm; uses a hidden form) --}}',
    'form.blade.php: remove Available checkbox + label'
);

// ─── 2. Remove the JS that toggled/disabled it ──────────────────────────

echo "\n[2] Removing JS that toggled the Available checkbox...\n";

apply_patch(
    $formPath,
    '    // ── Panelist JS ──────────────────────────────────────────────────────────

    // When assign checkbox is unchecked, disable the Available checkbox too
    document.querySelectorAll(\'.panelist-assign-cb\').forEach(function (cb) {
        cb.addEventListener(\'change\', function () {
            const row      = document.getElementById(\'panelistRow\' + this.value);
            const availCb  = row ? row.querySelector(\'.panelist-avail-cb\') : null;
            if (availCb) {
                availCb.disabled = !this.checked;
                if (!this.checked) availCb.checked = false;
            }
        });
    });

    // Add new panelist input row',
    '    // ── Panelist JS ──────────────────────────────────────────────────────────

    // Add new panelist input row',
    'form.blade.php: remove Available-checkbox toggle JS'
);

// ─── 3. Controller: assigned panelists are always available ────────────

echo "\n[3] Patching syncPanelists() to mark assigned panelists available automatically...\n";

apply_patch(
    $controllerPath,
    '    /**
     * Sync panelist assignments and availability from the form submission.
     * Expects:
     *   panelist_ids[]        — checked panelist IDs to assign
     *   panelist_available[]  — panelist IDs that are marked available
     *   new_panelist_names[]  — names of brand-new panelists to create and assign
     */
    private function syncPanelists(JobPosting $posting, \Illuminate\Http\Request $request): void
    {
        // Create any newly added panelists
        $newNames = array_filter(array_map(\'trim\', $request->input(\'new_panelist_names\', [])));
        foreach ($newNames as $name) {
            if ($name !== \'\') {
                $new = Panelist::create([\'name\' => $name]);
                // Add to assigned list so they get synced below
                $request->merge([
                    \'panelist_ids\' => array_merge($request->input(\'panelist_ids\', []), [$new->id]),
                    \'panelist_available\' => array_merge($request->input(\'panelist_available\', []), [$new->id]),
                ]);
            }
        }

        $assignedIds   = array_map(\'intval\', $request->input(\'panelist_ids\', []));
        $availableIds  = array_map(\'intval\', $request->input(\'panelist_available\', []));

        // Build pivot data: assigned panelists with their availability flag
        $syncData = [];
        foreach ($assignedIds as $panelistId) {
            $syncData[$panelistId] = [\'is_available\' => in_array($panelistId, $availableIds)];
        }

        // sync() removes unassigned, adds new, updates existing
        $posting->panelists()->sync($syncData);
    }',
    '    /**
     * Sync panelist assignments from the form submission. Checking a
     * panelist means HR wants them on this posting\'s panel -- there is no
     * separate "available" toggle; every assigned panelist is marked
     * available automatically.
     * Expects:
     *   panelist_ids[]        — checked panelist IDs to assign
     *   new_panelist_names[]  — names of brand-new panelists to create and assign
     */
    private function syncPanelists(JobPosting $posting, \Illuminate\Http\Request $request): void
    {
        // Create any newly added panelists
        $newNames = array_filter(array_map(\'trim\', $request->input(\'new_panelist_names\', [])));
        foreach ($newNames as $name) {
            if ($name !== \'\') {
                $new = Panelist::create([\'name\' => $name]);
                // Add to assigned list so they get synced below
                $request->merge([
                    \'panelist_ids\' => array_merge($request->input(\'panelist_ids\', []), [$new->id]),
                ]);
            }
        }

        $assignedIds = array_map(\'intval\', $request->input(\'panelist_ids\', []));

        // Build pivot data: every assigned panelist is available, always
        $syncData = [];
        foreach ($assignedIds as $panelistId) {
            $syncData[$panelistId] = [\'is_available\' => true];
        }

        // sync() removes unassigned, adds new, updates existing
        $posting->panelists()->sync($syncData);
    }',
    'JobPostingController::syncPanelists() -- assigned panelists always available, no separate toggle'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - The 'Available' checkbox is gone from the panelist assignment UI.\n";
echo "  - Checking a panelist now assigns them AND marks them available in\n";
echo "    one action -- no second checkbox needed.\n";
echo "  - is_available is still a real pivot column under the hood (used by\n";
echo "    panelistsForPosting() for the scheduling checklist) -- it's just\n";
echo "    always true now for any assigned panelist.\n\n";
echo "DELETE this script after running.\n";
