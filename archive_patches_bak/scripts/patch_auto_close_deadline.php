<?php

/**
 * patch_auto_close_deadline.php
 *
 * WHAT THIS DOES:
 *   1. Creates app/Console/Commands/CloseExpiredJobPostings.php
 *      — finds postings where closes_at <= today and status != 'closed'
 *      — sets them to 'closed'
 *      — cascades 'rejected' to all non-hired applications on those postings
 *
 *   2. Patches routes/console.php — registers the command to run daily
 *
 *   3. Patches routes/web.php — adds the hireApplicant route
 *      (needed by the status pipeline patch from the previous script)
 *
 * HOW TO RUN:
 *   php patch_auto_close_deadline.php     (from project root)
 *   No migration needed — no schema changes.
 *
 * DELETE this script after running.
 */

define('ROOT', __DIR__);

// ─── helpers ───────────────────────────────────────────────────────────────

function backup(string $path): void {
    if (!file_exists($path)) return;
    $bak = $path . '.bak';
    $i = 2;
    while (file_exists($bak)) { $bak = $path . '.bak' . $i++; }
    copy($path, $bak);
    echo "  [bak] $bak\n";
}

function apply_patch(string $path, string $old, string $new, string $label): void {
    if (!file_exists($path)) abort("File not found: $path");
    $content = file_get_contents($path);
    $count = substr_count($content, $old);
    if ($count === 0) abort("PATCH ABORTED — expected content not found in $path\nLabel: $label\nSearched for:\n$old");
    if ($count > 1)  abort("PATCH ABORTED — pattern found $count times (expected 1) in $path\nLabel: $label");
    backup($path);
    file_put_contents($path, str_replace($old, $new, $content));
    echo "  [ok ] $label\n";
}

function write_new(string $path, string $content, string $label): void {
    backup($path);
    file_put_contents($path, $content);
    echo "  [ok ] $label\n";
}

function abort(string $msg): void {
    echo "\n❌ $msg\n\n";
    exit(1);
}

echo "\n=== patch_auto_close_deadline.php ===\n\n";

// ─── 1. Artisan command ────────────────────────────────────────────────────

echo "[1] Creating app/Console/Commands/CloseExpiredJobPostings.php...\n";

$commandsDir = ROOT . '/app/Console/Commands';
if (!is_dir($commandsDir)) {
    mkdir($commandsDir, 0755, true);
    echo "  [mk ] app/Console/Commands/ directory created\n";
}

$commandPath = $commandsDir . '/CloseExpiredJobPostings.php';

$commandContent = <<<'PHP'
<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\JobPosting;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CloseExpiredJobPostings extends Command
{
    protected $signature   = 'job-postings:close-expired';
    protected $description = 'Automatically close job postings whose deadline has passed and reject all non-hired applicants.';

    public function handle(): int
    {
        $today = Carbon::today();

        // Find postings that have a closes_at in the past and are not yet closed
        $expired = JobPosting::whereNotNull('closes_at')
            ->where('closes_at', '<', $today)
            ->where('status', '!=', 'closed')
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired job postings found.');
            return self::SUCCESS;
        }

        foreach ($expired as $posting) {
            // Reject all applicants that have not been hired
            $rejected = Application::where('job_posting_id', $posting->id)
                ->where('status', '!=', 'hired')
                ->update(['status' => 'rejected']);

            // Close the posting
            $posting->update(['status' => 'closed']);

            $this->line("  Closed: [{$posting->id}] {$posting->title} (deadline: {$posting->closes_at}) — {$rejected} applicant(s) rejected.");
        }

        $this->info("Done. {$expired->count()} posting(s) closed.");

        return self::SUCCESS;
    }
}
PHP;

write_new($commandPath, $commandContent, 'CloseExpiredJobPostings command');

// ─── 2. routes/console.php — register scheduler ───────────────────────────

echo "\n[2] Patching routes/console.php...\n";

$consolePath = ROOT . '/routes/console.php';

$oldSchedule = "Schedule::command('schedules:send-reminders')->hourly();";

$newSchedule = "Schedule::command('schedules:send-reminders')->hourly();

// Auto-close job postings whose closes_at date has passed.
// Runs once a day at midnight; safe to run more often if needed.
Schedule::command('job-postings:close-expired')->dailyAt('00:00');";

apply_patch($consolePath, $oldSchedule, $newSchedule, 'console.php: register close-expired daily schedule');

// ─── 3. routes/web.php — add hireApplicant route ──────────────────────────

echo "\n[3] Patching routes/web.php...\n";

$webPath = ROOT . '/routes/web.php';

$oldJobPostingRoutes = "Route::delete('/job-postings/{id}', [JobPostingController::class, 'destroy'])->name('job-postings.destroy');";

$newJobPostingRoutes = "Route::delete('/job-postings/{id}', [JobPostingController::class, 'destroy'])->name('job-postings.destroy');
// Mark one applicant as hired → rejects all others on same posting + closes posting
Route::post('/job-postings/{postingId}/hire/{applicationId}', [JobPostingController::class, 'hireApplicant'])->name('job-postings.hire');";

apply_patch($webPath, $oldJobPostingRoutes, $newJobPostingRoutes, 'web.php: add hireApplicant route');

// ─── Done ──────────────────────────────────────────────────────────────────

echo <<<TEXT

✅ All patches applied successfully.

NEXT STEPS:

  1. Test the command manually:
     php artisan job-postings:close-expired
     (Should report "No expired job postings found." if none are past deadline,
      or list which postings it closed.)

  2. Make sure the Laravel scheduler is running on your machine.
     For local dev (XAMPP / Windows), run this in a terminal and leave it open:
     php artisan schedule:work
     (This ticks the scheduler every minute so the daily command will fire.)

  3. The hireApplicant route is now registered at:
     POST /job-postings/{postingId}/hire/{applicationId}
     name: job-postings.hire
     Wire a "Mark as Hired" button on the application show page when ready.

  4. PDF import (closes_at field) — noted for later. When you're ready,
     the import confirm step in JobPostingImportController needs to map
     the parsed vacancy close date into closes_at on the created JobPosting.

  5. Delete this script.

TEXT;
