<?php

/**
 * patch_import_detection_fixes.php
 *
 * WHAT THIS DOES:
 *   Two fixes:
 *
 *   A. PositionBlockDetector::findPositionHeadings()
 *      OSDS-073 has "A.  SECONDARY SCHOOL PRINCIPAL III (SG-21)" as the
 *      FIRST heading, but only Elementary (block B) was detected.
 *      Root cause: the multiline pattern anchor `^` matches start-of-line,
 *      but pdftotext sometimes outputs the heading with leading whitespace
 *      (e.g. "  A.  SECONDARY...") — a line that starts with spaces won't
 *      match `^[A-Z]\.` because `^` in /m mode matches after a newline
 *      but the first non-newline character is a space, not [A-Z].
 *      Fix: change `^[A-Z]\.` to `^\s*[A-Z]\.` to tolerate leading spaces.
 *
 *      Secondary cause: the canonical list has "Secondary School Principal III"
 *      but the PDF has "SECONDARY SCHOOL PRINCIPAL III" — normalizeForComparison()
 *      already lowercases both, so this should match. Verified the real issue
 *      is the leading whitespace on the heading line.
 *
 *   B. review.blade.php — "To be determined" pre-fill
 *      When place_of_assignment_parsed is null because the value is
 *      "To be determined", the location input is shown blank.
 *      Fix: pre-fill with "To be determined" as the input value so HR
 *      sees what the PDF said and can either leave it or type a real school.
 *      Also show a distinct placeholder style (yellow border) to signal
 *      it needs attention.
 *
 * HOW TO RUN:
 *   php patch_import_detection_fixes.php    (from project root)
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

echo "\n=== patch_import_detection_fixes.php ===\n\n";

// ─── A. findPositionHeadings() — tolerate leading whitespace ─────────────

echo "[A] Patching findPositionHeadings() — tolerate leading whitespace on heading lines...\n";

$detectorPath = ROOT . '/app/Services/PositionBlockDetector.php';

apply_patch(
    $detectorPath,
    '        // Allow optional "*" before title (marks new positions in some memos).
        // Title may have an en-dash role suffix like "– Supply Officer I" which
        // we strip during resolution but keep for display.
        $pattern = \'/^[A-Z]\\.\s+\\*?((?i)[A-Za-z][A-Za-z\\s.,\\\'\\-\\x{2013}\\x{2014}]+?)\\s*\\((?i)sg-?\\s*(\\d{1,2})\\)/mu\';',
    '        // Allow optional "*" before title (marks new positions in some memos).
        // Title may have an en-dash role suffix like "– Supply Officer I" which
        // we strip during resolution but keep for display.
        // ^\s* tolerates leading whitespace — pdftotext sometimes indents
        // heading lines slightly, which breaks ^[A-Z]\. in /m mode.
        $pattern = \'/^\\s*[A-Z]\\.\s+\\*?((?i)[A-Za-z][A-Za-z\\s.,\\\'\\-\\x{2013}\\x{2014}]+?)\\s*\\((?i)sg-?\\s*(\\d{1,2})\\)/mu\';',
    'findPositionHeadings(): ^\s* tolerates leading whitespace on heading lines'
);

// ─── B. review.blade.php — pre-fill "To be determined" ──────────────────

echo "\n[B] Patching review.blade.php — pre-fill 'To be determined' location...\n";

$reviewPath = ROOT . '/resources/views/job-postings/import/review.blade.php';

// The location input currently uses $loc['school'] ?? '' as value,
// and shows a blank input when school is null (TBD case).
// We change the value logic: if school is null AND the original
// place_of_assignment was "To be determined", show that text with
// a yellow border so HR knows it needs attention.
//
// The parsed_locations array in the review controller sets:
//   'school' => null  when place_of_assignment_parsed is null (TBD or unrecoverable)
//   'unrecoverable' => true  only for genuinely unreadable rows
//
// For TBD rows: school=null, unrecoverable=false
// We need to distinguish TBD from unrecoverable — add a 'tbd' flag
// in the review controller, or check the original row data.
// Simpler: patch the review blade to check if school is null AND
// unrecoverable is false → show "To be determined".

apply_patch(
    $reviewPath,
    '                                            <input
                                                type="text"
                                                class="form-control form-control-sm location-import-input {{ $loc[\'unrecoverable\'] ? \'border-warning\' : \'\' }}"
                                                name="rows[{{ $i }}][location_place][]"
                                                autocomplete="off"
                                                placeholder="{{ $loc[\'unrecoverable\'] ? \'Row \' . $loc[\'row_number\'] . \' unreadable — type school name manually\' : \'Search or type a school...\' }}"
                                                value="{{ $loc[\'school\'] ?? \'\' }}"
                                            >',
    '                                            @php
                                                $isTbd = ($loc[\'school\'] === null && !$loc[\'unrecoverable\']);
                                                $locValue = $isTbd ? \'To be determined\' : ($loc[\'school\'] ?? \'\');
                                                $locClass = $loc[\'unrecoverable\'] ? \'border-warning\' : ($isTbd ? \'border-warning text-muted\' : \'\');
                                                $locPlaceholder = $loc[\'unrecoverable\']
                                                    ? \'Row \' . $loc[\'row_number\'] . \' unreadable — type school name manually\'
                                                    : ($isTbd ? \'To be determined — update if school is now known\' : \'Search or type a school...\');
                                            @endphp
                                            <input
                                                type="text"
                                                class="form-control form-control-sm location-import-input {{ $locClass }}"
                                                name="rows[{{ $i }}][location_place][]"
                                                autocomplete="off"
                                                placeholder="{{ $locPlaceholder }}"
                                                value="{{ $locValue }}"
                                            >',
    'review.blade.php: pre-fill "To be determined" in location input with yellow border'
);

echo <<<TEXT

✅ Done. No migration needed.

WHAT WAS FIXED:

A. Leading whitespace on heading lines (OSDS-073 Secondary missing)
   pdftotext sometimes outputs "  A.  SECONDARY SCHOOL PRINCIPAL III (SG-21)"
   with leading spaces. The old pattern ^[A-Z]\. requires [A-Z] as the very
   first character after a newline in /m mode, so the indented heading was
   silently skipped. Changed to ^\s*[A-Z]\. — now both A. and B. headings
   are found regardless of leading whitespace.

B. "To be determined" pre-fill on review screen
   When a position's place of assignment is "To be determined" in the PDF,
   the review screen now shows that text in the location input with a yellow
   border, instead of leaving it blank. HR can leave it as-is or type the
   real school if it's now known. The yellow border signals it needs attention
   (same visual treatment as unreadable rows, but with different placeholder
   text).

DELETE this script after running.

TEXT;
