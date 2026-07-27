<?php
/**
 * patch_fix_dashboard_interviews_links.php
 *
 * Fixes: RouteNotFoundException "Route [interviews.index] not defined"
 * on the dashboard, after the old standalone Scheduling page was removed
 * by patch_remove_old_scheduling_page.php.
 *
 * Three links pointed at the deleted route:
 *   1. The "Interviews this week" stat card -- no specific posting in
 *      context, so sent to job-postings.index instead.
 *   2. Each row under "Upcoming interviews & exams" -- DOES have a
 *      specific posting available ($s->application->jobPosting), so it
 *      now links straight to that posting's pipeline page instead of a
 *      generic list.
 *   3. The "View full schedule" link at the bottom of that same card --
 *      no specific posting in context, sent to job-postings.index.
 *
 * Run once from the project root:
 *   php patch_fix_dashboard_interviews_links.php
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

$file = __DIR__ . '/resources/views/dashboard/index.blade.php';

// 1. Stat card
apply_patch(
    $file,
    <<<'OLD'
        <a href="{{ route('interviews.index') }}" class="stat-card-link">
            <div class="card stat-card p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small">Interviews this week</div>
OLD,
    <<<'NEW'
        <a href="{{ route('job-postings.index') }}" class="stat-card-link">
            <div class="card stat-card p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small">Interviews this week</div>
NEW,
    'Stat card: "Interviews this week" now links to job-postings.index'
);

// 2. Per-row upcoming interview link -- now goes to that posting's pipeline
apply_patch(
    $file,
    <<<'OLD'
                @forelse ($upcomingSchedules as $s)
                <a href="{{ route('interviews.index') }}" class="row-link">
OLD,
    <<<'NEW'
                @forelse ($upcomingSchedules as $s)
                <a href="{{ route('job-postings.show', $s->application->job_posting_id) }}" class="row-link">
NEW,
    'Upcoming interviews row: link to the specific posting\'s pipeline instead of the old index'
);

// 3. "View full schedule" link
apply_patch(
    $file,
    <<<'OLD'
                <a href="{{ route('interviews.index') }}" class="small d-block mt-2 view-all-link">View full schedule <i class="bi bi-arrow-right"></i></a>
OLD,
    <<<'NEW'
                <a href="{{ route('job-postings.index') }}" class="small d-block mt-2 view-all-link">View full schedule <i class="bi bi-arrow-right"></i></a>
NEW,
    '"View full schedule" link now points to job-postings.index'
);

echo "\nDone. The dashboard no longer references the deleted interviews.index route.\n";
