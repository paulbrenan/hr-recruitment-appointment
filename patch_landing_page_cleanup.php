<?php
/**
 * patch_landing_page_cleanup.php
 *
 * One-shot script — run once from the project root, then delete.
 *
 * What it does to resources/views/welcome.blade.php:
 *   1. Removes the "Staff Login" button (and its nav spacer) from the topnav
 *   2. Makes the live clock render on one line instead of stacked
 *   3. Removes the "Apply for a Position" button + "or" divider
 *   4. Removes the 3-card info strip (Transparent / Real-time / Secure)
 *
 * Usage:
 *   php patch_landing_page_cleanup.php
 *
 * Safety:
 *   - Backs up the target file to welcome.blade.php.bak (or .bak2, .bak3... if
 *     a backup already exists) before writing anything.
 *   - Each patch verifies its exact old-content match exists EXACTLY ONCE in
 *     the file before applying. If a match is missing or appears more than
 *     once (drift), the whole script aborts loudly and writes nothing.
 */

$targetFile = __DIR__ . '/resources/views/welcome.blade.php';

if (!file_exists($targetFile)) {
    fwrite(STDERR, "❌ File not found: $targetFile\n");
    fwrite(STDERR, "   If your landing page view lives elsewhere, edit \$targetFile at the top of this script and re-run.\n");
    exit(1);
}

$original = file_get_contents($targetFile);
if ($original === false) {
    fwrite(STDERR, "❌ Could not read $targetFile\n");
    exit(1);
}

$content = $original;
$patchesApplied = [];

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
// PATCH 1 — Remove .btn-admin CSS rules (no longer needed)
// ─────────────────────────────────────────────────────────────────────────
apply_patch(
    $content,
    "  .btn-admin {
    display: flex; align-items: center; gap: 7px;
    background: var(--red); color: #fff;
    padding: 9px 18px; border-radius: 8px;
    font-size: .82rem; font-weight: 700;
    text-decoration: none; transition: background .2s;
    border: none;
  }
  .btn-admin:hover { background: var(--red-dark); color: #fff; }
  .topnav-clock { display: flex; flex-direction: column; align-items: center; line-height: 1.2; }
  .topnav-clock .clock-date { font-size: .75rem; font-weight: 600; color: rgba(255,255,255,.65); }
  .topnav-clock .clock-time { font-size: .9rem; font-weight: 800; color: #fff; letter-spacing: .02em; font-variant-numeric: tabular-nums; }
  .topnav-spacer { width: 0; flex-shrink: 0; }
  @media(min-width:680px){ .topnav-spacer { width: 172px; } }
  @media(max-width:560px){ .topnav-clock { display: none; } }",
    "  .topnav-clock {
    display: flex;
    align-items: baseline;
    gap: 8px;
    line-height: 1.2;
  }
  .topnav-clock .clock-date { font-size: .75rem; font-weight: 600; color: rgba(255,255,255,.65); }
  .topnav-clock .clock-time { font-size: .9rem; font-weight: 800; color: #fff; letter-spacing: .02em; font-variant-numeric: tabular-nums; }
  @media(max-width:560px){ .topnav-clock { display: none; } }",
    'CSS: remove .btn-admin / .topnav-spacer rules, make .topnav-clock single-line',
    $patchesApplied
);

// ─────────────────────────────────────────────────────────────────────────
// PATCH 2 — Remove .divider and .btn-apply CSS rules (no longer needed)
// ─────────────────────────────────────────────────────────────────────────
apply_patch(
    $content,
    "  .btn-track:hover { background: var(--dark); }
  .btn-track:disabled { opacity: .6; cursor: not-allowed; }
  .divider {
    display: flex; align-items: center; gap: 12px;
    margin: 20px 0; color: var(--muted);
    font-size: .78rem; font-weight: 500;
  }
  .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #d0daea; }
  .btn-apply {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    width: 100%; padding: 13px;
    background: var(--red); color: #fff;
    border-radius: 8px; font-size: .9rem; font-weight: 700;
    text-decoration: none; transition: .2s;
  }
  .btn-apply:hover { background: var(--red-dark); color: #fff; }

  /* ── INFO STRIP ── */
  .info-strip {
    display: flex; gap: 16px; flex-wrap: wrap;
    justify-content: center;
    margin-top: 0; max-width: 580px; width: 100%;
  }
  .info-card {
    flex: 1; min-width: 140px;
    background: rgba(255,255,255,.15);
    border: 1px solid rgba(255,255,255,.25);
    border-radius: 10px; padding: 14px 16px; text-align: center;
    backdrop-filter: blur(4px);
  }
  .info-card i { font-size: 1.3rem; color: #ffd700; margin-bottom: 5px; display: block; }
  .info-card .val { font-size: .78rem; font-weight: 700; color: #fff; }
  .info-card .lbl { font-size: .7rem; color: rgba(255,255,255,.7); }

  /* ── CONTACT & LOCATION ── */",
    "  .btn-track:hover { background: var(--dark); }
  .btn-track:disabled { opacity: .6; cursor: not-allowed; }

  /* ── CONTACT & LOCATION ── */",
    'CSS: remove .divider / .btn-apply / .info-strip / .info-card rules',
    $patchesApplied
);

// ─────────────────────────────────────────────────────────────────────────
// PATCH 3 — Nav markup: remove Staff Login button + spacer
// ─────────────────────────────────────────────────────────────────────────
apply_patch(
    $content,
    "  <div class=\"topnav-clock\">
    <span class=\"clock-date\" id=\"clockDate\"></span>
    <span class=\"clock-time\" id=\"clockTime\"></span>
  </div>
  <div class=\"topnav-spacer\">
    <a href=\"/login\" class=\"btn-admin\"><i class=\"bi bi-shield-lock\"></i> Staff Login</a>
  </div>
</nav>",
    "  <div class=\"topnav-clock\">
    <span class=\"clock-date\" id=\"clockDate\"></span>
    <span class=\"clock-time\" id=\"clockTime\"></span>
  </div>
</nav>",
    'HTML: remove Staff Login button + topnav-spacer',
    $patchesApplied
);

// ─────────────────────────────────────────────────────────────────────────
// PATCH 4 — Track box markup: remove divider + Apply button
// ─────────────────────────────────────────────────────────────────────────
apply_patch(
    $content,
    "      <button class=\"btn-track\" onclick=\"trackApplication()\">TRACK <i class=\"bi bi-arrow-right\"></i></button>
    </div>
    <div class=\"divider\">or</div>
    <a href=\"/portal/register\" class=\"btn-apply\"><i class=\"bi bi-pencil-square\"></i> Apply for a Position</a>
  </div>

  <!-- Info strip -->
  <div class=\"info-strip\">
    <div class=\"info-card\">
      <i class=\"bi bi-file-earmark-text\"></i>
      <div class=\"val\">Transparent</div>
      <div class=\"lbl\">Merit-based hiring</div>
    </div>
    <div class=\"info-card\">
      <i class=\"bi bi-clock-history\"></i>
      <div class=\"val\">Real-time</div>
      <div class=\"lbl\">Status tracking</div>
    </div>
    <div class=\"info-card\">
      <i class=\"bi bi-shield-check\"></i>
      <div class=\"val\">Secure</div>
      <div class=\"lbl\">Data privacy</div>
    </div>
  </div>

  <!-- Contact Section -->",
    "      <button class=\"btn-track\" onclick=\"trackApplication()\">TRACK <i class=\"bi bi-arrow-right\"></i></button>
    </div>
  </div>

  <!-- Contact Section -->",
    'HTML: remove divider, Apply button, and 3-card info strip',
    $patchesApplied
);

// ─────────────────────────────────────────────────────────────────────────
// Backup, then write
// ─────────────────────────────────────────────────────────────────────────
$backupFile = $targetFile . '.bak';
$suffix = 2;
while (file_exists($backupFile)) {
    $backupFile = $targetFile . '.bak' . $suffix;
    $suffix++;
}

if (!copy($targetFile, $backupFile)) {
    fwrite(STDERR, "❌ Could not create backup at $backupFile — aborting, no changes written.\n");
    exit(1);
}

if (file_put_contents($targetFile, $content) === false) {
    fwrite(STDERR, "❌ Failed to write updated content to $targetFile\n");
    fwrite(STDERR, "   Backup is safe at: $backupFile\n");
    exit(1);
}

echo "✅ Patched: $targetFile\n";
echo "✅ Backup saved: $backupFile\n";
echo "✅ Patches applied (" . count($patchesApplied) . "):\n";
foreach ($patchesApplied as $p) {
    echo "   - $p\n";
}
echo "\nDone. Delete this script when you're satisfied with the result.\n";
