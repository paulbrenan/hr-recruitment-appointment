<?php
/**
 * One-shot patch: fix VacancyTableParser stopping early when OCR garbles
 * row numbers in the vacancy table.
 *
 * ROOT CAUSE:
 * The Tesseract OCR for this scanned PDF garbles rows 7 and 8's row numbers
 * (renders them as "_s" noise instead of "7" and "8"). So after parsing rows
 * 1-6, findNextRowNumberPosition() can't find "7", stops, and sets
 * nextExpected = 7. Pages 6+ of the PDF start at row 21 -- but parseMultiPage
 * looks for "7" on those pages, finds nothing, and gives up on all remaining
 * pages. Result: only 6 rows out of 89+.
 *
 * FIX:
 * When a page returns empty rows and nextExpected > 1 (meaning some earlier
 * rows were garbled), auto-scan the page for the first row number >= nextExpected
 * that IS legible, and parse from there instead of giving up. This lets the
 * parser skip over garbled rows and pick up the table again wherever OCR
 * quality recovers -- which it does by row 9, then again on pages 6 and 7.
 *
 * Run from your Laravel project root:
 *   php patch_vacancy_table_parser.php
 */

$target = __DIR__ . '/app/Services/VacancyTableParser.php';

if (!file_exists($target)) {
    fwrite(STDERR, "ERROR: Could not find $target\nRun from your Laravel project root.\n");
    exit(1);
}

$contents = file_get_contents($target);
$original = $contents;

$backupPath = $target . '.bak_' . date('Ymd_His');
copy($target, $backupPath);
echo "Backed up: $backupPath\n";

// ── Replace parseMultiPage() with a version that recovers from garbled row numbers
$oldParseMultiPage = <<<'PHP'
    public function parseMultiPage(array $pages): array
    {
        $allRows = [];
        $nextExpected = 1;

        foreach ($pages as $pageText) {
            $rows = $this->parse($pageText, $nextExpected);
            if (empty($rows)) {
                continue;
            }
            $allRows = array_merge($allRows, $rows);
            $nextExpected = max(array_column($rows, 'number')) + 1;
        }

        return $allRows;
    }
PHP;

$newParseMultiPage = <<<'PHP'
    public function parseMultiPage(array $pages): array
    {
        $allRows = [];
        $nextExpected = 1;

        foreach ($pages as $pageText) {
            $rows = $this->parse($pageText, $nextExpected);

            // If no rows found with the strict nextExpected number, OCR probably
            // garbled some row numbers on a previous page (confirmed: rows 7-8 on
            // page 5 of this PDF render as "_s" noise instead of "7" and "8").
            // Try auto-detecting the actual first legible row number on this page
            // that is >= nextExpected, instead of giving up on the whole page.
            if (empty($rows) && $nextExpected > 1) {
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
            '/(?<![\d.\-])(\d{1,3})(?=\s*[_|=(\s])/',
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
                return $this->parse($text, $num);
            }
        }

        return [];
    }
PHP;

if (strpos($contents, $oldParseMultiPage) !== false) {
    $contents = str_replace($oldParseMultiPage, $newParseMultiPage, $contents);
    echo "Applied: replaced parseMultiPage() with garbled-row-number recovery version\n";
    echo "         + added parseFromFirstLegibleRow() helper\n";
} else {
    fwrite(STDERR, "ERROR: Could not find parseMultiPage() block to replace.\n");
    fwrite(STDERR, "The file may have changed since this patch was written.\n");
    fwrite(STDERR, "Restore from backup if needed: cp \"$backupPath\" \"$target\"\n");
    exit(1);
}

// Sanity check braces
$open  = substr_count($contents, '{');
$close = substr_count($contents, '}');
if ($open !== $close) {
    fwrite(STDERR, "ERROR: Brace mismatch after patching ($open open vs $close close). Not writing file.\n");
    exit(1);
}

file_put_contents($target, $contents);
echo "\nDone. Patched: $target\n";
echo "Backup: $backupPath\n\n";
echo "What this fixes:\n";
echo "  Before: parser stopped at row 6 because OCR garbled row 7+8's numbers;\n";
echo "          all subsequent pages returned empty since nextExpected=7 never matched.\n";
echo "  After:  when a page returns no rows, the parser auto-scans for the first\n";
echo "          legible row number on that page and continues from there.\n\n";
echo "Test by uploading the same PDF -- you should now see all 89 AO II rows\n";
echo "and all 41 PDO I rows on the review screen.\n";
