<?php
/**
 * Patch v3 for VacancyTableParser: replace the fixed "+2 to +10" lookahead
 * (from patch v2) with an OPEN-ENDED scan for the next legible row number,
 * whatever it is.
 *
 * Why: v2's lookahead cap of 10 fixed the row 6->9 gap (rows 7-8 garbled),
 * but confirmed real output shows a LATER gap in the same table where the
 * parser still stops early (jumps 6 -> 62 instead of continuing to 89) —
 * meaning some gap between garbled rows is bigger than 10. Rather than
 * raising the cap again (same problem, different number), this patch adds
 * a new helper that scans forward for the FIRST number-like token greater
 * than the current row number, with no fixed window.
 *
 * Safety: to avoid accidentally treating an address/salary number inside a
 * school's continuation text as a new row marker, the scan:
 *   - reuses the same "looks like a row-number starter" pattern already
 *     used elsewhere in this class (digit run followed by _, |, =, (, or
 *     whitespace — the OCR table-border noise pattern), so stray numbers
 *     embedded in prose without that trailing punctuation/space profile
 *     won't match any more than they already could;
 *   - walks candidates in the order they appear in the text and returns
 *     the FIRST one whose value is strictly greater than the current row
 *     number, rather than jumping to a numerically-nearest match anywhere
 *     in the remaining text. This keeps the same left-to-right walk the
 *     parser already relies on.
 *
 * This does NOT change page-boundary behavior: parse() is still called
 * once per page (see parseMultiPage()), so the scan is naturally bounded
 * to the current page's text and can't merge two different pages' tables.
 *
 * Run from your Laravel project root:
 *   php patch_vacancy_table_parser_v3.php
 */

$target = __DIR__ . '/app/Services/VacancyTableParser.php';

if (!file_exists($target)) {
    fwrite(STDERR, "ERROR: Could not find $target\nRun from your Laravel project root.\n");
    exit(1);
}

$contents = file_get_contents($target);

$backupPath = $target . '.bak_' . date('Ymd_His');
copy($target, $backupPath);
echo "Backed up: $backupPath\n";

// ── Patch 1: replace the fixed-window lookahead loop with an open-ended scan ──

$oldLookahead = <<<'PHP'
            // If the immediate next row number is missing (OCR garbled it, e.g. "7"
            // rendered as "_s" noise), try looking ahead up to 10 rows to find the
            // next legible row number. This lets the parser skip over 1-2 garbled
            // rows and continue the table rather than stopping entirely.
            // Confirmed real case: rows 7 and 8 in this PDF garble to "_s", but
            // row 9 is legible and appears right after on the same OCR page.
            if ($nextPos === null) {
                for ($lookahead = 2; $lookahead <= 10; $lookahead++) {
                    $candidatePos = $this->findNextRowNumberPosition(
                        $normalized,
                        $currentNumber + $lookahead,
                        $afterMarker
                    );
                    if ($candidatePos !== null) {
                        $nextPos = $candidatePos;
                        break;
                    }
                }
            }
PHP;

$newLookahead = <<<'PHP'
            // If the immediate next row number is missing (OCR garbled it), fall
            // back to an open-ended scan for the next legible row number, however
            // far ahead it is. A fixed lookahead window isn't enough — confirmed
            // real output has gaps bigger than 10 rows later in the same table.
            if ($nextPos === null) {
                $nextPos = $this->findNextLegibleRowNumberPosition($normalized, $currentNumber, $afterMarker);
            }
PHP;

if (strpos($contents, $oldLookahead) === false) {
    fwrite(STDERR, "ERROR: Could not find the v2 lookahead block to replace.\n");
    fwrite(STDERR, "Is this file already patched with v2? Restore from: cp \"$backupPath\" \"$target\"\n");
    exit(1);
}
$contents = str_replace($oldLookahead, $newLookahead, $contents);
echo "Applied: parse() now falls back to an open-ended scan instead of a fixed +10 window\n";

// ── Patch 2: add the new helper method right after findNextRowNumberPosition ──

$anchor = <<<'PHP'
    private function findNextRowNumberPosition(string $text, int $expectedNumber, int $from): ?int
    {
        $pattern = '/(?<![\d.\-])' . $expectedNumber . '(?=\s*[_|=(\s])/';
        if (preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE, $from)) {
            return $m[0][1];
        }
        return null;
    }
PHP;

if (strpos($contents, $anchor) === false) {
    fwrite(STDERR, "ERROR: Could not find findNextRowNumberPosition() to anchor the new helper after.\n");
    fwrite(STDERR, "Restore from: cp \"$backupPath\" \"$target\"\n");
    exit(1);
}

$newHelper = $anchor . "\n\n" . <<<'PHP'
    /**
     * Open-ended fallback for findNextRowNumberPosition(): used when the
     * immediate next sequential row number can't be found (OCR garbled it).
     * Scans forward from $from for every number-like token that looks like
     * a row-number starter (same "digit run followed by table-border noise
     * or whitespace" shape used elsewhere in this class), walks them in the
     * order they appear in the text, and returns the position of the FIRST
     * one whose value is strictly greater than $currentNumber.
     *
     * No fixed window: this replaces the old +2..+10 lookahead loop, which
     * was confirmed too small for gaps later in the same table.
     */
    private function findNextLegibleRowNumberPosition(string $text, int $currentNumber, int $from): ?int
    {
        if (!preg_match_all(
            '/(?<![\d.\-])(\d{1,3})(?=\s*[_|=(\s])/',
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
                return $offset;
            }
        }

        return null;
    }
PHP;

$contents = str_replace($anchor, $newHelper, $contents);
echo "Applied: added findNextLegibleRowNumberPosition() helper\n";

// Sanity check braces
$open  = substr_count($contents, '{');
$close = substr_count($contents, '}');
if ($open !== $close) {
    fwrite(STDERR, "ERROR: Brace mismatch ($open open vs $close close). Not writing file.\n");
    exit(1);
}

file_put_contents($target, $contents);
echo "\nDone. Patched: $target\n";
echo "Backup: $backupPath\n\n";
echo "What this fixes:\n";
echo "  v2's lookahead was capped at +10 rows, which wasn't enough for a later,\n";
echo "  bigger gap of garbled row numbers in the same table (parser stopped at\n";
echo "  row 62 instead of continuing to row 89). Now it scans forward with no\n";
echo "  fixed cap for the next row number that's legible and greater than the\n";
echo "  current one.\n\n";
echo "Also restart your queue worker if it's running:\n";
echo "  php artisan queue:restart\n";
echo "  php artisan queue:work\n\n";
echo "Then do a fresh PDF upload to test.\n";
