<?php

/**
 * patch_import_locations.php
 *
 * WHAT THIS DOES:
 *   1. Patches review.blade.php:
 *      - Replaces the single place_of_assignment text input with a
 *        multi-row location table (school dropdown only, no vacancy column)
 *      - Adds +/- row buttons; duplicate rows = multiple vacancies for that school
 *      - Keeps the existing top-level Vacancies number field untouched
 *      - Pre-populates rows from scanned row count (one row per scanned row)
 *
 *   2. Patches JobPostingImportController@confirm:
 *      - Reads location_place[][] instead of rows[i][place_of_assignment]
 *      - Counts duplicate place entries to derive per-location vacancy count
 *      - Total vacancy is still taken from the top-level vacancies field HR edited
 *
 * HOW TO RUN:
 *   php patch_import_locations.php    (from project root)
 *   No migration needed.
 *
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
    if ($count === 0) { echo "\n❌ PATCH ABORTED — content not found in:\n  $path\nLabel: $label\n"; exit(1); }
    if ($count > 1)   { echo "\n❌ PATCH ABORTED — found $count times in:\n  $path\nLabel: $label\n"; exit(1); }
    backup($path);
    file_put_contents($path, str_replace($old, $new, $content));
    echo "  [ok ] $label\n";
}

echo "\n=== patch_import_locations.php ===\n\n";

// ─── 1. review.blade.php ───────────────────────────────────────────────────

echo "[1] Patching review.blade.php...\n";

$reviewPath = ROOT . '/resources/views/job-postings/import/review.blade.php';

// Replace single place_of_assignment input with multi-row location table
$oldPlace = <<<'BLADE'
                <div class="col-12">
                    <label class="form-label small text-muted mb-1">Place of assignment</label>
                    <input type="text" class="form-control form-control-sm" name="rows[{{ $i }}][place_of_assignment]" value="" placeholder="Enter the actual place of assignment (not reliably OCR'd — please type this in)">
                </div>
BLADE;

$newPlace = <<<'BLADE'
                <div class="col-12">
                    <label class="form-label small text-muted mb-1">
                        Places of assignment
                        <span class="text-muted fw-normal" style="font-size: 0.72rem;">
                            — one row per vacancy slot. Add the same school twice for 2 vacancies there.
                        </span>
                    </label>
                    <div class="border rounded p-2" style="background: #fafafa;">
                        <table class="table table-sm mb-2 align-middle" style="font-size: 0.82rem;">
                            <thead>
                                <tr>
                                    <th>Place of assignment</th>
                                    <th style="width: 40px;"></th>
                                </tr>
                            </thead>
                            <tbody class="location-tbody" data-group="{{ $i }}">
                                @for ($v = 0; $v < $group['rows']->count(); $v++)
                                <tr class="location-import-row">
                                    <td>
                                        <div class="position-relative location-import-wrapper">
                                            <input
                                                type="text"
                                                class="form-control form-control-sm location-import-input"
                                                name="rows[{{ $i }}][location_place][]"
                                                autocomplete="off"
                                                placeholder="Search or type a school..."
                                            >
                                            <div class="list-group position-absolute w-100 shadow-sm location-import-results"
                                                 style="z-index:1050;max-height:180px;overflow-y:auto;display:none;top:100%;"></div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-import-location"
                                                title="Remove row"><i class="bi bi-x-lg"></i></button>
                                    </td>
                                </tr>
                                @endfor
                            </tbody>
                        </table>
                        <button type="button" class="btn btn-sm btn-outline-secondary add-import-location" data-group="{{ $i }}">
                            <i class="bi bi-plus-lg me-1"></i> Add row
                        </button>
                    </div>
                </div>
BLADE;

apply_patch($reviewPath, $oldPlace, $newPlace, 'review.blade.php: multi-row location table');

// Add school search JS + location row JS inside @push('scripts')
$oldScript = <<<'BLADE'
<script>
    // ── Floating confirm bar ──────────────────────────────────────────
BLADE;

$newScript = <<<'BLADE'
<script>
    // ── School search for import location rows ────────────────────────
    @php
        $importSchoolOptions = array_merge(config('schools.schools', []), config('schools.sdo_units', []));
    @endphp
    const importSchools = @json($importSchoolOptions);

    function initImportLocationRow(row) {
        const input      = row.querySelector('.location-import-input');
        const resultsBox = row.querySelector('.location-import-results');
        if (!input || input._importInited) return;
        input._importInited = true;

        function render(filter) {
            const q = filter.trim().toLowerCase();
            const matches = q === ''
                ? importSchools
                : importSchools.filter(s => s.toLowerCase().includes(q));
            resultsBox.innerHTML = '';
            if (!matches.length) { resultsBox.style.display = 'none'; return; }
            matches.slice(0, 50).forEach(function (school) {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action small py-1';
                item.textContent = school;
                item.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    input.value = school;
                    resultsBox.style.display = 'none';
                });
                resultsBox.appendChild(item);
            });
            resultsBox.style.display = 'block';
        }

        input.addEventListener('input',  () => render(input.value));
        input.addEventListener('focus',  () => render(input.value));
        input.addEventListener('blur',   () => setTimeout(() => { resultsBox.style.display = 'none'; }, 200));
    }

    // Init all existing rows on page load
    document.querySelectorAll('.location-import-row').forEach(initImportLocationRow);

    // Remove row (keep at least 1)
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.remove-import-location');
        if (!btn) return;
        const tbody = btn.closest('tbody');
        if (tbody.querySelectorAll('.location-import-row').length <= 1) {
            // just clear it
            const input = btn.closest('tr').querySelector('.location-import-input');
            if (input) input.value = '';
            return;
        }
        btn.closest('tr').remove();
    });

    // Add row
    document.querySelectorAll('.add-import-location').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const group  = this.dataset.group;
            const tbody  = document.querySelector('.location-tbody[data-group="' + group + '"]');
            const tmpl   = tbody.querySelector('.location-import-row');
            const newRow = tmpl.cloneNode(true);
            const input  = newRow.querySelector('.location-import-input');
            const results = newRow.querySelector('.location-import-results');
            input.value = '';
            input._importInited = false;
            results.innerHTML = '';
            results.style.display = 'none';
            tbody.appendChild(newRow);
            initImportLocationRow(newRow);
            input.focus();
        });
    });

    // ── Floating confirm bar ──────────────────────────────────────────
BLADE;

apply_patch($reviewPath, $oldScript, $newScript, 'review.blade.php: school search + row management JS');

// ─── 2. JobPostingImportController@confirm ─────────────────────────────────

echo "\n[2] Patching JobPostingImportController@confirm...\n";

$controllerPath = ROOT . '/app/Http/Controllers/JobPostingImportController.php';

// Update validation to expect location_place[] instead of locations[].*.place
$oldValidation = <<<'PHP'
        $validated = $request->validate([
            'selected' => ['nullable', 'array'],
            'selected.*' => ['integer'],
            'rows' => ['required', 'array'],
            'rows.*.locations' => ['nullable', 'array'],
            'rows.*.locations.*.place' => ['nullable', 'string', 'max:255'],
            'rows.*.locations.*.vacancies' => ['nullable', 'integer', 'min:1'],
        ]);
PHP;

$newValidation = <<<'PHP'
        $validated = $request->validate([
            'selected'                   => ['nullable', 'array'],
            'selected.*'                 => ['integer'],
            'rows'                       => ['required', 'array'],
            'rows.*.title'               => ['nullable', 'string', 'max:255'],
            'rows.*.salary_grade'        => ['nullable', 'string', 'max:50'],
            'rows.*.vacancies'           => ['nullable', 'integer', 'min:1'],
            'rows.*.location_place'      => ['nullable', 'array'],
            'rows.*.location_place.*'    => ['nullable', 'string', 'max:500'],
        ]);
PHP;

apply_patch($controllerPath, $oldValidation, $newValidation, 'ImportController: validation — location_place[]');

// Replace the location-building block inside the foreach loop
$oldLocationBlock = <<<'PHP'
            // Clean up the locations the user typed in: drop any blank rows
            // (e.g. a trailing empty row left over from the add-on-Enter UI).
            $locationRows = [];
            foreach (($rowData['locations'] ?? []) as $loc) {
                $place = trim($loc['place'] ?? '');
                if ($place === '') {
                    continue;
                }
                $locationRows[] = [
                    'place_of_assignment' => $place,
                    'vacancies' => max(1, (int) ($loc['vacancies'] ?? 1)),
                ];
            }
PHP;

$newLocationBlock = <<<'PHP'
            // Build location rows from the flat location_place[] array.
            // Duplicate entries for the same school name = multiple vacancies
            // for that school. Blank rows are dropped.
            $placeCounts = [];
            foreach (($rowData['location_place'] ?? []) as $place) {
                $place = trim($place);
                if ($place === '') continue;
                $placeCounts[$place] = ($placeCounts[$place] ?? 0) + 1;
            }

            $locationRows = [];
            foreach ($placeCounts as $place => $count) {
                $locationRows[] = [
                    'place_of_assignment' => $place,
                    'vacancies'           => $count,
                ];
            }

            // Use the top-level vacancies field HR edited (or fall back to
            // total from location rows, or 1 if nothing was entered).
            $hrVacancies = max(1, (int) ($rowData['vacancies'] ?? 0));
PHP;

apply_patch($controllerPath, $oldLocationBlock, $newLocationBlock, 'ImportController: build locationRows from location_place[]');

// Fix the totalVacancies line to use $hrVacancies instead of array_sum
$oldTotalVac = <<<'PHP'
            $firstPlace = $locationRows[0]['place_of_assignment'] ?? null;
            $totalVacancies = array_sum(array_column($locationRows, 'vacancies')) ?: 1;
PHP;

$newTotalVac = <<<'PHP'
            $firstPlace     = $locationRows[0]['place_of_assignment'] ?? null;
            $totalVacancies = $hrVacancies; // HR's edited top-level vacancies field
PHP;

apply_patch($controllerPath, $oldTotalVac, $newTotalVac, 'ImportController: totalVacancies from HR input');

echo <<<TEXT

✅ Done. No migration needed.

HOW IT WORKS ON THE REVIEW SCREEN:
  - Each scanned row pre-populates one blank location input
    (e.g. 89 rows scanned = 89 pre-populated blank rows to fill in)
  - HR types/picks a school from the dropdown for each row
  - Same school entered twice = 2 vacancies for that school on confirm
  - "Add row" button adds more; X removes (keeps at least 1)
  - Top-level Vacancies field stays editable and is used as the total on import

ON CONFIRM:
  - Duplicate place entries are collapsed: School A x3 → vacancies: 3
  - One job_posting_locations row per unique school
  - Legacy place_of_assignment = first school; vacancies = HR's top-level field

DELETE this script after running.

TEXT;
