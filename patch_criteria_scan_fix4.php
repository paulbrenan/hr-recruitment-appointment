<?php
/**
 * patch_criteria_scan_fix4.php
 *
 * DepEd Cavite has (at least) four distinct official CAR templates, and
 * they redistribute the same 100 points differently across the same
 * criterion names:
 *
 *                              Educ Train Exp  Perf  OA   AppEd AppLD Pot
 *   School Admin (Sec Level)    10   10   10    25   10    10    10   15
 *   RTP-SG 11 to 15 (Elem)      10   10   10    20   10    10    10   20
 *   RTP-SG 16 to 23 (SGOD)      10   10   10    20    5    15    10   20
 *   Annex I-2 (Teaching/PPST): Educ 10, Train 10, Exp 10, Perf 30,
 *                              PPST COIs 25, PPST NCOIs 15
 *
 * Trying to infer weights from word-matching alone can't safely handle
 * this -- the same criterion name legitimately carries different
 * weights on different forms. Worse, several of these forms' narrow
 * multi-column table headers scramble multi-word labels like
 * "Application of Education" out of order in the extracted text even
 * without -layout, so even detecting *presence* isn't reliable for
 * every variant via plain substring matching.
 *
 * Fix: each known template has a short, reliably-intact corner/header
 * label that survives extraction untouched (it's outside the
 * interleaved table, e.g. "RTP-SG 11 to 15" or "Annex I-2"). This patch
 * recognizes those fingerprints first and returns each template's
 * pre-verified exact weights directly. Anything unrecognized still
 * falls through to the existing best-effort word scanner, so new/future
 * CAR variants aren't left completely unhandled -- just less precise
 * until a fingerprint is added for them.
 *
 * Run from your Laravel project root:
 *   php patch_criteria_scan_fix4.php
 *
 * Requires patch_criteria_scan_fix.php to already be applied (this
 * patch edits the same matchCriteriaCatalog() method). Works whether or
 * not patch_criteria_scan_fix3.php has also been applied. Creates a
 * .bak4 backup before editing. Aborts with no changes if the expected
 * current code isn't found.
 */

$target = __DIR__ . '/app/Http/Controllers/AssessmentController.php';

if (!file_exists($target)) {
    fwrite(STDERR, "ABORT: Could not find {$target}\n");
    fwrite(STDERR, "Run this script from your Laravel project root.\n");
    exit(1);
}

$original = file_get_contents($target);

$old = <<<'PHP'
        $normalized = str_replace('accomplishm ents', 'accomplishments', $normalized);

        $result = [];
PHP;

$new = <<<'PHP'
        $normalized = str_replace('accomplishm ents', 'accomplishments', $normalized);
        // The RTP-SG forms wrap the same word at a different point --
        // "Accomplishme" / "nts" -- so both split variants are rejoined.
        $normalized = str_replace('accomplishme nts', 'accomplishments', $normalized);

        // Known official DepEd Cavite CAR templates each redistribute the
        // same 100 points differently across the same criterion names
        // (e.g. Performance is 25 pts on the school-admin form but 20 pts
        // on both RTP-SG forms), and several of them scramble multi-word
        // labels across narrow table columns in the extracted text. Both
        // problems are avoided by recognizing each known template up
        // front via a short label that sits outside the table and
        // survives extraction intact, then using that template's
        // pre-verified exact weights directly. Anything unrecognized
        // falls through to the generic word scanner below.
        $knownTemplates = [
            'rtp-sg 11 to 15' => [
                'Education' => 10, 'Training' => 10, 'Experience' => 10, 'Performance' => 20,
                'Outstanding Accomplishments' => 10, 'Application of Education' => 10,
                'Application of Learning and Development' => 10, 'Potential' => 20,
            ],
            'rtp-sg 16 to 23' => [
                'Education' => 10, 'Training' => 10, 'Experience' => 10, 'Performance' => 20,
                'Outstanding Accomplishments' => 5, 'Application of Education' => 15,
                'Application of Learning and Development' => 10, 'Potential' => 20,
            ],
            'annex i-2' => [
                'Education' => 10, 'Training' => 10, 'Experience' => 10, 'Performance' => 30,
                'PPST COIs (Classroom Observation/Demo Teaching)' => 25,
                'PPST NCOIs (Portfolio Annotation and BEI)' => 15,
            ],
            'school administration for secondary level' => [
                'Education' => 10, 'Training' => 10, 'Experience' => 10, 'Performance' => 25,
                'Outstanding Accomplishments' => 10, 'Application of Education' => 10,
                'Application of Learning and Development' => 10, 'Potential' => 15,
            ],
        ];

        foreach ($knownTemplates as $fingerprint => $criteria) {
            if (str_contains($normalized, $fingerprint)) {
                return $criteria;
            }
        }

        $result = [];
PHP;

if (!str_contains($original, $old)) {
    fwrite(STDERR, "ABORT: matchCriteriaCatalog() didn't match the expected code.\n");
    fwrite(STDERR, "No changes made. Make sure patch_criteria_scan_fix.php has already been\n");
    fwrite(STDERR, "run, or this file has changed since.\n");
    exit(1);
}

$backup = $target . '.bak4';
if (!copy($target, $backup)) {
    fwrite(STDERR, "ABORT: Could not create backup at {$backup}\n");
    exit(1);
}

file_put_contents($target, str_replace($old, $new, $original));

echo "Patched: {$target}\n";
echo "Backup saved to: {$backup}\n";
echo "\nNow recognizes 4 known templates by fingerprint, with exact weights:\n";
echo "  - \"RTP-SG 11 to 15\"                       -> Perf 20, OA 10, AppEd 10, AppLD 10, Pot 20\n";
echo "  - \"RTP-SG 16 to 23\"                       -> Perf 20, OA 5,  AppEd 15, AppLD 10, Pot 20\n";
echo "  - \"Annex I-2\"                              -> Perf 30, PPST COIs 25, PPST NCOIs 15\n";
echo "  - \"School Administration for Secondary Level\" -> Perf 25, OA 10, AppEd 10, AppLD 10, Pot 15\n";
echo "Education/Training/Experience are 10 pts on all four.\n";
echo "\nAnything else still falls back to the generic word scanner.\n";
echo "Try scanning all your CAR PDFs again.\n";
