<?php
/**
 * ONE-SHOT PATCH: fixes 5 confirmed bugs in the PDF import pipeline,
 * found by running PositionBlockDetector + VacancyTableParser against
 * real OCR'd output of OSDS-2026-DM-0059, OSDS-2026-DM-073, and
 * SGOD-2026-DM-0079.
 *
 * Bugs fixed:
 *  1. PositionBlockDetector::resolveTitle() — dash-suffix stripping
 *     required whitespace on BOTH sides of the dash. Real OCR glues the
 *     dash to the preceding word ("ADMINISTRATIVE AIDE III- CLERK I"),
 *     so the whole position silently dropped from the import.
 *  2. PositionBlockDetector::normalizeForComparison() +
 *     buildDisplayTitle() — "III" OCR-misread as "Hil" wasn't in the
 *     Roman-numeral correction list, so "SECONDARY SCHOOL PRINCIPAL
 *     Hil (SG-21)" was silently dropped entirely.
 *  3. PositionBlockDetector::parseBlock() — vacancy-count regex only
 *     matched the plural "Positions", not singular "Position" (which
 *     extractLabeledField()'s stopLabels list already anticipated but
 *     this regex didn't), so some real blocks got vacancies = null.
 *  4. PositionBlockDetector::extractPlaceOfAssignment() — only the
 *     LAST page of a block's table-page range was truncated (at the
 *     duties heading). The FIRST page was never truncated at the
 *     block's own heading offset, so when a block starts partway
 *     through a page (right after the previous block's trailing duty
 *     text), that leftover text bled into the table parser and
 *     corrupted early rows.
 *  5. VacancyTableParser::cleanArtifacts() — "None" (the structural
 *     anchor row-splitting depends on) occasionally OCR's as "Nome",
 *     causing that row to fall through to the wrong parsing branch.
 *
 * Usage: drop this file in the Laravel project root, run:
 *   php patch_pdf_import_fixes.php
 * then delete it. Backs up both files first; verifies exact string
 * matches before patching (aborts with no changes made if anything
 * doesn't match, so it's safe to run against a drifted copy — it just
 * won't apply).
 */

$targets = [
    'app/Services/PositionBlockDetector.php' => [
        [
            'find' => "        \$strippedSuffix = preg_replace('/\\s+[\\x{2013}\\x{2014}\\-]{1,2}\\s+.+\$/u', '', \$rawTitle);",
            'replace' => "        // NOTE: leading whitespace before the dash is OPTIONAL (\\s* not \\s+).\n"
                . "        // Confirmed real OCR case: \"ADMINISTRATIVE AIDE III- CLERK I\" has no\n"
                . "        // space before the dash (though there is one after it). Requiring\n"
                . "        // \\s+ on both sides silently failed to strip the suffix, which made\n"
                . "        // the title fail canonical matching entirely and drop the whole\n"
                . "        // position from the import with no error. Whitespace AFTER the dash\n"
                . "        // stays mandatory so genuine no-space compound words aren't split.\n"
                . "        \$strippedSuffix = preg_replace('/\\s*[\\x{2013}\\x{2014}\\-]{1,2}\\s+.+\$/u', '', \$rawTitle);",
        ],
        [
            'find' => "        // Fix common OCR Roman-numeral misreads before comparing.\n"
                . "        \$text = preg_replace('/\\bIll\\b/', 'III', \$text);\n"
                . "        \$text = preg_replace('/\\bll\\b/', 'II', \$text);\n"
                . "        \$text = preg_replace('/\\bl\\b/', 'I', \$text);",
            'replace' => "        // Fix common OCR Roman-numeral misreads before comparing.\n"
                . "        // \"Hil\" confirmed real case: \"SECONDARY SCHOOL PRINCIPAL Hil (SG-21)\"\n"
                . "        // — tesseract misread \"III\" as \"Hil\", which caused this position to\n"
                . "        // fail canonical matching (even via the prefix-strip path) and be\n"
                . "        // silently dropped from the import entirely.\n"
                . "        \$text = preg_replace('/\\bHil\\b/', 'III', \$text);\n"
                . "        \$text = preg_replace('/\\bIll\\b/', 'III', \$text);\n"
                . "        \$text = preg_replace('/\\bll\\b/', 'II', \$text);\n"
                . "        \$text = preg_replace('/\\bl\\b/', 'I', \$text);",
        ],
        [
            'find' => "        \$vacancies = null;\n"
                . "        if (preg_match('/Number of Vacant Positions:?\\s*(\\d+)/i', \$blockText, \$m)) {\n"
                . "            \$vacancies = (int) \$m[1];\n"
                . "        }",
            'replace' => "        // \"Position[s]?\" — real OCR renders this both plural (\"Number of\n"
                . "        // Vacant Positions: 1\") and singular (\"Number of Vacant Position: 1\")\n"
                . "        // depending on the memo. extractLabeledField()'s stopLabels list\n"
                . "        // already anticipated both forms (see the two entries above), but\n"
                . "        // this regex only matched the plural, so singular-form blocks\n"
                . "        // silently got vacancies = null. The COS-format parser below\n"
                . "        // (parseCosBlock) already handled this correctly with Position[s]? —\n"
                . "        // this was the one path that got missed.\n"
                . "        \$vacancies = null;\n"
                . "        if (preg_match('/Number of Vacant Positions?:?\\s*(\\d+)/i', \$blockText, \$m)) {\n"
                . "            \$vacancies = (int) \$m[1];\n"
                . "        }",
        ],
        [
            'find' => "    private function buildDisplayTitle(string \$rawTitle, string \$canonicalTitle): string\n"
                . "    {\n"
                . "        \$fixed = preg_replace('/\\bIll\\b/', 'III', \$rawTitle);",
            'replace' => "    private function buildDisplayTitle(string \$rawTitle, string \$canonicalTitle): string\n"
                . "    {\n"
                . "        // Same OCR misread fixups as normalizeForComparison() — this method\n"
                . "        // builds the string that actually gets registered as a permanent\n"
                . "        // canonical title, so it needs to be corrected too, not just used\n"
                . "        // for matching. Without this, \"Hil\" (OCR misread of \"III\") slipped\n"
                . "        // through matching fine but got registered verbatim as a new bogus\n"
                . "        // canonical title (\"Secondary School Principal Hil\").\n"
                . "        \$fixed = preg_replace('/\\bHil\\b/', 'III', \$rawTitle);\n"
                . "        \$fixed = preg_replace('/\\bIll\\b/', 'III', \$fixed);",
        ],
        [
            'find' => "        \$schools = \$this->tableParser->parseMultiPage(\$pageTextsOnly);\n\n"
                . "        return ['type' => 'table', 'schools' => \$schools];",
            'replace' => "        // IMPORTANT (mirror of the trailing-page fix above, same root cause):\n"
                . "        // a block's own table can also start partway through its FIRST\n"
                . "        // relevant page, not just end partway through its last one.\n"
                . "        // Confirmed real case: \"B. PROJECT DEVELOPMENT OFFICER I (SG-11)\"\n"
                . "        // begins on the same physical page as the PREVIOUS block's trailing\n"
                . "        // duty bullets (\"...Perform other functions as may be assigned by\n"
                . "        // the School Head.\" sits right above this block's own heading on\n"
                . "        // that page). Without this, the previous block's leftover duty text\n"
                . "        // is passed through unmodified as this block's \"first page,\" and it\n"
                . "        // isn't covered by any noise-phrase pattern (it's real position-\n"
                . "        // specific content, not boilerplate) — so it corrupts the table\n"
                . "        // parser's row detection, gluing unrelated duty text onto one of\n"
                . "        // this block's early rows. Truncate the first page's text to start\n"
                . "        // at this block's own heading offset. This must run AFTER the\n"
                . "        // trailing-page truncation above (not before), so that when a block\n"
                . "        // fits entirely on one page (startPage === endPage), the trailing\n"
                . "        // cut is computed against the page's original, untruncated text —\n"
                . "        // truncating the front first would shift offsets out from under it.\n"
                . "        if (!empty(\$pageTextsOnly)) {\n"
                . "            \$startPageStart = \$this->pageStartOffset(\$startPage, \$pageBoundaries);\n"
                . "            \$startRelativeOffset = \$blockStartOffset - \$startPageStart;\n"
                . "            if (\$startRelativeOffset > 0 && \$startRelativeOffset < strlen(\$pageTextsOnly[0])) {\n"
                . "                \$pageTextsOnly[0] = substr(\$pageTextsOnly[0], \$startRelativeOffset);\n"
                . "            }\n"
                . "        }\n\n"
                . "        \$schools = \$this->tableParser->parseMultiPage(\$pageTextsOnly);\n\n"
                . "        return ['type' => 'table', 'schools' => \$schools];",
        ],
    ],
    'app/Services/VacancyTableParser.php' => [
        [
            'find' => "        \$text = preg_replace('/\\x{2014}+/u', ' ', \$text);              // remaining em-dashes\n"
                . "        \$text = preg_replace('/\\x{2013}+/u', ' ', \$text);              // remaining en-dashes\n\n"
                . "        // Collapse stray table-border characters and extra whitespace.",
            'replace' => "        \$text = preg_replace('/\\x{2014}+/u', ' ', \$text);              // remaining em-dashes\n"
                . "        \$text = preg_replace('/\\x{2013}+/u', ' ', \$text);              // remaining en-dashes\n\n"
                . "        // Confirmed real OCR misread: \"None\" (the Adopted School column's\n"
                . "        // usual value) occasionally comes through as \"Nome\". \"None\" is the\n"
                . "        // structural anchor finalizeRow()/peelTrailingOrphanRows() search\n"
                . "        // for to find each row's school/municipality boundary, so a missed\n"
                . "        // \"Nome\" doesn't just corrupt one cell — it makes the whole row fall\n"
                . "        // through to the adopted-school-variant branch and misreads \"Nome\"\n"
                . "        // itself as part of the adopted-school name.\n"
                . "        \$text = preg_replace('/\\bNome\\b/', 'None', \$text);\n\n"
                . "        // Collapse stray table-border characters and extra whitespace.",
        ],
    ],
];

$root = __DIR__;
$errors = [];
$plan = [];

// ---- Verify phase: every find-string must match exactly once before ANY file is touched.
foreach ($targets as $relPath => $edits) {
    $fullPath = $root . '/' . $relPath;
    if (!file_exists($fullPath)) {
        $errors[] = "MISSING FILE: $relPath";
        continue;
    }
    $contents = file_get_contents($fullPath);
    foreach ($edits as $i => $edit) {
        $count = substr_count($contents, $edit['find']);
        if ($count === 0) {
            $errors[] = "$relPath edit #$i: find-string NOT FOUND (file may have drifted — paste current content for a fresh patch)";
        } elseif ($count > 1) {
            $errors[] = "$relPath edit #$i: find-string matched $count times (expected exactly 1 — ambiguous, refusing to guess)";
        }
    }
    $plan[$relPath] = $contents;
}

if (!empty($errors)) {
    echo "ABORTED — no files were modified. Problems:\n";
    foreach ($errors as $e) {
        echo "  - $e\n";
    }
    exit(1);
}

// ---- Backup phase
$backupDir = $root . '/.patch_backup_pdf_import_fixes_' . date('Ymd_His');
mkdir($backupDir, 0777, true);
foreach ($targets as $relPath => $edits) {
    $fullPath = $root . '/' . $relPath;
    $backupPath = $backupDir . '/' . str_replace('/', '__', $relPath);
    copy($fullPath, $backupPath);
    echo "Backed up $relPath -> $backupPath\n";
}

// ---- Patch phase
foreach ($targets as $relPath => $edits) {
    $fullPath = $root . '/' . $relPath;
    $contents = $plan[$relPath];
    foreach ($edits as $edit) {
        $contents = str_replace($edit['find'], $edit['replace'], $contents);
    }
    file_put_contents($fullPath, $contents);
    echo "Patched $relPath (" . count($edits) . " edit" . (count($edits) === 1 ? '' : 's') . ")\n";
}

echo "\nDone. Backups in: $backupDir\n";
echo "Delete this patch script once you've confirmed the results.\n";
