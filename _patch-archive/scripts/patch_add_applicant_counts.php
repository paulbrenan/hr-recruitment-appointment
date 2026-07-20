<?php
/**
 * patch_add_applicant_counts.php
 *
 * Adds a "Total applicants" figure in two places:
 *   1. job-postings/index.blade.php -- each row in the list.
 *   2. job-postings/show.blade.php  -- the pipeline's Overview tab,
 *      next to "Total vacancies".
 *
 * Controller changes:
 *   - index(): computes applicant counts for all listed postings in a
 *     single grouped query (no N+1), then assigns each posting an
 *     ->applicant_count dynamic property.
 *   - show(): no change needed -- $applications is already fully loaded
 *     for the posting, so the Overview tab just uses $applications->count().
 *
 * Run once from the project root:
 *   php patch_add_applicant_counts.php
 * Then delete this file — it is a one-shot installer, not idempotent.
 */

function apply_patch($path, $old, $new, $label) {
    if (!file_exists($path)) {
        fwrite(STDERR, "[ABORT] File not found: $path ($label)\n");
        exit(1);
    }
    $contents = file_get_contents($path);
    if (strpos($contents, $old) === false) {
        fwrite(STDERR, "[ABORT] Expected content not found for: $label\n");
        fwrite(STDERR, "        File may already be patched or is a different version. No changes made.\n");
        exit(1);
    }
    copy($path, $path . '.bak');
    $updated = str_replace($old, $new, $contents, $count);
    if ($count !== 1) {
        fwrite(STDERR, "[ABORT] Expected exactly 1 match for '$label', found $count. Restoring backup.\n");
        copy($path . '.bak', $path);
        exit(1);
    }
    file_put_contents($path, $updated);
    echo "[OK] $label\n";
}

$indexView      = __DIR__ . '/resources/views/job-postings/index.blade.php';
$showView       = __DIR__ . '/resources/views/job-postings/show.blade.php';

// NOTE: step 1 (JobPostingController.php index()) already ran successfully
// on your first attempt before the script hit the bug below -- it is not
// repeated here. Only the two view patches remain.

// ── 2. index.blade.php: show the count on each row, under the title ─────

$old2 = <<<'OLD'
                    <td class="fw-medium" style="word-break: break-word;">{{ $posting->title }}</td>
OLD;

$new2 = <<<'NEW'
                    <td class="fw-medium" style="word-break: break-word;">
                        {{ $posting->title }}
                        <div class="text-muted fw-normal" style="font-size: 0.75rem;">
                            <i class="bi bi-person-lines-fill"></i> {{ $posting->applicant_count }} {{ Str::plural('applicant', $posting->applicant_count) }}
                        </div>
                    </td>
NEW;

apply_patch($indexView, $old2, $new2, 'index.blade.php: show applicant count under each posting title');

// ── 3. show.blade.php: add "Total applicants" next to "Total vacancies"
//       in the Overview tab (resize the 3 existing columns to make room) ──

$old3 = <<<'OLD'
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <div class="text-muted small">Posted</div>
                            <div class="fw-medium">{{ $posting->posted_at ? \Carbon\Carbon::parse($posting->posted_at)->format('M d, Y') : '—' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Closes</div>
                            <div class="fw-medium">{{ $posting->closes_at ? \Carbon\Carbon::parse($posting->closes_at)->format('M d, Y') : '—' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Total vacancies</div>
                            <div class="fw-medium">{{ $locations->sum('vacancies') ?: ($posting->vacancies ?? '—') }}</div>
                        </div>
                    </div>
OLD;

$new3 = <<<'NEW'
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <div class="text-muted small">Posted</div>
                            <div class="fw-medium">{{ $posting->posted_at ? \Carbon\Carbon::parse($posting->posted_at)->format('M d, Y') : '—' }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted small">Closes</div>
                            <div class="fw-medium">{{ $posting->closes_at ? \Carbon\Carbon::parse($posting->closes_at)->format('M d, Y') : '—' }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted small">Total vacancies</div>
                            <div class="fw-medium">{{ $locations->sum('vacancies') ?: ($posting->vacancies ?? '—') }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted small">Total applicants</div>
                            <div class="fw-medium">{{ $applications->count() }}</div>
                        </div>
                    </div>
NEW;

apply_patch($showView, $old3, $new3, 'show.blade.php: add Total applicants column to the Overview tab');

echo "\nDone. Applicant counts now show on the job postings list and in each posting's\n";
echo "pipeline Overview tab.\n";
