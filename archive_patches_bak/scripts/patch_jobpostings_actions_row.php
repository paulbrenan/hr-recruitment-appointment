<?php
/**
 * patch_jobpostings_actions_row.php
 *
 * Moves the "Show archived / Import from PDF / New posting" buttons off
 * their own row (next to the description text) and onto the same row as
 * the "Search by job title..." input, right-aligned.
 *
 * Usage: php patch_jobpostings_actions_row.php
 */

function apply_patch(string $file, string $search, string $replace, string $label): void
{
    if (!file_exists($file)) {
        echo "[ABORT] File not found: {$file}\n";
        exit(1);
    }

    $contents = file_get_contents($file);

    if (strpos($contents, $search) === false) {
        echo "[ABORT] {$label}: expected text not found in {$file}. File may have drifted — aborting without changes.\n";
        exit(1);
    }

    if (substr_count($contents, $search) > 1) {
        echo "[ABORT] {$label}: expected text is not unique in {$file}. Aborting to avoid ambiguous edit.\n";
        exit(1);
    }

    $backup = $file . '.bak';
    if (!file_exists($backup)) {
        copy($file, $backup);
    }

    $new_contents = str_replace($search, $replace, $contents);
    file_put_contents($file, $new_contents);

    echo "[OK] {$label} applied to {$file}\n";
}

// Adjust this if the live path differs from the Laravel default.
$view = __DIR__ . '/resources/views/job-postings/index.blade.php';

// ---------------------------------------------------------------------
// 1. Strip the buttons out of the top description row, leaving just the
//    "Manage open positions..." text.
// ---------------------------------------------------------------------
apply_patch(
    $view,
    '<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <p class="text-muted mb-0 small">Manage open positions, qualifications, and assignment details</p>
    </div>
    <div class="d-flex gap-2">
        @if ($showArchived ?? false)
            <a href="{{ route(\'job-postings.index\') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to active postings
            </a>
        @else
            <a href="{{ route(\'job-postings.index\', [\'archived\' => 1]) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-archive me-1"></i> Show archived
            </a>
        @endif
        <a href="{{ route(\'job-postings.import.create\') }}" class="btn btn-sm btn-success">
            <i class="bi bi-file-earmark-pdf me-1"></i> Import from PDF
        </a>
        <a href="{{ route(\'job-postings.create\') }}" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">
            <i class="bi bi-plus-lg me-1"></i> New posting
        </a>
    </div>
</div>',
    '<div class="mb-3">
    <p class="text-muted mb-0 small">Manage open positions, qualifications, and assignment details</p>
</div>',
    'Strip buttons from top description row'
);

// ---------------------------------------------------------------------
// 2. Turn the search bar row into a flex row that also holds the buttons,
//    right-aligned on the same line.
// ---------------------------------------------------------------------
apply_patch(
    $view,
    '<div class="mb-3">
    <div class="input-group input-group-sm" style="max-width: 320px;">
        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
        <input
            type="text"
            id="jobTitleSearch"
            class="form-control"
            placeholder="Search by job title..."
            autocomplete="off"
        >
    </div>
</div>',
    '<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="input-group input-group-sm" style="max-width: 320px;">
        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
        <input
            type="text"
            id="jobTitleSearch"
            class="form-control"
            placeholder="Search by job title..."
            autocomplete="off"
        >
    </div>
    <div class="d-flex gap-2">
        @if ($showArchived ?? false)
            <a href="{{ route(\'job-postings.index\') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to active postings
            </a>
        @else
            <a href="{{ route(\'job-postings.index\', [\'archived\' => 1]) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-archive me-1"></i> Show archived
            </a>
        @endif
        <a href="{{ route(\'job-postings.import.create\') }}" class="btn btn-sm btn-success">
            <i class="bi bi-file-earmark-pdf me-1"></i> Import from PDF
        </a>
        <a href="{{ route(\'job-postings.create\') }}" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">
            <i class="bi bi-plus-lg me-1"></i> New posting
        </a>
    </div>
</div>',
    'Move action buttons onto the search bar row'
);

echo "\nDone. Reload the Job Vacancy index page to verify:\n";
echo " - Description text sits on its own line above the stat cards.\n";
echo " - Search bar and the three action buttons (Show archived / Import from PDF / New posting) now share one row.\n";
