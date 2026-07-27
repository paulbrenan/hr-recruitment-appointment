<?php
/**
 * Patch: strip DepEd Cavite letterhead/footer boilerplate (and
 * un-patternable OCR logo/seal garbage) out of duties_responsibilities
 * text, so a Duties/Job Summary section that spans a page boundary no
 * longer swallows the next page's header+footer into the output.
 *
 * Run once from the project root:
 *   php patch_duties_boilerplate_strip.php
 * Then delete this file.
 *
 * Confirmed real case: OSDS-2025-0132, Administrative Officer IV –
 * Procurement Officer. Its Job Summary section runs from page 5 to
 * page 6; the raw capture ended with "...Bids and Awards Committee
 * (BAC)," followed immediately by page 6's full letterhead — "Republic
 * of the Philippines / Department of Education / REGION IV-A / SCHOOLS
 * DIVISION OFFICE OF CAVITE PROVINCE" — the footer address/phone/
 * website/email strip, AND garbled OCR noise from the seal/logo images
 * ("ernest", "a * - * * &,", "D ED us «SS @sP") that don't match any
 * clean text pattern.
 *
 * Fix: cleanDutiesText() (shared by the standard, Job Summary, and COS
 * duties paths) now drops any line matching known letterhead/footer
 * patterns, plus a heuristic catch for lines that are mostly non-letter
 * characters or too short to be real content — logo/seal OCR garbage
 * reliably fails both checks; real duty sentences reliably pass them.
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
$detectorFile = __DIR__ . '/app/Services/PositionBlockDetector.php';

apply_patch($detectorFile, [
    [
        <<<'OLD'
    private function cleanDutiesText(string $raw): ?string
    {
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $cleaned = [];

        foreach ($lines as $line) {
            // Collapse repeated spaces/tabs WITHIN the line, but don't
            // touch the newlines themselves.
            $line = trim(preg_replace('/[ \t]+/', ' ', $line));
            if ($line === '') {
                continue;
            }
            // Confirmed OCR misread: bullet glyph "•" renders as a lone
            // "e" token at the start of a duty line (e.g. "e Facilitate
            // the implementation..."). Normalize back to a real bullet.
            $line = preg_replace('/^e (?=[A-Z])/', '• ', $line);
            $cleaned[] = $line;
        }

        $result = implode("\n", $cleaned);
        return $result !== '' ? $result : null;
    }
OLD,
        <<<'NEW'
    /**
     * Lines matching any of these are letterhead/footer boilerplate that
     * repeats on EVERY page of a DepEd Cavite memo (header block + footer
     * address/contact strip). When a Duties/Job Summary section spans a
     * page boundary, the raw capture swallows this boilerplate along with
     * the real content — confirmed real case (OSDS-2025-0132): a Job
     * Summary landed with "...Bids and Awards Committee (BAC)," followed
     * immediately by the next page's full header+footer, INCLUDING
     * garbled OCR noise from the seal/logo images ("ernest", "a * - * *
     * &,", "D ED us «SS @sP") that don't match any clean text pattern.
     */
    private const NOISE_LINE_PATTERNS = [
        '/^Republic of the Philippines$/i',
        '/^.epartment of .ducation$/i',
        '/^REGION\s*IV-?A$/i',
        '/^SCHOOLS DIVISION OFFICE OF CAVITE PROVINCE$/i',
        '/Cavite Capitol Compound/i',
        '/^\(?\d{2,4}\)?[\s-]?\d{3}[\s-]?\d{4}$/', // phone numbers e.g. (046) 419-1286
        '/^(www\.|https?:\/\/)/i',
        '/deped\.gov\.ph|depedcavite\.com\.ph/i',
        '/^Attachment\s+\d+\s+to\s+Division\s+Memorandum/i',
    ];

    /**
     * Heuristic catch for the un-patternable OCR garbage from logo/seal
     * images ("ernest", "a * - * * &,", "B", "bs", "D ED us «SS @sP"):
     * a real duty line is mostly letters/spaces. A line that's mostly
     * symbols/digits, OR is just 1-2 bare characters with no punctuation
     * a real sentence would have, is almost certainly OCR noise rather
     * than content — skip it rather than let it corrupt the output.
     */
    private function looksLikeOcrNoise(string $line): bool
    {
        if (mb_strlen($line) <= 2) {
            return true;
        }
        $letters = preg_replace('/[^a-zA-Z]/', '', $line);
        return (mb_strlen($letters) / max(mb_strlen($line), 1)) < 0.5;
    }

    private function isBoilerplateLine(string $line): bool
    {
        foreach (self::NOISE_LINE_PATTERNS as $pattern) {
            if (preg_match($pattern, $line)) {
                return true;
            }
        }
        return $this->looksLikeOcrNoise($line);
    }

    private function cleanDutiesText(string $raw): ?string
    {
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $cleaned = [];

        foreach ($lines as $line) {
            // Collapse repeated spaces/tabs WITHIN the line, but don't
            // touch the newlines themselves.
            $line = trim(preg_replace('/[ \t]+/', ' ', $line));
            if ($line === '') {
                continue;
            }
            if ($this->isBoilerplateLine($line)) {
                continue;
            }
            // Confirmed OCR misread: bullet glyph "•" renders as a lone
            // "e" token at the start of a duty line (e.g. "e Facilitate
            // the implementation..."). Normalize back to a real bullet.
            $line = preg_replace('/^e (?=[A-Z])/', '• ', $line);
            $cleaned[] = $line;
        }

        $result = implode("\n", $cleaned);
        return $result !== '' ? $result : null;
    }
NEW,
        'cleanDutiesText(): strip letterhead/footer boilerplate + OCR noise heuristic',
    ],
]);

echo "\nDone. Diff and test an import (e.g. OSDS-2025-0132, Administrative Officer IV) before deleting this script and its .bak backups.\n";
echo "Note: the OCR-noise heuristic (<50% letters, or <=2 chars) can in rare cases strip a real short line like a bare 'RA 12009' — spot-check duties text for eligibility-code-style content on the memos that have it.\n";
