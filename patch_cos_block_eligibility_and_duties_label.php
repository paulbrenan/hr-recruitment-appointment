<?php
/**
 * Patch: fix two data-loss bugs in parseCosBlock() (the parser used for
 * memos that route through detectCosFormat() — bullet-heading position
 * blocks with no "A." lettering).
 *
 * Run once from the project root:
 *   php patch_cos_block_eligibility_and_duties_label.php
 * Then delete this file.
 *
 * Confirmed real case: OSDS-2025-0149 ("SCHOOL PRINCIPAL II", heading
 * "> SCHOOL PRINCIPAL II (SG-20)"). This is a normal plantilla position,
 * not a genuine Contract-of-Service posting — it just uses a bullet
 * instead of "A." lettering, so it falls through to the same
 * detectCosFormat()/parseCosBlock() path built for COS memos. That path
 * assumes COS-only field labels, which caused two bugs on this memo:
 *
 *   1. qualification_eligibility was hardcoded to null in the return
 *      array — never even attempted — so the real line
 *      ("Eligibility: RA 1080, as amended (Teacher)") was silently
 *      dropped for every non-COS memo that happens to route through
 *      this parser.
 *   2. Duties extraction only looked for "Terms of Reference:" (the COS
 *      label). This memo uses "Duties and Responsibilities:" instead, so
 *      the regex never matched and duties_responsibilities came back
 *      null for the whole memo.
 *
 * Fix: extract Eligibility the same way Education/Training/Experience
 * already are, and try "Duties and Responsibilities" before falling
 * back to "Terms of Reference" so both memo styles resolve correctly.
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
        // Qualifications — same field names, under "Qualifications:" header.
        // extractLabeledField() works unchanged since it matches the label
        // name anywhere in the block text.
        $education  = $this->extractLabeledField($blockText, 'Education');
        $training   = $this->extractLabeledField($blockText, 'Training');
        $experience = $this->extractLabeledField($blockText, 'Experience');
OLD,
        <<<'NEW'
        // Qualifications — same field names, under "Qualifications:" header.
        // extractLabeledField() works unchanged since it matches the label
        // name anywhere in the block text.
        $education   = $this->extractLabeledField($blockText, 'Education');
        $training    = $this->extractLabeledField($blockText, 'Training');
        $experience  = $this->extractLabeledField($blockText, 'Experience');

        // CONFIRMED REAL BUG (OSDS-2025-0149): this method previously
        // hardcoded qualification_eligibility to null unconditionally,
        // on the assumption COS memos never state an Eligibility line.
        // detectCosFormat()'s heading pattern also matches non-COS single
        // positions that just use a bullet instead of "A." lettering
        // (this memo: "> SCHOOL PRINCIPAL II (SG-20)") — those DO have a
        // real Eligibility line ("RA 1080, as amended (Teacher)"), and it
        // was being silently dropped. extractLabeledField() already knows
        // how to find it; just call it like the other qualification fields.
        $eligibility = $this->extractLabeledField($blockText, 'Eligibility');
NEW,
        'parseCosBlock(): actually extract Eligibility instead of hardcoding null',
    ],
    [
        <<<'OLD'
        // Duties — "Terms of Reference:" in COS memos.
        // CONFIRMED REAL BUG: the stop-lookahead only recognized a numbered
        // list item ("\n  1. ...") as the end of the duties section.
        // Bullet-style duty lines never hit that stop condition, so the
        // capture ran all the way to the end of the block/document,
        // swallowing the entire "Interested and qualified applicants..."
        // intro, the full Mandatory Requirements A-J list, footer contact
        // info, and the start of Additional Requirements into
        // duties_responsibilities. Added the recurring boilerplate section
        // markers shared across every DepEd Cavite memo as stop points.
        $duties = null;
        if (preg_match(
            '/Terms of Reference:?\s*(.*?)(?=\n\s*\d+\.\s|Interested and qualified applicants|Mandatory Requirements|Additional Requirements|Checklist of Requirements|\z)/is',
            $blockText, $m
        )) {
            $duties = $this->cleanDutiesText($m[1]);
        }
OLD,
        <<<'NEW'
        // Duties — "Terms of Reference:" in true COS memos, but
        // "Duties and Responsibilities:" in non-COS single-position memos
        // that route through this same COS-format detector because they
        // use a bullet heading instead of "A." lettering (confirmed real
        // case: OSDS-2025-0149, "SCHOOL PRINCIPAL II"). Try the latter
        // first since it's the more common plain-position label; fall
        // back to "Terms of Reference" for genuine COS memos.
        // CONFIRMED REAL BUG: the stop-lookahead only recognized a numbered
        // list item ("\n  1. ...") as the end of the duties section.
        // Bullet-style duty lines never hit that stop condition, so the
        // capture ran all the way to the end of the block/document,
        // swallowing the entire "Interested and qualified applicants..."
        // intro, the full Mandatory Requirements A-J list, footer contact
        // info, and the start of Additional Requirements into
        // duties_responsibilities. Added the recurring boilerplate section
        // markers shared across every DepEd Cavite memo as stop points.
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
NEW,
        'parseCosBlock(): accept "Duties and Responsibilities" label before falling back to "Terms of Reference"',
    ],
    [
        "            'qualification_eligibility' => null,",
        "            'qualification_eligibility' => \$eligibility,",
        'parseCosBlock(): return the extracted eligibility, not a hardcoded null',
    ],
]);

echo "\nDone. Diff and test an import (e.g. OSDS-2025-0149, School Principal II) before deleting this script and its .bak backups.\n";
