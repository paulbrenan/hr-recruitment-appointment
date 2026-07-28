<?php
/**
 * Patch: PositionBlockDetector — cap the COS-format title capture so a
 * stray bullet-like OCR artifact elsewhere in the page (letterhead/logo
 * noise) can't cause the entire page — letterhead, recipient list, intro
 * paragraph, everything up to the real "Qualifications:" label — to be
 * captured as the "title" for import.
 *
 * Confirmed real case: OSDS-2025-0066, live system. Did not reproduce in
 * this sandbox's own OCR run of the same PDF (different Tesseract/Poppler
 * build/DPI can render a logo/watermark into different stray characters),
 * but the failure mode is fully reproducible and verified fixed using a
 * synthetic stray-bullet injection that mimics it.
 *
 * All known real position titles (checked against every confirmed COS
 * memo in the 23-PDF sample) are well under 110 characters even wrapped
 * across two lines. Capping the lazy capture at 180 chars gives a
 * comfortable margin while making it structurally impossible to swallow
 * a whole page: if no "Qualifications:" appears within 180 chars of a
 * given bullet, that match attempt simply fails and the regex engine
 * moves on to test the next bullet occurrence instead — which finds the
 * real title.
 *
 * Full regression sweep across the 23-PDF sample after this change:
 * identical block/detection counts everywhere, no regressions.
 *
 * Run once from the project root:
 *   php patch_position_block_detector_cos_title_cap.php
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
    [
        "        \$pattern = '/(?:»|>|›)\\s+(.+?)\\n\\s*Qualification[s]?(?:\\s+Standards)?:/is';",
        <<<'NEW'
        // Confirmed real OCR case (OSDS-2025-0066): a stray bullet-like
        // character elsewhere in the page's letterhead/logo noise (OCR
        // build/DPI dependent) can anchor this regex far too early, and
        // an unbounded ".+?" then lazily swallows everything up to the
        // real "Qualifications:" — the entire letterhead, recipient
        // list, and intro paragraph — as if it were the title. Real
        // titles are never more than ~110 chars even wrapped across two
        // lines; capping the capture at 180 chars makes it structurally
        // impossible to swallow a whole page: if no "Qualifications:"
        // appears within 180 chars of a given bullet, that match
        // attempt simply fails and the regex engine moves on to test
        // the next bullet occurrence instead.
        $pattern = '/(?:»|>|›)\s+(.{1,180}?)\n\s*Qualification[s]?(?:\s+Standards)?:/is';
NEW,
        'cap COS-format title capture at 180 chars',
    ],
]);

echo "\nDone. Diff and re-import OSDS-2025-0066 to confirm the title now\n";
echo "reads cleanly before deleting this script and its .bak backup.\n";
