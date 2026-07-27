<?php
/**
 * patch_theme_colors_background.php
 *
 * One-shot script — run once from the project root, then delete.
 *
 * What it does:
 *   1. public/css/deped-theme.css
 *      - Repoints the shared theme colors from teal/gold to the landing
 *        page's blue/red/gold DepEd Cavite palette (blue: #003087,
 *        blue-mid: #0047b3, blue-light: #e6ecf7, dark: #0a1a33, gold: #ffd700).
 *        Since register/submitted pages consume these via var(--teal) etc.,
 *        this alone re-colors headers, section titles, buttons, radio
 *        highlights, and the privacy notice box on both pages.
 *      - Swaps the flat teal gradient body background for the same fixed
 *        matatag-bg.png + blue overlay treatment used on the landing page.
 *   2. resources/views/portal/submitted.blade.php
 *      - Fixes 3 spots that hardcode #2b7a78 (old teal) directly in inline
 *        styles / <style> block rather than going through the CSS variable,
 *        so the confirmation page matches too.
 *
 * Usage:
 *   php patch_theme_colors_background.php
 *
 * Safety:
 *   - Backs up each target file (.bak, .bak2, ... if a backup already exists)
 *     before writing anything.
 *   - Each patch verifies its exact old-content match exists EXACTLY ONCE in
 *     its target file before applying. If any patch doesn't match, the whole
 *     script aborts loudly and writes nothing to any file.
 *
 * NOTE: If resources/views/portal/submitted.blade.php isn't the real path
 * for that view in your project, edit $submittedFile below and re-run.
 */

$themeFile     = __DIR__ . '/public/css/deped-theme.css';
$submittedFile = __DIR__ . '/resources/views/portal/submitted.blade.php';

foreach ([$themeFile, $submittedFile] as $f) {
    if (!file_exists($f)) {
        fwrite(STDERR, "❌ File not found: $f\n");
        fwrite(STDERR, "   Edit the path variables at the top of this script and re-run.\n");
        exit(1);
    }
}

$themeOriginal     = file_get_contents($themeFile);
$submittedOriginal = file_get_contents($submittedFile);

if ($themeOriginal === false || $submittedOriginal === false) {
    fwrite(STDERR, "❌ Could not read one or both target files.\n");
    exit(1);
}

$themeContent     = $themeOriginal;
$submittedContent = $submittedOriginal;
$patchesApplied   = [];

/**
 * Applies a single find/replace patch. Aborts the whole script if $old
 * does not appear in $content exactly once.
 */
function apply_patch(string &$content, string $old, string $new, string $label, array &$patchesApplied): void
{
    $count = substr_count($content, $old);

    if ($count === 0) {
        fwrite(STDERR, "❌ ABORT — patch \"$label\" failed: expected content not found.\n");
        fwrite(STDERR, "   No changes have been written to disk.\n");
        fwrite(STDERR, "   This usually means the file has drifted from what this script expects.\n");
        fwrite(STDERR, "   Please paste the CURRENT file content so the script can be regenerated.\n");
        exit(1);
    }

    if ($count > 1) {
        fwrite(STDERR, "❌ ABORT — patch \"$label\" failed: expected content found $count times (expected exactly 1).\n");
        fwrite(STDERR, "   No changes have been written to disk.\n");
        exit(1);
    }

    $content = str_replace($old, $new, $content);
    $patchesApplied[] = $label;
}

// ─────────────────────────────────────────────────────────────────────────
// deped-theme.css — PATCH 1: color variables (teal/gold → blue/red/gold)
// ─────────────────────────────────────────────────────────────────────────
apply_patch(
    $themeContent,
    ":root {
  --teal: #2b7a78;
  --teal-light: #def2f1;
  --teal-mid: #3aafa9;
  --teal-dark: #1d5e5c;
  --gold: #ffc20e;
  --navy: #00247d;
}",
    ":root {
  --teal: #003087;
  --teal-light: #e6ecf7;
  --teal-mid: #0047b3;
  --teal-dark: #0a1a33;
  --gold: #ffd700;
  --navy: #00247d;
}",
    'deped-theme.css: repoint color variables to blue/red/gold palette',
    $patchesApplied
);

// ─────────────────────────────────────────────────────────────────────────
// deped-theme.css — PATCH 2: body background (teal gradient → fixed photo + overlay)
// ─────────────────────────────────────────────────────────────────────────
apply_patch(
    $themeContent,
    "body {
  background: linear-gradient(135deg, #d9f0ef 0%, #e8f5f5 50%, #c8e6e5 100%);
  min-height: 100vh;
  font-family: 'Segoe UI', Arial, sans-serif;
}",
    "body {
  background: url('../matatag-bg.png') center center / cover no-repeat fixed;
  min-height: 100vh;
  font-family: 'Segoe UI', Arial, sans-serif;
  position: relative;
}
body::after {
  content: \"\";
  position: fixed;
  inset: 0;
  background: rgba(0, 48, 135, 0.72);
  z-index: 0;
  pointer-events: none;
}",
    'deped-theme.css: swap body background for fixed matatag-bg.png + blue overlay',
    $patchesApplied
);

// ─────────────────────────────────────────────────────────────────────────
// submitted.blade.php — PATCH 3: txn-box hardcoded teal → blue
// ─────────────────────────────────────────────────────────────────────────
apply_patch(
    $submittedContent,
    "  .txn-box { background:#e8f5f5; border:2px dashed #2b7a78; border-radius:8px;
             text-align:center; padding:18px; margin:20px 24px; }
  .txn-box .label { font-size:.8rem; color:#555; margin-bottom:4px; }
  .txn-box .number { font-size:1.4rem; font-weight:800; color:#2b7a78; letter-spacing:.04em; }",
    "  .txn-box { background:#e6ecf7; border:2px dashed #003087; border-radius:8px;
             text-align:center; padding:18px; margin:20px 24px; }
  .txn-box .label { font-size:.8rem; color:#555; margin-bottom:4px; }
  .txn-box .number { font-size:1.4rem; font-weight:800; color:#003087; letter-spacing:.04em; }",
    'submitted.blade.php: txn-box hardcoded teal → blue',
    $patchesApplied
);

// ─────────────────────────────────────────────────────────────────────────
// submitted.blade.php — PATCH 4: "Track Your Application" button hardcoded teal → blue
// ─────────────────────────────────────────────────────────────────────────
apply_patch(
    $submittedContent,
    "      <a href=\"{{ url('/') }}?txn={{ urlencode(\$transactionNumber) }}\"
         style=\"display:inline-flex;align-items:center;gap:8px;background:#2b7a78;color:#fff;
                font-weight:700;font-size:.9rem;padding:10px 24px;border-radius:8px;
                text-decoration:none;\">",
    "      <a href=\"{{ url('/') }}?txn={{ urlencode(\$transactionNumber) }}\"
         style=\"display:inline-flex;align-items:center;gap:8px;background:#003087;color:#fff;
                font-weight:700;font-size:.9rem;padding:10px 24px;border-radius:8px;
                text-decoration:none;\">",
    'submitted.blade.php: Track Your Application button hardcoded teal → blue',
    $patchesApplied
);

// ─────────────────────────────────────────────────────────────────────────
// submitted.blade.php — PATCH 5: memo PDF link hardcoded teal → blue
// ─────────────────────────────────────────────────────────────────────────
apply_patch(
    $submittedContent,
    "      <a href=\"{{ \$jobPosting->memoPdfUrl() }}\" target=\"_blank\" rel=\"noopener\"
         style=\"color:#2b7a78; font-weight:700; font-size:.85rem; text-decoration:underline;\">",
    "      <a href=\"{{ \$jobPosting->memoPdfUrl() }}\" target=\"_blank\" rel=\"noopener\"
         style=\"color:#003087; font-weight:700; font-size:.85rem; text-decoration:underline;\">",
    'submitted.blade.php: memo PDF link hardcoded teal → blue',
    $patchesApplied
);

// ─────────────────────────────────────────────────────────────────────────
// Backup + write both files (only after ALL patches above succeeded)
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
        fwrite(STDERR, "❌ Could not create backup at $backupFile — aborting, no changes written to $file.\n");
        exit(1);
    }

    if (file_put_contents($file, $content) === false) {
        fwrite(STDERR, "❌ Failed to write updated content to $file\n");
        fwrite(STDERR, "   Backup is safe at: $backupFile\n");
        exit(1);
    }

    return $backupFile;
}

$themeBackup     = backup_and_write($themeFile, $themeContent);
$submittedBackup = backup_and_write($submittedFile, $submittedContent);

echo "✅ Patched: $themeFile\n";
echo "   Backup: $themeBackup\n\n";
echo "✅ Patched: $submittedFile\n";
echo "   Backup: $submittedBackup\n\n";
echo "✅ Patches applied (" . count($patchesApplied) . "):\n";
foreach ($patchesApplied as $p) {
    echo "   - $p\n";
}
echo "\nDone. Delete this script when you're satisfied with the result.\n";
echo "Note: register.blade.php needed no direct patch — it consumes the same\n";
echo "CSS variables from deped-theme.css, so it re-colors automatically.\n";
