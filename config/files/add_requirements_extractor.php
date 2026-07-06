<?php
/**
 * add_requirements_extractor.php
 *
 * One-shot script:
 * - Creates database/migrations/2026_07_01_100000_add_requirements_to_pdf_import_batches_table.php
 *   (adds `requirements` json nullable and `newly_registered_titles` json nullable
 *   to the pdf_import_batches table -- both columns are already in the model's
 *   $fillable and $casts but were missing from the original migration, which is
 *   why no import has completed successfully yet)
 * - Creates app/Services/RequirementsExtractor.php
 *   (the missing service class that JobPostingImportController::extract() calls;
 *   returns the standard DepEd A-J mandatory requirements and 7 additional
 *   categories as a hardcoded list -- no OCR parsing needed since both real
 *   memos use the identical list word-for-word)
 *
 * Usage: place this file in the project root (same folder as artisan) and run:
 *   php add_requirements_extractor.php
 * Then:
 *   php artisan migrate
 * Then delete this script.
 *
 * Backs up any file it overwrites to .bak (or .bak2, .bak3, ... if needed).
 * Fails loudly without writing if any new file already exists.
 */

function die_loud($msg) {
    fwrite(STDERR, "\n[ABORTED] $msg\n\n");
    exit(1);
}

function create_new_file($path, $content, $label) {
    if (file_exists($path)) {
        die_loud("$label already exists at $path -- looks like this script already ran.");
    }
    $dir = dirname($path);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            die_loud("Could not create directory $dir");
        }
        echo "Created directory $dir\n";
    }
    if (file_put_contents($path, $content) === false) {
        die_loud("Could not write $path");
    }
    echo "Created $label: $path\n";
}

$root = __DIR__;

// ---------------------------------------------------------------------------
// 1. Migration: add requirements + newly_registered_titles columns
// ---------------------------------------------------------------------------
$migrationContent = <<<'MIGRATION_EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdf_import_batches', function (Blueprint $table) {
            // Both columns exist in the model's $fillable/$casts but were
            // missing from the original create migration -- adding them here.
            $table->json('requirements')->nullable()->after('candidates');
            $table->json('newly_registered_titles')->nullable()->after('requirements');
        });
    }

    public function down(): void
    {
        Schema::table('pdf_import_batches', function (Blueprint $table) {
            $table->dropColumn(['requirements', 'newly_registered_titles']);
        });
    }
};
MIGRATION_EOF;

create_new_file(
    $root . '/database/migrations/2026_07_01_100000_add_requirements_to_pdf_import_batches_table.php',
    $migrationContent,
    'migration'
);

// ---------------------------------------------------------------------------
// 2. RequirementsExtractor service class
// ---------------------------------------------------------------------------
$serviceContent = <<<'SERVICE_EOF'
<?php

namespace App\Services;

/**
 * RequirementsExtractor
 *
 * Returns the standard DepEd mandatory and additional documentary requirements
 * for job posting imports.
 *
 * Both real sample PDFs (OSDS-2026-DM-073 and SGOD-2026-DM-0079) use the
 * identical requirements list, so we return the hardcoded standard DepEd A-J
 * list rather than attempting to OCR-extract it from the memo cover pages.
 * This is applied automatically to every job posting created during a PDF
 * import -- HR can edit individual postings afterward if needed.
 *
 * The $pageTexts parameter is accepted for interface compatibility (future
 * memos that differ could be parsed here), but is not used in the current
 * implementation.
 *
 * Return format (matches what JobPostingImportController::confirm() expects):
 *   [
 *       'mandatory'  => string[],   // one item per array entry
 *       'additional' => string,     // newline-delimited, matches additional_requirements column
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
            'J. Checklist of Requirements and Omnibus Sworn Statement on the Certification on the Authenticity and Veracity (CAV) of the documents submitted and Data Privacy Consent Form, signed by authorized official (e.g., Brgy. Captain)',
        ];

        $additional = implode("\n", [
            '1. Awards and Recognition',
            '   a. Citation or Commendation (Letter of Citation or Commendation from previous employer)',
            '   b. Academic or Inter-School Awards (academic/inter-school award; TOSP Award; or Top 10 in Board or Civil Service Eligibility Examination)',
            '   c. Outstanding Employee Award (issuance/memorandum showing Criteria for the Search; and Certificate of Recognition/Merit)',
            '2. Research and Innovation',
            '   a. Proposal duly approved by the Head of Office or designated Research Committee per DepEd Order No. 16, s. 2017',
            '   b. Accomplishment Report verified by the Head of Office',
            '   c. Certification of utilization of the innovation or research within the school/office',
            '   d. Certification of adoption by another school/office',
            '   e. Proof of citation by other researchers',
            '3. Subject Matter Expert / Membership in National Technical Working Groups or Committees',
            '   a. Issuance/Memorandum showing the membership in NTWG or Committees',
            '   b. Certificate of Participation or Attendance',
            '   c. Output/Adoption by the organization/DepEd',
            '4. Resource Speakership / Learning Facilitation',
            '   a. Issuance/Memorandum/Invitation/Training Matrix',
            '   b. Certificate of Recognition/Merit/Commendation/Appreciation',
            '   c. Slide deck/s used and/or Session guide/s',
            '5. NEAP Accredited Learning Facilitator',
            '   a. Certificate of Recognition as Learning Facilitator issued by NEAP Central or Regional Office',
            '6. Application of Education',
            '   a. Action Plan approved by the Head of Office',
            '   b. Accomplishment Report verified by the Head of Office',
            '   c. Certification of the utilization/adoption signed by the Head of Office',
            '7. Application of Learning and Development',
            '   a. Certificate of Training or Certification on any applicable L&D intervention aligned with the Individual Development Plan (IDP)',
            '   b. Action Plan/Re-entry Action Plan (REAP), Job Embedded Learning (JEL)/Impact Project applying the learnings from the L&D intervention',
            '   c. Accomplishment Report with General Certification that the L&D intervention was used/adopted at the local level',
            '   d. Accomplishment Report with General Certification that the L&D intervention was used/adopted by a different office at the local/higher level',
            '8. Photocopy of the Performance Rating obtained from the relevant work experience if latest performance rating is not relevant to the position applying for',
        ]);

        return [
            'mandatory'  => $mandatory,
            'additional' => $additional,
        ];
    }
}
SERVICE_EOF;

create_new_file(
    $root . '/app/Services/RequirementsExtractor.php',
    $serviceContent,
    'service class'
);

echo "\nDone.\n";
echo "Next steps:\n";
echo "  1. Run: php artisan migrate\n";
echo "  2. Try a PDF import end-to-end:\n";
echo "     - Upload one of the real DepEd memos\n";
echo "     - Confirm it reaches the review screen without errors\n";
echo "     - Confirm some postings, then check the job_postings table:\n";
echo "       SELECT title, mandatory_requirements, additional_requirements\n";
echo "       FROM job_postings ORDER BY id DESC LIMIT 5;\n";
echo "     - Both columns should now be populated for every imported posting.\n";
echo "  3. Delete this script once you've confirmed everything works.\n";
