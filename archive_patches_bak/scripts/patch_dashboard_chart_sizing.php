<?php
/**
 * patch_dashboard_chart_sizing.php
 *
 * Fixes the "Recruitment activity" and "Applications by status" charts on
 * the Dashboard leaving a large blank gap under them. Cause: the cards use
 * h-100 (so both stretch to match the taller neighbor) but Chart.js keeps
 * maintainAspectRatio: true by default, so each canvas only grows to the
 * aspect ratio implied by its `height="…"` attribute — not the full card.
 *
 * Fix:
 *  - Wrap each canvas in a fixed-height container div.
 *  - Set maintainAspectRatio: false so the chart fills that container.
 *
 * Usage: php patch_dashboard_chart_sizing.php
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
$view = __DIR__ . '/resources/views/dashboard/index.blade.php';

// ---------------------------------------------------------------------
// 1. Wrap the "Recruitment activity" canvas in a fixed-height container.
// ---------------------------------------------------------------------
apply_patch(
    $view,
    '<h6 class="mb-3">Recruitment activity (last 6 months)</h6>
                <canvas id="applicationsChart" height="110"></canvas>',
    '<h6 class="mb-3">Recruitment activity (last 6 months)</h6>
                <div style="height: 320px;">
                    <canvas id="applicationsChart"></canvas>
                </div>',
    'Wrap recruitment activity canvas in fixed-height container'
);

// ---------------------------------------------------------------------
// 2. Wrap the "Applications by status" canvas in a fixed-height container.
// ---------------------------------------------------------------------
apply_patch(
    $view,
    '<h6 class="mb-3">Applications by status</h6>
                <canvas id="statusChart" height="160"></canvas>',
    '<h6 class="mb-3">Applications by status</h6>
                <div style="height: 320px;">
                    <canvas id="statusChart"></canvas>
                </div>',
    'Wrap applications by status canvas in fixed-height container'
);

// ---------------------------------------------------------------------
// 3. Make the line chart fill its container (maintainAspectRatio: false).
// ---------------------------------------------------------------------
apply_patch(
    $view,
    "        options: {
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: true, position: 'bottom', labels: { boxWidth: 12, font: { size: 11 }, usePointStyle: true, pointStyle: 'circle' } },
                tooltip: { backgroundColor: '#0a1a33', padding: 10, cornerRadius: 8, titleFont: { size: 12 }, bodyFont: { size: 12 } }
            },",
    "        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: true, position: 'bottom', labels: { boxWidth: 12, font: { size: 11 }, usePointStyle: true, pointStyle: 'circle' } },
                tooltip: { backgroundColor: '#0a1a33', padding: 10, cornerRadius: 8, titleFont: { size: 12 }, bodyFont: { size: 12 } }
            },",
    'Line chart fills its container'
);

// ---------------------------------------------------------------------
// 4. Make the doughnut chart fill its container (maintainAspectRatio: false).
// ---------------------------------------------------------------------
apply_patch(
    $view,
    "        options: {
            cutout: '68%',
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 }, usePointStyle: true, pointStyle: 'circle' } },
                tooltip: { backgroundColor: '#0a1a33', padding: 10, cornerRadius: 8 }
            },",
    "        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 }, usePointStyle: true, pointStyle: 'circle' } },
                tooltip: { backgroundColor: '#0a1a33', padding: 10, cornerRadius: 8 }
            },",
    'Doughnut chart fills its container'
);

echo "\nDone. Reload the Dashboard to verify:\n";
echo " - Both charts now fill their card height, no more blank space below.\n";
echo " - The doughnut chart should be noticeably smaller/tighter, matching its card.\n";
