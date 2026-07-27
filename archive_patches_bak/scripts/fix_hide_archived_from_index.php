<?php
/**
 * fix_hide_archived_from_index.php
 *
 * - JobPostingController::index() now excludes 'archived' postings by
 *   default. A ?archived=1 query param flips it to show ONLY archived
 *   postings instead.
 * - index.blade.php gets a "Show archived" / "Back to active" toggle
 *   button above the summary cards + table (next to Import/New posting).
 *   The summary cards row is replaced with a single "Archived postings"
 *   count card while viewing the archived list, since Open/Interview/
 *   Ranking/Closed counts don't apply there.
 *
 * HOW TO RUN:
 *   php fix_hide_archived_from_index.php   (from project root)
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

echo "\n=== fix_hide_archived_from_index.php ===\n\n";

$controllerPath = ROOT . '/app/Http/Controllers/JobPostingController.php';
$indexPath      = ROOT . '/resources/views/job-postings/index.blade.php';

// ─── 1. Controller: filter archived in/out based on ?archived=1 ─────────

echo "[1] Patching JobPostingController::index()...\n";

apply_patch(
    $controllerPath,
    '    public function index()
    {
        $postings = JobPosting::with(\'locations\')->latest()->get();

        return view(\'job-postings.index\', compact(\'postings\'));
    }',
    '    public function index(Request $request)
    {
        // Archived postings are terminal/out-of-pipeline -- keep them out
        // of the default list, toggle-able via ?archived=1.
        $showArchived = $request->boolean(\'archived\');

        $postings = JobPosting::with(\'locations\')
            ->when($showArchived, fn ($q) => $q->where(\'status\', \'archived\'))
            ->when(!$showArchived, fn ($q) => $q->where(\'status\', \'!=\', \'archived\'))
            ->latest()
            ->get();

        return view(\'job-postings.index\', compact(\'postings\', \'showArchived\'));
    }',
    'JobPostingController: index() excludes archived by default, ?archived=1 to view them'
);

// ─── 2. Blade: toggle button ──────────────────────────────────────────────

echo "\n[2] Adding Show archived / Back to active toggle button...\n";

apply_patch(
    $indexPath,
    '    <div class="d-flex gap-2">
        <a href="{{ route(\'job-postings.import.create\') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-file-earmark-pdf me-1"></i> Import from PDF
        </a>
        <a href="{{ route(\'job-postings.create\') }}" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">
            <i class="bi bi-plus-lg me-1"></i> New posting
        </a>
    </div>',
    '    <div class="d-flex gap-2">
        @if ($showArchived ?? false)
            <a href="{{ route(\'job-postings.index\') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to active postings
            </a>
        @else
            <a href="{{ route(\'job-postings.index\', [\'archived\' => 1]) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-archive me-1"></i> Show archived
            </a>
        @endif
        <a href="{{ route(\'job-postings.import.create\') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-file-earmark-pdf me-1"></i> Import from PDF
        </a>
        <a href="{{ route(\'job-postings.create\') }}" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">
            <i class="bi bi-plus-lg me-1"></i> New posting
        </a>
    </div>',
    'index.blade.php: Show archived / Back to active toggle button'
);

// ─── 3. Blade: summary cards swap when viewing archived list ────────────

echo "\n[3] Swapping summary cards for archived view...\n";

apply_patch(
    $indexPath,
    '<div class="row mb-3 g-2">
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Open</div>
            <div class="fs-4 fw-semibold">{{ $postings->where(\'status\', \'open\')->count() }}</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Interview</div>
            <div class="fs-4 fw-semibold">{{ $postings->where(\'status\', \'interview_scheduled\')->count() }}</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Ranking</div>
            <div class="fs-4 fw-semibold">{{ $postings->where(\'status\', \'ranking\')->count() }}</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Closed</div>
            <div class="fs-4 fw-semibold">{{ $postings->where(\'status\', \'closed\')->count() }}</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Total vacancies</div>
            <div class="fs-4 fw-semibold">{{ $postings->sum(fn($p) => $p->locations->sum(\'vacancies\') ?: $p->vacancies) }}</div>
        </div>
    </div>
</div>',
    '<div class="row mb-3 g-2">
    @if ($showArchived ?? false)
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Archived</div>
            <div class="fs-4 fw-semibold">{{ $postings->count() }}</div>
        </div>
    </div>
    @else
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Open</div>
            <div class="fs-4 fw-semibold">{{ $postings->where(\'status\', \'open\')->count() }}</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Interview</div>
            <div class="fs-4 fw-semibold">{{ $postings->where(\'status\', \'interview_scheduled\')->count() }}</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Ranking</div>
            <div class="fs-4 fw-semibold">{{ $postings->where(\'status\', \'ranking\')->count() }}</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Closed</div>
            <div class="fs-4 fw-semibold">{{ $postings->where(\'status\', \'closed\')->count() }}</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Total vacancies</div>
            <div class="fs-4 fw-semibold">{{ $postings->sum(fn($p) => $p->locations->sum(\'vacancies\') ?: $p->vacancies) }}</div>
        </div>
    </div>
    @endif
</div>',
    'index.blade.php: summary cards swap for archived view'
);

// ─── 4. Blade: statusColors/statusLabels include archived explicitly ────

echo "\n[4] Adding explicit archived entry to status badge map...\n";

apply_patch(
    $indexPath,
    "                            \$statusColors = [
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
    "                            \$statusColors = [
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
    'index.blade.php: statusColors/statusLabels include archived'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - /job-postings now excludes archived postings by default.\n";
echo "  - /job-postings?archived=1 shows ONLY archived postings, with a\n";
echo "    'Back to active postings' button and a single Archived count card.\n";
echo "  - Default view has a new 'Show archived' button next to Import/New.\n\n";
echo "DELETE this script after running.\n";
