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