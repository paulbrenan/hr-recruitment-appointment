<?php
/**
 * fix_add_archive_button_only.php
 *
 * Follow-up to fix_add_archive_feature.php. That script's route,
 * controller method, and status-badge patches already applied
 * successfully -- only the "Archive posting" button insertion failed,
 * because the sidebar markup had since grown edit-lock logic
 * ($currentStep < 3) that didn't exist when that patch was written.
 *
 * This patches ONLY the button, against the current real markup.
 * Safe to run even though the other 3 patches from
 * fix_add_archive_feature.php already succeeded -- this doesn't touch
 * routes/web.php or JobPostingController.php at all.
 *
 * HOW TO RUN:
 *   php fix_add_archive_button_only.php   (from project root)
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

echo "\n=== fix_add_archive_button_only.php ===\n\n";

$showPath = ROOT . '/resources/views/job-postings/show.blade.php';

echo "[1] Adding Archive button before Edit posting (closed postings only)...\n";

apply_patch(
    $showPath,
    '                <div class="mt-3 pt-3 border-top">
                    @if ($currentStep < 3)
                    <a href="{{ route(\'job-postings.edit\', $posting->id) }}"
                       class="btn btn-sm btn-outline-secondary w-100">
                        <i class="bi bi-pencil me-1"></i> Edit posting
                    </a>
                    @else
                    <button type="button" class="btn btn-sm btn-outline-secondary w-100" disabled
                            title="This posting can no longer be edited once scheduling has started.">
                        <i class="bi bi-lock me-1"></i> Edit posting
                    </button>
                    @endif
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
                    @if ($currentStep < 3)
                    <a href="{{ route(\'job-postings.edit\', $posting->id) }}"
                       class="btn btn-sm btn-outline-secondary w-100">
                        <i class="bi bi-pencil me-1"></i> Edit posting
                    </a>
                    @else
                    <button type="button" class="btn btn-sm btn-outline-secondary w-100" disabled
                            title="This posting can no longer be edited once scheduling has started.">
                        <i class="bi bi-lock me-1"></i> Edit posting
                    </button>
                    @endif
                </div>',
    'show.blade.php: Archive posting button (closed only)'
);

echo "\n✅ Done.\n\n";
echo "Archive button now appears in the sidebar, above Edit posting,\n";
echo "only when the posting's status is 'closed'.\n\n";
echo "DELETE this script after running.\n";
