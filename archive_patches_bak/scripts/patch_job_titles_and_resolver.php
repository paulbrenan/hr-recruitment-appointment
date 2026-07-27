<?php
/**
 * Patch: add confirmed-missing canonical job titles, and make title
 * resolution tolerant of OCR word-splitting (e.g. "Farm Worker I" vs
 * "Farmworker I") without needing a duplicate list entry for it.
 *
 * Run once from the project root:
 *   php patch_job_titles_and_resolver.php
 * Then delete this file.
 *
 * config/job_titles.php additions (all confirmed present in real memos,
 * confirmed NOT already covered by any existing entry or by
 * PositionBlockDetector's existing dash-suffix/prefix-stripping):
 *   - Special Education Teacher I (SNED)   [OSDS-2025-0087]
 *   - Special Science Teacher I (SPST I)   [OSDS-2025-0150]
 *   - Medical Officer II                   [OSDS-2025-0132, OSDS-2026-0014]
 *   - Chief Education Supervisor - CID     [OSDS-2025-0169] (confirmed real/distinct by user)
 *   - Chief Education Supervisor - SGOD    [OSDS-2025-0169] (confirmed real/distinct by user)
 *   - Handicraft Worker II                 [OSDS-2025-0107/0132] (confirmed real/distinct by user)
 *
 * NOT added: "Farm Worker I" (confirmed by user to be the same title as
 * the existing "Farmworker I", just OCR-split into two words). Instead
 * of a duplicate list entry, PositionBlockDetector::resolveTitle() gets
 * a space-insensitive fallback comparison so "Farm Worker I" resolves
 * to the existing "Farmworker I" entry.
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

// ── Adjust these paths if your project layout differs ──────────────────
$configFile   = __DIR__ . '/config/job_titles.php';
$detectorFile = __DIR__ . '/app/Services/PositionBlockDetector.php';

apply_patch($configFile, [
    [
        "        'Chief Education Program Supervisor',\n        'Cloud Engineer',",
        "        'Chief Education Program Supervisor',\n        'Chief Education Supervisor - CID',\n        'Chief Education Supervisor - SGOD',\n        'Cloud Engineer',",
        'add Chief Education Supervisor - CID / SGOD',
    ],
    [
        "        'Handicraft Worker',\n        'Head Teacher I',",
        "        'Handicraft Worker',\n        'Handicraft Worker II',\n        'Head Teacher I',",
        'add Handicraft Worker II',
    ],
    [
        "        'Medical Officer III',",
        "        'Medical Officer II',\n        'Medical Officer III',",
        'add Medical Officer II',
    ],
    [
        "        'Special Education Teacher I',\n        'Special Education Teacher II',",
        "        'Special Education Teacher I',\n        'Special Education Teacher I (SNED)',\n        'Special Education Teacher II',",
        'add Special Education Teacher I (SNED)',
    ],
    [
        "        'Special Science Teacher I',\n        'Teacher I',",
        "        'Special Science Teacher I',\n        'Special Science Teacher I (SPST I)',\n        'Teacher I',",
        'add Special Science Teacher I (SPST I)',
    ],
]);

apply_patch($detectorFile, [
    [
        <<<'OLD'
        // 1. Direct exact match (covers both plain titles AND any
        //    Secondary/Elementary variant already registered previously).
        foreach ($this->canonicalTitles as $canonical) {
            if ($this->normalizeForComparison($canonical) === $normalizedRaw) {
                return ['title' => $canonical, 'was_registered' => false];
            }
            // Also try the suffix-stripped form
            if ($normalizedStripped !== null &&
                $this->normalizeForComparison($canonical) === $normalizedStripped) {
                return ['title' => $canonical, 'was_registered' => false];
            }
        }
OLD,
        <<<'NEW'
        // 1. Direct exact match (covers both plain titles AND any
        //    Secondary/Elementary variant already registered previously).
        foreach ($this->canonicalTitles as $canonical) {
            if ($this->normalizeForComparison($canonical) === $normalizedRaw) {
                return ['title' => $canonical, 'was_registered' => false];
            }
            // Also try the suffix-stripped form
            if ($normalizedStripped !== null &&
                $this->normalizeForComparison($canonical) === $normalizedStripped) {
                return ['title' => $canonical, 'was_registered' => false];
            }
        }

        // 1b. Space-insensitive fallback — confirmed real OCR case:
        // "Farm Worker I" vs. canonical "Farmworker I". Tesseract
        // sometimes splits/joins compound words inconsistently. Compare
        // with ALL whitespace stripped (not just collapsed) as a last
        // resort before falling through to prefix-stripping below, so
        // this doesn't need its own duplicate canonical-list entry.
        $rawNoSpace = preg_replace('/\s+/', '', $normalizedRaw);
        foreach ($this->canonicalTitles as $canonical) {
            $canonicalNoSpace = preg_replace('/\s+/', '', $this->normalizeForComparison($canonical));
            if ($canonicalNoSpace === $rawNoSpace) {
                return ['title' => $canonical, 'was_registered' => false];
            }
        }
NEW,
        'resolveTitle(): add space-insensitive fallback match',
    ],
]);

echo "\nDone. Diff and test an import before deleting this script and its .bak backups.\n";
