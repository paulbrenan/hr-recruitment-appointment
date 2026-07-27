<?php
/**
 * fix_overview_places_show_more.php
 *
 * The Overview panel's Places of Assignment table rendered every
 * location row unconditionally -- postings with many locations forced
 * HR to scroll a long way down just to get past the table. Now shows
 * the first 5 rows, with the rest collapsed behind a "Show N more" /
 * "Show less" toggle, matching the same collapsible pattern already
 * used for extra locations on the job postings index page.
 *
 * Uses sibling <tbody> elements (visible rows / hidden rows / total row)
 * -- multiple <tbody> per <table> is valid HTML5, they just can't be
 * nested inside each other.
 *
 * HOW TO RUN:
 *   php fix_overview_places_show_more.php   (from project root)
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

echo "\n=== fix_overview_places_show_more.php ===\n\n";

$showPath = ROOT . '/resources/views/job-postings/show.blade.php';

echo "[1] Limiting Places of Assignment table to 5 visible rows + show-more toggle...\n";

apply_patch(
    $showPath,
    '                    @if ($locations->isNotEmpty())
                    <div class="mb-3">
                        <div class="text-muted small mb-2">Places of assignment</div>
                        <table class="table table-sm table-bordered mb-0" style="font-size:0.85rem;">
                            <thead class="table-light">
                                <tr><th>Place</th><th class="text-center" style="width:100px;">Vacancies</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($locations as $loc)
                                <tr>
                                    <td>{{ $loc->place_of_assignment }}</td>
                                    <td class="text-center">{{ $loc->vacancies }}</td>
                                </tr>
                                @endforeach
                                <tr class="table-light fw-medium">
                                    <td class="text-end text-muted small">Total</td>
                                    <td class="text-center">{{ $locations->sum(\'vacancies\') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    @endif',
    '                    @if ($locations->isNotEmpty())
                    @php
                        $locationsVisible = $locations->take(5);
                        $locationsHidden = $locations->slice(5);
                    @endphp
                    <div class="mb-3">
                        <div class="text-muted small mb-2">Places of assignment</div>
                        <table class="table table-sm table-bordered mb-0" style="font-size:0.85rem;">
                            <thead class="table-light">
                                <tr><th>Place</th><th class="text-center" style="width:100px;">Vacancies</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($locationsVisible as $loc)
                                <tr>
                                    <td>{{ $loc->place_of_assignment }}</td>
                                    <td class="text-center">{{ $loc->vacancies }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                            @if ($locationsHidden->isNotEmpty())
                            <tbody id="overviewLocationsExtra" class="d-none">
                                @foreach ($locationsHidden as $loc)
                                <tr>
                                    <td>{{ $loc->place_of_assignment }}</td>
                                    <td class="text-center">{{ $loc->vacancies }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                            @endif
                            <tbody>
                                <tr class="table-light fw-medium">
                                    <td class="text-end text-muted small">Total</td>
                                    <td class="text-center">{{ $locations->sum(\'vacancies\') }}</td>
                                </tr>
                            </tbody>
                        </table>
                        @if ($locationsHidden->isNotEmpty())
                        <button type="button" id="overviewLocationsToggle"
                                class="btn btn-link btn-sm p-0 mt-2"
                                style="font-size: 0.8rem; text-decoration: none; color: var(--hr-primary);"
                                onclick="
                                    const extra = document.getElementById(\'overviewLocationsExtra\');
                                    const isHidden = extra.classList.contains(\'d-none\');
                                    extra.classList.toggle(\'d-none\', !isHidden);
                                    this.textContent = isHidden ? \'Show less\' : \'Show {{ $locationsHidden->count() }} more\';
                                ">
                            Show {{ $locationsHidden->count() }} more
                        </button>
                        @endif
                    </div>
                    @endif',
    'show.blade.php: Places of Assignment table limited to 5 rows with show-more toggle'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - Overview panel's Places of Assignment table now shows the\n";
echo "    first 5 locations by default.\n";
echo "  - If there are more than 5, a \"Show N more\" link appears below\n";
echo "    the table -- click to expand the rest inline, click again\n";
echo "    (\"Show less\") to collapse.\n";
echo "  - Postings with 5 or fewer locations are completely unaffected --\n";
echo "    no toggle shown at all.\n\n";
echo "DELETE this script after running.\n";
