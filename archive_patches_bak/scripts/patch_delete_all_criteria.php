<?php
/**
 * patch_delete_all_criteria.php
 *
 * Run from the project root:
 *   php patch_delete_all_criteria.php
 *
 * What it does:
 *  1. app/Http/Controllers/AssessmentController.php
 *     - adds destroyAllCriteria(Request $request): deletes every
 *       AssessmentCriterion for the given job_posting_id, then redirects
 *       back to whichever page called it (so it works fine from the
 *       job-postings.show pipeline view).
 *  2. resources/views/job-postings/show.blade.php
 *     - adds a "Delete all" button next to "Add criterion" in the
 *       Assessment criteria card, shown only when there ARE criteria and
 *       the posting isn't closed.
 *
 * IMPORTANT — you must also add one route yourself (routes/web.php was not
 * provided, so this script does not touch it). Put this near your existing
 * assessments.criteria.destroy route:
 *
 *   Route::delete('/assessments/criteria/destroy-all', [AssessmentController::class, 'destroyAllCriteria'])
 *       ->name('assessments.criteria.destroy-all');
 *
 * Safe to run multiple times: aborts with no changes if an expected block
 * isn't found exactly. A .bak copy is made before any write.
 */

$root = __DIR__;

function patchFile(string $path, array $replacements, string $label): void
{
    if (!file_exists($path)) {
        echo "[SKIP] $label — file not found: $path\n";
        return;
    }

    $content = file_get_contents($path);
    $original = $content;

    foreach ($replacements as $i => [$search, $replace]) {
        if (strpos($content, $search) === false) {
            echo "[ABORT] $label — expected block #$i not found (file may already be patched, or has changed). No changes written.\n";
            return;
        }
    }

    foreach ($replacements as [$search, $replace]) {
        $content = substr_replace($content, $replace, strpos($content, $search), strlen($search));
    }

    if ($content === $original) {
        echo "[SKIP] $label — no changes needed.\n";
        return;
    }

    $backup = $path . '.bak';
    if (!file_exists($backup)) {
        copy($path, $backup);
    } else {
        copy($path, $path . '.bak.' . date('Ymd_His'));
    }

    file_put_contents($path, $content);
    echo "[OK] $label — patched. Backup at: $backup\n";
}

// ── 1. Controller ────────────────────────────────────────────────────────
$controllerPath = $root . '/app/Http/Controllers/AssessmentController.php';

$ctrlOld = <<<'OLD'
    public function destroyCriterion($id)
    {
        $criterion = AssessmentCriterion::findOrFail($id);
        $jobPostingId = $criterion->job_posting_id;
        $criterion->delete();

        return redirect()
            ->route('assessments.index', ['job_posting' => $jobPostingId])
            ->with('success', 'Assessment criterion removed.');
    }
OLD;

$ctrlNew = <<<'NEW'
    public function destroyCriterion($id)
    {
        $criterion = AssessmentCriterion::findOrFail($id);
        $jobPostingId = $criterion->job_posting_id;
        $criterion->delete();

        return redirect()
            ->route('assessments.index', ['job_posting' => $jobPostingId])
            ->with('success', 'Assessment criterion removed.');
    }

    /**
     * Delete every assessment criterion for a given posting at once.
     * Used by the "Delete all" button on the job-postings.show pipeline
     * view. Redirects back to whichever page the request came from.
     */
    public function destroyAllCriteria(Request $request)
    {
        $validated = $request->validate([
            'job_posting_id' => 'required|exists:job_postings,id',
        ]);

        $count = AssessmentCriterion::where('job_posting_id', $validated['job_posting_id'])->count();
        AssessmentCriterion::where('job_posting_id', $validated['job_posting_id'])->delete();

        return back()->with('success', "Deleted all {$count} assessment criteria for this posting.");
    }
NEW;

patchFile($controllerPath, [[$ctrlOld, $ctrlNew]], 'AssessmentController.php');


// ── 2. Blade view ────────────────────────────────────────────────────────
$bladePath = $root . '/resources/views/job-postings/show.blade.php';

$bladeOld = <<<'OLD'
                    @if ($posting->status === 'closed')
                    <button class="btn btn-sm btn-outline-secondary" disabled title="This posting is closed.">
                        <i class="bi bi-plus-lg me-1"></i> Add criterion
                    </button>
                    @elseif ($remainingWeight > 0)
                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addCriterionModal">
                        <i class="bi bi-plus-lg me-1"></i> Add criterion
                    </button>
                    @else
                    <button class="btn btn-sm btn-outline-secondary" disabled title="No weight remaining">
                        <i class="bi bi-plus-lg me-1"></i> Add criterion
                    </button>
                    @endif
OLD;

$bladeNew = <<<'NEW'
                    @if ($posting->status === 'closed')
                    <button class="btn btn-sm btn-outline-secondary" disabled title="This posting is closed.">
                        <i class="bi bi-plus-lg me-1"></i> Add criterion
                    </button>
                    @elseif ($remainingWeight > 0)
                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addCriterionModal">
                        <i class="bi bi-plus-lg me-1"></i> Add criterion
                    </button>
                    @else
                    <button class="btn btn-sm btn-outline-secondary" disabled title="No weight remaining">
                        <i class="bi bi-plus-lg me-1"></i> Add criterion
                    </button>
                    @endif

                    @if ($criteria->isNotEmpty() && $posting->status !== 'closed')
                    <form method="POST" action="{{ route('assessments.criteria.destroy-all') }}" class="d-inline ms-2"
                          onsubmit="return confirm('Delete ALL {{ $criteria->count() }} assessment criteria for this posting? This cannot be undone.')">
                        @csrf @method('DELETE')
                        <input type="hidden" name="job_posting_id" value="{{ $posting->id }}">
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash me-1"></i> Delete all
                        </button>
                    </form>
                    @endif
NEW;

patchFile($bladePath, [[$bladeOld, $bladeNew]], 'show.blade.php');

echo "\nDone. Remember to add the route manually (see comment at top of this script).\n";
