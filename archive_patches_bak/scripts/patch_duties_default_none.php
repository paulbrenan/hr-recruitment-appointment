<?php
/**
 * Patch: when a position block genuinely has no Duties/Responsibilities
 * or Job Summary section in the source memo, default
 * duties_responsibilities to the literal string "None" instead of null.
 *
 * Run once from the project root:
 *   php patch_duties_default_none.php
 * Then delete this file.
 *
 * Confirmed real case: OSDS-2025-0150 ("Call for Application for Various
 * Higher Teaching Positions in Senior High Schools" — Master Teacher II,
 * Master Teacher I x2, Special Science Teacher I). None of its four
 * positions have a duties/responsibilities section at all; the memo goes
 * straight from Qualification Standards / Place of Assignment to a
 * Performance Requirements rubric table instead. This is a genuinely
 * empty field in the source document, not a parse failure — but with
 * null, the review screen's missing-field flag (red outline) treated it
 * identically to a real extraction miss, making HR think something needs
 * fixing when there's simply nothing there to fix.
 *
 * Fix: extractDuties() and parseCosBlock() both default to 'None'
 * instead of null when no duties-style label is found in the block at
 * all. (A label that IS found but cleans down to nothing — e.g. all
 * lines were boilerplate/noise — also now resolves to 'None' rather than
 * null, via the `?? 'None'` fallback on cleanDutiesText()'s result.)
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
        if (preg_match('/Duties and Responsibilities(?: OF [A-Z\s]+)?:?\s*(.*)/is', $blockText, $m)) {
            return $this->cleanDutiesText($m[1]);
        }

        if (preg_match('/Job Summary:?\s*(.*)/is', $blockText, $m)) {
            return $this->cleanDutiesText($m[1]);
        }

        return null;
    }
OLD,
        <<<'NEW'
        if (preg_match('/Duties and Responsibilities(?: OF [A-Z\s]+)?:?\s*(.*)/is', $blockText, $m)) {
            return $this->cleanDutiesText($m[1]) ?? 'None';
        }

        if (preg_match('/Job Summary:?\s*(.*)/is', $blockText, $m)) {
            return $this->cleanDutiesText($m[1]) ?? 'None';
        }

        // Confirmed real case (OSDS-2025-0150): teaching-position memos
        // (Master Teacher I/II, Special Science Teacher I) have NO
        // duties/responsibilities section at all — nothing follows
        // Qualification Standards / Place of Assignment except a
        // Performance Requirements rubric table. There is genuinely
        // nothing to extract here, as opposed to a section that exists
        // but failed to parse — so this returns the literal string
        // "None" rather than null, so the review screen doesn't flag it
        // as a missing field HR needs to go fix.
        return 'None';
    }
NEW,
        'extractDuties(): default to "None" instead of null when no duties label exists at all',
    ],
    [
        <<<'OLD'
        $dutiesStopLookahead = '(?=\n\s*\d+\.\s|Interested and qualified applicants|Mandatory Requirements|Additional Requirements|Checklist of Requirements|\z)';
        $duties = null;
        if (preg_match(
            '/Duties and Responsibilities:?\s*(.*?)' . $dutiesStopLookahead . '/is',
            $blockText, $m
        )) {
            $duties = $this->cleanDutiesText($m[1]);
        } elseif (preg_match(
            '/Terms of Reference:?\s*(.*?)' . $dutiesStopLookahead . '/is',
            $blockText, $m
        )) {
            $duties = $this->cleanDutiesText($m[1]);
        }
OLD,
        <<<'NEW'
        $dutiesStopLookahead = '(?=\n\s*\d+\.\s|Interested and qualified applicants|Mandatory Requirements|Additional Requirements|Checklist of Requirements|\z)';
        // Default 'None' rather than null when neither label is present —
        // consistent with extractDuties() above: a section that's genuinely
        // absent from the source isn't a parse failure HR needs to fix.
        $duties = 'None';
        if (preg_match(
            '/Duties and Responsibilities:?\s*(.*?)' . $dutiesStopLookahead . '/is',
            $blockText, $m
        )) {
            $duties = $this->cleanDutiesText($m[1]) ?? 'None';
        } elseif (preg_match(
            '/Terms of Reference:?\s*(.*?)' . $dutiesStopLookahead . '/is',
            $blockText, $m
        )) {
            $duties = $this->cleanDutiesText($m[1]) ?? 'None';
        }
NEW,
        'parseCosBlock(): default to "None" instead of null when no duties label exists at all',
    ],
]);

echo "\nDone. Diff and test an import (e.g. OSDS-2025-0150) before deleting this script and its .bak backups.\n";
