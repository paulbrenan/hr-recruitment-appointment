<?php
/**
 * patch_criteria_scan_fix.php
 *
 * Fixes two bugs in AssessmentController::importCriteriaScan()'s PDF
 * scanning that cause the official CAR template to only ever match
 * 5 of its 8 criteria (Education, Training, Experience, Performance,
 * Potential) while silently dropping Outstanding Accomplishments,
 * Application of Education, and Application of Learning and Development:
 *
 *   1. extractTextFromPdf() used `pdftotext -layout`, which reconstructs
 *      the page by visual column position. On the CAR form's narrow
 *      8-column table, that interleaves text from neighboring columns,
 *      scattering "application" / "of" / "education" apart so the
 *      multi-word phrase match never fires. This patch also extracts
 *      WITHOUT -layout (content-stream order, which keeps those phrases
 *      intact on this document) and matches against both, concatenated.
 *
 *   2. The CAR template's own header cell hard-wraps "Accomplishments"
 *      as "Accomplishm" / "ents" across two lines (narrow column), which
 *      survives whitespace-collapsing as two separate tokens no matter
 *      how the PDF is read. This patch rejoins that specific fragment
 *      before matching.
 *
 * Run from your Laravel project root:
 *   php patch_criteria_scan_fix.php
 *
 * Creates app/Http/Controllers/AssessmentController.php.bak before
 * editing. Aborts with no changes made if the expected original code
 * isn't found (e.g. already patched, or file has since changed).
 */

$target = __DIR__ . '/app/Http/Controllers/AssessmentController.php';

if (!file_exists($target)) {
    fwrite(STDERR, "ABORT: Could not find {$target}\n");
    fwrite(STDERR, "Run this script from your Laravel project root.\n");
    exit(1);
}

$original = file_get_contents($target);

// ── Guard + patch 1: extractTextFromPdf() ──────────────────────────────────
$oldExtract = <<<'PHP'
    private function extractTextFromPdf(string $path): string
    {
        $text = @shell_exec('pdftotext -layout ' . escapeshellarg($path) . ' - 2>/dev/null');
        if ($text && strlen(trim($text)) > 20) {
            return $text;
        }
        // Likely a scanned/photographed PDF -- OCR fallback, same tools
        // the job posting PDF import already relies on.
        return $this->ocrPdf($path);
    }
PHP;

$newExtract = <<<'PHP'
    private function extractTextFromPdf(string $path): string
    {
        // `-layout` reconstructs the page by visual column position, which
        // is fine for simple documents but scrambles word order on narrow
        // multi-column tables (e.g. the official CAR form splits
        // "application of education" across columns so the words end up
        // nowhere near each other). Plain content-stream order doesn't have
        // that problem on such documents, so both are extracted and
        // concatenated -- this is only used for keyword matching, not
        // structure, so duplicated/reordered text is harmless.
        $layoutText = @shell_exec('pdftotext -layout ' . escapeshellarg($path) . ' - 2>/dev/null');
        $plainText  = @shell_exec('pdftotext ' . escapeshellarg($path) . ' - 2>/dev/null');
        $combined   = trim((string) $layoutText) . "\n" . trim((string) $plainText);

        if (trim($combined) !== '' && strlen(trim($combined)) > 20) {
            return $combined;
        }
        // Likely a scanned/photographed PDF -- OCR fallback, same tools
        // the job posting PDF import already relies on.
        return $this->ocrPdf($path);
    }
PHP;

if (!str_contains($original, $oldExtract)) {
    fwrite(STDERR, "ABORT: extractTextFromPdf() didn't match the expected original code.\n");
    fwrite(STDERR, "No changes made. The file may already be patched or has changed.\n");
    exit(1);
}

// ── Guard + patch 2: matchCriteriaCatalog() ────────────────────────────────
$oldMatch = <<<'PHP'
    private function matchCriteriaCatalog(string $text): array
    {
        $normalized = preg_replace('/\s+/', ' ', strtolower($text));
        $result = [];
PHP;

$newMatch = <<<'PHP'
    private function matchCriteriaCatalog(string $text): array
    {
        $normalized = preg_replace('/\s+/', ' ', strtolower($text));

        // The official CAR template hard-wraps "Accomplishments" as
        // "Accomplishm" / "ents" across two lines in its narrow column.
        // That survives whitespace-collapsing above as two separate
        // tokens with a space between them, so "outstanding
        // accomplishments" would never match as written. Rejoin this
        // known fragment before matching.
        $normalized = str_replace('accomplishm ents', 'accomplishments', $normalized);

        $result = [];
PHP;

if (!str_contains($original, $oldMatch)) {
    fwrite(STDERR, "ABORT: matchCriteriaCatalog() didn't match the expected original code.\n");
    fwrite(STDERR, "No changes made. The file may already be patched or has changed.\n");
    exit(1);
}

// ── Apply ───────────────────────────────────────────────────────────────────
$backup = $target . '.bak';
if (!copy($target, $backup)) {
    fwrite(STDERR, "ABORT: Could not create backup at {$backup}\n");
    exit(1);
}

$patched = str_replace($oldExtract, $newExtract, $original);
$patched = str_replace($oldMatch, $newMatch, $patched);

file_put_contents($target, $patched);

echo "Patched: {$target}\n";
echo "Backup saved to: {$backup}\n";
echo "\nWhat changed:\n";
echo "  1. extractTextFromPdf() now extracts both with and without -layout\n";
echo "     and matches against both, so multi-column tables like the CAR\n";
echo "     form don't scramble phrases like \"Application of Education\".\n";
echo "  2. matchCriteriaCatalog() now rejoins the CAR template's own\n";
echo "     mid-word wrap of \"Accomplishm ents\" before matching, so\n";
echo "     \"Outstanding Accomplishments\" is recognized too.\n";
echo "\nTry scanning your CAR PDF again -- you should now get all 8 criteria\n";
echo "(Education, Training, Experience, Performance, Outstanding\n";
echo "Accomplishments, Application of Education, Application of Learning\n";
echo "and Development, Potential) instead of just 5.\n";
