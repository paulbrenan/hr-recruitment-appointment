<?php
/**
 * fix_default_additional_requirements.php
 *
 * create() pre-fills mandatory_requirements with the standard DepEd A-J
 * list, but never set additional_requirements at all -- so a brand-new
 * posting's Additional Requirements widget started genuinely empty. Once
 * saved and reopened via edit(), you were just looking at the same
 * (still empty, unless manually filled) saved value -- not a bug where
 * data was lost, just missing a default like Mandatory has.
 *
 * Fix: adds a DEFAULT_ADDITIONAL_REQUIREMENTS constant (flattened, one
 * line per category, matching the widget's flat newline-delimited
 * format -- same flattening approach already used for Mandatory's
 * lettered A-J items) and pre-fills it in create(), same as Mandatory.
 *
 * HOW TO RUN:
 *   php fix_default_additional_requirements.php   (from project root)
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

echo "\n=== fix_default_additional_requirements.php ===\n\n";

$controllerPath = ROOT . '/app/Http/Controllers/JobPostingController.php';

echo "[1] Adding DEFAULT_ADDITIONAL_REQUIREMENTS constant...\n";

apply_patch(
    $controllerPath,
    "    private const DEFAULT_MANDATORY_REQUIREMENTS = [
        'Letter of intent addressed to the Schools Division Superintendent',
        'Duly Accomplished Personal Data Sheet (CSC Form No. 212, Revised 2025) with latest passport size picture and Work Experience Sheet, if applicable',
        'Photocopy of valid and updated PRC License/ID, if applicable',
        'Photocopy of Certificate of Eligibility/Rating, if applicable',
        'Photocopy of scholastic/academic record such as but not limited to Transcript of Records (TOR) and Diploma, including completion of graduate and post graduate units/degrees, if available',
        'Photocopy of Certificates of Training, if applicable',
        'Photocopy of Certificate of Employment, Contract of Service, or duly signed Service Record, whichever is/are applicable',
        'Photocopy of the latest appointment, if applicable',
        'Photocopy of Performance Rating in the last rating period(s) covering one (1) year performance in the current/latest position, if applicable',
        'Checklist of Requirements and Omnibus Sworn Statement on the Certification on the Authenticity and Veracity (CAV) of the documents submitted and Data Privacy Consent Form, signed by authorized official (e.g., Brgy. Captain)',
    ];",
    "    private const DEFAULT_MANDATORY_REQUIREMENTS = [
        'Letter of intent addressed to the Schools Division Superintendent',
        'Duly Accomplished Personal Data Sheet (CSC Form No. 212, Revised 2025) with latest passport size picture and Work Experience Sheet, if applicable',
        'Photocopy of valid and updated PRC License/ID, if applicable',
        'Photocopy of Certificate of Eligibility/Rating, if applicable',
        'Photocopy of scholastic/academic record such as but not limited to Transcript of Records (TOR) and Diploma, including completion of graduate and post graduate units/degrees, if available',
        'Photocopy of Certificates of Training, if applicable',
        'Photocopy of Certificate of Employment, Contract of Service, or duly signed Service Record, whichever is/are applicable',
        'Photocopy of the latest appointment, if applicable',
        'Photocopy of Performance Rating in the last rating period(s) covering one (1) year performance in the current/latest position, if applicable',
        'Checklist of Requirements and Omnibus Sworn Statement on the Certification on the Authenticity and Veracity (CAV) of the documents submitted and Data Privacy Consent Form, signed by authorized official (e.g., Brgy. Captain)',
    ];

    // Flattened to one line per category (the widget stores a flat
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
    'JobPostingController: add DEFAULT_ADDITIONAL_REQUIREMENTS constant'
);

echo "\n[2] Pre-filling additional_requirements in create()...\n";

apply_patch(
    $controllerPath,
    "        \$posting = new JobPosting();
        \$posting->exists = false;
        \$posting->mandatory_requirements = implode(\"\\n\", self::DEFAULT_MANDATORY_REQUIREMENTS);
        \$jobTitles         = config('job_titles.titles', []);",
    "        \$posting = new JobPosting();
        \$posting->exists = false;
        \$posting->mandatory_requirements  = implode(\"\\n\", self::DEFAULT_MANDATORY_REQUIREMENTS);
        \$posting->additional_requirements = implode(\"\\n\", self::DEFAULT_ADDITIONAL_REQUIREMENTS);
        \$jobTitles         = config('job_titles.titles', []);",
    'JobPostingController::create() -- pre-fill additional_requirements too'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - New job postings now start with the standard Additional\n";
echo "    Requirements list pre-filled in the widget, same as Mandatory\n";
echo "    already did.\n";
echo "  - HR can still edit/remove any pre-filled line before saving, same\n";
echo "    as Mandatory.\n";
echo "  - Existing postings are untouched -- this only affects the create\n";
echo "    form.\n\n";
echo "DELETE this script after running.\n";
