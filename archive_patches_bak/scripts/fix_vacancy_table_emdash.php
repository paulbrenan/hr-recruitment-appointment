<?php

/**
 * fix_vacancy_table_emdash.php
 *
 * WHAT THIS DOES:
 *   pdftotext -layout renders the school table's column separators as em-dashes (—)
 *   instead of spaces or pipe characters. VacancyTableParser's cleanArtifacts()
 *   strips |, _, = but not —, so "Noveleta National High School —sNone — —Noveleta—"
 *   fails to split into school/adopted/municipality correctly.
 *
 *   Also strips the repeated "s" prefix that appears after — when "None" runs
 *   into the dash: "—sNone" → "None".
 *
 * HOW TO RUN:
 *   php fix_vacancy_table_emdash.php    (from project root)
 *   Then re-upload SGOD-2026-DM-0079.pdf
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

echo "\n=== fix_vacancy_table_emdash.php ===\n\n";

$parserPath = ROOT . '/app/Services/VacancyTableParser.php';

// Fix cleanArtifacts() to also strip em-dashes and en-dashes used as column separators
$oldClean = <<<'PHP'
    private function cleanArtifacts(string $text): string
    {
        // Collapse stray table-border characters and extra whitespace.
        $text = preg_replace('/[_|=]+/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
PHP;

$newClean = <<<'PHP'
    private function cleanArtifacts(string $text): string
    {
        // Strip em-dashes and en-dashes used as column separators by pdftotext -layout.
        // pdftotext renders table cell borders as runs of —— or – characters.
        // Must happen BEFORE collapsing whitespace so "School —sNone" becomes "School None".
        // The "s" prefix on "sNone" is a pdftotext artifact where the dash runs into
        // the word "None" — strip it: "—sNone" → " None", "—None" → " None".
        $text = preg_replace('/\x{2014}+s?(?=None)/u', ' ', $text);   // —sNone or —None
        $text = preg_replace('/\x{2013}+s?(?=None)/u', ' ', $text);   // –sNone or –None
        $text = preg_replace('/\x{2014}+/u', ' ', $text);              // remaining em-dashes
        $text = preg_replace('/\x{2013}+/u', ' ', $text);              // remaining en-dashes

        // Collapse stray table-border characters and extra whitespace.
        $text = preg_replace('/[_|=]+/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
PHP;

apply_patch($parserPath, $oldClean, $newClean, 'VacancyTableParser: strip em/en-dash column separators');

// Also fix normalizeSchoolName() to clean up trailing dash noise
$oldNormalize = <<<'PHP'
    private function normalizeSchoolName(string $name): string
    {
        // Fix the one confirmed, predictable OCR substitution seen so far:
        // ñ -> fi (e.g. "Acufia" -> "Acuña"). Extend this list as more
        // real OCR artifacts are confirmed against actual output.
        $corrections = [
            'Acufia' => 'Acuña',
            'TuaEs' => 'Tua ES',
        ];

        foreach ($corrections as $wrong => $right) {
            $name = str_ireplace($wrong, $right, $name);
        }

        // Confirmed real OCR pattern: a Roman numeral glued directly onto
        // "ES" with no space (e.g. "IVES)" should be "IV ES)").
        $name = preg_replace('/\b(I{1,3}|IV|VI{0,3}|IX|X)ES\b/', '$1 ES', $name);

        return trim($name);
    }
PHP;

$newNormalize = <<<'PHP'
    private function normalizeSchoolName(string $name): string
    {
        // Strip em/en-dash artifacts that may remain after cleanArtifacts()
        // in certain edge cases (e.g. leading "——i" before a school name).
        $name = preg_replace('/^[\x{2013}\x{2014}\s]+/u', '', $name);
        $name = preg_replace('/[\x{2013}\x{2014}]+/u', ' ', $name);

        // Fix the one confirmed, predictable OCR substitution seen so far:
        // ñ -> fi (e.g. "Acufia" -> "Acuña"). Extend this list as more
        // real OCR artifacts are confirmed against actual output.
        $corrections = [
            'Acufia' => 'Acuña',
            'TuaEs'  => 'Tua ES',
            'sNone'  => '',        // stray "sNone" artifact from em-dash+None
        ];

        foreach ($corrections as $wrong => $right) {
            $name = str_ireplace($wrong, $right, $name);
        }

        // Confirmed real OCR pattern: a Roman numeral glued directly onto
        // "ES" with no space (e.g. "IVES)" should be "IV ES)").
        $name = preg_replace('/\b(I{1,3}|IV|VI{0,3}|IX|X)ES\b/', '$1 ES', $name);

        // Strip trailing noise (numbers, symbols, stray letters after the school name)
        $name = preg_replace('/\s+\d+\s*$/', '', $name);

        return trim($name);
    }
PHP;

apply_patch($parserPath, $oldNormalize, $newNormalize, 'VacancyTableParser: normalizeSchoolName strips em-dash artifacts');

echo <<<TEXT

✅ Done. No migration needed.

Re-upload SGOD-2026-DM-0079.pdf.

The fix handles:
  - "Alfonso Integrated High School — —sNone — —Alifonso—"
    → school: "Alfonso Integrated High School", municipality: "Alfonso"

  - "Noveleta National High School —sNone — —Noveleta—"
    → school: "Noveleta National High School", municipality: "Noveleta"

  - "——i 5 a 2 Integrated School" (leading dash noise)
    → cleaned before matching

The underlying issue is pdftotext -layout rendering table column padding
as em-dash runs. This fix strips them before the municipality matching logic.

DELETE this script after running.

TEXT;
