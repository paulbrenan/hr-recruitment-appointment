<?php

/**
 * fix_all_location_formats.php
 *
 * Fixes place-of-assignment pre-fill for all three PDF formats:
 *
 *   1. TABLE type (SGOD-0079 style) — school names already fixed in expander,
 *      but parsed_locations builder in controller wasn't reading the key correctly
 *
 *   2. SINGLE type inline text (OSDS-0059 style) — "Place of Assignment: School X"
 *      The expander produces one candidate row with place_of_assignment set but
 *      no place_of_assignment_parsed key. Fix: add the key on the single branch.
 *
 *   3. SINGLE type "To be determined" (OSDS-073 style) — one blank editable row.
 *      Already works (blank row), just needs place_of_assignment_parsed = null.
 *
 * HOW TO RUN:
 *   php fix_all_location_formats.php    (from project root)
 *   Then re-upload any PDF to reprocess.
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

function write_file(string $path, string $content, string $label): void {
    backup($path);
    file_put_contents($path, $content);
    echo "  [ok ] $label\n";
}

echo "\n=== fix_all_location_formats.php ===\n\n";

// ─── 1. Rewrite PositionBlockExpander completely (clean, correct) ──────────

echo "[1] Rewriting PositionBlockExpander.php...\n";

$expanderContent = <<<'PHP'
<?php

namespace App\Services;

/**
 * PositionBlockExpander
 *
 * Takes PositionBlockDetector::detect()'s output (one entry per DETECTED
 * POSITION) and expands it into a flat list of candidate rows.
 *
 * place_of_assignment_parsed is the pre-fill value for the review form's
 * location input — just the school/office name, no municipality suffix.
 * It is null for unrecoverable rows and "To be determined" positions.
 *
 * Three place_of_assignment types:
 *   'single'  — inline text ("School X" or "To be determined")
 *               → one candidate row, place_of_assignment_parsed = the text
 *                 (or null for "To be determined")
 *   'table'   — school table from VacancyTableParser
 *               → one candidate row per school row
 */
class PositionBlockExpander
{
    public function expand(array $blocks): array
    {
        $candidates = [];

        foreach ($blocks as $blockIndex => $block) {
            $groupKey = 'block_' . $blockIndex;
            $shared = [
                'title'                    => $block['title'],
                'canonical_title'          => $block['canonical_title'],
                'salary_grade'             => $block['salary_grade'],
                'qualification_education'  => $block['qualification_education'],
                'qualification_training'   => $block['qualification_training'],
                'qualification_experience' => $block['qualification_experience'],
                'qualification_eligibility'=> $block['qualification_eligibility'],
                'duties_responsibilities'  => $block['duties_responsibilities'],
                'group_key'                => $groupKey,
                'group_label'              => $block['title'] . ' (' . $block['salary_grade'] . ')',
            ];

            $placeOfAssignment = $block['place_of_assignment'];

            if ($placeOfAssignment['type'] === 'single') {
                $value = $placeOfAssignment['value'] ?? '';
                $isTbd = stripos(trim($value), 'to be determined') === 0 || trim($value) === '';

                $candidates[] = array_merge($shared, [
                    'vacancies'                  => $block['vacancies'] ?? 1,
                    'place_of_assignment'        => $value,
                    // Pre-fill the location input with the inline text,
                    // unless it's "To be determined" (leave blank for HR to fill).
                    'place_of_assignment_parsed' => $isTbd ? null : $value,
                    'school_row_number'          => null,
                    'needs_manual_review'        => false,
                ]);
                continue;
            }

            // Table type: one candidate row per school.
            foreach ($placeOfAssignment['schools'] as $schoolRow) {
                $isUnrecoverable = !empty($schoolRow['unrecoverable']);

                $candidates[] = array_merge($shared, [
                    'vacancies'                  => 1,
                    'place_of_assignment'        => $isUnrecoverable
                        ? '[Unreadable - row ' . $schoolRow['number'] . ']'
                        : $this->formatPlaceOfAssignment($schoolRow),
                    'place_of_assignment_parsed' => $isUnrecoverable
                        ? null
                        : ($schoolRow['school'] ?? null),
                    'school_row_number'          => $schoolRow['number'],
                    'needs_manual_review'        => $isUnrecoverable,
                ]);
            }
        }

        return $candidates;
    }

    private function formatPlaceOfAssignment(array $schoolRow): string
    {
        $school      = $schoolRow['school'] ?? '';
        $adopted     = $schoolRow['adopted'] ?? null;
        $municipality = $schoolRow['municipality'] ?? null;

        $name = $school;
        if (!empty($adopted)) {
            $name .= ', ' . $adopted;
        }
        if (!empty($municipality)) {
            $name .= ' (' . $municipality . ')';
        }

        return trim($name);
    }
}
PHP;

write_file(ROOT . '/app/Services/PositionBlockExpander.php', $expanderContent, 'PositionBlockExpander.php rewritten');

// ─── 2. Fix ImportController@review parsed_locations builder ─────────────

echo "\n[2] Patching JobPostingImportController@review...\n";

$controllerPath = ROOT . '/app/Http/Controllers/JobPostingImportController.php';
if (!file_exists($controllerPath)) {
    echo "❌ Controller not found.\n";
    exit(1);
}

$content = file_get_contents($controllerPath);

$old = <<<'PHP'
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

$new = <<<'PHP'
        $grouped = collect($batch->candidates)
            ->groupBy('group_key')
            ->map(function ($rows) {
                // Build parsed_locations for the review form.
                // For table-type blocks: one entry per school row.
                // For single-type blocks: one entry with the inline place text.
                // place_of_assignment_parsed is null for unrecoverable/TBD rows.
                $parsedLocations = $rows->map(function ($row) {
                    $parsed = array_key_exists('place_of_assignment_parsed', $row)
                        ? $row['place_of_assignment_parsed']
                        : ($row['place_of_assignment'] ?? null);

                    // Don't pre-fill "To be determined" or unrecoverable markers
                    if ($parsed !== null) {
                        $lc = strtolower(trim($parsed));
                        if (str_starts_with($lc, 'to be determined') ||
                            str_starts_with($lc, '[unreadable') ||
                            str_starts_with($lc, '[unreadable')) {
                            $parsed = null;
                        }
                    }

                    return [
                        'school'        => $parsed,
                        'unrecoverable' => !empty($row['needs_manual_review']),
                        'row_number'    => $row['school_row_number'] ?? null,
                    ];
                })->values()->toArray();

                return [
                    'label'            => $rows->first()['group_label'] ?? 'Untitled position',
                    'rows'             => $rows->values(),
                    'parsed_locations' => $parsedLocations,
                ];
            });
PHP;

$count = substr_count($content, $old);
if ($count === 0) {
    echo "❌ Pattern not found in controller. It may have already been patched differently.\n";
    echo "   Check JobPostingImportController@review manually — look for the \$grouped block.\n";
    exit(1);
}

backup($controllerPath);
file_put_contents($controllerPath, str_replace($old, $new, $content));
echo "  [ok ] Controller: parsed_locations builder fixed for all 3 formats\n";

echo <<<TEXT

✅ Done. No migration needed.

WHAT EACH PDF FORMAT NOW DOES:

  SGOD-0079 style (school TABLE):
    → 89 location rows pre-filled with Mother School names
    → Unreadable rows show ⚠️ with blank editable input

  OSDS-0059 style (INLINE place text):
    → 1 location row pre-filled with the exact inline text
      e.g. "General Mariano Alvarez Technical High School"
    → HR can edit or add more rows

  OSDS-073 style ("To be determined"):
    → 1 blank location row for HR to fill in manually

NEXT STEPS:
  Restart queue worker and re-upload a PDF to reprocess:
    php artisan queue:work

  If using sync driver (no queue worker needed), just re-upload directly.

DELETE this script after running.

TEXT;
