<?php

/**
 * patch_status_pipeline.php
 *
 * WHAT THIS DOES:
 *   1. Creates a migration that changes job_postings.status enum to pipeline stages:
 *      open | screening | interview_scheduled | ranking | closed
 *   2. Patches JobPostingController.php — update() now cascades status to all applications
 *      + hired-one → reject-rest + close-posting logic
 *   3. Patches resources/views/job-postings/form.blade.php — status dropdown
 *   4. Patches resources/views/job-postings/index.blade.php — badge colors + summary cards
 *
 * HOW TO RUN:
 *   php patch_status_pipeline.php          (from project root)
 *   php artisan migrate                    (run afterward — required)
 *
 * SAFE TO RE-RUN: No. Each file is backed up to .bak before changes.
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
    if (!file_exists($path)) {
        abort("File not found: $path");
    }
    $content = file_get_contents($path);
    $count = substr_count($content, $old);
    if ($count === 0) {
        abort("PATCH ABORTED — could not find the expected content in $path\nLabel: $label\nSearched for:\n$old");
    }
    if ($count > 1) {
        abort("PATCH ABORTED — found the pattern $count times (expected exactly 1) in $path\nLabel: $label");
    }
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

echo "\n=== patch_status_pipeline.php ===\n\n";

// ─── 1. Migration ──────────────────────────────────────────────────────────

echo "[1] Creating migration...\n";

$migrationDir = ROOT . '/database/migrations';
if (!is_dir($migrationDir)) {
    abort("database/migrations directory not found. Are you in the project root?");
}

$migrationFile = $migrationDir . '/' . date('Y_m_d_His') . '_update_job_postings_status_to_pipeline.php';

$migrationContent = <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Change column to string temporarily so we can safely alter the enum
        Schema::table('job_postings', function (Blueprint $table) {
            $table->string('status', 30)->default('open')->change();
        });

        // Step 2: Map any legacy values to the new pipeline values
        DB::table('job_postings')->where('status', 'draft')->update(['status' => 'open']);
        DB::table('job_postings')->where('status', 'filled')->update(['status' => 'closed']);
        // 'open' and 'closed' are unchanged

        // Step 3: Apply the new enum
        Schema::table('job_postings', function (Blueprint $table) {
            $table->enum('status', ['open', 'screening', 'interview_scheduled', 'ranking', 'closed'])
                  ->default('open')
                  ->change();
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->string('status', 30)->default('draft')->change();
        });

        Schema::table('job_postings', function (Blueprint $table) {
            $table->enum('status', ['draft', 'open', 'filled', 'closed'])
                  ->default('draft')
                  ->change();
        });
    }
};
PHP;

write_new($migrationFile, $migrationContent, 'Migration: update_job_postings_status_to_pipeline');

// ─── 2. JobPostingController.php ───────────────────────────────────────────

echo "\n[2] Patching JobPostingController.php...\n";

$controllerPath = ROOT . '/app/Http/Controllers/JobPostingController.php';

// 2a. Replace validation rule for status
apply_patch(
    $controllerPath,
    "            'status' => ['required', 'in:draft,open,filled,closed'],",
    "            'status' => ['required', 'in:open,screening,interview_scheduled,ranking,closed'],",
    'Controller: update status validation rule'
);

// 2b. Replace update() method with cascade logic
$oldUpdate = <<<'PHP'
    public function update(Request $request, $id)
    {
        $posting = JobPosting::findOrFail($id);

        $validated = $request->validate($this->rules());

        $posting->update($validated);

        return redirect()
            ->route('job-postings.index')
            ->with('success', 'Job posting updated successfully.');
    }
PHP;

$newUpdate = <<<'PHP'
    public function update(Request $request, $id)
    {
        $posting = JobPosting::findOrFail($id);

        $validated = $request->validate($this->rules());

        $oldStatus = $posting->status;
        $newStatus = $validated['status'];

        $posting->update($validated);

        // Cascade status to applications when the posting stage changes
        if ($oldStatus !== $newStatus) {
            $this->cascadeStatusToApplications($posting, $newStatus);
        }

        return redirect()
            ->route('job-postings.index')
            ->with('success', 'Job posting updated successfully.');
    }

    /**
     * Map job posting pipeline stage → application status, then bulk-update.
     *
     * Mapping:
     *   open                → submitted
     *   screening           → screening
     *   interview_scheduled → interview_scheduled
     *   ranking             → ranked
     *   closed              → rejected  (for all non-hired applicants)
     *
     * Special rule: if any applicant on this posting is already 'hired',
     * closing the posting will NOT override their status (hired stays hired).
     */
    private function cascadeStatusToApplications(JobPosting $posting, string $postingStatus): void
    {
        $map = [
            'open'                 => 'submitted',
            'screening'            => 'screening',
            'interview_scheduled'  => 'interview_scheduled',
            'ranking'              => 'ranked',
            'closed'               => 'rejected',
        ];

        if (!isset($map[$postingStatus])) {
            return;
        }

        $applicationStatus = $map[$postingStatus];

        $query = Application::where('job_posting_id', $posting->id);

        // Never override an applicant who has already been hired
        if ($applicationStatus === 'rejected') {
            $query->where('status', '!=', 'hired');
        }

        $query->update(['status' => $applicationStatus]);
    }

    /**
     * Mark one applicant as Hired, reject all others on the same posting,
     * and close the posting. Called from ApplicationController or a
     * dedicated route — not directly from the form.
     */
    public function hireApplicant(Request $request, $postingId, $applicationId)
    {
        $posting     = JobPosting::findOrFail($postingId);
        $application = Application::where('job_posting_id', $postingId)
                                  ->findOrFail($applicationId);

        // Hire the selected applicant
        $application->update(['status' => 'hired']);

        // Reject all others on this posting
        Application::where('job_posting_id', $postingId)
                   ->where('id', '!=', $applicationId)
                   ->where('status', '!=', 'hired')
                   ->update(['status' => 'rejected']);

        // Close the posting
        $posting->update(['status' => 'closed']);

        return redirect()
            ->back()
            ->with('success', 'Applicant marked as hired. All other applicants have been rejected and the posting is now closed.');
    }
PHP;

apply_patch(
    $controllerPath,
    $oldUpdate,
    $newUpdate,
    'Controller: update() with cascade + hireApplicant()'
);

// ─── 3. form.blade.php — status dropdown ───────────────────────────────────

echo "\n[3] Patching form.blade.php...\n";

$formPath = ROOT . '/resources/views/job-postings/form.blade.php';

$oldStatusSelect = <<<'BLADE'
                <div class="col-md-4">
                    <label class="form-label small fw-medium">Status</label>
                    <select class="form-select" name="status">
                        @foreach (['draft', 'open', 'filled', 'closed'] as $status)
                            <option value="{{ $status }}" {{ ($posting->status ?? 'draft') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
BLADE;

$newStatusSelect = <<<'BLADE'
                <div class="col-md-4">
                    <label class="form-label small fw-medium">Status</label>
                    <select class="form-select" name="status">
                        @php
                            $pipelineStages = [
                                'open'                => 'Open',
                                'screening'           => 'Screening',
                                'interview_scheduled' => 'Interview Scheduled',
                                'ranking'             => 'Ranking',
                                'closed'              => 'Closed',
                            ];
                        @endphp
                        @foreach ($pipelineStages as $value => $label)
                            <option value="{{ $value }}" {{ ($posting->status ?? 'open') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text" style="font-size: 0.72rem;">
                        Changing the stage will update all applicants on this posting.
                    </div>
                </div>
BLADE;

apply_patch($formPath, $oldStatusSelect, $newStatusSelect, 'form.blade.php: status dropdown → pipeline stages');

// ─── 4. index.blade.php — summary cards + badge colors ─────────────────────

echo "\n[4] Patching index.blade.php...\n";

$indexPath = ROOT . '/resources/views/job-postings/index.blade.php';

// 4a. Summary cards
$oldCards = <<<'BLADE'
<div class="row mb-3 g-2">
    <div class="col-md-3">
        <div class="card p-3">
            <div class="text-muted small">Open postings</div>
            <div class="fs-4 fw-semibold">{{ $postings->where('status', 'open')->count() }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3">
            <div class="text-muted small">Total vacancies</div>
            <div class="fs-4 fw-semibold">{{ $postings->sum('vacancies') }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3">
            <div class="text-muted small">Filled</div>
            <div class="fs-4 fw-semibold">{{ $postings->where('status', 'filled')->count() }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3">
            <div class="text-muted small">Closed</div>
            <div class="fs-4 fw-semibold">{{ $postings->where('status', 'closed')->count() }}</div>
        </div>
    </div>
</div>
BLADE;

$newCards = <<<'BLADE'
<div class="row mb-3 g-2">
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Open</div>
            <div class="fs-4 fw-semibold">{{ $postings->where('status', 'open')->count() }}</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Screening</div>
            <div class="fs-4 fw-semibold">{{ $postings->where('status', 'screening')->count() }}</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Interview</div>
            <div class="fs-4 fw-semibold">{{ $postings->where('status', 'interview_scheduled')->count() }}</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Ranking</div>
            <div class="fs-4 fw-semibold">{{ $postings->where('status', 'ranking')->count() }}</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Closed</div>
            <div class="fs-4 fw-semibold">{{ $postings->where('status', 'closed')->count() }}</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Total vacancies</div>
            <div class="fs-4 fw-semibold">{{ $postings->sum('vacancies') }}</div>
        </div>
    </div>
</div>
BLADE;

apply_patch($indexPath, $oldCards, $newCards, 'index.blade.php: summary cards → pipeline stages');

// 4b. Badge colors
$oldBadge = <<<'BLADE'
                    <td>
                        @php
                            $statusColors = [
                                'draft' => 'secondary',
                                'open' => 'success',
                                'filled' => 'primary',
                                'closed' => 'dark',
                            ];
                        @endphp
                        <span class="badge badge-status text-bg-{{ $statusColors[$posting->status] ?? 'secondary' }}">
                            {{ ucfirst($posting->status) }}
                        </span>
                    </td>
BLADE;

$newBadge = <<<'BLADE'
                    <td>
                        @php
                            $statusColors = [
                                'open'                => 'success',
                                'screening'           => 'info',
                                'interview_scheduled' => 'primary',
                                'ranking'             => 'warning',
                                'closed'              => 'dark',
                            ];
                            $statusLabels = [
                                'open'                => 'Open',
                                'screening'           => 'Screening',
                                'interview_scheduled' => 'Interview',
                                'ranking'             => 'Ranking',
                                'closed'              => 'Closed',
                            ];
                        @endphp
                        <span class="badge badge-status text-bg-{{ $statusColors[$posting->status] ?? 'secondary' }}">
                            {{ $statusLabels[$posting->status] ?? ucfirst($posting->status) }}
                        </span>
                    </td>
BLADE;

apply_patch($indexPath, $oldBadge, $newBadge, 'index.blade.php: badge colors + labels → pipeline stages');

// ─── Done ──────────────────────────────────────────────────────────────────

echo <<<TEXT

✅ All patches applied successfully.

NEXT STEPS (in order):
  1. php artisan migrate
     → Alters job_postings.status enum, maps draft→open and filled→closed

  2. Add the hireApplicant route to routes/web.php:
     POST /job-postings/{postingId}/hire/{applicationId}
     → JobPostingController@hireApplicant

     Example line to add inside your HR admin routes:
     Route::post('/job-postings/{postingId}/hire/{applicationId}', [JobPostingController::class, 'hireApplicant'])
          ->name('job-postings.hire');

  3. On the application show/detail page, add a "Mark as Hired" button
     that POSTs to that route (only shown when posting is in 'ranking' stage).

  4. Delete this script.

TEXT;
