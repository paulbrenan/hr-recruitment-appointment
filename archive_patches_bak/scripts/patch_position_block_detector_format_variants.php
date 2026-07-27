<?php
/**
 * Patch: PositionBlockDetector — handle real-world memo format variants
 * that were causing several PDFs to fail import entirely or produce
 * garbage place_of_assignment values.
 *
 * Run once from the project root:
 *   php patch_position_block_detector_format_variants.php
 * Then delete this file.
 *
 * Confirmed against actual OCR output from your 23-PDF sample. Fixes:
 *
 *  1. Lowercase lettered headings ("a. TITLE (SG-XX)") now detected —
 *     previously only uppercase "A." was recognized.
 *     Fixes: OSDS-2025-0087, OSDS-2025-0150
 *
 *  2. Primary heading title may now contain an inner parenthetical
 *     abbreviation like "(SNED)" before the "(SG-XX)" suffix.
 *     Fixes: OSDS-2025-0087
 *
 *  3. COS-format ("»" bullet) titles may now contain commas, inline
 *     parens, and wrap across multiple lines before the "Qualifications:"
 *     / "Qualification Standards:" header. Also now correctly splits a
 *     trailing "(SG-XX)" into salary_grade vs. a trailing "(Appointment
 *     Type)" into the display title, and allows the SG code on the same
 *     line as the bullet instead of only the next line.
 *     Fixes: OSDS-2025-0113, OSDS-2025-0149, OSDS-2025-0179
 *
 *  4. "Place of Assignment" matching now tolerates the confirmed real
 *     OCR misread "Piace of Assignment" (l -> i) in every place it's
 *     checked. Previously this silently broke place extraction and fell
 *     through to the table-parsing fallback, producing a 500+ char
 *     garbage value stuffed with unrelated block text.
 *     Fixes: OSDS-2026-0014
 *
 *  5. Inline place-of-assignment extraction now also stops at
 *     "Performance Requirements" (in addition to the existing stop
 *     words), so a comma-separated inline school list doesn't run past
 *     its real end into an unrelated OCR'd table.
 *     Fixes: OSDS-2025-0087
 *
 * NOT fixed by this patch (confirmed separately, pre-existing before
 * and after this patch):
 *   - OSDS-2025-0187 correctly still fails to detect any headings — it's
 *     a general process-announcement memo with no per-position vacancy
 *     data at all, not a vacancy table. This is expected behavior, not a
 *     bug.
 *   - OSDS-2025-0132's "PROJECT DEVELOPMENT OFFICER I" block has a
 *     place_of_assignment that overruns into "Job Summary:" text
 *     (525 chars). This is a different, pre-existing bug in how that
 *     block's boundary is computed and is NOT touched by this patch —
 *     flagging for a follow-up if you want it addressed.
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
    // 1 & 2: primary heading regex — lowercase letter + inner parens
    [
        <<<'OLD'
        // Letter + title + (SG-XX), tolerant of OCR's "Ill" for "III" etc.
        // IMPORTANT: the leading "A." / "B." position-letter prefix must
        // stay case-SENSITIVE (uppercase only) — see prior session's
        // confirmed bug where /i across the whole pattern matched
        // lowercase sub-bullets inside Duties text as fake headings.
        // Allow optional "*" before title (marks new positions in some memos).
        // Title may have an en-dash role suffix like "– Supply Officer I" which
        // we strip during resolution but keep for display.
        // ^\s* tolerates leading whitespace — pdftotext sometimes indents
        // heading lines slightly, which breaks ^[A-Z]\. in /m mode.
        $pattern = '/^\s*[A-Z]\.\s+\*?((?i)[A-Za-z][A-Za-z\s.,\'\-\x{2013}\x{2014}]+?)\s*\((?i)sg-?\s*(\d{1,2})\)/mu';
OLD,
        <<<'NEW'
        // Letter + title + (SG-XX), tolerant of OCR's "Ill" for "III" etc.
        // Letter prefix is now case-INSENSITIVE ("a." as well as "A.") —
        // confirmed real memos (OSDS-2025-0087, -0150) use lowercase
        // lettering. A prior session made this uppercase-only after a
        // false-positive match on lowercase sub-bullets inside Duties
        // text, but that false positive would have needed to (a) be
        // immediately followed by "(SG-nn)" AND (b) have its captured
        // text exactly match an entry in the canonical title list via
        // resolveTitle() below — both required, so re-widening the case
        // here is safe: the SG-suffix requirement and the canonical-list
        // gate together do the job the case restriction used to do alone.
        // Allow optional "*" before title (marks new positions in some memos).
        // Title may have an en-dash role suffix like "– Supply Officer I" which
        // we strip during resolution but keep for display.
        // ^\s* tolerates leading whitespace — pdftotext sometimes indents
        // heading lines slightly, which breaks ^[A-Z]\. in /m mode.
        // Title char class now also allows an inner parenthetical
        // abbreviation like "(SNED)" — confirmed real case (OSDS-2025-0087):
        // "Special Education Teacher I (SNED) (SG-14)". The lazy quantifier
        // still stops at the EARLIEST point that satisfies the trailing
        // "(SG-nn)" literal, so this doesn't change behavior for titles
        // that have no inner parens.
        $pattern = '/^\s*[A-Za-z]\.\s+\*?((?i)[A-Za-z][A-Za-z\s.,\'\-\x{2013}\x{2014}()]+?)\s*\((?i)sg-?\s*(\d{1,2})\)/mu';
NEW,
        'primary heading regex: lowercase letter + inner parens',
    ],

    // 3: COS-format detectCosFormat() — comma/parens/multi-line titles,
    //    same-line SG code, split trailing (...) into SG vs appointment type
    [
        <<<'OLD'
        // Match » or > followed by an all-caps title, then optionally
        // "(Appointment Type)" on the next line.
        $pattern = '/(?:»|>|›)\s+([A-Z][A-Z\s\-]+?)[ \t]*\n[ \t]*(?:\(([^)\n]+)\))?/m';

        if (!preg_match_all($pattern, $fullText, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $blocks  = [];
        $total   = count($matches[0]);

        foreach ($matches[0] as $idx => $fullMatch) {
            $rawTitle        = trim($matches[1][$idx][0]);
            $appointmentType = isset($matches[2][$idx][0]) && $matches[2][$idx][0] !== ''
                               ? trim($matches[2][$idx][0])
                               : null;
            $offset          = $fullMatch[1];

            $displayTitle = $this->titleCaseOcr($rawTitle);
            if ($appointmentType) {
                $displayTitle .= ' (' . $appointmentType . ')';
            }

            $canonicalTitle = $this->resolveCosTitleAgainstList($displayTitle);

            $blockStart = $offset;
            $blockEnd   = ($idx + 1 < $total)
                          ? $matches[0][$idx + 1][1]
                          : strlen($fullText);
            $blockText  = substr($fullText, $blockStart, $blockEnd - $blockStart);

            $block = $this->parseCosBlock(
                $displayTitle,
                $canonicalTitle,
                $blockText
            );

            if ($block !== null) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }
OLD,
        <<<'NEW'
        // Match » or > followed by the title text (which may wrap across
        // multiple lines and contain commas or inline parenthetical
        // abbreviations, e.g. "SBFP, WINS AND NSP FOCAL PERSONS" or
        // "INFORMATION AND COMMUNICATIONS TECHNOLOGY (ICT)\nINVENTORY
        // AND PERSONNEL MASTERLIST"), stopping at the next known
        // "Qualifications:" / "Qualification Standards:" header rather
        // than at a restrictive character class. This also covers memos
        // that use a bullet heading for a non-COS single position with
        // an inline SG code instead of an appointment type, e.g.
        // "» SCHOOL PRINCIPAL II (SG-20)" (confirmed real: OSDS-2025-0149).
        $pattern = '/(?:»|>|›)\s+(.+?)\n\s*Qualification[s]?(?:\s+Standards)?:/is';

        if (!preg_match_all($pattern, $fullText, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $blocks  = [];
        $total   = count($matches[0]);

        foreach ($matches[0] as $idx => $fullMatch) {
            $titleBlock = trim(preg_replace('/\s+/', ' ', $matches[1][$idx][0]));
            $offset     = $fullMatch[1];

            // Peel off a trailing "(...)" — it's either an SG code
            // ("(SG-20)") or an appointment type ("(Contract of
            // Service)"). Greedy capture on the base ensures we grab the
            // LAST parenthetical, not an earlier inline one like "(ICT)"
            // that's actually part of the title itself.
            $rawTitle        = $titleBlock;
            $appointmentType = null;
            $salaryGrade     = null;
            if (preg_match('/^(.*)\(([^)]*)\)\s*$/s', $titleBlock, $pm)) {
                $base     = trim($pm[1]);
                $trailing = trim($pm[2]);
                if ($base !== '' && preg_match('/^sg-?\s*(\d{1,2})$/i', $trailing, $sgm)) {
                    $salaryGrade = 'SG-' . $sgm[1];
                    $rawTitle    = $base;
                } elseif ($base !== '') {
                    $appointmentType = $trailing;
                    $rawTitle        = $base;
                }
            }

            $displayTitle = $this->titleCaseOcr($rawTitle);
            if ($appointmentType) {
                $displayTitle .= ' (' . $appointmentType . ')';
            }

            $canonicalTitle = $this->resolveCosTitleAgainstList($displayTitle);

            $blockStart = $offset;
            $blockEnd   = ($idx + 1 < $total)
                          ? $matches[0][$idx + 1][1]
                          : strlen($fullText);
            $blockText  = substr($fullText, $blockStart, $blockEnd - $blockStart);

            $block = $this->parseCosBlock(
                $displayTitle,
                $canonicalTitle,
                $blockText,
                $salaryGrade
            );

            if ($block !== null) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }
NEW,
        'detectCosFormat(): title regex + trailing (...) split into SG/appointment type',
    ],

    // parseCosBlock() signature — accept the salary grade extracted above
    [
        <<<'OLD'
    private function parseCosBlock(
        string $displayTitle,
        string $canonicalTitle,
        string $blockText
    ): ?array {
OLD,
        <<<'NEW'
    private function parseCosBlock(
        string $displayTitle,
        string $canonicalTitle,
        string $blockText,
        ?string $salaryGrade = null
    ): ?array {
NEW,
        'parseCosBlock(): add $salaryGrade parameter',
    ],

    // 4: Piace/Place tolerance — Additional Qualifications stop-lookahead
    [
        "            '/Additional Qualifications?:?\\s*(.*?)(?=Number of Vacant|Place of Assignment|Terms of Reference|Mandatory Requirements|\$)/is',",
        "            '/Additional Qualifications?:?\\s*(.*?)(?=Number of Vacant|P[l1i]ace of Assignment|Terms of Reference|Mandatory Requirements|\$)/is',",
        'Additional Qualifications stop-lookahead: Piace/Place tolerance',
    ],

    // 4: Piace/Place tolerance — COS place-of-assignment extraction + use $salaryGrade
    [
        <<<'OLD'
        // Place of assignment — inline prose in COS memos, not a table.
        // Confirmed: "Place of Assignment: Schools Division Office — Curriculum Implementation Division"
        $place = 'To be determined';
        if (preg_match('/Place of Assignment:?\s*(.+?)(?:\n|$)/i', $blockText, $m)) {
            $extracted = trim($m[1]);
            if ($extracted !== '') {
                $place = $extracted;
            }
        }
OLD,
        <<<'NEW'
        // Place of assignment — inline prose in COS memos, not a table.
        // Confirmed: "Place of Assignment: Schools Division Office — Curriculum Implementation Division"
        // "P[l1i]ace" tolerates the confirmed real OCR misread "Piace of
        // Assignment" (l -> i), which previously caused this whole match
        // to silently fail and fall through to the table-parsing branch
        // in extractPlaceOfAssignment(), swallowing hundreds of
        // characters of unrelated block text into place_of_assignment.
        $place = 'To be determined';
        if (preg_match('/P[l1i]ace of Assignment:?\s*(.+?)(?:\n|$)/i', $blockText, $m)) {
            $extracted = trim($m[1]);
            if ($extracted !== '') {
                $place = $extracted;
            }
        }
NEW,
        'parseCosBlock(): Piace/Place tolerance',
    ],

    // Use the extracted salary grade instead of hardcoded null
    [
        <<<'OLD'
            'title'                     => $displayTitle,
            'canonical_title'           => $canonicalTitle,
            'was_registered'            => false,
            'salary_grade'              => null,
OLD,
        <<<'NEW'
            'title'                     => $displayTitle,
            'canonical_title'           => $canonicalTitle,
            'was_registered'            => false,
            'salary_grade'              => $salaryGrade,
NEW,
        'parseCosBlock(): return $salaryGrade instead of null',
    ],

    // 4: Piace/Place tolerance — 'To be determined' single-value check
    [
        "        if (preg_match('/Place of Assignment:?\\s*To be determined/i', \$blockText)) {",
        "        if (preg_match('/P[l1i]ace of Assignment:?\\s*To be determined/i', \$blockText)) {",
        "'To be determined' check: Piace/Place tolerance",
    ],

    // 4: Piace/Place tolerance — bullet-list place-of-assignment
    [
        "        if (preg_match('/Place\\s+of\\s+Assignment:?\\s*((?:[\\x{27A4}>›].*(?:\\n|\$))+)/iu', \$blockText, \$bm)) {",
        "        if (preg_match('/P[l1i]ace\\s+of\\s+Assignment:?\\s*((?:[\\x{27A4}>›].*(?:\\n|\$))+)/iu', \$blockText, \$bm)) {",
        'bullet-list place-of-assignment: Piace/Place tolerance',
    ],

    // 4 & 5: Piace/Place tolerance + Performance Requirements stop word —
    //        inline single-value place-of-assignment extraction
    [
        <<<'OLD'
        $inlineStopPattern = '(?:Duties\s+and\s+Responsibilities|Job\s+Summary|Terms\s+of\s+Reference|Preferred\s+Qualification|Qualification\s+Standards|Number\s+of\s+Vacant\s+Position)';
OLD,
        <<<'NEW'
        // "Performance Requirements" added — confirmed real case
        // (OSDS-2025-0087): without it, a comma-separated inline school
        // list ("Place of Assignment: School A, School B, ...") ran past
        // its real boundary into the next section's garbled OCR table,
        // exceeded the 300-char inline-value ceiling below, and fell
        // through to the table-parsing branch, producing hundreds of
        // bogus "unrecoverable" rows instead of one clean inline value.
        $inlineStopPattern = '(?:Duties\s+and\s+Responsibilities|Job\s+Summary|Terms\s+of\s+Reference|Preferred\s+Qualification|Qualification\s+Standards|Performance\s+Requirements|Number\s+of\s+Vacant\s+Position)';
NEW,
        'inlineStopPattern: add Performance Requirements',
    ],
    [
        "            '/Place\\s+of\\s+Assignment:?\\s+(.+?)(?=\\s*' . \$inlineStopPattern . '|\$)/is',",
        "            '/P[l1i]ace\\s+of\\s+Assignment:?\\s+(.+?)(?=\\s*' . \$inlineStopPattern . '|\$)/is',",
        'inline single-value place-of-assignment: Piace/Place tolerance',
    ],

    // 4: Piace/Place tolerance — table-header lookalike guard
    [
        "            \$looksLikeTable = preg_match('/\\bNo\\.?\\s+(Mother\\s+School|Place\\s+of\\s+Assignment)\\b/i', \$value)",
        "            \$looksLikeTable = preg_match('/\\bNo\\.?\\s+(Mother\\s+School|P[l1i]ace\\s+of\\s+Assignment)\\b/i', \$value)",
        'table-header lookalike guard: Piace/Place tolerance',
    ],

    // 4: Piace/Place tolerance — extractLabeledField()'s stop-label list
    //    (used to bound Education/Training/Experience/Eligibility fields)
    [
        <<<'OLD'
        $stopLabels = array_filter($stopLabels, fn ($l) => $l !== $label);
        $stopPattern = implode('|', array_map(fn ($l) => preg_quote($l, '/'), $stopLabels));
OLD,
        <<<'NEW'
        $stopLabels = array_filter($stopLabels, fn ($l) => $l !== $label);
        // preg_quote each label, then loosen "Place of Assignment"
        // specifically to tolerate the confirmed real OCR misread
        // "Piace of Assignment" (l -> i) so a field like Eligibility
        // doesn't run past its real boundary and swallow the place text.
        $stopPattern = implode('|', array_map(function ($l) {
            $quoted = preg_quote($l, '/');
            return $l === 'Place of Assignment' ? 'P[l1i]ace of Assignment' : $quoted;
        }, $stopLabels));
NEW,
        'extractLabeledField(): stop-label Piace/Place tolerance',
    ],
]);

echo "\nDone. Diff and test an import before deleting this script and its .bak backup.\n";
