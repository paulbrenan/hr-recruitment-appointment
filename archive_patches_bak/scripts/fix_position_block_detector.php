<?php

/**
 * fix_position_block_detector.php
 *
 * Fixes two bugs in PositionBlockDetector confirmed against OSDS-2026-DM-0059:
 *
 * BUG 1 — Nurse II gets 9 fake rows instead of 1 clean inline place:
 *   extractPlaceOfAssignment() only checks for "To be determined" or
 *   defers to VacancyTableParser. For PDFs where Place of Assignment is
 *   inline text (no school table), VacancyTableParser misreads page footer
 *   numbers as row numbers. Fix: detect inline place text directly from
 *   "Place of Assignment: <text>" before falling through to the table parser.
 *   Also add "Job Summary:" as an additional stop phrase (some PDFs use this
 *   instead of "Duties and Responsibilities:").
 *
 * BUG 2 — Administrative Assistant III place bleeds eligibility text:
 *   extractLabeledField() for Eligibility doesn't stop at
 *   "Number of Vacant Position" so it captures everything up to the next
 *   known label — including the place text and duties. Fix: add
 *   "Number of Vacant Position" to the stop labels for Eligibility.
 *
 * HOW TO RUN:
 *   php fix_position_block_detector.php    (from project root)
 *   Then re-upload the PDF.
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

echo "\n=== fix_position_block_detector.php ===\n\n";

$detectorPath = ROOT . '/app/Services/PositionBlockDetector.php';

// ─── FIX 1: extractPlaceOfAssignment — detect inline text before table parser

echo "[1] Fixing extractPlaceOfAssignment — inline place detection...\n";

$oldExtractPlace = <<<'PHP'
    private function extractPlaceOfAssignment(
        string $blockText,
        int $blockStartOffset,
        array $pageTexts,
        array $pageBoundaries,
        ?int $vacancies
    ): array {
        if (preg_match('/Place of Assignment:?\s*To be determined/i', $blockText)) {
            return ['type' => 'single', 'value' => 'To be determined'];
        }
PHP;

$newExtractPlace = <<<'PHP'
    private function extractPlaceOfAssignment(
        string $blockText,
        int $blockStartOffset,
        array $pageTexts,
        array $pageBoundaries,
        ?int $vacancies
    ): array {
        if (preg_match('/Place of Assignment:?\s*To be determined/i', $blockText)) {
            return ['type' => 'single', 'value' => 'To be determined'];
        }

        // Detect inline place of assignment (no school table).
        // Pattern: "Place of Assignment: <text>" where <text> ends at the
        // next known section heading. Some PDFs use "Job Summary:" or
        // "Duties and Responsibilities:" or "Terms of Reference:" as the
        // next heading — stop at whichever comes first.
        // This MUST run before the table parser so inline-place PDFs
        // don't fall through to VacancyTableParser which misreads footer
        // numbers as table row numbers.
        $inlineStopPattern = '(?:Duties\s+and\s+Responsibilities|Job\s+Summary|Terms\s+of\s+Reference|Preferred\s+Qualification|Qualification\s+Standards|Number\s+of\s+Vacant\s+Position)';
        if (preg_match(
            '/Place\s+of\s+Assignment:?\s+(.+?)(?=\s*' . $inlineStopPattern . '|$)/is',
            $blockText,
            $m
        )) {
            $value = trim(preg_replace('/\s+/', ' ', $m[1]));

            // Only treat as inline if the extracted value looks like a real
            // place name (not a table header like "No. Mother School ...").
            // A table header will contain "Mother School" or "No." at the start.
            $looksLikeTable = preg_match('/\bNo\.?\s+(Mother\s+School|Place\s+of\s+Assignment)\b/i', $value)
                || preg_match('/^\s*\d+\s+\w/', $value); // starts with a row number

            if (!$looksLikeTable && strlen($value) > 2 && strlen($value) < 300) {
                return ['type' => 'single', 'value' => $value];
            }
        }
PHP;

apply_patch($detectorPath, $oldExtractPlace, $newExtractPlace, 'PositionBlockDetector: inline place detection before table parser');

// ─── FIX 2: extractLabeledField — add "Number of Vacant Position" as stop label

echo "\n[2] Fixing extractLabeledField — add vacancy count as stop label...\n";

$oldStopLabels = <<<'PHP'
        $stopLabels = ['Education', 'Training', 'Experience', 'Eligibility', 'Number of Vacant Positions'];
        $stopLabels = array_filter($stopLabels, fn ($l) => $l !== $label);
PHP;

$newStopLabels = <<<'PHP'
        $stopLabels = [
            'Education',
            'Training',
            'Experience',
            'Eligibility',
            'Number of Vacant Positions',
            'Number of Vacant Position',  // singular form used in some PDFs
            'Place of Assignment',
            'Duties and Responsibilities',
            'Job Summary',
            'Preferred Qualification',
        ];
        $stopLabels = array_filter($stopLabels, fn ($l) => $l !== $label);
PHP;

apply_patch($detectorPath, $oldStopLabels, $newStopLabels, 'PositionBlockDetector: expanded stop labels for extractLabeledField');

echo <<<TEXT

✅ Done. No migration needed.

Re-upload OSDS-2026-DM-0059.pdf. Expected results:

  Dentist II (SG-17)
    place: School Division Office – Dental Section   ← 1 clean row

  Nurse II (SG-16)
    place: School Division Office – School Governance and Operations Division (Health and Nutrition Unit)
    ← 1 clean row, no fake rows 2-9

  Administrative Assistant III (SG-9)
    place: Schools Division Office – Schools Division Superintendent (SDS) Office
    ← clean, no eligibility bleed

  Administrative Aide III–Clerk I (SG-3)
    place: General Mariano Alvarez Technical High School   ← 1 clean row

  Security Guard I (SG-3)
    place: Emiliano Tria Tirona Memorial National Integrated High School

  Administrative Aide I (SG-1)
    place: Tanza National Comprehensive High School   ← already working

DELETE this script after running.

TEXT;
