<?php
/**
 * Patch: paginate the job postings list at 10/page, and keep the stat
 * cards (Open/Interview/Ranking/Closed/Total vacancies) accurate against
 * the FULL filtered set rather than just whichever page is showing.
 *
 * Run once from the project root:
 *   php patch_jobpostings_pagination.php
 * Then delete this file.
 *
 * Note: the "Search by job title" box on this page is client-side and
 * only filters rows already in the DOM — with pagination that now means
 * it only searches the current page's 10 rows. Flagging in case that's
 * not what you want; happy to swap it for a real server-side search if so.
 */

function apply_patch(string $path, array $edits): void
{
    if (!file_exists($path)) {
        fwrite(STDERR, "ABORT: file not found: $path\n");
        exit(1);
    }

    $original = file_get_contents($path);
    $working = $original;

    foreach ($edits as $i => [$search, $replace, $label]) {
        $count = substr_count($working, $search);
        if ($count !== 1) {
            fwrite(STDERR, "ABORT: edit #$i ($label) matched $count times (expected exactly 1) in $path\n");
            fwrite(STDERR, "No changes were written.\n");
            exit(1);
        }
        $working = str_replace($search, $replace, $working);
    }

    $backup = $path . '.bak';
    if (!copy($path, $backup)) {
        fwrite(STDERR, "ABORT: could not create backup at $backup\n");
        exit(1);
    }

    file_put_contents($path, $working);
    echo "Patched: $path\n";
    echo "Backup:  $backup\n";
}

// ── Adjust these paths if your project layout differs ──────────────────
$controllerFile = __DIR__ . '/app/Http/Controllers/JobPostingController.php';
$indexBladeFile = __DIR__ . '/resources/views/job-postings/index.blade.php';

apply_patch($controllerFile, [
    [
        <<<'OLD'
        $postings = JobPosting::with('locations')
            ->when($showArchived, fn ($q) => $q->where('status', 'archived'))
            ->when(!$showArchived, fn ($q) => $q->where('status', '!=', 'archived'))
            ->orderByDesc('id')
            ->get();

        // Applicant counts for all listed postings in a single grouped
        // query, then attach as a dynamic property -- avoids an N+1
        // query per row on the list page.
        $applicantCounts = Application::whereIn('job_posting_id', $postings->pluck('id'))
            ->selectRaw('job_posting_id, count(*) as total')
            ->groupBy('job_posting_id')
            ->pluck('total', 'job_posting_id');

        $postings->each(function ($posting) use ($applicantCounts) {
            $posting->applicant_count = $applicantCounts->get($posting->id, 0);
        });

        return view('job-postings.index', compact('postings', 'showArchived'));
    }
OLD,
        <<<'NEW'
        // Base query (unpaginated) — reused for both the stat cards, which
        // need to reflect the FULL filtered set, and the paginated list
        // itself. Cloned before each terminal call since a query builder
        // gets consumed once executed.
        $baseQuery = JobPosting::query()
            ->when($showArchived, fn ($q) => $q->where('status', 'archived'))
            ->when(!$showArchived, fn ($q) => $q->where('status', '!=', 'archived'))
            ->orderByDesc('id');

        // Stat cards: pull just id/status/vacancies (+ locations for the
        // vacancy sum) across the WHOLE filtered set, independent of which
        // page is showing. Cheap since it skips every other posting column.
        $statsSource   = (clone $baseQuery)->with('locations:id,job_posting_id,vacancies')
            ->get(['id', 'status', 'vacancies']);
        $statusCounts  = $statsSource->countBy('status');
        $totalVacancies = $statsSource->sum(fn ($p) => $p->locations->sum('vacancies') ?: $p->vacancies);

        $postings = (clone $baseQuery)->with('locations')
            ->paginate(10)
            ->withQueryString();

        // Applicant counts for all listed postings in a single grouped
        // query, then attach as a dynamic property -- avoids an N+1
        // query per row on the list page.
        $applicantCounts = Application::whereIn('job_posting_id', $postings->pluck('id'))
            ->selectRaw('job_posting_id, count(*) as total')
            ->groupBy('job_posting_id')
            ->pluck('total', 'job_posting_id');

        $postings->each(function ($posting) use ($applicantCounts) {
            $posting->applicant_count = $applicantCounts->get($posting->id, 0);
        });

        return view('job-postings.index', compact('postings', 'showArchived', 'statusCounts', 'totalVacancies'));
    }
NEW,
        'index(): paginate(10) + compute stats from full filtered set',
    ],
]);

apply_patch($indexBladeFile, [
    [
        <<<'OLD'
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
            <div class="fs-4 fw-semibold">{{ $postings->where('status', 'open')->count() }}</div>
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
            <div class="fs-4 fw-semibold">{{ $postings->sum(fn($p) => $p->locations->sum('vacancies') ?: $p->vacancies) }}</div>
        </div>
    </div>
    @endif
</div>
OLD,
        <<<'NEW'
    @if ($showArchived ?? false)
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Archived</div>
            <div class="fs-4 fw-semibold">{{ $postings->total() }}</div>
        </div>
    </div>
    @else
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Open</div>
            <div class="fs-4 fw-semibold">{{ $statusCounts->get('open', 0) }}</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Interview</div>
            <div class="fs-4 fw-semibold">{{ $statusCounts->get('interview_scheduled', 0) }}</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Ranking</div>
            <div class="fs-4 fw-semibold">{{ $statusCounts->get('ranking', 0) }}</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Closed</div>
            <div class="fs-4 fw-semibold">{{ $statusCounts->get('closed', 0) }}</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-3">
            <div class="text-muted small">Total vacancies</div>
            <div class="fs-4 fw-semibold">{{ $totalVacancies }}</div>
        </div>
    </div>
    @endif
</div>
NEW,
        'index blade: use precomputed stats instead of full-collection queries',
    ],
    [
        <<<'OLD'
        </table>
    </div>
</div>
@push('scripts')
OLD,
        <<<'NEW'
        </table>
    </div>
</div>
<div class="mt-3">
    {{ $postings->onEachSide(1)->links() }}
</div>
@push('scripts')
NEW,
        'index blade: render pagination links below the table',
    ],
]);

echo "\nDone. Diff and reload the Job postings page before deleting this script and its .bak backups.\n";
