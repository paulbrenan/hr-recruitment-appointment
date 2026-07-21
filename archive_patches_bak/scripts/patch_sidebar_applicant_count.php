<?php
/**
 * patch_sidebar_applicant_count.php
 *
 * Adds the total applicant count to the pipeline sidebar header (next to
 * the title/status badge), so it's visible no matter which step tab is
 * currently active -- not just on the Overview tab.
 *
 * Reuses the already-loaded $applications collection (no extra query).
 *
 * Run once from the project root:
 *   php patch_sidebar_applicant_count.php
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

$file = __DIR__ . '/resources/views/job-postings/show.blade.php';

$old = <<<'OLD'
                <div class="fw-semibold mb-1" style="font-size:0.95rem;">{{ $posting->title }}</div>
                @if ($sg)
                <div class="text-muted small mb-1">{{ $sg }} &middot; {{ $posting->employment_type }}</div>
                @endif
                <span class="badge text-bg-{{ $statusColors[$posting->status] ?? 'secondary' }} mb-3">
                    {{ $statusLabels[$posting->status] ?? ucfirst($posting->status) }}
                </span>
OLD;

$new = <<<'NEW'
                <div class="fw-semibold mb-1" style="font-size:0.95rem;">{{ $posting->title }}</div>
                @if ($sg)
                <div class="text-muted small mb-1">{{ $sg }} &middot; {{ $posting->employment_type }}</div>
                @endif
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="badge text-bg-{{ $statusColors[$posting->status] ?? 'secondary' }}">
                        {{ $statusLabels[$posting->status] ?? ucfirst($posting->status) }}
                    </span>
                    <span class="text-muted small">
                        <i class="bi bi-person-lines-fill"></i> {{ $applications->count() }} {{ Str::plural('applicant', $applications->count()) }}
                    </span>
                </div>
NEW;

apply_patch($file, $old, $new, 'Sidebar header: show persistent applicant count next to the status badge');

echo "\nDone. The applicant count now shows in the sidebar header on every step, not just Overview.\n";
