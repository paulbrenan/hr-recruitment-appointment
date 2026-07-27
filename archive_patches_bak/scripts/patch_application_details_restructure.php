<?php
/**
 * One-shot patch: restructures the Application details page per spec:
 *  1. Personal Information moved to the top of the right column.
 *  2. "Qualifications" card merged into "Qualification Checklist" card
 *     (still fully editable, all existing checklist functionality kept).
 *  3. "Interview / Exam Schedule" card removed entirely.
 *  4. Saving the qualification check now automatically sets the
 *     application's status to "Qualified" or "Not Qualified".
 *
 * Touches:
 *   - New migration: adds 'qualified','not_qualified' to applications
 *     status enum (preserving every existing enum value).
 *   - app/Http/Controllers/ApplicationController.php
 *   - resources/views/applications/show.blade.php
 *   - resources/views/applications/index.blade.php (status filter + colors)
 *   - resources/views/welcome.blade.php (candidate-facing status tracker)
 *
 * All verification happens first (read-only); files are only written
 * once every check has passed. Backs up every file it modifies. Does
 * NOT run `php artisan migrate` for you. Deletes itself after a fully
 * successful run.
 */

$root = __DIR__;
$controllerPath = $root . '/app/Http/Controllers/ApplicationController.php';
$showPath = $root . '/resources/views/applications/show.blade.php';
$indexPath = $root . '/resources/views/applications/index.blade.php';
$welcomePath = $root . '/resources/views/welcome.blade.php';
$migrationsDir = $root . '/database/migrations';

function fail($msg) {
    echo "ABORTED: $msg\n";
    echo "No files were modified.\n";
    exit(1);
}

function backup($path) {
    $backupPath = $path . '.bak.' . date('Ymd_His');
    if (!copy($path, $backupPath)) {
        fail("Could not create backup for $path");
    }
    echo "Backed up: $backupPath\n";
    return $backupPath;
}

foreach ([$controllerPath, $showPath, $indexPath, $welcomePath] as $p) {
    if (!file_exists($p)) fail("Required file not found: $p");
}
if (!is_dir($migrationsDir)) fail("Migrations directory not found: $migrationsDir");

$controllerContent = file_get_contents($controllerPath);
$showContent = file_get_contents($showPath);
$indexContent = file_get_contents($indexPath);
$welcomeContent = file_get_contents($welcomePath);

// ---------------------------------------------------------------------
// Idempotency check
// ---------------------------------------------------------------------
if (strpos($controllerContent, "'status' => \$passed ? 'qualified' : 'not_qualified'") !== false) {
    echo "Already patched -- nothing to do.\n";
    exit(0);
}

foreach (glob($migrationsDir . '/*.php') as $file) {
    if (strpos(file_get_contents($file), "'not_qualified'") !== false) {
        fail("A migration already references 'not_qualified': $file. Aborting to avoid duplicate migration.");
    }
}

// =======================================================================
// STEP 1 (verify): app/Http/Controllers/ApplicationController.php
// =======================================================================

$oldImport = "use App\\Models\\InterviewSchedule;\n";
if (substr_count($controllerContent, $oldImport) !== 1) {
    fail("ApplicationController.php: InterviewSchedule import not found exactly once.");
}

$oldShowMethod = <<<'EOT'
    public function show($id)
    {
        $application = Application::with(['candidate', 'jobPosting'])->findOrFail($id);
        // Real interview schedules from the database
        $schedules = InterviewSchedule::where('application_id', $id)
            ->orderBy('scheduled_at')
            ->get();
        return view('applications.show', compact('application', 'schedules'));
    }
EOT;

$newShowMethod = <<<'EOT'
    public function show($id)
    {
        $application = Application::with(['candidate', 'jobPosting'])->findOrFail($id);
        return view('applications.show', compact('application'));
    }
EOT;

if (substr_count($controllerContent, $oldShowMethod) !== 1) {
    fail("ApplicationController.php: show() method not found exactly once in expected form.");
}

$oldValidationLine = "            'status' => ['required', 'in:submitted,screening,shortlisted,interview_scheduled,assessed,ranked,offer_sent,offer_accepted,offer_declined,hired,rejected'],";
$newValidationLine = "            'status' => ['required', 'in:submitted,screening,shortlisted,interview_scheduled,assessed,ranked,qualified,not_qualified,offer_sent,offer_accepted,offer_declined,hired,rejected'],";
if (substr_count($controllerContent, $oldValidationLine) !== 1) {
    fail("ApplicationController.php: updateStatus() validation line not found exactly once.");
}

$oldQualUpdate = <<<'EOT'
        $application->update([
            'qualification_check' => $check,
            'qualification_result' => $passed ? 'qualified' : 'disqualified',
            'qualification_checked_at' => now(),
        ]);
EOT;

$newQualUpdate = <<<'EOT'
        $application->update([
            'qualification_check' => $check,
            'qualification_result' => $passed ? 'qualified' : 'disqualified',
            'qualification_checked_at' => now(),
            'status' => $passed ? 'qualified' : 'not_qualified',
        ]);
EOT;

if (substr_count($controllerContent, $oldQualUpdate) !== 1) {
    fail("ApplicationController.php: saveQualificationCheck() update block not found exactly once.");
}

// =======================================================================
// STEP 2 (verify + extract): resources/views/applications/show.blade.php
// =======================================================================

// --- 2a. Simple array edits (ASCII-safe lines) ---
$oldStatusOptions = "    \$statusOptions = ['submitted', 'screening', 'interview_scheduled', 'ranked', 'offer_sent', 'offer_accepted', 'offer_declined', 'hired', 'rejected'];";
$newStatusOptions = "    \$statusOptions = ['submitted', 'screening', 'qualified', 'not_qualified', 'interview_scheduled', 'ranked', 'offer_sent', 'offer_accepted', 'offer_declined', 'hired', 'rejected'];";
if (substr_count($showContent, $oldStatusOptions) !== 1) {
    fail("show.blade.php: \$statusOptions line not found exactly once.");
}

$oldShowColors = "        'screening'           => 'info',";
$newShowColors = "        'screening'           => 'info',\n        'qualified'           => 'success',\n        'not_qualified'       => 'danger',";
if (substr_count($showContent, $oldShowColors) !== 1) {
    fail("show.blade.php: \$statusColors 'screening' line not found exactly once.");
}

// --- 2b. Delete "Interview / exam schedule" card (ASCII-safe, hardcoded) ---
$oldScheduleCard = <<<'EOT'
        {{-- Interview / exam schedule --}}
        <div class="card">
            <div class="card-header bg-white py-2">
                <span class="fw-medium small">Interview / Exam Schedule</span>
            </div>
            <div class="card-body p-3">
                @forelse ($schedules as $s)
                <div class="d-flex justify-content-between align-items-start border-bottom py-2">
                    <div>
                        <div class="fw-medium small">{{ str_replace('_', ' ', ucfirst($s->type)) }}</div>
                        <div class="text-muted" style="font-size:.75rem;">
                            {{ \Carbon\Carbon::parse($s->scheduled_at)->format('M d, Y h:i A') }}
                            @if($s->location) <br>{{ $s->location }} @endif
                        </div>
                    </div>
                    <span class="badge text-bg-secondary ms-2">{{ ucfirst($s->status) }}</span>
                </div>
                @empty
                <p class="text-muted small mb-2">No schedule set yet.</p>
                @endforelse
                <button class="btn btn-sm btn-outline-secondary w-100 mt-2">
                    <i class="bi bi-plus-lg me-1"></i> Schedule
                </button>
            </div>
        </div>
EOT;

if (substr_count($showContent, $oldScheduleCard) !== 1) {
    fail("show.blade.php: Interview/Exam Schedule card not found exactly once in expected form.");
}

// --- 2c. Dynamic extraction: "Personal information" card (contains
//         encoding-uncertain dash characters, so extract at runtime
//         instead of hardcoding) ---
$piStartMarker = "        {{-- Personal information --}}";
$piStartPos = strpos($showContent, $piStartMarker);
if ($piStartPos === false) {
    fail("show.blade.php: Personal information card start marker not found.");
}
$qStartMarker = "        {{-- Qualifications --}}";
$qStartPos = strpos($showContent, $qStartMarker, $piStartPos);
if ($qStartPos === false) {
    fail("show.blade.php: Qualifications card start marker not found after Personal information.");
}
// The Personal information block runs from its marker up to (not
// including) the Qualifications marker that follows it.
$personalInfoBlock = substr($showContent, $piStartPos, $qStartPos - $piStartPos);
// Trim trailing blank lines for a clean splice, we'll control spacing
// explicitly when re-inserting.
$personalInfoBlock = rtrim($personalInfoBlock) . "\n";

// --- 2d. Dynamic extraction: inner content of "Qualifications" card ---
$cardBodyMarker = '<div class="card-body p-3">';
$qCardBodyPos = strpos($showContent, $cardBodyMarker, $qStartPos);
if ($qCardBodyPos === false) {
    fail("show.blade.php: Qualifications card-body marker not found.");
}
$rowMarker = '<div class="row g-2 small">';
$rowStart = strpos($showContent, $rowMarker, $qCardBodyPos);
if ($rowStart === false) {
    fail("show.blade.php: Qualifications row marker not found.");
}
$tailSeq = "\n                </div>\n            </div>\n        </div>\n    </div>\n</div>\n@push";
$tailPos = strpos($showContent, $tailSeq, $rowStart);
if ($tailPos === false) {
    fail("show.blade.php: expected end-of-file tail sequence not found after Qualifications card. File structure may differ from expected.");
}
if (substr_count($showContent, $tailSeq) !== 1) {
    fail("show.blade.php: end-of-file tail sequence is not unique -- aborting to avoid ambiguous match.");
}
// Inner content = from the row's opening tag through its own closing
// </div> (16-space indent), which is the first line of $tailSeq.
$qualInnerContent = substr($showContent, $rowStart, ($tailPos + strlen("\n                </div>")) - $rowStart);

// Full block to delete (comment through the card's own closing </div>,
// i.e. everything up to but not including the col-md-8/</div> close).
$qDeleteEnd = $tailPos + strlen("\n                </div>\n            </div>\n        </div>\n    </div>");
$qualificationsFullBlock = substr($showContent, $qStartPos, $qDeleteEnd - $qStartPos);

// --- 2e. Insertion anchor inside "Qualification checklist" card, right
//         before the editable form (ASCII-safe, hardcoded) ---
$checklistFormAnchor = <<<'EOT'
            <div class="card-body p-3">
                <form action="{{ route('applications.qualification-check', $application->id) }}" method="POST">
EOT;

if (substr_count($showContent, $checklistFormAnchor) !== 1) {
    fail("show.blade.php: Qualification checklist form anchor not found exactly once.");
}

// --- 2f. Right-column opening tag, insertion point for Personal Info ---
$rightColOpen = '<div class="col-md-8 d-flex flex-column gap-3">';
if (substr_count($showContent, $rightColOpen) !== 1) {
    fail("show.blade.php: right-column opening div not found exactly once.");
}

// =======================================================================
// STEP 3 (verify): resources/views/applications/index.blade.php
// =======================================================================

$oldIndexForeach = "@foreach (['submitted', 'screening', 'interview_scheduled', 'ranked', 'offer_sent', 'offer_accepted', 'offer_declined', 'hired', 'rejected'] as \$statusOption)";
$newIndexForeach = "@foreach (['submitted', 'screening', 'qualified', 'not_qualified', 'interview_scheduled', 'ranked', 'offer_sent', 'offer_accepted', 'offer_declined', 'hired', 'rejected'] as \$statusOption)";
if (substr_count($indexContent, $oldIndexForeach) !== 1) {
    fail("index.blade.php: status filter @foreach line not found exactly once.");
}

$oldIndexColors = "                                'screening' => 'info',";
$newIndexColors = "                                'screening' => 'info',\n                                'qualified' => 'success',\n                                'not_qualified' => 'danger',";
if (substr_count($indexContent, $oldIndexColors) !== 1) {
    fail("index.blade.php: \$statusColors 'screening' line not found exactly once.");
}

// =======================================================================
// STEP 4 (verify): resources/views/welcome.blade.php
// =======================================================================

$oldStepMap = "    const stepMap = { submitted:1, screening:2, shortlisted:3, interview_scheduled:4, assessed:4, ranked:4, ranking_sent:4, offer_sent:5, offer_accepted:6, hired:6, offer_declined:6, rejected:6 };";
$newStepMap = "    const stepMap = { submitted:1, screening:2, shortlisted:3, qualified:4, not_qualified:6, interview_scheduled:4, assessed:4, ranked:4, ranking_sent:4, offer_sent:5, offer_accepted:6, hired:6, offer_declined:6, rejected:6 };";
if (substr_count($welcomeContent, $oldStepMap) !== 1) {
    fail("welcome.blade.php: stepMap line not found exactly once.");
}

$oldBadgeMap = "      submitted:'s-submitted', screening:'s-screening', shortlisted:'s-shortlisted',";
$newBadgeMap = "      submitted:'s-submitted', screening:'s-screening', shortlisted:'s-shortlisted', qualified:'s-qualified', not_qualified:'s-not_qualified',";
if (substr_count($welcomeContent, $oldBadgeMap) !== 1) {
    fail("welcome.blade.php: badgeMap line not found exactly once.");
}

$oldGreenCss = "  .s-offer_sent,.s-offer_accepted,.s-hired { background: #e8f5e9; color: #1b5e20; }";
$newGreenCss = "  .s-offer_sent,.s-offer_accepted,.s-hired,.s-qualified { background: #e8f5e9; color: #1b5e20; }";
if (substr_count($welcomeContent, $oldGreenCss) !== 1) {
    fail("welcome.blade.php: green status CSS line not found exactly once.");
}

$oldRedCss = "  .s-offer_declined,.s-rejected { background: #ffebee; color: #b71c1c; }";
$newRedCss = "  .s-offer_declined,.s-rejected,.s-not_qualified { background: #ffebee; color: #b71c1c; }";
if (substr_count($welcomeContent, $oldRedCss) !== 1) {
    fail("welcome.blade.php: red status CSS line not found exactly once.");
}

// =======================================================================
// All verifications passed. Build final file contents in memory before
// writing anything.
// =======================================================================

// --- Controller ---
$newControllerContent = str_replace($oldImport, '', $controllerContent);
$newControllerContent = str_replace($oldShowMethod, $newShowMethod, $newControllerContent);
$newControllerContent = str_replace($oldValidationLine, $newValidationLine, $newControllerContent);
$newControllerContent = str_replace($oldQualUpdate, $newQualUpdate, $newControllerContent);

// --- show.blade.php ---
$newShowContent = $showContent;
// 1. Simple array edits
$newShowContent = str_replace($oldStatusOptions, $newStatusOptions, $newShowContent);
$newShowContent = str_replace($oldShowColors, $newShowColors, $newShowContent);
// 2. Remove Interview/Exam Schedule card
$newShowContent = str_replace($oldScheduleCard, '', $newShowContent);
// 3. Merge Qualifications inner content into Qualification checklist,
//    right before the editable form, with a small heading + divider.
$mergedInsert = <<<'EOT'
            <div class="card-body p-3">
                <div class="mb-1" style="font-size:.75rem;" class="text-muted">Candidate's self-reported qualifications</div>
EOT;
// (Insert the extracted row content, then a divider, then the form.)
$newFormAnchor = "            <div class=\"card-body p-3\">\n"
    . "                <p class=\"text-muted mb-2\" style=\"font-size:.75rem;\">Candidate's self-reported qualifications</p>\n"
    . "                " . trim($qualInnerContent) . "\n"
    . "                <hr class=\"my-3\">\n"
    . "                <form action=\"{{ route('applications.qualification-check', \$application->id) }}\" method=\"POST\">";
$newShowContent = str_replace($checklistFormAnchor, $newFormAnchor, $newShowContent);
// 4. Delete the now-redundant standalone Qualifications card
$newShowContent = str_replace($qualificationsFullBlock, '', $newShowContent);
// 5. Remove Personal information card from its old location...
$newShowContent = str_replace($personalInfoBlock, '', $newShowContent);
// 6. ...and re-insert it at the top of the right column.
$newShowContent = str_replace(
    $rightColOpen,
    $rightColOpen . "\n" . $personalInfoBlock,
    $newShowContent
);

// --- index.blade.php ---
$newIndexContent = str_replace($oldIndexForeach, $newIndexForeach, $indexContent);
$newIndexContent = str_replace($oldIndexColors, $newIndexColors, $newIndexContent);

// --- welcome.blade.php ---
$newWelcomeContent = str_replace($oldStepMap, $newStepMap, $welcomeContent);
$newWelcomeContent = str_replace($oldBadgeMap, $newBadgeMap, $newWelcomeContent);
$newWelcomeContent = str_replace($oldGreenCss, $newGreenCss, $newWelcomeContent);
$newWelcomeContent = str_replace($oldRedCss, $newRedCss, $newWelcomeContent);

// =======================================================================
// Write everything
// =======================================================================

// --- Migration (new file) ---
$timestamp = date('Y_m_d_His');
$migrationFile = $migrationsDir . '/' . $timestamp . '_add_qualified_statuses_to_applications_status_enum.php';
$migrationContent = <<<'EOT'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE applications
            MODIFY status ENUM(
                'submitted',
                'screening',
                'shortlisted',
                'interview',
                'assessed',
                'ranked',
                'ranking_sent',
                'offer',
                'offer_sent',
                'offer_accepted',
                'offer_declined',
                'qualified',
                'not_qualified',
                'hired',
                'rejected'
            ) NOT NULL DEFAULT 'submitted'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE applications
            MODIFY status ENUM(
                'submitted',
                'screening',
                'shortlisted',
                'interview',
                'assessed',
                'ranked',
                'ranking_sent',
                'offer',
                'offer_sent',
                'offer_accepted',
                'offer_declined',
                'hired',
                'rejected'
            ) NOT NULL DEFAULT 'submitted'
        ");
    }
};
EOT;

if (file_put_contents($migrationFile, $migrationContent) === false) {
    fail("Failed to write migration file.");
}
echo "Created migration: $migrationFile\n";

backup($controllerPath);
if (file_put_contents($controllerPath, $newControllerContent) === false) {
    fail("Failed to write ApplicationController.php.");
}
echo "Patched: $controllerPath\n";

backup($showPath);
if (file_put_contents($showPath, $newShowContent) === false) {
    fail("Failed to write show.blade.php.");
}
echo "Patched: $showPath\n";

backup($indexPath);
if (file_put_contents($indexPath, $newIndexContent) === false) {
    fail("Failed to write index.blade.php.");
}
echo "Patched: $indexPath\n";

backup($welcomePath);
if (file_put_contents($welcomePath, $newWelcomeContent) === false) {
    fail("Failed to write welcome.blade.php.");
}
echo "Patched: $welcomePath\n";

echo "\nSuccess.\n";
echo "Next steps:\n";
echo "  1. Review the new migration: $migrationFile\n";
echo "  2. Run: php artisan migrate\n";
echo "  3. Reload an application details page and check:\n";
echo "     - Personal Information is now the first card in the right column\n";
echo "     - Qualification Checklist shows the candidate's self-reported\n";
echo "       quals above the editable form, and still saves/loads correctly\n";
echo "     - Interview / Exam Schedule card is gone\n";
echo "     - Saving a qualification check sets status to Qualified/Not Qualified\n";

@unlink(__FILE__);
echo "This patch script has removed itself.\n";
