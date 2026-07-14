<?php
/**
 * patch_criteria_scan_fix3.php
 *
 * DepEd Cavite uses more than one official CAR template, and they don't
 * all share the same 8 criteria or the same weights:
 *
 *   - The school-admin CAR (already handled): Education, Training,
 *     Experience, Performance (25 pts), Outstanding Accomplishments,
 *     Application of Education, Application of Learning and
 *     Development, Potential.
 *
 *   - The teaching-position CAR ("Annex I-2", PPST-based): Education,
 *     Training, Experience, Performance (30 pts -- different weight!),
 *     PPST COIs (Classroom Observation/Demo Teaching, 25 pts), PPST
 *     NCOIs (Portfolio Annotation and BEI, 15 pts). No Potential,
 *     Outstanding Accomplishments, or Application of ... criteria at
 *     all in this one.
 *
 * Without this patch, scanning the Annex I-2 template would add wrong
 * data: Performance would get the school-admin form's 25pt weight
 * instead of its own 30pts, and "PPST COIs"/"PPST NCOIs" wouldn't be
 * recognized as criteria at all, silently dropping 40% of the total
 * weight.
 *
 * This adds "PPST COIs" and "PPST NCOIs" to the recognized catalog
 * (their own label text is contiguous in the extracted text even
 * though the surrounding parenthetical descriptions get scrambled by
 * the narrow multi-column table, so a plain substring match on just
 * "ppst cois" / "ppst ncois" is reliable), and makes Performance's
 * weight profile-aware: 30 if a PPST criterion was also found in the
 * same file, 25 otherwise (unchanged behavior for the school-admin
 * form).
 *
 * Run from your Laravel project root:
 *   php patch_criteria_scan_fix3.php
 *
 * Requires patch_criteria_scan_fix.php to already be applied (this
 * patch edits the same matchCriteriaCatalog() method that one left
 * untouched, but assumes its accomplishm/ents fix is already there).
 * Creates a .bak3 backup before editing. Aborts with no changes if the
 * expected current code isn't found.
 */

$target = __DIR__ . '/app/Http/Controllers/AssessmentController.php';

if (!file_exists($target)) {
    fwrite(STDERR, "ABORT: Could not find {$target}\n");
    fwrite(STDERR, "Run this script from your Laravel project root.\n");
    exit(1);
}

$original = file_get_contents($target);

// ── Guard + patch 1: add PPST COIs / NCOIs to the multi-word catalog ──────
$oldMultiWord = <<<'PHP'
        $multiWord = [
            ['patterns' => ['application of learning and development', 'application of l&d', 'application of l & d'], 'name' => 'Application of Learning and Development', 'weight' => 10],
            ['patterns' => ['application of education'], 'name' => 'Application of Education', 'weight' => 10],
            ['patterns' => ['outstanding accomplishments', 'outstanding accomplishment'], 'name' => 'Outstanding Accomplishments', 'weight' => 10],
        ];
PHP;

$newMultiWord = <<<'PHP'
        $multiWord = [
            ['patterns' => ['application of learning and development', 'application of l&d', 'application of l & d'], 'name' => 'Application of Learning and Development', 'weight' => 10],
            ['patterns' => ['application of education'], 'name' => 'Application of Education', 'weight' => 10],
            ['patterns' => ['outstanding accomplishments', 'outstanding accomplishment'], 'name' => 'Outstanding Accomplishments', 'weight' => 10],
            ['patterns' => ['ppst cois'], 'name' => 'PPST COIs (Classroom Observation/Demo Teaching)', 'weight' => 25],
            ['patterns' => ['ppst ncois'], 'name' => 'PPST NCOIs (Portfolio Annotation and BEI)', 'weight' => 15],
        ];
PHP;

if (!str_contains($original, $oldMultiWord)) {
    fwrite(STDERR, "ABORT: the multi-word criteria catalog didn't match the expected code.\n");
    fwrite(STDERR, "No changes made. Make sure patch_criteria_scan_fix.php has already been\n");
    fwrite(STDERR, "run, or this file has changed since.\n");
    exit(1);
}

// ── Guard + patch 2: make Performance's weight profile-aware ──────────────
$oldSingleWord = <<<'PHP'
        $singleWord = [
            'performance' => ['Performance', 25],
            'experience'  => ['Experience', 10],
            'training'    => ['Training', 10],
            'potential'   => ['Potential', 15],
            'education'   => ['Education', 10],
        ];
PHP;

$newSingleWord = <<<'PHP'
        // The teaching-position CAR (PPST-based, e.g. Annex I-2) weights
        // Performance at 30 instead of the school-admin form's 25, since
        // its 100-point total is split differently between criteria.
        $isTeachingProfile = isset($result['PPST COIs (Classroom Observation/Demo Teaching)'])
            || isset($result['PPST NCOIs (Portfolio Annotation and BEI)']);

        $singleWord = [
            'performance' => ['Performance', $isTeachingProfile ? 30 : 25],
            'experience'  => ['Experience', 10],
            'training'    => ['Training', 10],
            'potential'   => ['Potential', 15],
            'education'   => ['Education', 10],
        ];
PHP;

if (!str_contains($original, $oldSingleWord)) {
    fwrite(STDERR, "ABORT: the single-word criteria catalog didn't match the expected code.\n");
    fwrite(STDERR, "No changes made.\n");
    exit(1);
}

// ── Apply ───────────────────────────────────────────────────────────────────
$backup = $target . '.bak3';
if (!copy($target, $backup)) {
    fwrite(STDERR, "ABORT: Could not create backup at {$backup}\n");
    exit(1);
}

$patched = str_replace($oldMultiWord, $newMultiWord, $original);
$patched = str_replace($oldSingleWord, $newSingleWord, $patched);

file_put_contents($target, $patched);

echo "Patched: {$target}\n";
echo "Backup saved to: {$backup}\n";
echo "\nWhat changed:\n";
echo "  1. \"PPST COIs\" and \"PPST NCOIs\" are now recognized criteria names\n";
echo "     (25 pts and 15 pts), for the teaching-position / Annex I-2 CAR.\n";
echo "  2. Performance is now weighted 30 pts when a PPST criterion is also\n";
echo "     found in the same file (teaching-position form), and 25 pts\n";
echo "     otherwise (school-admin form) -- instead of always 25.\n";
echo "\nScan the Annex I-2 PDF again -- it should now add all 6 of its\n";
echo "criteria (Education 10, Training 10, Experience 10, Performance 30,\n";
echo "PPST COIs 25, PPST NCOIs 15) instead of 4 with a wrong Performance\n";
echo "weight and 2 silently missing.\n";
