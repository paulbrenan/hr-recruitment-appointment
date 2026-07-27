<?php
/**
 * patch_criteria_file_scan_blade.php
 *
 * Run from the project root:
 *   php patch_criteria_file_scan_blade.php
 *
 * What it does:
 *  resources/views/job-postings/show.blade.php
 *    - adds a "Scan file for criteria" button next to "Delete all" in the
 *      Assessment criteria card
 *    - adds the upload modal (#importCriteriaModal) that posts to
 *      assessments.criteria.import-scan
 *
 * Depends on patch_delete_all_criteria.php having already been run (this
 * anchors on the "Delete all" block it added). Safe to run multiple times:
 * aborts with no changes if the expected anchors aren't found exactly.
 * A .bak copy is made before any write.
 */

$root = __DIR__;
$path = $root . '/resources/views/job-postings/show.blade.php';

if (!file_exists($path)) {
    echo "[SKIP] show.blade.php — file not found: $path\n";
    exit;
}

$content = file_get_contents($path);
$original = $content;

// ── 1. Button next to "Delete all" ───────────────────────────────────────
$buttonOld = <<<'OLD'
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
OLD;

$buttonNew = <<<'NEW'
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

                    @if ($posting->status !== 'closed')
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-2" data-bs-toggle="modal" data-bs-target="#importCriteriaModal">
                        <i class="bi bi-upload me-1"></i> Scan file for criteria
                    </button>
                    @endif
NEW;

if (strpos($content, $buttonOld) === false) {
    echo "[ABORT] show.blade.php — Delete-all button block not found (run patch_delete_all_criteria.php first, or file has changed). No changes written.\n";
    exit;
}
$content = str_replace($buttonOld, $buttonNew, $content);


// ── 2. Upload modal ──────────────────────────────────────────────────────
$modalAnchor = <<<'ANCHOR'
{{-- ── Modals ────────────────────────────────────────────────────────────── --}}

{{-- New Schedule (per-job: schedules ALL qualified applicants at once) --}}
ANCHOR;

$modalNew = <<<'NEW'
{{-- ── Modals ────────────────────────────────────────────────────────────── --}}

{{-- Scan file for assessment criteria --}}
<div class="modal fade" id="importCriteriaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('assessments.criteria.import-scan') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="job_posting_id" value="{{ $posting->id }}">
                <div class="modal-header">
                    <h6 class="modal-title">Scan file for assessment criteria</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        Upload a PDF, Word document, Excel file, or photo of the criteria table
                        (e.g. a CSC merit selection form). The system scans it for recognized
                        criteria names — Education, Training, Experience, Performance,
                        Outstanding Accomplishments, Application of Education, Application of
                        Learning and Development, Potential — and adds whichever ones it finds,
                        using their standard weight.
                    </p>
                    <input type="file" name="criteria_file" class="form-control form-control-sm"
                           accept=".pdf,.docx,.xlsx,.xls,.jpg,.jpeg,.png" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background-color:var(--hr-primary);color:#fff;">
                        <i class="bi bi-upload me-1"></i> Scan &amp; add criteria
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- New Schedule (per-job: schedules ALL qualified applicants at once) --}}
NEW;

if (strpos($content, $modalAnchor) === false) {
    echo "[ABORT] show.blade.php — modal anchor not found (file may have changed). Button was added but modal was not — check the file.\n";
    file_put_contents($path . '.bak', $original);
    file_put_contents($path, $content);
    echo "Partial patch written with backup at {$path}.bak — please re-upload the current file so I can add the modal.\n";
    exit;
}
$content = str_replace($modalAnchor, $modalNew, $content);

if ($content === $original) {
    echo "[SKIP] show.blade.php — no changes needed.\n";
    exit;
}

$backup = $path . '.bak';
if (!file_exists($backup)) {
    copy($path, $backup);
} else {
    copy($path, $path . '.bak.' . date('Ymd_His'));
}

file_put_contents($path, $content);
echo "[OK] show.blade.php — patched. Backup at: $backup\n";
