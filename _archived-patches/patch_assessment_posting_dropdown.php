<?php

/**
 * patch_assessment_posting_dropdown.php
 *
 * WHAT THIS DOES:
 *   Fixes the duplicate job title problem in Assessment & Ranking.
 *
 *   Root cause: each job_posting row is one title + one place_of_assignment
 *   (stored in job_posting_locations). The old single dropdown listed every
 *   posting row, so "Project Development Officer I" appeared N times.
 *
 *   Fix:
 *   1. AssessmentController::index()
 *      - Groups postings by title for the first dropdown (unique titles only)
 *      - Accepts ?title= + ?job_posting= query params
 *      - When only ?title= is given, auto-selects the first matching posting
 *      - Passes $locationPostings (all postings matching the selected title)
 *        to the view for the second dropdown
 *
 *   2. assessments/index.blade.php
 *      - First dropdown: unique titles — submits ?title=...
 *      - Second dropdown: place-of-assignment options for that title,
 *        each option value = job_posting_id — submits ?job_posting=...
 *      - JS wires the two dropdowns: title change resubmits the form;
 *        location change resubmits with both title + job_posting
 *
 * HOW TO RUN:
 *   php patch_assessment_posting_dropdown.php    (from project root)
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

echo "\n=== patch_assessment_posting_dropdown.php ===\n\n";

// ─── 1. AssessmentController::index() ─────────────────────────────────────

echo "[1] Patching AssessmentController::index()...\n";

$controllerPath = ROOT . '/app/Http/Controllers/AssessmentController.php';

apply_patch(
    $controllerPath,
    '    public function index(Request $request)
    {
        $postings = JobPosting::orderBy(\'title\')->get();

        $selectedPostingId = $request->query(\'job_posting\');

        if (!$selectedPostingId && $postings->isNotEmpty()) {
            $selectedPostingId = $postings->first()->id;
        }',
    '    public function index(Request $request)
    {
        // All postings with their locations eager-loaded
        $allPostings = JobPosting::with(\'locations\')->orderBy(\'title\')->get();

        // Unique titles for the first dropdown
        $postings = $allPostings->unique(\'title\')->values();

        // Which title is selected? Default to the first unique title.
        $selectedTitle = $request->query(\'title\');
        if (!$selectedTitle && $postings->isNotEmpty()) {
            $selectedTitle = $postings->first()->title;
        }

        // All postings matching the selected title (one per place of assignment)
        $locationPostings = $allPostings->where(\'title\', $selectedTitle)->values();

        // Which specific posting (place of assignment) is selected?
        $selectedPostingId = $request->query(\'job_posting\');

        // Auto-select the first location posting if none chosen yet
        if (!$selectedPostingId && $locationPostings->isNotEmpty()) {
            $selectedPostingId = $locationPostings->first()->id;
        }',
    'Controller: index() groups by title, adds location sub-dropdown logic'
);

// Also pass $locationPostings and $selectedTitle to the view
apply_patch(
    $controllerPath,
    "        return view('assessments.index', compact('criteria', 'rankedCandidates', 'postings', 'selectedPostingId', 'selectedPosting', 'usedWeight', 'remainingWeight'));",
    "        return view('assessments.index', compact('criteria', 'rankedCandidates', 'postings', 'selectedPostingId', 'selectedPosting', 'usedWeight', 'remainingWeight', 'locationPostings', 'selectedTitle'));",
    'Controller: pass locationPostings + selectedTitle to view'
);

// ─── 2. assessments/index.blade.php ───────────────────────────────────────

echo "\n[2] Patching assessments/index.blade.php...\n";

$bladePath = ROOT . '/resources/views/assessments/index.blade.php';

// Replace the single posting dropdown with title + location dropdowns
apply_patch(
    $bladePath,
    '        <form method="GET" action="{{ route(\'assessments.index\') }}" class="m-0">
            <select name="job_posting" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                @forelse ($postings as $p)
                    <option value="{{ $p->id }}" {{ (string) $selectedPostingId === (string) $p->id ? \'selected\' : \'\' }}>{{ $p->title }}</option>
                @empty
                    <option>No job postings yet</option>
                @endforelse
            </select>
        </form>',
    '        {{-- Two-level dropdown: Title → Place of Assignment --}}
        <form method="GET" action="{{ route(\'assessments.index\') }}" class="m-0 d-flex align-items-center gap-2" id="postingFilterForm">
            {{-- Level 1: unique job titles --}}
            <select name="title" id="titleSelect" class="form-select form-select-sm" style="min-width: 220px; max-width: 280px;">
                @forelse ($postings as $p)
                    <option value="{{ $p->title }}" {{ $selectedTitle === $p->title ? \'selected\' : \'\' }}>
                        {{ $p->title }}
                    </option>
                @empty
                    <option>No job postings yet</option>
                @endforelse
            </select>

            {{-- Level 2: place of assignment for the selected title --}}
            @if ($locationPostings->count() > 1)
            <select name="job_posting" id="locationSelect" class="form-select form-select-sm" style="min-width: 200px; max-width: 260px;">
                @foreach ($locationPostings as $lp)
                    @php
                        // Show the first location name, or fall back to legacy place_of_assignment
                        $loc = $lp->locations->first();
                        $label = $loc ? $loc->place_of_assignment : ($lp->place_of_assignment ?? \'—\');
                    @endphp
                    <option value="{{ $lp->id }}" {{ (string) $selectedPostingId === (string) $lp->id ? \'selected\' : \'\' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            @else
            {{-- Only one location — hidden field, no need to show dropdown --}}
            <input type="hidden" name="job_posting" value="{{ $selectedPostingId }}">
            @endif
        </form>',
    'blade: replace single posting dropdown with title + location dropdowns'
);

// Also fix the send-all form hidden field to pass title too
apply_patch(
    $bladePath,
    '        @if ($rankedCandidates->isNotEmpty())
        <form method="POST" action="{{ route(\'assessments.send-all\') }}" class="m-0">
            @csrf
            <input type="hidden" name="job_posting_id" value="{{ $selectedPostingId }}">',
    '        @if ($rankedCandidates->isNotEmpty())
        <form method="POST" action="{{ route(\'assessments.send-all\') }}" class="m-0">
            @csrf
            <input type="hidden" name="job_posting_id" value="{{ $selectedPostingId }}">
            <input type="hidden" name="selected_title" value="{{ $selectedTitle }}">',
    'blade: send-all form carries selected title'
);

// Add JS to wire the two dropdowns — append before @endpush
apply_patch(
    $bladePath,
    '@endsection

@push(\'scripts\')',
    '@endsection

@push(\'scripts\')',
    '' // no-op placeholder
);

// Wire the dropdowns in the scripts section
apply_patch(
    $bladePath,
    '    @if ($errors->has(\'weight_percentage\'))
    document.addEventListener(\'DOMContentLoaded\', function () {
        new bootstrap.Modal(document.getElementById(\'addCriterionModal\')).show();
    });
    @endif',
    '    // ── Posting filter dropdowns ──────────────────────────────────────────────
    (function () {
        const form        = document.getElementById(\'postingFilterForm\');
        const titleSelect = document.getElementById(\'titleSelect\');
        const locSelect   = document.getElementById(\'locationSelect\');

        if (!form || !titleSelect) return;

        // Title changes → clear job_posting and resubmit (controller picks first location)
        titleSelect.addEventListener(\'change\', function () {
            if (locSelect) locSelect.value = \'\';
            // Remove job_posting from form so controller auto-selects first location
            let hidden = form.querySelector(\'input[name="job_posting"]\');
            if (hidden) hidden.remove();
            form.submit();
        });

        // Location changes → just submit (both title + job_posting are in the form)
        if (locSelect) {
            locSelect.addEventListener(\'change\', function () {
                form.submit();
            });
        }
    })();

    @if ($errors->has(\'weight_percentage\'))
    document.addEventListener(\'DOMContentLoaded\', function () {
        new bootstrap.Modal(document.getElementById(\'addCriterionModal\')).show();
    });
    @endif',
    'blade: JS to wire title → location cascade and auto-submit'
);

echo <<<TEXT

✅ Done. No migration needed — hard refresh the page (Ctrl+Shift+R).

HOW IT WORKS:
  - First dropdown shows unique job titles only (no duplicates).
  - If a title has multiple places of assignment, a second dropdown
    appears listing each location. Picking one reloads the ranking
    table for that specific posting.
  - If a title has only one location, no second dropdown is shown —
    the posting ID is passed as a hidden field automatically.
  - Changing the title resets to the first location for that title.

DELETE this script after running.

TEXT;
