<?php
/**
 * Patch v2 for VacancyTableParser: fix parse() stopping at row 6 when rows 7-8
 * are garbled by OCR on the SAME page.
 *
 * The previous patch (parseFromFirstLegibleRow) only helped when an ENTIRE PAGE
 * returned empty rows. But the real problem is within page 5 itself: rows 7 and 8
 * are garbled ("_s" noise), so parse() can't find row 7 and stops — even though
 * rows 9-20 are legible and present right after on the same page.
 *
 * FIX: in parse()'s main while-loop, when findNextRowNumberPosition() can't find
 * the immediate next sequential row number, try jumping ahead up to 10 rows to
 * find the next legible one. This lets the parser skip over 1-2 garbled rows and
 * pick up the table again, rather than giving up entirely.
 *
 * Run from your Laravel project root:
 *   php patch_vacancy_table_parser_v2.php
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

// ── Patch: replace the nextPos lookup inside parse()'s while loop ─────────────
// Find the two-line block that finds nextPos and builds $content, and replace it
// with a version that tries ahead when the immediate next number is garbled.

$oldNextPos = <<<'PHP'
            $nextPos = $this->findNextRowNumberPosition($normalized, $currentNumber + 1, $afterMarker);

            $content = $nextPos === null
                ? substr($normalized, $afterMarker)
                : substr($normalized, $afterMarker, $nextPos - $afterMarker);
PHP;

$newNextPos = <<<'PHP'
            $nextPos = $this->findNextRowNumberPosition($normalized, $currentNumber + 1, $afterMarker);

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

            $content = $nextPos === null
                ? substr($normalized, $afterMarker)
                : substr($normalized, $afterMarker, $nextPos - $afterMarker);
PHP;

if (strpos($contents, $oldNextPos) !== false) {
    $contents = str_replace($oldNextPos, $newNextPos, $contents);
    echo "Applied: parse() now looks ahead up to 10 rows when a row number is garbled\n";
} else {
    fwrite(STDERR, "ERROR: Could not find the nextPos block in parse() to replace.\n");
    fwrite(STDERR, "The file may have changed. Restore from: cp \"$backupPath\" \"$target\"\n");
    exit(1);
}

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
echo "  parse() was stopping at row 6 because rows 7-8 are garbled ('_s' noise)\n";
echo "  and findNextRowNumberPosition() returned null for row 7. Now when the\n";
echo "  immediate next row number is missing, it tries rows +2 through +10 before\n";
echo "  giving up, skipping over the garbled rows and picking up at row 9.\n\n";
echo "Also restart your queue worker if it's running:\n";
echo "  php artisan queue:restart\n";
echo "  php artisan queue:work\n\n";
echo "Then do a fresh PDF upload to test.\n";
