<?php

/**
 * fix_index_row_click_and_locations.php
 *
 * WHAT THIS DOES:
 *   1. Makes each job posting row fully clickable (links to show page)
 *   2. Locations column: shows first 2 inline, collapses the rest
 *      behind a "+N more" toggle that expands on click
 *
 * HOW TO RUN:
 *   php fix_index_row_click_and_locations.php    (from project root)
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
    if ($count === 0) { echo "\n❌ PATCH ABORTED — content not found in:\n  $path\nLabel: $label\n"; exit(1); }
    if ($count > 1)   { echo "\n❌ PATCH ABORTED — pattern found $count times in $path\nLabel: $label\n"; exit(1); }
    backup($path);
    file_put_contents($path, str_replace($old, $new, $content));
    echo "  [ok ] $label\n";
}

echo "\n=== fix_index_row_click_and_locations.php ===\n\n";

$indexPath = ROOT . '/resources/views/job-postings/index.blade.php';

// ─── 1. Make rows clickable + fix locations column ─────────────────────────

// Replace the entire <tr> opening + locations cell + action buttons
$oldRow = <<<'BLADE'
                @foreach ($postings as $posting)
                <tr>
                    <td class="fw-medium">{{ $posting->title }}</td>
                    <td>
                        @if ($posting->locations->isNotEmpty())
                            <div class="d-flex flex-column gap-1">
                                @foreach ($posting->locations as $loc)
                                    <span class="small">{{ $loc->place_of_assignment }}
                                        <span class="text-muted">({{ $loc->vacancies }} {{ Str::plural('vacancy', $loc->vacancies) }})</span>
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
BLADE;

$newRow = <<<'BLADE'
                @foreach ($postings as $posting)
                <tr class="posting-row" style="cursor: pointer;" data-href="{{ route('job-postings.show', $posting->id) }}">
                    <td class="fw-medium">{{ $posting->title }}</td>
                    <td>
                        @if ($posting->locations->isNotEmpty())
                            @php $locs = $posting->locations; $extra = $locs->count() - 2; @endphp
                            <div class="d-flex flex-column gap-1">
                                @foreach ($locs->take(2) as $loc)
                                    <span class="small">{{ $loc->place_of_assignment }}
                                        <span class="text-muted">({{ $loc->vacancies }} {{ Str::plural('vacancy', $loc->vacancies) }})</span>
                                    </span>
                                @endforeach
                                @if ($extra > 0)
                                    <div class="location-extra d-none">
                                        @foreach ($locs->skip(2) as $loc)
                                            <span class="small d-block">{{ $loc->place_of_assignment }}
                                                <span class="text-muted">({{ $loc->vacancies }} {{ Str::plural('vacancy', $loc->vacancies) }})</span>
                                            </span>
                                        @endforeach
                                    </div>
                                    <button type="button"
                                        class="btn btn-link btn-sm p-0 text-start location-toggle"
                                        style="font-size: 0.75rem; text-decoration: none; color: var(--hr-primary);">
                                        +{{ $extra }} more
                                    </button>
                                @endif
                            </div>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
BLADE;

apply_patch($indexPath, $oldRow, $newRow, 'index.blade.php: clickable rows + collapsible locations');

// ─── 2. Stop action buttons from triggering the row click ─────────────────

$oldActions = <<<'BLADE'
                    <td class="text-end">
                        <a href="{{ route('job-postings.show', $posting->id) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="{{ route('job-postings.edit', $posting->id) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="{{ route('job-postings.destroy', $posting->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this job posting? This cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
BLADE;

$newActions = <<<'BLADE'
                    <td class="text-end" onclick="event.stopPropagation()">
                        <a href="{{ route('job-postings.show', $posting->id) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="{{ route('job-postings.edit', $posting->id) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="{{ route('job-postings.destroy', $posting->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this job posting? This cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
BLADE;

apply_patch($indexPath, $oldActions, $newActions, 'index.blade.php: stop action buttons from triggering row click');

// ─── 3. Add JS before @endsection ─────────────────────────────────────────

$oldEnd = <<<'BLADE'
@endsection
BLADE;

$newEnd = <<<'BLADE'
@push('scripts')
<script>
    // Clickable rows
    document.querySelectorAll('.posting-row').forEach(function (row) {
        row.addEventListener('click', function () {
            window.location = this.dataset.href;
        });
    });

    // Collapsible extra locations — stop click from triggering row nav
    document.querySelectorAll('.location-toggle').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            const extra = this.previousElementSibling;
            const isHidden = extra.classList.contains('d-none');
            extra.classList.toggle('d-none', !isHidden);
            this.textContent = isHidden
                ? 'Show less'
                : '+' + extra.querySelectorAll('.small').length + ' more';
        });
    });
</script>
@endpush
@endsection
BLADE;

apply_patch($indexPath, $oldEnd, $newEnd, 'index.blade.php: row click + toggle JS');

echo <<<TEXT

✅ Done. No migration needed.

HOW IT WORKS:
  - Click anywhere on a row → goes to the job posting show page
  - Action buttons (eye/pencil/trash) still work independently
  - 1–2 locations: shown as-is
  - 3+ locations: first 2 shown, rest hidden behind "+N more" button
  - Clicking "+N more" expands inline (does not navigate away)
  - Clicking "Show less" collapses again

DELETE this script after running.

TEXT;
