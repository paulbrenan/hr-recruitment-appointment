<?php
/**
 * fix_requirements_extractor.php
 *
 * One-shot script: full-file replace of app/Services/RequirementsExtractor.php.
 *
 * The existing class attempts to OCR-parse requirements from memo cover pages,
 * but the real OCR output has no spaces between words and picks up footer/
 * letterhead noise (e.g. "Cavite Capitol Compound, Brgy. Luciano..." appended
 * to the last mandatory item). Since both real DepEd Cavite memos use the
 * identical A-J mandatory list and the same 8 additional categories,
 * hardcoding the clean official text is more reliable than any parsing approach.
 *
 * Usage: place this file in the project root (same folder as artisan) and run:
 *   php fix_requirements_extractor.php
 * Then delete this script. No migration needed.
 *
 * Backs up the file before overwriting (.bak, .bak2, ... if needed).
 * Verifies the file matches the expected current content before writing.
 */

function die_loud($msg) {
    fwrite(STDERR, "\n[ABORTED] $msg\n\n");
    exit(1);
}

function backup_file($path) {
    if (!file_exists($path)) {
        die_loud("Expected file not found: $path");
    }
    $backupPath = $path . '.bak';
    $n = 1;
    while (file_exists($backupPath)) {
        $n++;
        $backupPath = $path . '.bak' . $n;
    }
    if (!copy($path, $backupPath)) {
        die_loud("Could not create backup at $backupPath");
    }
    echo "Backed up " . $path . " -> " . basename($backupPath) . "\n";
}

$root = __DIR__;
$path = $root . '/app/Services/RequirementsExtractor.php';

$current = file_get_contents($path);
if ($current === false) {
    die_loud("Could not read $path");
}

// Verify it's the OCR-parsing version we expect (not something else entirely)
if (strpos($current, 'buildCoverText') === false || strpos($current, 'extractMandatory') === false) {
    die_loud("RequirementsExtractor.php doesn't match the expected OCR-parsing version.\nPlease re-paste it so this script can be updated.");
}

$newContent = <<<'NEW_EOF'
<?php

namespace App\Services;

/**
 * RequirementsExtractor
 *
 * Returns the standard DepEd mandatory and additional documentary requirements
 * for job posting imports.
 *
 * Both real DepEd Cavite memos (OSDS-2026-DM-073, SGOD-2026-DM-0079) use the
 * identical A-J mandatory list and the same 8 additional requirement categories.
 * OCR parsing of these sections proved unreliable (words run together without
 * spaces; footer/letterhead noise bleeds into the last item), so this class
 * returns the clean official text directly rather than attempting to extract
 * it from the raw OCR output.
 *
 * The $pageTexts parameter is kept for interface compatibility — if a future
 * memo uses a genuinely different requirements list, this method can be updated
 * to detect and parse that variation.
 *
 * Return format (matches what JobPostingImportController::confirm() expects):
 *   [
 *       'mandatory'  => string[],  // one clean string per A-J item
 *       'additional' => string,    // full additional requirements block, newline-delimited
 *   ]
 */
class RequirementsExtractor
{
    public function extract(array $pageTexts): array
    {
        $mandatory = [
            'A. Letter of intent addressed to the Schools Division Superintendent',
            'B. Duly Accomplished Personal Data Sheet (CSC Form No. 212, Revised 2025) with latest passport size picture and Work Experience Sheet, if applicable',
            'C. Photocopy of valid and updated PRC License/ID, if applicable',
            'D. Photocopy of Certificate of Eligibility/Rating, if applicable',
            'E. Photocopy of scholastic/academic record such as but not limited to Transcript of Records (TOR) and Diploma, including completion of graduate and post graduate units/degrees, if available',
            'F. Photocopy of Certificates of Training, if applicable',
            'G. Photocopy of Certificate of Employment, Contract of Service, or duly signed Service Record, whichever is/are applicable',
            'H. Photocopy of the latest appointment, if applicable',
            'I. Photocopy of Performance Rating in the last rating period(s) covering one (1) year performance in the current/latest position, if applicable',
            'J. Checklist of Requirements and Omnibus Sworn Statement on the Certification on the Authenticity and Veracity (CAV) of the documents submitted and Data Privacy Consent Form, signed by authorized official (e.g., Brgy. Captain). Access and download here: http://tinyurl.com/ChecklistOfReqtOmnibus',
        ];

        $additional = implode("\n", [
            'A. Means of Verification showing Outstanding Accomplishments, Application of Education, and Application of Learning and Development, reckoned from the date of last issuance of appointment (if any):',
            '',
            '1. Awards and Recognition',
            '   a. Citation or Commendation',
            '      - Letter of Citation or Commendation from previous employer',
            '   b. Academic or Inter-School Awards',
            '      - Academic or inter-school award; or',
            '      - Ten Outstanding Students of the Philippines (TOSP) Award; or',
            '      - Certification of any document that the applicant belongs to the Top 10 in the Board or Civil Service Eligibility Examination.',
            '   c. Outstanding Employee Award',
            '      - Any issuance, memorandum or document showing the Criteria for the Search; and',
            '      - Certificate of Recognition/Merit',
            '',
            '2. Research and Innovation',
            '   a. Proposal duly approved by the Head of Office or the designated Research Committee per DepEd Order No. 16, s. 2017',
            '   b. Accomplishment Report verified by the Head of Office',
            '   c. Certification of utilization of the innovation or research, within the school/office duly signed by the Head of Office',
            '   d. Certification of adoption of the innovation or research by another school/office duly signed by the Head of Office',
            '   e. Proof of citation by other researchers (whose study/research, whether published or unpublished, is likewise approved by authorized body) of the concept/s developed in the research',
            '',
            '3. Subject Matter Expert / Membership in National Technical Working Groups or Committees',
            '   a. Issuance/Memorandum showing the membership in NTWG or Committees',
            '   b. Certificate of Participation or Attendance',
            '   c. Output/Adoption by the organization/DepEd',
            '',
            '4. Resource Speakership / Learning Facilitation',
            '   a. Issuance/Memorandum/Invitation/Training Matrix',
            '   b. Certificate of Recognition/Merit/Commendation/Appreciation',
            '   c. Slide deck/s used and/or Session guide/s',
            '',
            '5. NEAP Accredited Learning Facilitator',
            '   a. Certificate of Recognition as Learning Facilitator issued by NEAP Central or Regional Office',
            '',
            '6. Application of Education',
            '   a. Action Plan approved by the Head of Office',
            '   b. Accomplishment Report verified by the Head of Office',
            '   c. Certification of the utilization/adoption signed by the Head of Office',
            '',
            '7. Application of Learning and Development',
            '   a. Certificate of Training or Certification on any applicable L&D intervention acquired that is aligned with the Individual Development Plan (IDP); for external applicants, a certification from HR stating that the L&D intervention is aligned with the core tasks of the applicant in their current or previous position shall be required',
            '   b. Action Plan/Re-entry Action Plan (REAP), Job Embedded Learning (JEL)/Impact Project applying the learnings from the L&D intervention done/attended, duly approved by the Head of Office',
            '   c. Accomplishment Report together with a General Certification that the L&D intervention was used/adopted by the office at the local level',
            '   d. Accomplishment Report together with a General Certification that the L&D intervention was used/adopted by a different office at the local/higher level',
            '',
            '8. Photocopy of the Performance Rating obtained from the relevant work experience if latest performance rating is not relevant to the position applying for',
        ]);

        return [
            'mandatory'  => $mandatory,
            'additional' => $additional,
        ];
    }
}
NEW_EOF;

backup_file($path);
if (file_put_contents($path, $newContent) === false) {
    die_loud("Could not write $path");
}
echo "Updated app/Services/RequirementsExtractor.php\n";

echo "\nDone. No migration needed.\n";
echo "Next steps:\n";
echo "  1. Try a full PDF import end-to-end.\n";
echo "  2. After confirming some postings, verify in phpMyAdmin:\n";
echo "     SELECT title, mandatory_requirements, additional_requirements\n";
echo "     FROM job_postings ORDER BY id DESC LIMIT 5;\n";
echo "  3. Both columns should now be populated with clean, properly spaced text.\n";
echo "  4. Delete this script once confirmed.\n";
