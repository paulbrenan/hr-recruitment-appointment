<?php
/**
 * Patch: PositionBlockDetector — fix the place-of-assignment overrun
 * confirmed on OSDS-2025-0132's "PROJECT DEVELOPMENT OFFICER I" block,
 * where the extracted value ran 525 characters into unrelated "Job
 * Summary:" text instead of stopping at the real boundary.
 *
 * Root causes (all confirmed against the actual OCR text of that block):
 *
 *  1. The label itself OCR'd as "Place ofAssignment:" — zero space
 *     between "of" and "Assignment" — which every regex expecting
 *     \s+ between those words failed to match at all. Loosened to \s*
 *     everywhere the label is checked.
 *
 *  2. This memo's schools use "»" bullets (the same character the
 *     COS-format detector already had to special-case), but the
 *     bullet-list place-of-assignment extractor's character class only
 *     recognized ➤ (U+27A4), ">", and "›" — not "»". Added.
 *
 *  3. A stray same-line OCR artifact (a lone "=") plus, in this specific
 *     case, a full page-break's worth of boilerplate (footer address/
 *     phone/email, then the next page's header) sits between the label
 *     and the first real bullet. The junk-skipping ".*?" couldn't cross
 *     those blank lines without the DOTALL flag; switched to a negated
 *     bullet-character class, which matches across newlines regardless
 *     of DOTALL.
 *
 *  4. Each school entry can wrap across two physical lines, and entries
 *     are separated by a blank line. The old approach only matched
 *     consecutive lines that individually started with a bullet, so it
 *     lost every entry after the first blank line AND dropped the
 *     wrapped continuation text of even that first entry. Rewritten to
 *     capture the whole region up to a real stop word (or the previous,
 *     narrower failure mode: end of string, which combined badly with a
 *     greedy inner quantifier to swallow all the way to end-of-block
 *     regardless of stop words), then split on the bullet character
 *     itself — correctly handles both wrapping and blank-line
 *     separators.
 *
 * Verified: OSDS-2025-0132 now extracts all 3 schools cleanly and stops
 * before "Job Summary:". Full regression sweep across the existing
 * 23-PDF sample shows no changes in block/detection counts anywhere
 * else, and the original ➤-bullet format (OSDS-2025-0020) still parses
 * identically to before.
 *
 * Run once from the project root:
 *   php patch_position_block_detector_place_boundary.php
 * Then delete this file.
 */

function apply_patch(string $path, array $edits): void
{
    if (!file_exists($path)) {
        fwrite(STDERR, "ABORT: file not found: $path\n");
        exit(1);
    }

    $original = file_get_contents($path);
    $working = $original;

    foreach ($edits as $i => [$search, $replace, $label]) {
        $count = substr_count($working, $search);
        if ($count !== 1) {
            fwrite(STDERR, "ABORT: edit #$i ($label) matched $count times (expected exactly 1) in $path\n");
            fwrite(STDERR, "No changes were written.\n");
            exit(1);
        }
        $working = str_replace($search, $replace, $working);
    }

    $backup = $path . '.bak';
    if (!copy($path, $backup)) {
        fwrite(STDERR, "ABORT: could not create backup at $backup\n");
        exit(1);
    }

    file_put_contents($path, $working);
    echo "Patched: $path\n";
    echo "Backup:  $backup\n";
}

// ── Adjust this path if your project layout differs ────────────────────
$file = __DIR__ . '/app/Services/PositionBlockDetector.php';

apply_patch($file, [
    // Move $inlineStopPattern to the top of the function so both the
    // bullet-list branch and the inline single-value branch can use it,
    // and tolerate the "ofAssignment" zero-space merge in the
    // 'To be determined' check.
    [
        <<<'OLD'
    private function extractPlaceOfAssignment(
        string $blockText,
        int $blockStartOffset,
        array $pageTexts,
        array $pageBoundaries,
        ?int $vacancies
    ): array {
        if (preg_match('/P[l1i]ace of Assignment:?\s*To be determined/i', $blockText)) {
            return ['type' => 'single', 'value' => 'To be determined'];
        }
OLD,
        <<<'NEW'
    private function extractPlaceOfAssignment(
        string $blockText,
        int $blockStartOffset,
        array $pageTexts,
        array $pageBoundaries,
        ?int $vacancies
    ): array {
        // "Performance Requirements" added — confirmed real case
        // (OSDS-2025-0087): without it, a comma-separated inline school
        // list ran past its real boundary into the next section's garbled
        // OCR table. Used as a stop boundary by both the bullet-list and
        // inline single-value branches below.
        $inlineStopPattern = '(?:Duties\s+and\s+Responsibilities|Job\s+Summary|Terms\s+of\s+Reference|Preferred\s+Qualification|Qualification\s+Standards|Performance\s+Requirements|Number\s+of\s+Vacant\s+Position)';

        if (preg_match('/P[l1i]ace\s*of\s*Assignment:?\s*To be determined/i', $blockText)) {
            return ['type' => 'single', 'value' => 'To be determined'];
        }
NEW,
        'move $inlineStopPattern up + zero-space merge tolerance in TBD check',
    ],

    // Rewrite the bullet-list extractor: add "»", tolerate the
    // zero-space label merge, cross blank lines/page-break boilerplate
    // via a negated character class, and capture+split instead of
    // matching only consecutive bullet-prefixed lines.
    [
        <<<'OLD'
        if (preg_match('/P[l1i]ace\s+of\s+Assignment:?\s*((?:[\x{27A4}>›].*(?:\n|$))+)/iu', $blockText, $bm)) {
            $bulletBlock = $bm[1];
            // Extract each bullet line
            preg_match_all('/[\x{27A4}>›]\s*(.*)/u', $bulletBlock, $lines);
OLD,
        <<<'NEW'
        // Confirmed real OCR case (OSDS-2025-0132): "»" bullets (same
        // character the COS-format detector already special-cases) used
        // here too, plus the label itself rendered as "Place ofAssignment:"
        // with zero space between "of" and "Assignment" — \s* (not \s+)
        // tolerates that merge.
        //
        // Between the label and the first bullet there can also be a
        // stray same-line artifact (a lone "=") AND a full page-break's
        // worth of boilerplate (footer address/phone/email, then next
        // page's header) — a negated bullet-char class (rather than ".")
        // skips past all of that since it matches across newlines
        // regardless of the DOTALL flag.
        //
        // Also confirmed real (same memo): each school entry can wrap
        // across two physical lines ("» Main School - Indang Central ES,
        // Adopted Schools - Kayquit ES and\nAlulod ES, Indang"), and
        // entries are separated by a blank line. The previous approach —
        // matching only consecutive lines that each individually start
        // with a bullet — broke on both: it stopped at the first blank
        // line (losing every entry after the first) and dropped the
        // wrapped continuation text of even that first entry. Capturing
        // the whole region up to a real stop word, then splitting on the
        // bullet character itself, handles wrapping and blank-line
        // separators the same way.
        if (preg_match(
            '/P[l1i]ace\s*of\s*Assignment:?[^\x{27A4}\x{00BB}>\x{203A}]*?([\x{27A4}\x{00BB}>\x{203A}].*?)(?=' . $inlineStopPattern . '|\z)/isu',
            $blockText,
            $bm
        )) {
            $bulletBlock = $bm[1];
            // Split on the bullet character itself rather than matching
            // consecutive bullet-prefixed lines, so wrapped continuation
            // lines and blank-line-separated entries both stay intact.
            $rawEntries = preg_split('/[\x{27A4}\x{00BB}>\x{203A}]\s*/u', $bulletBlock);
            $lines = [1 => array_values(array_filter(array_map(
                fn ($e) => trim(preg_replace('/\s+/', ' ', $e)),
                $rawEntries
            ), fn ($e) => $e !== ''))];
NEW,
        'rewrite bullet-list place-of-assignment extraction',
    ],

    // Remove the now-duplicate $inlineStopPattern definition (moved to
    // the top of the function above) and loosen the label's whitespace.
    [
        <<<'OLD'
        // don't fall through to VacancyTableParser which misreads footer
        // numbers as table row numbers.
        // "Performance Requirements" added — confirmed real case
        // (OSDS-2025-0087): without it, a comma-separated inline school
        // list ("Place of Assignment: School A, School B, ...") ran past
        // its real boundary into the next section's garbled OCR table,
        // exceeded the 300-char inline-value ceiling below, and fell
        // through to the table-parsing branch, producing hundreds of
        // bogus "unrecoverable" rows instead of one clean inline value.
        $inlineStopPattern = '(?:Duties\s+and\s+Responsibilities|Job\s+Summary|Terms\s+of\s+Reference|Preferred\s+Qualification|Qualification\s+Standards|Performance\s+Requirements|Number\s+of\s+Vacant\s+Position)';
        if (preg_match(
            '/P[l1i]ace\s+of\s+Assignment:?\s+(.+?)(?=\s*' . $inlineStopPattern . '|$)/is',
            $blockText,
OLD,
        <<<'NEW'
        // don't fall through to VacancyTableParser which misreads footer
        // numbers as table row numbers.
        if (preg_match(
            '/P[l1i]ace\s*of\s*Assignment:?\s+(.+?)(?=\s*' . $inlineStopPattern . '|$)/is',
            $blockText,
NEW,
        'remove duplicate inlineStopPattern + loosen label whitespace',
    ],

    // Table-header lookalike guard: tolerate zero-space label merge.
    [
        "            \$looksLikeTable = preg_match('/\\bNo\\.?\\s+(Mother\\s+School|P[l1i]ace\\s+of\\s+Assignment)\\b/i', \$value)",
        "            \$looksLikeTable = preg_match('/\\bNo\\.?\\s+(Mother\\s+School|P[l1i]ace\\s*of\\s*Assignment)\\b/i', \$value)",
        'table-header lookalike guard: zero-space label tolerance',
    ],

    // Additional Qualifications stop-lookahead: tolerate zero-space label merge.
    [
        "            '/Additional Qualifications?:?\\s*(.*?)(?=Number of Vacant|P[l1i]ace of Assignment|Terms of Reference|Mandatory Requirements|\$)/is',",
        "            '/Additional Qualifications?:?\\s*(.*?)(?=Number of Vacant|P[l1i]ace\\s*of\\s*Assignment|Terms of Reference|Mandatory Requirements|\$)/is',",
        'Additional Qualifications stop-lookahead: zero-space label tolerance',
    ],

    // COS-format place-of-assignment extraction: tolerate zero-space label merge.
    [
        "        if (preg_match('/P[l1i]ace of Assignment:?\\s*(.+?)(?:\\n|\$)/i', \$blockText, \$m)) {",
        "        if (preg_match('/P[l1i]ace\\s*of\\s*Assignment:?\\s*(.+?)(?:\\n|\$)/i', \$blockText, \$m)) {",
        'parseCosBlock(): zero-space label tolerance',
    ],

    // extractLabeledField()'s stop-label conversion: tolerate zero-space label merge.
    [
        "            return \$l === 'Place of Assignment' ? 'P[l1i]ace of Assignment' : \$quoted;",
        "            return \$l === 'Place of Assignment' ? 'P[l1i]ace\\s*of\\s*Assignment' : \$quoted;",
        'extractLabeledField(): zero-space label tolerance',
    ],
]);

echo "\nDone. Diff and test an import of OSDS-2025-0132 (or any memo with a\n";
echo "» / ➤ bullet-list place-of-assignment) before deleting this script\n";
echo "and its .bak backup.\n";
