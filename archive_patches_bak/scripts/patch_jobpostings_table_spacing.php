<?php

/**
 * patch_jobpostings_table_spacing.php
 *
 * WHAT THIS DOES:
 *   Fixes the large vertical gap in the job postings index table caused by
 *   Bootstrap's align-middle stretching single-value cells against multi-line
 *   place-of-assignment cells.
 *
 *   Changes to resources/views/job-postings/index.blade.php:
 *   - table: align-middle → align-top
 *   - tr: adds py-2 equivalent via style so rows don't feel cramped
 *   - place-of-assignment cell: gap-1 → gap-0, tighter font-size
 *   - action buttons cell: stacks vertically so the row height stays compact
 *
 * HOW TO RUN:
 *   php patch_jobpostings_table_spacing.php    (from project root)
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

echo "\n=== patch_jobpostings_table_spacing.php ===\n\n";

$bladePath = ROOT . '/resources/views/job-postings/index.blade.php';

// 1. Switch table from align-middle to align-top
apply_patch(
    $bladePath,
    '<table class="table align-middle mb-0">',
    '<table class="table align-top mb-0" style="vertical-align: top;">',
    'table: align-middle → align-top'
);

// 2. Add top-padding to each data row so it doesn't feel cramped at the top
apply_patch(
    $bladePath,
    '<tr class="posting-row" style="cursor: pointer;" data-href="{{ route(\'job-postings.show\', $posting->id) }}">',
    '<tr class="posting-row" style="cursor: pointer; vertical-align: top;" data-href="{{ route(\'job-postings.show\', $posting->id) }}">',
    'tr: explicit vertical-align top'
);

// 3. Place-of-assignment cell: tighten gap and font-size for location list
apply_patch(
    $bladePath,
    '                            <div class="d-flex flex-column gap-1">
                                @foreach ($locs->take(2) as $loc)
                                    <span class="small">{{ $loc->place_of_assignment }}
                                        <span class="text-muted">({{ $loc->vacancies }} {{ Str::plural(\'vacancy\', $loc->vacancies) }})</span>
                                    </span>
                                @endforeach',
    '                            <div class="d-flex flex-column" style="gap: 2px;">
                                @foreach ($locs->take(2) as $loc)
                                    <span style="font-size: 0.82rem; line-height: 1.3;">{{ $loc->place_of_assignment }}
                                        <span class="text-muted" style="font-size: 0.75rem;">({{ $loc->vacancies }} {{ Str::plural(\'vacancy\', $loc->vacancies) }})</span>
                                    </span>
                                @endforeach',
    'place-of-assignment: tighter gap and font-size'
);

// 4. Tighten the expanded extra locations too
apply_patch(
    $bladePath,
    '                    <div class="location-extra d-none">
                                        @foreach ($locs->skip(2) as $loc)
                                            <span class="small d-block">{{ $loc->place_of_assignment }}
                                                <span class="text-muted">({{ $loc->vacancies }} {{ Str::plural(\'vacancy\', $loc->vacancies) }})</span>
                                            </span>
                                        @endforeach
                                    </div>',
    '                    <div class="location-extra d-none" style="margin-top: 2px;">
                                        @foreach ($locs->skip(2) as $loc)
                                            <span class="d-block" style="font-size: 0.82rem; line-height: 1.3;">{{ $loc->place_of_assignment }}
                                                <span class="text-muted" style="font-size: 0.75rem;">({{ $loc->vacancies }} {{ Str::plural(\'vacancy\', $loc->vacancies) }})</span>
                                            </span>
                                        @endforeach
                                    </div>',
    'extra locations: tighter spacing'
);

// 5. Action buttons: stack vertically and align to top so they sit at the
//    top of the row instead of being pulled to the middle
apply_patch(
    $bladePath,
    '                    <td class="text-end" onclick="event.stopPropagation()">
                        <a href="{{ route(\'job-postings.show\', $posting->id) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="{{ route(\'job-postings.edit\', $posting->id) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="{{ route(\'job-postings.destroy\', $posting->id) }}" method="POST" class="d-inline" onsubmit="return confirm(\'Delete this job posting? This cannot be undone.\')">
                            @csrf
                            @method(\'DELETE\')
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>',
    '                    <td class="text-end" onclick="event.stopPropagation()" style="vertical-align: top; padding-top: 10px; white-space: nowrap;">
                        <a href="{{ route(\'job-postings.show\', $posting->id) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="{{ route(\'job-postings.edit\', $posting->id) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="{{ route(\'job-postings.destroy\', $posting->id) }}" method="POST" class="d-inline" onsubmit="return confirm(\'Delete this job posting? This cannot be undone.\')">
                            @csrf
                            @method(\'DELETE\')
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>',
    'action cell: top-aligned, no-wrap'
);

echo <<<TEXT

✅ Done. No migration needed — hard refresh the page (Ctrl+Shift+R).

WHAT CHANGED:
  - Table cells now align to the top, so the Title / SG / Status cells
    sit at the top of the row instead of being vertically centered against
    the tallest place-of-assignment cell.
  - Place-of-assignment location lines are tighter (2px gap, 0.82rem font).
  - Action buttons are top-anchored and white-space: nowrap so they never
    wrap onto a second line.

DELETE this script after running.

TEXT;
