<?php
/**
 * Patch: two changes to the candidate ranking table on the job posting
 * show page (Assessment & ranking panel):
 *
 *   1. CONFIRMED REAL BUG: $rankableApplications was built with
 *      whereNotIn(status, ['not_qualified', 'rejected']) — which let any
 *      applicant who simply hadn't been qualification-checked yet
 *      (submitted, screening, etc.) show up in the ranking table. Changed
 *      to where('qualification_result', 'qualified') so only applicants
 *      who actually PASSED qualification checking make it here.
 *   2. Added client-side pagination (10 rows/page, Prev/Next) to the
 *      ranking table. This table lives inside the single-page posting
 *      wizard rather than its own route, so server-side pagination would
 *      fight the step-panel switching JS — this mirrors it closely enough
 *      (fixed page size, Prev/Next, a "Showing X–Y of Z" count) without a
 *      page reload.
 *
 * Run once from the project root:
 *   php patch_ranking_qualified_and_pagination.php
 * Then delete this file.
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
$showBladeFile  = __DIR__ . '/resources/views/job-postings/show.blade.php';

apply_patch($controllerFile, [
    [
        <<<'OLD'
        // Disqualified (and rejected) applicants must never appear in
        // ranking/assessment -- only candidates who passed Qualification
        // Checking (step 2) belong here. Built from a filtered subset,
        // NOT $applications itself, so $applications stays the full list
        // for the qualification-checking view (step 2) where disqualified
        // applicants should still show up, correctly labeled.
        $rankableApplications = $applications->whereNotIn('status', ['not_qualified', 'rejected'])->values();
OLD,
        <<<'NEW'
        // Disqualified (and rejected) applicants must never appear in
        // ranking/assessment -- only candidates who PASSED Qualification
        // Checking (step 2) belong here. Built from a filtered subset,
        // NOT $applications itself, so $applications stays the full list
        // for the qualification-checking view (step 2) where disqualified
        // applicants should still show up, correctly labeled.
        //
        // CONFIRMED REAL BUG: this previously used whereNotIn(status,
        // [not_qualified, rejected]) — which let anyone who simply hadn't
        // been checked yet (submitted, screening, etc.) show up in the
        // ranking table. Filtering on qualification_result === 'qualified'
        // instead means only applicants who actually passed qualification
        // checking make it here, and a qualified applicant later manually
        // rejected still won't show up either.
        $rankableApplications = $applications
            ->where('qualification_result', 'qualified')
            ->whereNotIn('status', ['rejected'])
            ->values();
NEW,
        'show(): filter ranking table to actually-qualified applicants only',
    ],
]);

apply_patch($showBladeFile, [
    [
        <<<'OLD'
                            @foreach ($rankedCandidates as $i => $cand)
                            <tr>
OLD,
        <<<'NEW'
                            @foreach ($rankedCandidates as $i => $cand)
                            <tr class="rank-row">
NEW,
        'show blade: tag each ranking row for client-side pagination',
    ],
    [
        <<<'OLD'
                        </tbody>
                    </table>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Assessment criteria --}}
OLD,
        <<<'NEW'
                        </tbody>
                    </table>
                    </div>
                    @if ($rankedCandidates->count() > 10)
                    <nav class="d-flex justify-content-between align-items-center mt-3">
                        <span class="text-muted small" id="rankPageInfo"></span>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-secondary" id="rankPagePrev">
                                <i class="bi bi-chevron-left"></i> Prev
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="rankPageNext">
                                Next <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    </nav>
                    @endif
                    @endif
                </div>
            </div>

            {{-- Assessment criteria --}}
NEW,
        'show blade: add pagination controls below the ranking table',
    ],
    [
        <<<'OLD'
})();
</script>
@endpush
OLD,
        <<<'NEW'
})();

// ── Candidate ranking table pagination (client-side, 10 rows/page) ──────
// This table lives inside the single-page posting wizard rather than its
// own route, so server-side pagination isn't practical here — this mirrors
// it closely enough (fixed page size, Prev/Next, page count) without a
// page reload breaking the step-panel navigation.
(function () {
    var rows = Array.prototype.slice.call(document.querySelectorAll('.rank-row'));
    var prevBtn = document.getElementById('rankPagePrev');
    var nextBtn = document.getElementById('rankPageNext');
    var pageInfo = document.getElementById('rankPageInfo');
    if (!rows.length || !prevBtn || !nextBtn) return;

    var pageSize = 10;
    var totalPages = Math.ceil(rows.length / pageSize);
    var currentPage = 1;

    function render() {
        rows.forEach(function (row, i) {
            var page = Math.floor(i / pageSize) + 1;
            row.style.display = (page === currentPage) ? '' : 'none';
        });
        if (pageInfo) {
            var start = (currentPage - 1) * pageSize + 1;
            var end = Math.min(currentPage * pageSize, rows.length);
            pageInfo.textContent = 'Showing ' + start + '–' + end + ' of ' + rows.length;
        }
        prevBtn.disabled = currentPage === 1;
        nextBtn.disabled = currentPage === totalPages;
    }

    prevBtn.addEventListener('click', function () {
        if (currentPage > 1) { currentPage--; render(); }
    });
    nextBtn.addEventListener('click', function () {
        if (currentPage < totalPages) { currentPage++; render(); }
    });

    render();
})();
</script>
@endpush
NEW,
        'show blade: add the pagination JS at the end of the scripts block',
    ],
]);

echo "\nDone. Diff and test the ranking table (with 11+ qualified candidates on one posting, to see pagination kick in) before deleting this script and its .bak backups.\n";
