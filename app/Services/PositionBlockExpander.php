<?php

namespace App\Services;

/**
 * PositionBlockExpander
 *
 * Takes PositionBlockDetector::detect()'s output (one entry per DETECTED
 * POSITION, with place_of_assignment either a single "To be determined"
 * value or a full table of schools) and expands it into a flat list of
 * candidate job_postings rows -- one row per school for table-type blocks
 * (each with vacancies = 1, since each school slot is its own real
 * vacancy), or one row for single-value blocks (keeping the original
 * vacancy count).
 *
 * Each expanded row carries a 'group_key' so the review screen can group
 * all schools belonging to the same original position block together
 * under one collapsible section.
 */
class PositionBlockExpander
{
    /**
     * @param array $blocks Output of PositionBlockDetector::detect()
     * @return array<int, array> Flat list of candidate job_postings rows
     */
    public function expand(array $blocks): array
    {
        $candidates = [];

        foreach ($blocks as $blockIndex => $block) {
            $groupKey = 'block_' . $blockIndex;
            $shared = [
                'title' => $block['title'],
                'canonical_title' => $block['canonical_title'],
                'salary_grade' => $block['salary_grade'],
                'qualification_education' => $block['qualification_education'],
                'qualification_training' => $block['qualification_training'],
                'qualification_experience' => $block['qualification_experience'],
                'qualification_eligibility' => $block['qualification_eligibility'],
                'duties_responsibilities' => $block['duties_responsibilities'],
                'group_key' => $groupKey,
                'group_label' => $block['title'] . ' (' . $block['salary_grade'] . ')',
            ];

            $placeOfAssignment = $block['place_of_assignment'];

            if ($placeOfAssignment['type'] === 'single') {
                $candidates[] = array_merge($shared, [
                    'vacancies' => $block['vacancies'] ?? 1,
                    'place_of_assignment' => $placeOfAssignment['value'],
                ]);
                continue;
            }

            // Table type: one candidate row per school.
            foreach ($placeOfAssignment['schools'] as $schoolRow) {
                $candidates[] = array_merge($shared, [
                    'vacancies' => 1,
                    'place_of_assignment' => $this->formatPlaceOfAssignment($schoolRow),
                    'school_row_number' => $schoolRow['number'],
                ]);
            }
        }

        return $candidates;
    }

    /**
     * Builds the place_of_assignment display/storage string from a parsed
     * school row, including the adopted school(s) and municipality if
     * present, e.g. "Amuyong Elementary School (Alfonso)" or
     * "Area J ES, Bulihan ES (General Mariano Alvarez)".
     */
    private function formatPlaceOfAssignment(array $schoolRow): string
    {
        $school = $schoolRow['school'] ?? '';
        $adopted = $schoolRow['adopted'] ?? null;
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