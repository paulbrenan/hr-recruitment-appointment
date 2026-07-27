<?php
/**
 * patch_admin_ui_colors.php
 *
 * One-shot script — run once from the project root, then delete.
 *
 * Files patched:
 *   1. resources/views/layouts/app.blade.php
 *      --hr-primary: #1a5f4f → #003087  (dark green → DepEd blue)
 *      --hr-primary-dark: #134539 → #0a1a33  (darker green → DepEd dark)
 *      --hr-accent: #2fae57 → #ffd700  (green accent → DepEd gold)
 *      --hr-bg: #f4f6f7 → #f0f4fa  (slight blue tint to bg)
 *
 *   2. resources/views/layouts/portal.blade.php
 *      --hr-primary: #2f4858 → #003087
 *      --hr-primary-dark: #233843 → #0a1a33
 *      --hr-accent: #3f7d8c → #ffd700
 *
 *   3. resources/views/dashboard/index.blade.php  (or dashboard/index.blade.php)
 *      Chart.js Applications line: borderColor '#3f7d8c' + rgba(63,125,140) → blue
 *
 * All other admin blade files (applications, job-postings, offers, talent-pool,
 * pipelines, form, show, etc.) use var(--hr-primary) and inherit from app.blade.php,
 * so they re-color automatically with no direct patch.
 *
 * Usage:
 *   php patch_admin_ui_colors.php
 *
 * Safety:
 *   - Backs up each file (.bak, .bak2, ...) before writing.
 *   - Each patch verifies exact old-content match exists EXACTLY ONCE.
 *     If any patch fails, the script aborts loudly and writes nothing.
 */

$appLayout      = __DIR__ . '/resources/views/layouts/app.blade.php';
$portalLayout   = __DIR__ . '/resources/views/layouts/portal.blade.php';
$dashboardIndex = __DIR__ . '/resources/views/dashboard/index.blade.php';

// ── verify all three files exist before doing anything ─────────────────────
foreach ([$appLayout, $portalLayout, $dashboardIndex] as $f) {
    if (!file_exists($f)) {
        fwrite(STDERR, "❌ File not found: $f\n");
        fwrite(STDERR, "   Edit the path variable at the top of this script and re-run.\n");
        exit(1);
    }
}

$appContent       = file_get_contents($appLayout);
$portalContent    = file_get_contents($portalLayout);
$dashboardContent = file_get_contents($dashboardIndex);

$patchesApplied = [];

function apply_patch(string &$content, string $old, string $new, string $label, array &$patchesApplied): void
{
    $count = substr_count($content, $old);

    if ($count === 0) {
        fwrite(STDERR, "❌ ABORT — patch \"$label\" failed: expected content not found.\n");
        fwrite(STDERR, "   No changes have been written to disk.\n");
        fwrite(STDERR, "   Please paste the CURRENT file content so the script can be regenerated.\n");
        exit(1);
    }

    if ($count > 1) {
        fwrite(STDERR, "❌ ABORT — patch \"$label\" failed: content found $count times (expected exactly 1).\n");
        fwrite(STDERR, "   No changes have been written to disk.\n");
        exit(1);
    }

    $content = str_replace($old, $new, $content);
    $patchesApplied[] = $label;
}

// ─────────────────────────────────────────────────────────────────────────
// PATCH 1 — app.blade.php: recolor CSS variables
// ─────────────────────────────────────────────────────────────────────────
apply_patch(
    $appContent,
    "        :root {
            --hr-primary: #1a5f4f;
            --hr-primary-dark: #134539;
            --hr-accent: #2fae57;
            --hr-bg: #f4f6f7;
            --hr-header-h: 56px;
        }",
    "        :root {
            --hr-primary: #003087;
            --hr-primary-dark: #0a1a33;
            --hr-accent: #ffd700;
            --hr-bg: #f0f4fa;
            --hr-header-h: 56px;
        }",
    'app.blade.php: recolor --hr-primary/dark/accent/bg to DepEd Cavite blue/gold palette',
    $patchesApplied
);

// ─────────────────────────────────────────────────────────────────────────
// PATCH 2 — portal.blade.php: recolor CSS variables
// ─────────────────────────────────────────────────────────────────────────
apply_patch(
    $portalContent,
    "        :root {
            --hr-primary: #2f4858;
            --hr-primary-dark: #233843;
            --hr-accent: #3f7d8c;
            --hr-bg: #f4f6f7;
        }",
    "        :root {
            --hr-primary: #003087;
            --hr-primary-dark: #0a1a33;
            --hr-accent: #ffd700;
            --hr-bg: #f0f4fa;
        }",
    'portal.blade.php: recolor --hr-primary/dark/accent/bg to DepEd Cavite blue/gold palette',
    $patchesApplied
);

// ─────────────────────────────────────────────────────────────────────────
// PATCH 3 — dashboard/index.blade.php: Applications chart line color
// ─────────────────────────────────────────────────────────────────────────
apply_patch(
    $dashboardContent,
    "                {
                    label: 'Applications',
                    data: @json(\$monthlyApplicationsData),
                    borderColor: '#3f7d8c',
                    backgroundColor: 'rgba(63, 125, 140, 0.1)',
                    tension: 0.3,
                    fill: true,
                },",
    "                {
                    label: 'Applications',
                    data: @json(\$monthlyApplicationsData),
                    borderColor: '#003087',
                    backgroundColor: 'rgba(0, 48, 135, 0.1)',
                    tension: 0.3,
                    fill: true,
                },",
    'dashboard/index.blade.php: Applications chart line teal → DepEd blue',
    $patchesApplied
);

// ─────────────────────────────────────────────────────────────────────────
// Backup + write (only after ALL patches succeeded)
// ─────────────────────────────────────────────────────────────────────────
function backup_and_write(string $file, string $content): string
{
    $backupFile = $file . '.bak';
    $suffix = 2;
    while (file_exists($backupFile)) {
        $backupFile = $file . '.bak' . $suffix;
        $suffix++;
    }

    if (!copy($file, $backupFile)) {
        fwrite(STDERR, "❌ Could not create backup at $backupFile — aborting, nothing written.\n");
        exit(1);
    }

    if (file_put_contents($file, $content) === false) {
        fwrite(STDERR, "❌ Failed to write to $file\n");
        fwrite(STDERR, "   Backup is safe at: $backupFile\n");
        exit(1);
    }

    return $backupFile;
}

$b1 = backup_and_write($appLayout,      $appContent);
$b2 = backup_and_write($portalLayout,   $portalContent);
$b3 = backup_and_write($dashboardIndex, $dashboardContent);

echo "✅ Patched: $appLayout\n   Backup: $b1\n\n";
echo "✅ Patched: $portalLayout\n   Backup: $b2\n\n";
echo "✅ Patched: $dashboardIndex\n   Backup: $b3\n\n";
echo "✅ Patches applied (" . count($patchesApplied) . "):\n";
foreach ($patchesApplied as $p) {
    echo "   - $p\n";
}
echo "\n";
echo "Note: All other blade files (applications, job-postings, offers, talent-pool,\n";
echo "pipelines, form.blade.php, show.blade.php, etc.) inherit via var(--hr-primary)\n";
echo "and re-color automatically — no direct patch needed on those files.\n";
echo "\nDone. Delete this script when you're satisfied with the result.\n";
