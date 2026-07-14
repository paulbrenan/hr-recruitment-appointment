<?php
/**
 * fix_add_archive_feature.php
 *
 * Adds an "Archive" action for closed job postings:
 *   - routes/web.php: POST /job-postings/{id}/archive
 *   - JobPostingController::archive() -- only allowed from status 'closed'
 *   - show.blade.php: "Archive posting" button in the sidebar, shown only
 *     when the posting is closed; status badge colors/labels updated to
 *     include 'archived'.
 *
 * REQUIRES: run the accompanying migration FIRST --
 *   2026_07_14_010000_add_archived_status_to_job_postings.php
 * (copy it into database/migrations/, then `php artisan migrate`)
 * -- otherwise the 'archived' status value will be rejected by MySQL's
 * enum column.
 *
 * HOW TO RUN:
 *   php fix_add_archive_feature.php   (from project root)
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

echo "\n=== fix_add_archive_feature.php ===\n\n";

$routesPath     = ROOT . '/routes/web.php';
$controllerPath = ROOT . '/app/Http/Controllers/JobPostingController.php';
$showPath       = ROOT . '/resources/views/job-postings/show.blade.php';

// ─── 1. Route ────────────────────────────────────────────────────────────

echo "[1] Adding archive route...\n";

apply_patch(
    $routesPath,
    "Route::post('/job-postings/{id}/advance', [JobPostingController::class, 'advance'])->name('job-postings.advance');",
    "Route::post('/job-postings/{id}/advance', [JobPostingController::class, 'advance'])->name('job-postings.advance');
Route::post('/job-postings/{id}/archive', [JobPostingController::class, 'archive'])->name('job-postings.archive');",
    'web.php: job-postings.archive route'
);

// ─── 2. Controller method ────────────────────────────────────────────────

echo "\n[2] Adding archive() method to JobPostingController...\n";

apply_patch(
    $controllerPath,
    '    /**
     * DELETE /job-postings/{posting}/panelists/{panelist}
     * Removes a panelist from this posting\'s panel (pivot only, not global pool).
     */
    public function detachPanelist($postingId, $panelistId)',
    '    /**
     * POST /job-postings/{id}/archive
     * Archives a closed posting. Only valid from \'closed\' -- archiving is
     * a terminal, one-way move out of the active pipeline, not a pipeline
     * stage itself.
     */
    public function archive(Request $request, $id)
    {
        $posting = JobPosting::findOrFail($id);

        if ($posting->status !== \'closed\') {
            if ($request->expectsJson()) {
                return response()->json([\'ok\' => false, \'message\' => \'Only closed postings can be archived.\'], 422);
            }

            return back()->with(\'error\', \'Only closed postings can be archived.\');
        }

        $posting->update([\'status\' => \'archived\']);

        if ($request->expectsJson()) {
            return response()->json([\'status\' => $posting->fresh()->status, \'ok\' => true]);
        }

        return redirect()->route(\'job-postings.index\')
            ->with(\'success\', \'Posting archived.\');
    }

    /**
     * DELETE /job-postings/{posting}/panelists/{panelist}
     * Removes a panelist from this posting\'s panel (pivot only, not global pool).
     */
    public function detachPanelist($postingId, $panelistId)',
    'JobPostingController: archive() method'
);

// ─── 3. Status badge colors/labels include archived ─────────────────────

echo "\n[3] Updating status badge map in show.blade.php...\n";

apply_patch(
    $showPath,
    "    \$statusColors = [
        'open'                => 'success',
        'interview_scheduled' => 'primary',
        'ranking'             => 'warning',
        'closed'              => 'dark',
    ];
    \$statusLabels = [
        'open'                => 'Open',
        'interview_scheduled' => 'Interview',
        'ranking'             => 'Ranking',
        'closed'              => 'Closed',
    ];",
    "    \$statusColors = [
        'open'                => 'success',
        'interview_scheduled' => 'primary',
        'ranking'             => 'warning',
        'closed'              => 'dark',
        'archived'            => 'secondary',
    ];
    \$statusLabels = [
        'open'                => 'Open',
        'interview_scheduled' => 'Interview',
        'ranking'             => 'Ranking',
        'closed'              => 'Closed',
        'archived'            => 'Archived',
    ];",
    'show.blade.php: statusColors/statusLabels include archived'
);

// ─── 4. Archive button in sidebar (shown only when closed) ──────────────

echo "\n[4] Adding Archive button to sidebar...\n";

apply_patch(
    $showPath,
    '                <div class="mt-3 pt-3 border-top">
                    <a href="{{ route(\'job-postings.edit\', $posting->id) }}"
                       class="btn btn-sm btn-outline-secondary w-100">
                        <i class="bi bi-pencil me-1"></i> Edit posting
                    </a>
                </div>',
    '                @if ($posting->status === \'closed\')
                <div class="mt-3">
                    <form action="{{ route(\'job-postings.archive\', $posting->id) }}" method="POST"
                          onsubmit="return confirm(\'Archive this posting? It will move out of the active job postings list.\');">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-dark w-100">
                            <i class="bi bi-archive me-1"></i> Archive posting
                        </button>
                    </form>
                </div>
                @endif

                <div class="mt-3 pt-3 border-top">
                    <a href="{{ route(\'job-postings.edit\', $posting->id) }}"
                       class="btn btn-sm btn-outline-secondary w-100">
                        <i class="bi bi-pencil me-1"></i> Edit posting
                    </a>
                </div>',
    'show.blade.php: Archive posting button (closed only)'
);

echo "\n✅ Done.\n\n";
echo "REMINDER: run the migration BEFORE this script, or the 'archived'\n";
echo "status update will fail against the DB enum:\n";
echo "  1. copy 2026_07_14_010000_add_archived_status_to_job_postings.php\n";
echo "     into database/migrations/\n";
echo "  2. php artisan migrate\n";
echo "  3. php fix_add_archive_feature.php\n\n";
echo "WHAT CHANGED:\n";
echo "  - New route: POST /job-postings/{id}/archive\n";
echo "  - JobPostingController::archive() -- only allowed from 'closed',\n";
echo "    otherwise redirects back with an error.\n";
echo "  - 'Archive posting' button appears in the sidebar only when the\n";
echo "    posting's status is 'closed'.\n\n";
echo "NOT changed (flag if you want it): job-postings.index currently\n";
echo "still lists archived postings alongside active ones -- I didn't\n";
echo "filter them out since you didn't ask for that. Want archived\n";
echo "postings hidden from the default list / moved to a separate tab?\n";
echo "DELETE this script after running.\n";
