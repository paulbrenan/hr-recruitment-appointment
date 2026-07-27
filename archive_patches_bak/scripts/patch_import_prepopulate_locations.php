<?php

/**
 * patch_import_prepopulate_locations.php
 *
 * WHAT THIS DOES:
 *   The VacancyTableParser already extracts school names correctly from
 *   the PDF. The problem is that PositionBlockExpander formats them into
 *   a flat place_of_assignment string and the review form treats that
 *   as unreadable OCR. This patch:
 *
 *   1. Patches PositionBlockExpander to also pass the raw parsed school
 *      name as 'place_of_assignment_parsed' so the review form can use it
 *
 *   2. Patches JobPostingImportController@review to group parsed school
 *      names per position group and pass them to the view
 *
 *   3. Patches review.blade.php to:
 *      - Pre-populate each location row with the parsed school name
 *      - Remove the "not reliably OCR'd" placeholder text
 *      - Mark unreadable rows with a clear visual indicator
 *      - Keep everything editable via the school search dropdown
 *
 * HOW TO RUN:
 *   php patch_import_prepopulate_locations.php    (from project root)
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

echo "\n=== patch_import_prepopulate_locations.php ===\n\n";

// ─── 1. PositionBlockExpander — pass parsed school name through ────────────

echo "[1] Patching PositionBlockExpander...\n";

$expanderPath = ROOT . '/app/Services/PositionBlockExpander.php';

// In the table-type branch, add place_of_assignment_parsed to each candidate row
$oldTableBranch = <<<'PHP'
            // Table type: one candidate row per school.
            foreach ($placeOfAssignment['schools'] as $schoolRow) {
                // Rows the parser flagged as unrecoverable (OCR never produced
                // a legible number for them, so their school name couldn't be
                // safely reconstructed) still get a candidate row -- with a
                // visible placeholder instead of a blank field -- so the
                // vacancy count on the review screen matches the memo's real
                // total and the reviewer knows exactly which slots need
                // manual entry, rather than those slots silently vanishing.
                $isUnrecoverable = !empty($schoolRow['unrecoverable']);

                $candidates[] = array_merge($shared, [
                    'vacancies' => 1,
                    'place_of_assignment' => $isUnrecoverable
                        ? '[Unreadable in scan - row ' . $schoolRow['number'] . ', needs manual entry]'
                        : $this->formatPlaceOfAssignment($schoolRow),
                    'school_row_number' => $schoolRow['number'],
                    'needs_manual_review' => $isUnrecoverable,
                ]);
            }
PHP;

$newTableBranch = <<<'PHP'
            // Table type: one candidate row per school.
            foreach ($placeOfAssignment['schools'] as $schoolRow) {
                $isUnrecoverable = !empty($schoolRow['unrecoverable']);

                // Pass the raw parsed school name separately so the review
                // form can pre-populate the location input with it.
                // 'place_of_assignment' keeps the full formatted string
                // (with adopted school + municipality) for backward compat;
                // 'place_of_assignment_parsed' is just the Mother School
                // name, suitable for pre-filling the school dropdown.
                $candidates[] = array_merge($shared, [
                    'vacancies' => 1,
                    'place_of_assignment' => $isUnrecoverable
                        ? '[Unreadable - row ' . $schoolRow['number'] . ']'
                        : $this->formatPlaceOfAssignment($schoolRow),
                    'place_of_assignment_parsed' => $isUnrecoverable
                        ? null
                        : ($schoolRow['school'] ?? null),
                    'school_row_number' => $schoolRow['number'],
                    'needs_manual_review' => $isUnrecoverable,
                ]);
            }
PHP;

apply_patch($expanderPath, $oldTableBranch, $newTableBranch, 'PositionBlockExpander: pass place_of_assignment_parsed');

// ─── 2. ImportController@review — group parsed locations per group ─────────

echo "\n[2] Patching JobPostingImportController@review...\n";

$controllerPath = ROOT . '/app/Http/Controllers/JobPostingImportController.php';

$oldGrouped = <<<'PHP'
        $grouped = collect($batch->candidates)
            ->groupBy('group_key')
            ->map(function ($rows) {
                return [
                    'label' => $rows->first()['group_label'] ?? 'Untitled position',
                    'rows' => $rows->values(),
                ];
            });
PHP;

$newGrouped = <<<'PHP'
        $grouped = collect($batch->candidates)
            ->groupBy('group_key')
            ->map(function ($rows) {
                // Collect the parsed school names from all rows in this group.
                // These pre-populate the location inputs on the review form.
                // Rows with unrecoverable OCR have null here — the form shows
                // an empty editable input for those.
                $parsedLocations = $rows->map(function ($row) {
                    return [
                        'school'           => $row['place_of_assignment_parsed'] ?? null,
                        'unrecoverable'    => !empty($row['needs_manual_review']),
                        'row_number'       => $row['school_row_number'] ?? null,
                    ];
                })->values()->toArray();

                return [
                    'label'           => $rows->first()['group_label'] ?? 'Untitled position',
                    'rows'            => $rows->values(),
                    'parsed_locations' => $parsedLocations,
                ];
            });
PHP;

apply_patch($controllerPath, $oldGrouped, $newGrouped, 'ImportController@review: build parsed_locations per group');

// ─── 3. review.blade.php — pre-populate location rows ─────────────────────

echo "\n[3] Patching review.blade.php...\n";

$reviewPath = ROOT . '/resources/views/job-postings/import/review.blade.php';

// Replace the location table section
$oldLocationTable = <<<'BLADE'
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

$newLocationTable = <<<'BLADE'
                <div class="col-12">
                    <label class="form-label small text-muted mb-1">
                        Places of assignment
                        <span class="text-muted fw-normal" style="font-size: 0.72rem;">
                            — pre-filled from PDF. Edit, remove, or add rows as needed. Duplicate rows = more vacancies for that school.
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
                                @foreach ($group['parsed_locations'] as $loc)
                                <tr class="location-import-row">
                                    <td>
                                        <div class="position-relative location-import-wrapper">
                                            <input
                                                type="text"
                                                class="form-control form-control-sm location-import-input {{ $loc['unrecoverable'] ? 'border-warning' : '' }}"
                                                name="rows[{{ $i }}][location_place][]"
                                                autocomplete="off"
                                                placeholder="{{ $loc['unrecoverable'] ? 'Row ' . $loc['row_number'] . ' unreadable — type school name manually' : 'Search or type a school...' }}"
                                                value="{{ $loc['school'] ?? '' }}"
                                            >
                                            <div class="list-group position-absolute w-100 shadow-sm location-import-results"
                                                 style="z-index:1050;max-height:180px;overflow-y:auto;display:none;top:100%;"></div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        @if ($loc['unrecoverable'])
                                            <span title="This row was unreadable in the PDF" style="font-size:0.8rem;">⚠️</span>
                                        @endif
                                        <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-import-location"
                                                title="Remove row"><i class="bi bi-x-lg"></i></button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <button type="button" class="btn btn-sm btn-outline-secondary add-import-location" data-group="{{ $i }}">
                            <i class="bi bi-plus-lg me-1"></i> Add row
                        </button>
                    </div>
                </div>
BLADE;

apply_patch($reviewPath, $oldLocationTable, $newLocationTable, 'review.blade.php: pre-populate location rows from parsed PDF data');

// Also update the info text at the top to reflect that locations are now pre-filled
apply_patch(
    $reviewPath,
    "            Review and edit the fields below — vacancies defaults to the number of rows scanned for that position,
            and place of assignment is left blank for you to fill in manually since OCR placement isn't reliable.
            Check the ones you want to import, then confirm.",
    "            Review and edit the fields below — vacancies defaults to the number of rows scanned for that position,
            and places of assignment are pre-filled from the PDF table (editable).
            Rows marked ⚠️ were unreadable and need manual entry.
            Check the ones you want to import, then confirm.",
    'review.blade.php: update info text'
);

echo <<<TEXT

✅ Done. No migration needed.

WHAT CHANGED:
  - PositionBlockExpander now passes 'place_of_assignment_parsed' (just the
    Mother School name) alongside the existing formatted string
  - The review form now pre-populates each location row with the parsed
    school name from the PDF — fully editable via the school search dropdown
  - Unreadable rows show a ⚠️ icon, yellow border, and a placeholder asking
    HR to type the school name manually
  - The "not reliably OCR'd — please type this in" placeholder is gone

HOW IT LOOKS FOR THIS PDF (SGOD-2026-DM-0079):
  Administrative Officer II (SG-11) — 89 location rows pre-filled:
    Row 1:  Amuyong Elementary School   ← editable input, pre-filled
    Row 2:  Marahan ES
    Row 3:  Matagbak ES
    ... (all 89 schools from the PDF table)

  Project Development Officer I (SG-11) — 41 location rows pre-filled:
    Row 1:  Amadeo ES
    Row 2:  Bailen ES
    ... (all 41 schools)

  Any row the parser couldn't read shows:
    [empty input, yellow border, ⚠️, placeholder: "Row N unreadable — type manually"]

DELETE this script after running.

TEXT;
