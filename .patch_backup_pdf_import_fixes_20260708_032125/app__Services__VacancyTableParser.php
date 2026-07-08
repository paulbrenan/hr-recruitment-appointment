<?php

namespace App\Services;

/**
 * VacancyTableParser
 *
 * Parses OCR'd "Place of Assignment" tables (No. | Mother School |
 * Adopted School | City/Municipality) out of DepEd Cavite vacancy
 * announcement PDFs.
 *
 * Real OCR output for these tables is NOT reliably delimited — table
 * borders sometimes render as "|", sometimes "_", sometimes nothing at
 * all, and long school names wrap onto a second physical line WITHOUT
 * any row number on that continuation line. A naive "one line = one
 * row" parser breaks on both of these cases.
 *
 * Strategy: walk the text line by line. A new row starts only when a
 * line begins with the NEXT EXPECTED sequential integer (1, 2, 3...).
 * Any line that doesn't start a new row is treated as a continuation
 * of the current row (e.g. a wrapped school name) and appended to it.
 * Once a row is closed out, it's split into school name / adopted
 * school / municipality by matching the LAST token(s) against a known
 * list of valid municipalities (since municipality names can be single
 * or multi-word, e.g. "Gen. Emilio Aguinaldo", "Tagaytay City").
 */

class VacancyTableParser
{
    /**
     * Known Cavite municipalities/cities that appear in the City/Municipality
     * column. Multi-word names must be listed in full. Sorted longest-first
     * so multi-word names are matched before a shorter substring (e.g.
     * "Trece Martires City" before "City" doesn't apply here, but ordering
     * longest-first is a safe general habit for this kind of matching).
     */
    private const MUNICIPALITIES = [
        'Gen. Emilio Aguinaldo',
        'General Emilio Aguinaldo',
        'Gen. Mariano Alvarez',
        'General Mariano Alvarez',
        'Trece Martires City',
        'Tagaytay City',
        'Alfonso',
        'Amadeo',
        'Bacoor',
        'Carmona',
        'Dasmarinas',
        'Dasmariñas',
        'General Trias',
        'Imus',
        'Indang',
        'Kawit',
        'Magallanes',
        'Maragondon',
        'Mendez',
        'Naic',
        'Noveleta',
        'Rosario',
        'Silang',
        'Tanza',
        'Ternate',
    ];

    /**
     * Parse raw OCR text (one table page's worth, or multiple pages
     * concatenated) into structured rows.
     *
     * @return array<int, array{number:int, school:string, adopted:?string, municipality:?string}>
     */
    public function parse(string $text, int $startNumber = 1): array
    {
        // IMPORTANT: real OCR output for these tables is NOT reliably one
        // row per physical line — sometimes a whole page comes through as
        // a single continuous line with no newlines between rows at all
        // (confirmed against real output). So we cannot split on lines
        // first. Instead, normalize all whitespace (including newlines)
        // into single spaces, strip noise phrases, then walk the token
        // stream and cut a new row whenever we hit "<whitespace><N><not a
        // digit>" where N is the next expected sequential number.
        //
        // $startNumber lets a caller parse a table that CONTINUES from a
        // previous page (e.g. page 2 starts at row 21, not row 1) — see
        // parseMultiPage() below for the normal way to use this.

        $normalized = $this->stripNoisePhrases($text);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        $normalized = trim($normalized);

        $rows = [];
        $expectedNext = $startNumber;

        // Find the position of the first expected row number to start from
        // (skips over "Place of Assignment:", the column header, etc.)
        $startPos = $this->findNextRowNumberPosition($normalized, $expectedNext, 0);
        if ($startPos === null) {
            return [];
        }
        $cursor = $startPos;

        while (true) {
            // Match the number itself (with any stray OCR artifacts right
            // after it: _, |, =, (, spaces) at the cursor position.
            if (!preg_match(
                '/^(\d{1,3})\s*(?:[_|=\s]|\((?=\s*[_|=\s]))*\|?\s*/',
                substr($normalized, $cursor),
                $m
            )) {
                break;
            }

            $currentNumber = (int) $m[1];
            $afterMarker = $cursor + strlen($m[0]);

            // Find where the NEXT row starts, so we know where this row's
            // content ends. (Earlier attempt tried to "skip past" the next
            // number when an unclosed "(" was detected, on the theory that
            // the number was still inside a wrapped school name — that was
            // WRONG: the number itself is still a real, correct boundary.
            // The actual fix belongs in finalizeRow()'s cleanup instead —
            // see WRAPPED_PAREN_FIXUPS below, applied as a post-processing
            // pass across the whole row list.)
            $nextPos = $this->findNextRowNumberPosition($normalized, $currentNumber + 1, $afterMarker);
            $nextNumber = $currentNumber + 1;
            $isGap = false;

            // If the immediate next row number is missing (OCR garbled it),
            // fall back to an open-ended scan for the next legible row
            // number, however far ahead it is. A fixed lookahead window
            // isn't enough — confirmed real output has gaps bigger than 10
            // rows later in the same table.
            if ($nextPos === null) {
                $scan = $this->findNextLegibleRowNumberPosition($normalized, $currentNumber, $afterMarker);
                if ($scan !== null) {
                    $nextPos = $scan['position'];
                    $nextNumber = $scan['number'];
                    $isGap = true;
                }
            }

            $content = $nextPos === null
                ? substr($normalized, $afterMarker)
                : substr($normalized, $afterMarker, $nextPos - $afterMarker);

            // $isGap tells finalizeRow() whether $content might contain
            // debris from OTHER (skipped) rows, not just this one — see
            // the comment on finalizeRow() for why that matters.
            $rows[] = $this->finalizeRow($currentNumber, trim($content), $isGap);

            // When a gap was crossed, the row numbers strictly between
            // $currentNumber and $nextNumber were never found as their own
            // legible markers. Their content can't be safely reconstructed
            // (it's what got smeared into neighboring rows before this fix)
            // — so record them as explicit unrecoverable placeholders
            // instead of silently dropping them or leaking them into a
            // neighbor. This keeps row numbering honest (the review screen
            // can show "row 43: unreadable, needs manual entry" instead of
            // either a missing row or a corrupted one).
            if ($isGap) {
                for ($missing = $currentNumber + 1; $missing < $nextNumber; $missing++) {
                    $rows[] = [
                        'number' => $missing,
                        'school' => null,
                        'adopted' => null,
                        'municipality' => null,
                        'orphaned_prefix' => null,
                        'unrecoverable' => true,
                    ];
                }
            }

            if ($nextPos === null) {
                break;
            }
            $cursor = $nextPos;
        }

        // If any row left an orphaned_prefix (text after its own "None
        // <Municipality>" that actually belongs to the NEXT row's school
        // name, since OCR put it before that row's number marker), prepend
        // it to the next row's school name now.
        for ($i = 0; $i < count($rows) - 1; $i++) {
            if (!empty($rows[$i]['orphaned_prefix'])) {
                $rows[$i + 1]['school'] = trim($rows[$i]['orphaned_prefix'] . ' ' . $rows[$i + 1]['school']);
                $rows[$i + 1]['school'] = $this->normalizeSchoolName($rows[$i + 1]['school']);
            }
            unset($rows[$i]['orphaned_prefix']);
        }
        if (!empty($rows)) {
            $lastIndex = count($rows) - 1;
            $trailingOrphan = $rows[$lastIndex]['orphaned_prefix'] ?? null;
            unset($rows[$lastIndex]['orphaned_prefix']);

            // If the LAST legible row still had leftover buffer text after
            // its own boundary, that's not noise — it's one OR MORE more
            // rows whose numbers never rendered at all (so no scan could
            // ever have found them as a "next" marker to stop at). Confirmed
            // real case: AO II's row 89 was lost this way (one clean trailing
            // row), but PDO I's tail showed this can also be MULTIPLE rows'
            // worth of content glued together (rows 40 AND 41 both missing
            // legible numbers), and treating the whole thing as one opaque
            // placeholder silently threw away row 40's perfectly legible
            // "<School> None <Municipality>" content along with row 41's
            // genuinely unreadable remainder. Peel it apart instead: recover
            // every row that still has a findable "None <Municipality>" (or
            // adopted-school) boundary, and only fall back to an
            // unrecoverable placeholder for whatever's left after that.
            if (!empty($trailingOrphan)) {
                $rows = array_merge(
                    $rows,
                    $this->peelTrailingOrphanRows($trailingOrphan, $rows[$lastIndex]['number'] + 1)
                );
            }
        }

        return $rows;
    }

    /**
     * Merges a row like {school: "Marcelo D. Samaniego ES (Bucal"} with
     * the following row like {school: "IV ES)", municipality: "Maragondon"}
     * into one row: {school: "Marcelo D. Samaniego ES (Bucal IV ES)",
     * municipality: "Maragondon"}, keeping the FOLLOWING row's number
     * (since that's the row the table intended this school to occupy —
     * the wrap just pushed its closing text under the next number).
     */
    private function mergeWrappedParentheticals(array $rows): array
    {
        $merged = [];
        $i = 0;
        $count = count($rows);

        while ($i < $count) {
            $row = $rows[$i];
            $school = $row['school'] ?? '';

            $hasUnclosedParen = substr_count($school, '(') > substr_count($school, ')');

            if ($hasUnclosedParen && isset($rows[$i + 1])) {
                $nextRow = $rows[$i + 1];
                $combinedSchool = trim($school . ' ' . ($nextRow['school'] ?? ''));

                $merged[] = [
                    'number' => $nextRow['number'], // table's real intended row number
                    'school' => $this->normalizeSchoolName($combinedSchool),
                    'adopted' => $nextRow['adopted'] ?? $row['adopted'] ?? null,
                    'municipality' => $nextRow['municipality'] ?? $row['municipality'] ?? null,
                ];

                $i += 2; // consumed both rows
                continue;
            }

            $merged[] = $row;
            $i++;
        }

        return $merged;
    }

    /**
     * True if the given text has an opening "(" with no matching ")" —
     * meaning a school name's parenthetical wrapped onto a continuation
     * that we haven't reached yet.
     */
    private function hasUnbalancedOpenParen(string $text): bool
    {
        $open = substr_count($text, '(');
        $close = substr_count($text, ')');
        return $open > $close;
    }

    /**
     * Finds the character offset of the next occurrence of the exact
     * sequential number we're expecting, as a standalone token (not part
     * of a longer number, not part of "SG-11" etc.), searching from $from.
     */
    private function findNextRowNumberPosition(string $text, int $expectedNumber, int $from): ?int
    {
        // (?=\s*[_|=(\s]|\s*$) — a row-number marker is also valid at the
        // very end of the string with nothing after it. Confirmed real
        // case: a table's TRUE last row often has no trailing border char
        // or whitespace at all, because the extracted text is truncated
        // right at the table's end (e.g. right before "Duties and
        // Responsibilities"). Without this, that final row's number is
        // invisible to every lookup in this class, and the row is lost
        // even though its content is sitting right there in the text.
        $pattern = '/(?<![\d.\-])' . $expectedNumber . '(?=\s*[_|=(\s]|\s*$)/';
        if (preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE, $from)) {
            return $m[0][1];
        }
        return null;
    }

    /**
     * Open-ended fallback for findNextRowNumberPosition(): used when the
     * immediate next sequential row number can't be found (OCR garbled it).
     * Scans forward from $from for every number-like token that looks like
     * a row-number starter (same "digit run followed by table-border noise
     * or whitespace" shape used elsewhere in this class), walks them in the
     * order they appear in the text, and returns the position AND value of
     * the FIRST one whose value is strictly greater than $currentNumber.
     *
     * No fixed window: this replaces the old +2..+10 lookahead loop, which
     * was confirmed too small for gaps later in the same table. Returning
     * the matched number (not just its position) lets the caller know how
     * many row numbers were skipped, so it can flag them instead of
     * silently losing them or smearing their debris into a neighbor.
     *
     * @return array{position:int, number:int}|null
     */
    private function findNextLegibleRowNumberPosition(string $text, int $currentNumber, int $from): ?array
    {
        if (!preg_match_all(
            '/(?<![\d.\-])(\d{1,3})(?=\s*[_|=(\s]|\s*$)/',
            $text,
            $matches,
            PREG_OFFSET_CAPTURE,
            $from
        )) {
            return null;
        }

        foreach ($matches[1] as $match) {
            [$numberStr, $offset] = $match;
            if ((int) $numberStr > $currentNumber) {
                return ['position' => $offset, 'number' => (int) $numberStr];
            }
        }

        return null;
    }

    /**
     * Convenience method for the real-world case: a table spans multiple
     * OCR'd pages. Concatenates the pages and tracks row numbering across
     * the boundary automatically.
     *
     * @param string[] $pages Raw OCR text for each page, in order.
     */
    public function parseMultiPage(array $pages): array
    {
        $allRows = [];
        $nextExpected = 1;

        foreach ($pages as $pageText) {
            $rows = $this->parse($pageText, $nextExpected);

            // If no rows found with the strict nextExpected number, OCR probably
            // garbled some row numbers on this page (confirmed: rows 7-8 on
            // page 5 of one PDF render as "_s" noise instead of "7" and "8").
            // Try auto-detecting the actual first legible row number on this page
            // that is >= nextExpected, instead of giving up on the whole page.
            //
            // NOTE: this used to be gated on "$nextExpected > 1", skipping the
            // fallback for a table's very first page. That was wrong: it's
            // just as possible for row 1 specifically to be the unreadable
            // one (confirmed real case: a Project Development Officer I
            // table whose row 1 cell wraps across two lines and OCR drops
            // the leading "1" entirely) — which silently lost the WHOLE
            // table (0 rows, no error) instead of recovering from row 2+.
            if (empty($rows)) {
                $rows = $this->parseFromFirstLegibleRow($pageText, $nextExpected);
            }

            if (empty($rows)) {
                continue;
            }

            // Merge, deduplicating against rows already collected (avoids double-
            // counting if a page boundary falls mid-row).
            $existingNumbers = array_column($allRows, 'number');
            foreach ($rows as $row) {
                if (!in_array($row['number'], $existingNumbers, true)) {
                    $allRows[] = $row;
                }
            }

            $nextExpected = max(array_column($allRows, 'number')) + 1;
        }

        // Sort by row number in case pages overlapped or auto-detected starts
        // were out of order.
        usort($allRows, fn ($a, $b) => $a['number'] <=> $b['number']);

        return $allRows;
    }

    /**
     * Fallback for parseMultiPage(): scans a page's normalized text for the
     * first integer token >= $minNumber that looks like a row-number starter,
     * then delegates to parse() from that number. Used when OCR has garbled
     * the row numbers we expected to find (so parse($text, $nextExpected)
     * returned empty), but the page still contains legible rows at a higher
     * number.
     */
    private function parseFromFirstLegibleRow(string $text, int $minNumber): array
    {
        $normalized = $this->stripNoisePhrases($text);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        $normalized = trim($normalized);

        // Find every number-like token that looks like a table row starter.
        if (!preg_match_all(
            '/(?<![\d.\-])(\d{1,3})(?=\s*[_|=(\s]|\s*$)/',
            $normalized,
            $allMatches,
            PREG_OFFSET_CAPTURE
        )) {
            return [];
        }

        // Pick the first token >= minNumber.
        foreach ($allMatches[1] as $i => $match) {
            $num = (int) $match[0];
            if ($num >= $minNumber) {
                $rows = $this->parse($text, $num);
                if (empty($rows)) {
                    return [];
                }

                // If the first legible number on this page is HIGHER than
                // what we expected ($minNumber), the row numbers in between
                // never rendered legibly anywhere on this page — this is a
                // whole-page version of the same gap parse()'s main loop
                // already tracks internally. Without this, that whole
                // leading stretch just vanishes with no trace (confirmed
                // real case: PDO I lost ~10 rows this way — a page's
                // leading rows were unreadable, and the parser silently
                // started counting from the first one it COULD read).
                $placeholders = [];
                for ($missing = $minNumber; $missing < $num; $missing++) {
                    $placeholders[] = [
                        'number' => $missing,
                        'school' => null,
                        'adopted' => null,
                        'municipality' => null,
                        'orphaned_prefix' => null,
                        'unrecoverable' => true,
                    ];
                }

                return array_merge($placeholders, $rows);
            }
        }

        return [];
    }

    private function stripNoisePhrases(string $text): string
    {
        $noisePhrases = [
            'Republic of the Philippines',
            'Department of Education',
            'REGION IV-A',
            'SCHOOLS DIVISION OFFICE OF CAVITE PROVINCE',
            '/Attachment \d+ to Division Memorandum.*?POSITIONS/is',
            '/LIST OF VACANT POSITIONS/i',
            '/[A-Z]\.\s+[A-Z][A-Z\s]+\(SG-\d+\)/', // position heading, e.g. "A. ADMINISTRATIVE OFFICER II (SG-11)"
            '/Qualification Standards:.*?Number of Vacant Positions:\s*\d+/is',
            'Place of Assignment:',
            '/No\.?\s*Mother School.*?City\s*\/\s*Municipality/i',
            '/(?<![a-zA-Z])Elementary(?!\s+School)/i', // section divider only, not "Amuyong Elementary School"
            'Junior High School',
            'Senior High School',
            '/[a-zA-Z\s.,*]{0,20}Cavite Capitol Compound.*$/is',
            '/[«»a-zA-Z\s.,*+\x{2018}\x{2019}]{0,10}\(?046\)?[\d,\-\s]+.*$/iu',
            '/\bD\s*ED\b.*$/i',
            '/\bDepED\b.*$/i',
            '/www\.depedcavite\.com\.ph/i',
            '/deped\.cavite@deped\.gov\.ph/i',
        ];

        foreach ($noisePhrases as $phrase) {
            if ($phrase[0] === '/') {
                $text = preg_replace($phrase, ' ', $text);
            } else {
                $text = str_ireplace($phrase, ' ', $text);
            }
        }

        return $text;
    }

    /**
     * Splits a closed-out row's raw buffer into school / adopted school /
     * municipality, by finding the LAST occurrence of a known
     * municipality name at the end of the string.
     */
    /**
     * Splits a closed-out row's raw buffer into school / adopted school /
     * municipality. Real OCR sometimes appends an ORPHANED FRAGMENT after
     * the row's own "None <Municipality>" — the start of the NEXT row's
     * school name, which got OCR'd before that row's number marker. This
     * method finds "None <Municipality>" as the row's true end (it does
     * NOT assume that's the end of the buffer) and returns any leftover
     * text after it as $orphanedPrefix, for the caller to attach to the
     * next row.
     */
    private function finalizeRow(int $number, string $buffer, bool $isGap = false): array
    {
        $buffer = $this->cleanArtifacts($buffer);

        // All candidates combined into one alternation, longest-first so a
        // longer name wins over a shorter one that happens to be a prefix
        // of it. Matching them together (instead of looping candidates one
        // at a time and taking whichever matches first) means the regex
        // engine finds the LEFTMOST valid match in the buffer, not just the
        // first candidate-by-length that happens to match anywhere in it.
        // That distinction only matters when $buffer spans a GAP (see
        // below), but getting it right there is the whole point.
        [$sorted, $alternation] = $this->municipalityAlternation();

        $municipality = null;
        $school = $buffer;
        $adopted = null;
        $orphanedPrefix = null;

        // IMPORTANT: when $isGap is true, this row's marker and the next
        // LEGIBLE marker weren't adjacent — one or more row numbers in
        // between were unreadable, so $buffer can contain OCR debris for
        // those skipped rows too, appended after this row's own content.
        // The leftmost "None <Municipality>" / "<Municipality>" match in
        // the buffer is always THIS row's own boundary (this row's real
        // content necessarily reads first, before any skipped-row debris).
        // Anything after that point is therefore debris from the skipped
        // rows — NOT the start of the next found row's school name — so on
        // a gap it must be discarded here, not handed off as an
        // "orphaned_prefix" for the next row. (Confirmed real bug: doing
        // that produced garbled concatenations like "i a eel School Trece
        // Martires Ci Luis Aguado National High School" glued onto a row
        // that had actually read fine.)
        $noneStylePattern = '/^(.*?)\s+None\s+(' . $alternation . ')\b(.*)$/is';
        if (preg_match($noneStylePattern, $buffer, $m)) {
            $school = trim($m[1]);
            $municipality = $this->canonicalMunicipality($m[2], $sorted);
            $adopted = null;
            $trailing = trim($m[3]);
            $orphanedPrefix = (!$isGap && $trailing !== '') ? $trailing : null;
        } else {
            // Adopted-school variant: remainder is "<School> <AdoptedSchool(s)>"
            // where BOTH typically end in ES/MES/PS, with no reliable
            // delimiter between them (no comma before the first adopted
            // school, e.g. "Area J ES Bulihan ES"). Strategy: find the
            // municipality first (this branch only runs when "None"
            // didn't match), then within the remainder, find the FIRST
            // ES/MES/PS suffix — everything up to and including it is the
            // school name; everything after it (if any) is the adopted
            // school(s), which may themselves be a comma-separated list.
            $pattern2 = '/^(.*?)\s+(' . $alternation . ')\b(.*)$/is';
            if (preg_match($pattern2, $buffer, $m)) {
                $beforeMunicipality = trim($m[1]);
                $trailing = trim($m[3]);

                if (preg_match('/^(\(?[A-Za-z.\'\- ]*?(?:ES|MES|PS)\)?)\s+(.+)$/s', $beforeMunicipality, $sm)) {
                    $school = trim($sm[1]);
                    $adopted = trim($sm[2]);
                } else {
                    $school = $beforeMunicipality;
                    $adopted = null;
                }

                $municipality = $this->canonicalMunicipality($m[2], $sorted);
                $orphanedPrefix = (!$isGap && $trailing !== '') ? $trailing : null;
            }
        }

        return [
            'number' => $number,
            'school' => $this->normalizeSchoolName($school),
            'adopted' => $adopted,
            'municipality' => $municipality,
            'orphaned_prefix' => $orphanedPrefix,
            'unrecoverable' => false,
        ];
    }

    /**
     * Longest-first-sorted municipality list plus its combined regex
     * alternation, shared by finalizeRow() and peelTrailingOrphanRows() so
     * both do the exact same leftmost/longest-match boundary detection.
     *
     * @return array{0: array<int,string>, 1: string}
     */
    private function municipalityAlternation(): array
    {
        $sorted = self::MUNICIPALITIES;
        usort($sorted, fn ($a, $b) => strlen($b) - strlen($a));
        $alternation = implode('|', array_map(fn ($m) => preg_quote($m, '/'), $sorted));
        return [$sorted, $alternation];
    }

    /**
     * Peels apart a trailing orphan blob — the leftover text after the
     * table's last legible row number's own boundary, when no further
     * legible row-number marker exists anywhere in the rest of the text —
     * into as many recoverable rows as it actually contains, rather than
     * dumping the whole thing into one opaque placeholder.
     *
     * Confirmed real case (PDO I): row 39 closes cleanly ("...Tanza
     * National Trade School"), but rows 40 and 41's printed numbers OCR'd
     * as pure noise (not digits at all), so the entire remainder of the
     * table's text — two more schools' worth — lands in row 39's trailing
     * buffer. One of those two ("...High School None Ternate") is
     * perfectly legible; only the fragment after it (cut short by the
     * page-boundary truncation upstream) is genuinely unreadable. Walking
     * the blob and peeling off every recognizable "<School> None
     * <Municipality>" (or adopted-school) boundary recovers the legible
     * row(s) with real content, leaving only the true leftover — if any —
     * to become an unrecoverable placeholder.
     *
     * Recovered rows have no legible printed number (that's WHY their
     * content ended up here instead of being found as its own row by the
     * main scan), so they're assigned sequential numbers continuing on
     * from the last known legible row.
     *
     * @return array<int, array> One or more rows: any recovered rows with
     *                           real content, plus at most one trailing
     *                           unrecoverable placeholder for whatever
     *                           text is left after peeling.
     */
    private function peelTrailingOrphanRows(string $blob, int $startNumber): array
    {
        $blob = trim($blob);
        if ($blob === '') {
            return [];
        }

        [$sorted, $alternation] = $this->municipalityAlternation();

        // Same "None <Municipality>" boundary finalizeRow() looks for —
        // the LEFTMOST occurrence in what's left of the blob is always the
        // next recoverable row's true end, since that row's own content
        // necessarily reads before anything past it.
        $noneStylePattern = '/^(.*?)\s+None\s+(' . $alternation . ')\b(.*)$/is';
        if (preg_match($noneStylePattern, $blob, $m)) {
            $school = trim($m[1]);
            $remainder = trim($m[3]);

            if ($school !== '') {
                $recovered = [
                    'number' => $startNumber,
                    'school' => $this->normalizeSchoolName($school),
                    'adopted' => null,
                    'municipality' => $this->canonicalMunicipality($m[2], $sorted),
                    'orphaned_prefix' => null,
                    'unrecoverable' => false,
                ];

                // Keep peeling — the remainder may still hold more rows.
                return array_merge([$recovered], $this->peelTrailingOrphanRows($remainder, $startNumber + 1));
            }
        }

        // Adopted-school variant, same shape as finalizeRow()'s second
        // branch: "<School> <AdoptedSchool(s)> <Municipality>", no "None".
        $pattern2 = '/^(.*?)\s+(' . $alternation . ')\b(.*)$/is';
        if (preg_match($pattern2, $blob, $m)) {
            $beforeMunicipality = trim($m[1]);
            $remainder = trim($m[3]);

            if (preg_match('/^(\(?[A-Za-z.\'\- ]*?(?:ES|MES|PS)\)?)\s+(.+)$/s', $beforeMunicipality, $sm)) {
                $school = trim($sm[1]);
                $adopted = trim($sm[2]);
            } else {
                $school = $beforeMunicipality;
                $adopted = null;
            }

            // Guard: if there's nothing resembling a school name before
            // the municipality match, this isn't a real row boundary —
            // fall through to the unrecoverable placeholder below instead
            // of fabricating a row with no school name.
            if ($school !== '') {
                $recovered = [
                    'number' => $startNumber,
                    'school' => $this->normalizeSchoolName($school),
                    'adopted' => $adopted,
                    'municipality' => $this->canonicalMunicipality($m[2], $sorted),
                    'orphaned_prefix' => null,
                    'unrecoverable' => false,
                ];

                return array_merge([$recovered], $this->peelTrailingOrphanRows($remainder, $startNumber + 1));
            }
        }

        // Nothing recognizable left to peel off — this is genuinely
        // unreadable, so surface it as a single placeholder rather than
        // silently discarding it or misattributing it to a school name
        // that isn't really there.
        return [[
            'number' => $startNumber,
            'school' => null,
            'adopted' => null,
            'municipality' => null,
            'orphaned_prefix' => null,
            'unrecoverable' => true,
        ]];
    }

    /**
     * The combined-alternation match in finalizeRow() is case-insensitive
     * (/i), so $matchedText preserves whatever case the OCR buffer had.
     * Map it back to the canonically-cased entry from MUNICIPALITIES for
     * consistent output.
     */
    private function canonicalMunicipality(string $matchedText, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (strcasecmp($candidate, $matchedText) === 0) {
                return $candidate;
            }
        }
        return $matchedText;
    }

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
}