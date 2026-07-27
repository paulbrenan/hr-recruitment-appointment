<?php

/**
 * fix_applications_location_and_email.php
 *
 * WHAT THIS DOES:
 *   1. Creates migration to add job_posting_location_id to applications table
 *   2. Patches CandidateAuthController to check email uniqueness against
 *      candidates WHO HAVE SUBMITTED APPLICATIONS (not all candidates),
 *      so a failed partial registration doesn't block re-registration
 *
 * HOW TO RUN:
 *   php fix_applications_location_and_email.php    (from project root)
 *   php artisan migrate                             (required)
 *
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
    if ($count === 0) { echo "\n❌ Pattern not found in $path\nLabel: $label\n"; exit(1); }
    if ($count > 1)   { echo "\n❌ Pattern found $count times in $path\nLabel: $label\n"; exit(1); }
    backup($path);
    file_put_contents($path, str_replace($old, $new, $content));
    echo "  [ok ] $label\n";
}

function write_new(string $path, string $content, string $label): void {
    backup($path);
    file_put_contents($path, $content);
    echo "  [ok ] $label\n";
}

echo "\n=== fix_applications_location_and_email.php ===\n\n";

// ─── 1. Migration: add job_posting_location_id to applications ────────────

echo "[1] Creating migration...\n";

$migrationDir = ROOT . '/database/migrations';
$migrationFile = $migrationDir . '/' . date('Y_m_d_His') . '_add_location_id_to_applications_table.php';

$migrationContent = <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('job_posting_location_id')
                  ->nullable()
                  ->after('job_posting_id')
                  ->constrained('job_posting_locations')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['job_posting_location_id']);
            $table->dropColumn('job_posting_location_id');
        });
    }
};
PHP;

write_new($migrationFile, $migrationContent, 'Migration: add job_posting_location_id to applications');

// ─── 2. Fix email uniqueness — allow re-registration if no application ────

echo "\n[2] Patching CandidateAuthController — email uniqueness rule...\n";

$controllerPath = ROOT . '/app/Http/Controllers/CandidateAuthController.php';

// Replace the email validation rule to check against candidates with applications
$oldEmailRule = "            'email'            => ['required', 'email', 'max:255', 'unique:candidates,email'],";

$newEmailRule = <<<'PHP'
            // Only block the email if this candidate already has a submitted
            // application. A candidate record without an application means a
            // previous registration attempt failed partway through — allow retry.
            'email'            => [
                'required', 'email', 'max:255',
                \Illuminate\Validation\Rule::unique('candidates', 'email')->where(function ($query) {
                    return $query->whereExists(function ($sub) {
                        $sub->select(\DB::raw(1))
                            ->from('applications')
                            ->whereColumn('applications.candidate_id', 'candidates.id');
                    });
                }),
            ],
PHP;

apply_patch($controllerPath, $oldEmailRule, $newEmailRule, 'CandidateAuthController: email unique only if candidate has application');

// ─── 3. Also clean up orphaned candidate records before creating a new one ─

echo "\n[3] Patching CandidateAuthController — clean up orphaned candidates...\n";

$oldCandidateCreate = <<<'PHP'
        $candidate = Candidate::create([
PHP;

$newCandidateCreate = <<<'PHP'
        // Clean up any orphaned candidate record for this email
        // (a previous registration that failed before creating the application).
        Candidate::where('email', $validated['email'])
            ->whereDoesntHave('applications')
            ->delete();

        $candidate = Candidate::create([
PHP;

apply_patch($controllerPath, $oldCandidateCreate, $newCandidateCreate, 'CandidateAuthController: delete orphaned candidate before create');

// ─── 4. Add applications() relationship to Candidate model if missing ──────

echo "\n[4] Checking Candidate model for applications() relationship...\n";

$candidatePath = ROOT . '/app/Models/Candidate.php';
if (file_exists($candidatePath)) {
    $content = file_get_contents($candidatePath);
    if (strpos($content, 'function applications') === false) {
        // Add the relationship before the last closing brace
        backup($candidatePath);
        $patched = preg_replace('/(\n\})\s*$/', "\n\n    public function applications()\n    {\n        return \$this->hasMany(Application::class);\n    }\n}", $content);
        file_put_contents($candidatePath, $patched);
        echo "  [ok ] Candidate model: added applications() hasMany\n";
    } else {
        echo "  [--] Candidate model already has applications() — skipping\n";
    }
} else {
    echo "  [!!] Candidate model not found — skipping\n";
}

echo <<<TEXT

✅ Done.

NEXT STEPS:
  1. php artisan migrate
     → Adds job_posting_location_id to applications table

  2. Delete the orphaned candidate records from your test runs:
     php artisan tinker
     App\Models\Candidate::whereDoesntHave('applications')->delete();

  3. Try registering again — the email block is now only triggered
     if a completed application already exists for that email.

DELETE this script after running.

TEXT;
