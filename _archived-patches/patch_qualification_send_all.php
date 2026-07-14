<?php

/**
 * patch_qualification_send_all.php
 *
 * REQUIRES patch_qualification_groups.php to have already been run
 * (this patches the grouped panel-2 markup it created).
 *
 * WHAT THIS DOES:
 *   1. Reorders the qualification groups so Qualified shows before
 *      Disqualified (then Pending last, unchanged).
 *   2. Adds a "Send all qualified mail" / "Send all disqualified mail"
 *      button to each of those two group headers, which emails every
 *      applicant currently in that group their qualification result in
 *      one click (with a confirm() prompt first).
 *   3. Adds ApplicationController::sendAllQualificationNotices() to
 *      actually do the bulk send.
 *
 * AFTER RUNNING THIS SCRIPT you must also add ONE route by hand --
 * see the printed instructions at the end (this script doesn't touch
 * routes/web.php since it wasn't provided, to avoid guessing at its
 * surrounding structure and breaking something).
 *
 * HOW TO RUN:
 *   php patch_qualification_send_all.php    (from project root)
 *   No migration needed.
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
    if ($count === 0) {
        echo "\n❌ PATCH ABORTED — content not found in $path\nLabel: $label\nSearched for:\n---\n$old\n---\n";
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

echo "\n=== patch_qualification_send_all.php ===\n\n";

$bladePath      = ROOT . '/resources/views/job-postings/show.blade.php';
$controllerPath = ROOT . '/app/Http/Controllers/ApplicationController.php';

// ── 1. Reorder groups: Qualified, Disqualified, Pending ────────────────────
apply_patch(
    $bladePath,
    <<<'EOT'
                    @php
                        // Group by the PERSISTED qualification_result, not by
                        // status -- status keeps advancing (interview_scheduled,
                        // ranked, hired, etc.) once an applicant qualifies, but
                        // qualification_result stays 'qualified' until HR re-runs
                        // the check, so this is what keeps the grouping stable.
                        // Applicants who haven't been checked yet have a null
                        // qualification_result and land in "Pending".
                        $qualGroups = [
                            'not_qualified' => $applications->where('qualification_result', 'not_qualified')->values(),
                            'qualified'     => $applications->where('qualification_result', 'qualified')->values(),
                            'pending'       => $applications->whereNull('qualification_result')->values(),
                        ];
                        $qualGroupMeta = [
                            'not_qualified' => ['label' => 'Disqualified', 'color' => 'danger'],
                            'qualified'     => ['label' => 'Qualified', 'color' => 'success'],
                            'pending'       => ['label' => 'Pending qualification check', 'color' => 'secondary'],
                        ];
                    @endphp
EOT,
    <<<'EOT'
                    @php
                        // Group by the PERSISTED qualification_result, not by
                        // status -- status keeps advancing (interview_scheduled,
                        // ranked, hired, etc.) once an applicant qualifies, but
                        // qualification_result stays 'qualified' until HR re-runs
                        // the check, so this is what keeps the grouping stable.
                        // Applicants who haven't been checked yet have a null
                        // qualification_result and land in "Pending".
                        $qualGroups = [
                            'qualified'     => $applications->where('qualification_result', 'qualified')->values(),
                            'not_qualified' => $applications->where('qualification_result', 'not_qualified')->values(),
                            'pending'       => $applications->whereNull('qualification_result')->values(),
                        ];
                        $qualGroupMeta = [
                            'qualified'     => ['label' => 'Qualified', 'color' => 'success'],
                            'not_qualified' => ['label' => 'Disqualified', 'color' => 'danger'],
                            'pending'       => ['label' => 'Pending qualification check', 'color' => 'secondary'],
                        ];
                    @endphp
EOT,
    'show.blade.php: reorder groups to Qualified, Disqualified, Pending'
);

// ── 2. Add "Send all" button to each group header ──────────────────────────
apply_patch(
    $bladePath,
    <<<'EOT'
                    @foreach ($qualGroups as $groupKey => $groupApps)
                    <div class="mb-4">
                        <h6 class="text-uppercase small fw-bold text-{{ $qualGroupMeta[$groupKey]['color'] }} mb-2" style="letter-spacing:.03em;">
                            {{ $qualGroupMeta[$groupKey]['label'] }}
                            <span class="badge text-bg-light text-dark border ms-1">{{ $groupApps->count() }}</span>
                        </h6>
                        @forelse ($groupApps as $app)
EOT,
    <<<'EOT'
                    @foreach ($qualGroups as $groupKey => $groupApps)
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                            <h6 class="text-uppercase small fw-bold text-{{ $qualGroupMeta[$groupKey]['color'] }} mb-0" style="letter-spacing:.03em;">
                                {{ $qualGroupMeta[$groupKey]['label'] }}
                                <span class="badge text-bg-light text-dark border ms-1">{{ $groupApps->count() }}</span>
                            </h6>
                            @if ($groupKey !== 'pending' && $groupApps->isNotEmpty())
                            <form action="{{ route('applications.qualification-notices.send-all', $posting->id) }}" method="POST" class="m-0"
                                  onsubmit="return confirm('Email the {{ $qualGroupMeta[$groupKey]['label'] }} result to all {{ $groupApps->count() }} applicant(s) in this group?');">
                                @csrf
                                <input type="hidden" name="result" value="{{ $groupKey }}">
                                <button type="submit" class="btn btn-sm btn-outline-{{ $qualGroupMeta[$groupKey]['color'] }}">
                                    <i class="bi bi-envelope-check me-1"></i> Send all {{ strtolower($qualGroupMeta[$groupKey]['label']) }} mail
                                </button>
                            </form>
                            @endif
                        </div>
                        @forelse ($groupApps as $app)
EOT,
    'show.blade.php: add "Send all" button to Qualified/Disqualified group headers'
);

// ── 3. Controller: bulk-send action ─────────────────────────────────────────
apply_patch(
    $controllerPath,
    <<<'EOT'
        $application->candidate->notify(new QualificationResultNotification($application));

        $application->update(['qualification_notified_at' => now()]);

        return redirect()
            ->route('applications.show', $application->id)
            ->with('success', 'Qualification result notice emailed to the candidate, with the official notice PDF attached.');
    }
}
EOT,
    <<<'EOT'
        $application->candidate->notify(new QualificationResultNotification($application));

        $application->update(['qualification_notified_at' => now()]);

        return redirect()
            ->route('applications.show', $application->id)
            ->with('success', 'Qualification result notice emailed to the candidate, with the official notice PDF attached.');
    }

    /**
     * Bulk version of sendQualificationNotice(): emails every applicant in
     * one job posting who currently has the given qualification_result
     * ('qualified' or 'not_qualified') their result notice + PDF, in one
     * click from the group header button. Applicants without a saved
     * qualification_check are skipped (nothing to notify them of yet).
     * Re-sending is allowed -- this always sends, even to applicants who
     * were already notified once, same as the per-applicant "Resend result"
     * button.
     */
    public function sendAllQualificationNotices(Request $request, $jobPostingId)
    {
        $validated = $request->validate([
            'result' => ['required', 'in:qualified,not_qualified'],
        ]);

        $applications = Application::with(['candidate', 'jobPosting'])
            ->where('job_posting_id', $jobPostingId)
            ->where('qualification_result', $validated['result'])
            ->whereNotNull('qualification_check')
            ->get();

        $sent = 0;
        foreach ($applications as $application) {
            try {
                $application->candidate->notify(new QualificationResultNotification($application));
                $application->update(['qualification_notified_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Bulk qualification notice failed for application ' . $application->id . ': ' . $e->getMessage());
            }
        }

        $label = $validated['result'] === 'qualified' ? 'qualified' : 'disqualified';

        return redirect()
            ->route('job-postings.show', ['id' => $jobPostingId, 'step' => 2])
            ->with('success', "Emailed qualification result to {$sent} {$label} applicant(s).");
    }
}
EOT,
    'ApplicationController.php: add sendAllQualificationNotices()'
);

echo <<<TEXT

✅ Patches applied. Hard-refresh the page (Ctrl+Shift+R).

⚠️  ONE MANUAL STEP LEFT — add this route to routes/web.php, right next to
    your existing line for 'applications.qualification-notice'
    (search for that route name to find the spot):

    Route::post('/job-postings/{id}/qualification-notices/send-all', [App\Http\Controllers\ApplicationController::class, 'sendAllQualificationNotices'])
        ->name('applications.qualification-notices.send-all');

    (If your routes file already has "use App\Http\Controllers\ApplicationController;"
    at the top and other routes just use [ApplicationController::class, ...],
    drop the "App\Http\Controllers\" prefix to match that style.)

HOW IT WORKS:
  - Qualified group now renders before Disqualified.
  - Each of those two group headers gets a "Send all ... mail" button that
    posts to the new route with result=qualified or result=not_qualified.
  - The controller re-sends the QualificationResultNotification (email body
    + PDF) to every applicant in that posting currently sitting in that
    result group, and stamps qualification_notified_at on each.
  - Pending applicants have no result yet, so there's nothing to email --
    no button shown for that group.

DELETE this script after running.

TEXT;
