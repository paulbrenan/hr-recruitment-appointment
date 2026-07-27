<?php
/**
 * fix_full_additional_requirements_text.php
 *
 * Follow-up to fix_default_additional_requirements.php. That script
 * flattened the Additional Requirements into one summary line per
 * category to fit the widget's flat newline-delimited storage --
 * but the real DepEd checklist has much more detail per category
 * (numbered categories, lettered sub-items, dash-level specifics) that
 * shouldn't be lost.
 *
 * This replaces DEFAULT_ADDITIONAL_REQUIREMENTS with the FULL text,
 * one bullet per array entry, keeping the "1.", "a.", "-" prefixes as
 * part of the text itself -- since the widget only supports a flat
 * list, the numbering/lettering IS how the hierarchy stays visually
 * readable once rendered.
 *
 * REQUIRES fix_default_additional_requirements.php already applied
 * (this patch's old_str is exactly what that script produced).
 *
 * HOW TO RUN:
 *   php fix_full_additional_requirements_text.php   (from project root)
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
        echo "\n❌ PATCH ABORTED — content not found in $path\nLabel: $label\n";
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

echo "\n=== fix_full_additional_requirements_text.php ===\n\n";

$controllerPath = ROOT . '/app/Http/Controllers/JobPostingController.php';

echo "[1] Replacing DEFAULT_ADDITIONAL_REQUIREMENTS with the full checklist...\n";

apply_patch(
    $controllerPath,
    "    // Flattened to one line per category (the widget stores a flat
    // newline-delimited list, same convention as Mandatory above) --
    // matches the standard DepEd \"Means of Verification showing
    // Outstanding Accomplishments\" categories used on the official
    // registration confirmation notice.
    private const DEFAULT_ADDITIONAL_REQUIREMENTS = [
        'Awards and Recognition — Letter of Citation/Commendation, Academic or Inter-School Award, TOSP Award, or Top 10 Board/CSC Eligibility certification, if applicable',
        'Research and Innovation — DepEd-approved research/innovation proposal, accomplishment report, and certification of utilization or adoption, if applicable',
        'Subject Matter Expert / Membership in National Technical Working Groups or Committees — issuance/memorandum of membership, certificate of participation, and proof of output/adoption, if applicable',
        'Resource Speakership / Learning Facilitation — issuance/memorandum/invitation, certificate of recognition, and materials used, if applicable',
        'NEAP Accredited Learning Facilitator — Certificate of Recognition issued by NEAP Central or Regional Office, if applicable',
        'Application of Education — approved action plan, accomplishment report, and certification of utilization/adoption, if applicable',
        'Application of Learning and Development — certificate of training, action plan/REAP/JEL/impact project, and accomplishment report with certification of use, if applicable',
        'Performance Rating — photocopy of Performance Rating from relevant work experience, if the latest rating is not relevant to the position applied for',
    ];",
    "    // Full DepEd \"Means of Verification\" checklist. The widget only
    // stores a flat newline-delimited list, so numbering/lettering
    // (\"1.\", \"a.\", \"-\") is kept as part of each line's own text --
    // that's what keeps the category hierarchy visually readable once
    // rendered as a flat list.
    private const DEFAULT_ADDITIONAL_REQUIREMENTS = [
        'A. Means of Verification showing Outstanding Accomplishments, Application of Education, and Application of Learning and Development, reckoned from the date of last issuance of appointment (if any):',
        '1. Awards and Recognition',
        'a. Citation or Commendation',
        '- Letter of Citation or Commendation from previous employer',
        'b. Academic or Inter-School Awards',
        '- Academic or inter-school award; or',
        '- Ten Outstanding Students of the Philippines (TOSP) Award; or',
        '- Certification of any document that the applicant belongs to the Top 10 in the Board or Civil Service Eligibility Examination.',
        'c. Outstanding Employee Award',
        '- Any issuance, memorandum or document showing the Criteria for the Search; and',
        '- Certificate of Recognition/Merit',
        '2. Research and Innovation',
        'a. Proposal duly approved by the Head of Office or the designated Research Committee per DepEd Order No. 16, s. 2017',
        'b. Accomplishment Report verified by the Head of Office',
        'c. Certification of utilization of the innovation or research, within the school/office duly signed by the Head of Office',
        'd. Certification of adoption of the innovation or research by another school/office duly signed by the Head of Office',
        'e. Proof of citation by other researchers (whose study/research, whether published or unpublished, is likewise approved by authorized body) of the concept/s developed in the research',
        '3. Subject Matter Expert / Membership in National Technical Working Groups or Committees',
        'a. Issuance/Memorandum showing the membership in NTWG or Committees',
        'b. Certificate of Participation or Attendance',
        'c. Output/Adoption by the organization/DepEd',
        '4. Resource Speakership / Learning Facilitation',
        'a. Issuance/Memorandum/Invitation/Training Matrix',
        'b. Certificate of Recognition/Merit/Commendation/Appreciation',
        'c. Slide deck/s used and/or Session guide/s',
        '5. NEAP Accredited Learning Facilitator',
        'a. Certificate of Recognition as Learning Facilitator issued by NEAP Central or Regional Office',
        '6. Application of Education',
        'a. Action Plan approved by the Head of Office',
        'b. Accomplishment Report verified by the Head of Office',
        'c. Certification of the utilization/adoption signed by the Head of Office',
        '7. Application of Learning and Development',
        'a. Certificate of Training or Certification on any applicable L&D intervention acquired that is aligned with the Individual Development Plan (IDP); for external applicants, a certification from HR stating that the L&D intervention is aligned with the core tasks of the applicant in their current or previous position shall be required',
        'b. Action Plan/Re-entry Action Plan (REAP), Job Embedded Learning (JEL)/Impact Project applying the learnings from the L&D intervention done/attended, duly approved by the Head of Office',
        'c. Accomplishment Report together with a General Certification that the L&D intervention was used/adopted by the office at the local level',
        'd. Accomplishment Report together with a General Certification that the L&D intervention was used/adopted by a different office at the local/higher level',
        '8. Photocopy of the Performance Rating obtained from the relevant work experience if latest performance rating is not relevant to the position applying for',
    ];",
    'JobPostingController: DEFAULT_ADDITIONAL_REQUIREMENTS -- full nested checklist text'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - New job postings now pre-fill Additional Requirements with the\n";
echo "    full official checklist (35 lines), not a flattened 8-line\n";
echo "    summary.\n";
echo "  - Numbering/lettering (1., a., -) is kept in each line's own text\n";
echo "    since the widget only supports a flat list -- this is what\n";
echo "    keeps the hierarchy readable.\n";
echo "  - HR can still delete/edit any line before saving, same as\n";
echo "    Mandatory.\n\n";
echo "DELETE this script after running.\n";
