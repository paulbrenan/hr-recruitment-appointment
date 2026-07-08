<?php

/**
 * patch_import_format6.php
 *
 * WHAT THIS DOES:
 *   Fixes PositionBlockDetector to handle the OSDS-0020 / OSDS-053 format
 *   variants not covered by the previous patches:
 *
 *   NEW ISSUES FROM OSDS-0020:
 *
 *   A. En-dash suffix in title — "Administrative Officer I – Supply Officer I (SG-10)"
 *      The heading regex matches the title up to "(SG-XX)" but the raw title
 *      captured is "Administrative Officer I – Supply Officer I". resolveTitle()
 *      won't find this in the canonical list. Fix: strip everything from the
 *      first " – " or " - " onward before resolving, so "Administrative Officer I –
 *      Supply Officer I" → "Administrative Officer I" which IS in the list.
 *      The full raw title (with suffix) is kept for display.
 *
 *   B. Asterisk (*) prefix — "D. *Administrative Aide IV – Clerk II (SG-4)"
 *      The heading pattern requires the title to start with [A-Za-z] after
 *      the letter+dot+space. An asterisk blocks the match entirely.
 *      Fix: make the heading regex optionally strip a leading "*" before
 *      the title text.
 *
 *   C. ➤ bullet place-of-assignment list — OSDS-0020 uses:
 *        Place of Assignment:
 *        ➤  Schools Division Office – Accounting Unit
 *        ➤  Constancio E. Aure Sr. NHS, Mendez
 *        ➤  Luis Aguado NHS, Trece Martires City
 *      This isn't a numbered table (VacancyTableParser) or a single inline
 *      value. Fix: detect the ➤ bullet list pattern in
 *      extractPlaceOfAssignment() and return a synthetic 'table' result
 *      with one school per bullet, so PositionBlockExpander creates one
 *      candidate row per assignment location.
 *
 *   D. "Senior High School" prefix — same issue as Secondary/Elementary.
 *      "Senior High School Administrative Assistant II" needs to be
 *      registered as a canonical title variant. Fix: add "Senior High School"
 *      to STRIPPABLE_PREFIXES.
 *
 *   E. Vacancy count from ➤ bullet blocks — "Number of Vacant Positions: 3"
 *      followed by three ➤ bullets means 3 total vacancies across those
 *      3 locations (1 each). The existing vacancies extractor already reads
 *      the number correctly; the ➤ expander should set vacancies=1 per row.
 *
 * HOW TO RUN:
 *   php patch_import_format6.php    (from project root)
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
    if ($count === 0) {
        echo "\n❌ PATCH ABORTED — content not found in $path\nLabel: $label\nSearched for:\n---\n$old\n---\n";
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

echo "\n=== patch_import_format6.php ===\n\n";

$detectorPath = ROOT . '/app/Services/PositionBlockDetector.php';

// ─── A + B. Heading regex — strip asterisk prefix, allow en-dash suffix ───

echo "[A+B] Patching findPositionHeadings() — asterisk prefix + en-dash suffix...\n";

apply_patch(
    $detectorPath,
    '        $pattern = \'/^[A-Z]\\.\s+((?i)[A-Za-z][A-Za-z\s.,\\\'\\-]+?)\\s*\\((?i)sg-?\\s*(\\d{1,2})\\)/m\';',
    '        // Allow optional "*" before title (marks new positions in some memos).
        // Title may have an en-dash role suffix like "– Supply Officer I" which
        // we strip during resolution but keep for display.
        $pattern = \'/^[A-Z]\\.\s+\\*?((?i)[A-Za-z][A-Za-z\\s.,\\\'\\-\\x{2013}\\x{2014}]+?)\\s*\\((?i)sg-?\\s*(\\d{1,2})\\)/mu\';',
    'findPositionHeadings(): allow * prefix and en-dash in title'
);

// ─── A. resolveTitle() — strip en-dash role suffix before matching ─────────

echo "\n[A] Patching resolveTitle() — strip en-dash suffix before canonical lookup...\n";

apply_patch(
    $detectorPath,
    '    private function resolveTitle(string $rawTitle): ?array
    {
        $normalizedRaw = $this->normalizeForComparison($rawTitle);

        // 1. Direct exact match (covers both plain titles AND any
        //    Secondary/Elementary variant already registered previously).
        foreach ($this->canonicalTitles as $canonical) {
            if ($this->normalizeForComparison($canonical) === $normalizedRaw) {
                return [\'title\' => $canonical, \'was_registered\' => false];
            }
        }',
    '    private function resolveTitle(string $rawTitle): ?array
    {
        // Strip en-dash / em-dash role-suffix appended to some titles,
        // e.g. "Administrative Officer I – Supply Officer I" → "Administrative Officer I"
        // or "Administrative Aide IV – Clerk II" → "Administrative Aide IV".
        // We try the FULL raw title first (direct match), then the stripped form.
        $strippedSuffix = preg_replace(\'/\s+[\x{2013}\x{2014}\-]{1,2}\s+.+$/u\', \'\', $rawTitle);
        $strippedSuffix = trim($strippedSuffix);

        $normalizedRaw     = $this->normalizeForComparison($rawTitle);
        $normalizedStripped = ($strippedSuffix !== $rawTitle)
            ? $this->normalizeForComparison($strippedSuffix)
            : null;

        // 1. Direct exact match (covers both plain titles AND any
        //    Secondary/Elementary variant already registered previously).
        foreach ($this->canonicalTitles as $canonical) {
            if ($this->normalizeForComparison($canonical) === $normalizedRaw) {
                return [\'title\' => $canonical, \'was_registered\' => false];
            }
            // Also try the suffix-stripped form
            if ($normalizedStripped !== null &&
                $this->normalizeForComparison($canonical) === $normalizedStripped) {
                return [\'title\' => $canonical, \'was_registered\' => false];
            }
        }',
    'resolveTitle(): try suffix-stripped form against canonical list'
);

// ─── D. STRIPPABLE_PREFIXES — add "Senior High School" ────────────────────

echo "\n[D] Adding 'Senior High School' to STRIPPABLE_PREFIXES...\n";

apply_patch(
    $detectorPath,
    "    /** Prefixes that may appear in a real memo but aren't part of the canonical list. */
    private const STRIPPABLE_PREFIXES = ['Secondary', 'Elementary'];",
    "    /** Prefixes that may appear in a real memo but aren't part of the canonical list. */
    private const STRIPPABLE_PREFIXES = ['Secondary', 'Elementary', 'Senior High School'];",
    'STRIPPABLE_PREFIXES: add Senior High School'
);

// ─── C. extractPlaceOfAssignment() — detect ➤ bullet list ────────────────

echo "\n[C] Patching extractPlaceOfAssignment() — add ➤ bullet list detection...\n";

// Insert ➤ bullet detection BEFORE the "To be determined" check so it
// runs first and doesn't fall through to the table parser.
apply_patch(
    $detectorPath,
    '    private function extractPlaceOfAssignment(
        string $blockText,
        int $blockStartOffset,
        array $pageTexts,
        array $pageBoundaries,
        ?int $vacancies
    ): array {
        if (preg_match(\'/Place of Assignment:?\\s*To be determined/i\', $blockText)) {
            return [\'type\' => \'single\', \'value\' => \'To be determined\'];
        }',
    '    private function extractPlaceOfAssignment(
        string $blockText,
        int $blockStartOffset,
        array $pageTexts,
        array $pageBoundaries,
        ?int $vacancies
    ): array {
        if (preg_match(\'/Place of Assignment:?\\s*To be determined/i\', $blockText)) {
            return [\'type\' => \'single\', \'value\' => \'To be determined\'];
        }

        // ── ➤ bullet list place-of-assignment (OSDS-0020 style) ───────────
        // Pattern: "Place of Assignment:" followed by one or more lines
        // starting with ➤ (U+27A4), >, or › each listing one school/office.
        // Example:
        //   Place of Assignment:
        //   ➤  Schools Division Office – Accounting Unit
        //   ➤  Constancio E. Aure Sr. NHS, Mendez
        //   ➤  Luis Aguado NHS, Trece Martires City
        //
        // Also handles inline bullet format where count prefix is given:
        //   ➤  2 – Tanza National Comprehensive HS
        //   ➤  1 – Emiliano Tria Tirona Memorial Integrated NHS, Kawit
        if (preg_match(\'/Place\\s+of\\s+Assignment:?\\s*((?:[\\x{27A4}>›].*(?:\\n|$))+)/iu\', $blockText, $bm)) {
            $bulletBlock = $bm[1];
            // Extract each bullet line
            preg_match_all(\'/[\\x{27A4}>›]\\s*(.*)/u\', $bulletBlock, $lines);
            $schools = [];
            $rowNum = 1;
            foreach ($lines[1] as $line) {
                $line = trim($line);
                if ($line === \'\') continue;

                // Strip leading count like "2 – " or "1 - "
                $line = preg_replace(\'/^\\d+\\s*[\\x{2013}\\x{2014}\\-]\\s*/u\', \'\', $line);
                $line = trim($line);
                if ($line === \'\') continue;

                // Strip trailing municipality hint after comma if it looks like
                // ", GMA" or ", Mendez" — keep the school name clean
                // (we keep it as-is; HR can edit on review screen)
                $schools[] = [
                    \'number\'        => $rowNum++,
                    \'school\'        => $line,
                    \'adopted\'       => null,
                    \'municipality\'  => null,
                    \'unrecoverable\' => false,
                ];
            }
            if (!empty($schools)) {
                return [\'type\' => \'table\', \'schools\' => $schools];
            }
        }',
    'extractPlaceOfAssignment(): detect ➤ bullet list as multi-location table'
);

echo <<<TEXT

✅ Done. No migration needed.

WHAT WAS FIXED:

A. En-dash suffix in titles (OSDS-0020)
   "Administrative Officer I – Supply Officer I" now resolves to
   "Administrative Officer I" by stripping the " – Supply Officer I" part
   before the canonical lookup. The full title is still used for display.

B. Asterisk (*) prefix (OSDS-0020)
   "D. *Administrative Aide IV – Clerk II (SG-4)" now has the * stripped
   by the heading regex so the title is found correctly.

C. ➤ bullet place-of-assignment list (OSDS-0020)
   Multiple ➤ bullets under "Place of Assignment:" are now parsed as a
   synthetic school table — one row per bullet — so PositionBlockExpander
   creates one candidate row per location. HR sees all locations pre-filled
   on the review screen and can edit them before confirming.
   Also handles count-prefixed bullets like "2 – Tanza National HS" by
   stripping the "2 – " prefix (the vacancy count per location is not
   tracked separately — HR edits the total vacancies field).

D. "Senior High School" prefix
   "Senior High School Administrative Assistant II" now resolves to
   "Administrative Assistant II" via STRIPPABLE_PREFIXES, then gets
   registered as a new permanent canonical title "Senior High School
   Administrative Assistant II" via JobTitleRegistrar.

DELETE this script after running.

TEXT;
