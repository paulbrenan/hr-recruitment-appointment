<?php
/**
 * patch_welcome_footer_clock.php
 *
 * One-shot script — run once from the project root, then delete.
 *
 * What it does to resources/views/welcome.blade.php:
 *   1. Replaces the footer CSS + HTML with a centered white footer:
 *      SDO logo | divider | Facebook circle | divider | Email circle
 *      + copyright line below, matching the reference screenshot style.
 *   2. Updates the live clock JS to output full day/month names
 *      (e.g. "Thursday, July 3, 2026  2:45:09 PM" instead of "Thu, Jul 3").
 *
 * Usage:
 *   php patch_welcome_footer_clock.php
 */

$targetFile = __DIR__ . '/resources/views/welcome.blade.php';

if (!file_exists($targetFile)) {
    fwrite(STDERR, "❌ File not found: $targetFile\n");
    exit(1);
}

$content = file_get_contents($targetFile);
if ($content === false) {
    fwrite(STDERR, "❌ Could not read $targetFile\n");
    exit(1);
}

$original       = $content;
$patchesApplied = [];

function apply_patch(string &$content, string $old, string $new, string $label, array &$patchesApplied): void
{
    $count = substr_count($content, $old);
    if ($count === 0) {
        fwrite(STDERR, "❌ ABORT — patch \"$label\" failed: expected content not found.\n");
        fwrite(STDERR, "   No changes written. Paste the CURRENT file so the script can be regenerated.\n");
        exit(1);
    }
    if ($count > 1) {
        fwrite(STDERR, "❌ ABORT — patch \"$label\" failed: found $count times (expected exactly 1).\n");
        fwrite(STDERR, "   No changes written.\n");
        exit(1);
    }
    $content = str_replace($old, $new, $content);
    $patchesApplied[] = $label;
}

// ─────────────────────────────────────────────────────────────────────────
// PATCH 1 — Footer CSS: replace old multi-column flex layout with centered
// ─────────────────────────────────────────────────────────────────────────
apply_patch(
    $content,
    "  /* ── FOOTER ── */
  footer {
    padding: 18px 32px;
    background: rgba(0, 48, 135, 0.97);
    border-top: 2px solid var(--red);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    backdrop-filter: blur(8px);
  }
  .footer-brand {
    display: flex; align-items: center; gap: 12px;
  }
  .footer-logo-link { display: flex; align-items: center; }
  .footer-logo-img { height: 40px; width: auto; filter: drop-shadow(0 1px 4px rgba(0,0,0,.4)); }
  .footer-brand-text { font-size: .75rem; font-weight: 700; color: rgba(255,255,255,.8); line-height: 1.4; }
  .footer-social { display: flex; align-items: center; gap: 10px; }
  .footer-social a {
    display: flex; align-items: center; justify-content: center;
    width: 36px; height: 36px; border-radius: 8px;
    color: #fff; font-size: 1rem; text-decoration: none;
    transition: background .2s;
  }
  .footer-social a.fb { background: #1877f2; }
  .footer-social a.fb:hover { background: #0d5fd3; }
  .footer-social a.email { background: var(--red); }
  .footer-social a.email:hover { background: var(--red-dark); }
  .footer-copy { font-size: .7rem; color: rgba(255,255,255,.55); text-align: right; }",
    "  /* ── FOOTER ── */
  footer {
    background: rgba(255,255,255,0.97);
    border-top: 3px solid #d0d8e8;
    padding: 22px 32px 16px;
    position: relative;
    z-index: 1;
  }
  .footer-inner {
    display: flex; flex-direction: column; align-items: center; gap: 14px;
  }
  .footer-items {
    display: flex; align-items: center; gap: 28px;
  }
  .footer-divider {
    width: 1px; height: 52px; background: #c8d0dc;
  }
  .footer-logo-img { height: 56px; width: auto; }
  .footer-icon-link {
    display: flex; align-items: center; justify-content: center;
    width: 46px; height: 46px; border-radius: 50%;
    color: #fff; font-size: 1.25rem; text-decoration: none;
    transition: opacity .2s;
  }
  .footer-icon-link:hover { opacity: .82; }
  .footer-icon-link.fb { background: #1877f2; }
  .footer-icon-link.email { background: var(--red); }
  .footer-copy {
    font-size: .7rem; color: #8a95a8;
    letter-spacing: .06em; text-transform: uppercase;
  }",
    'CSS: replace old footer styles with centered white footer',
    $patchesApplied
);

// ─────────────────────────────────────────────────────────────────────────
// PATCH 2 — Footer HTML: replace old brand/social/copy layout
// ─────────────────────────────────────────────────────────────────────────
apply_patch(
    $content,
    "<!-- ── FOOTER ── -->
<footer>
  <div class=\"footer-brand\">
    <a href=\"https://depedcavite.com.ph/\" target=\"_blank\" rel=\"noopener\" class=\"footer-logo-link\">
      <img src=\"/sdo-logo.png\" alt=\"DepEd Cavite\" class=\"footer-logo-img\">
    </a>
    <div class=\"footer-brand-text\">
      Department of Education<br>Schools Division Office of Cavite Province
    </div>
  </div>
  <div class=\"footer-social\">
    <a href=\"https://www.facebook.com/depedtayocaviteprovince\" target=\"_blank\" rel=\"noopener\" class=\"fb\" title=\"Facebook\">
      <i class=\"bi bi-facebook\"></i>
    </a>
    <a href=\"mailto:deped.cavite@deped.gov.ph\" class=\"email\" title=\"Email us\">
      <i class=\"bi bi-envelope-fill\"></i>
    </a>
  </div>
  <div class=\"footer-copy\">
    &copy; {{ date('Y') }} DepEd Cavite Province<br>All rights reserved.
  </div>
</footer>",
    "<!-- ── FOOTER ── -->
<footer>
  <div class=\"footer-inner\">
    <div class=\"footer-items\">
      <a href=\"https://depedcavite.com.ph/\" target=\"_blank\" rel=\"noopener\">
        <img src=\"/sdo-logo.png\" alt=\"SDO Cavite\" class=\"footer-logo-img\">
      </a>
      <div class=\"footer-divider\"></div>
      <a href=\"https://www.facebook.com/depedtayocaviteprovince\" target=\"_blank\" rel=\"noopener\" class=\"footer-icon-link fb\" title=\"Facebook\">
        <i class=\"bi bi-facebook\"></i>
      </a>
      <div class=\"footer-divider\"></div>
      <a href=\"mailto:deped.cavite@deped.gov.ph\" class=\"footer-icon-link email\" title=\"Email us\">
        <i class=\"bi bi-envelope-fill\"></i>
      </a>
    </div>
    <div class=\"footer-copy\">&copy; {{ date('Y') }} DepEd &mdash; Schools Division Office of Cavite Province</div>
  </div>
</footer>",
    'HTML: centered white footer — logo | divider | fb | divider | email + copyright',
    $patchesApplied
);

// ─────────────────────────────────────────────────────────────────────────
// PATCH 3 — Clock JS: full day/month names, single combined span
// ─────────────────────────────────────────────────────────────────────────
apply_patch(
    $content,
    "// ── Live clock ────────────────────────────────────────────────────────────────
(function clock() {
  function tick() {
    const now = new Date();
    document.getElementById('clockDate').textContent = now.toLocaleDateString('en-PH',{weekday:'short',month:'short',day:'numeric'});
    document.getElementById('clockTime').textContent = now.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
  }
  tick();
  setInterval(tick, 1000);
})();",
    "// ── Live clock ────────────────────────────────────────────────────────────────
(function clock() {
  function tick() {
    const now = new Date();
    const datePart = now.toLocaleDateString('en-PH', {
      weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
    });
    const timePart = now.toLocaleTimeString('en-PH', {
      hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
    });
    document.getElementById('clockDate').textContent = datePart;
    document.getElementById('clockTime').textContent = timePart;
  }
  tick();
  setInterval(tick, 1000);
})();",
    'JS: clock outputs full weekday + month names (Thursday, July 3, 2026 / 2:45:09 PM)',
    $patchesApplied
);

// ─────────────────────────────────────────────────────────────────────────
// Backup + write
// ─────────────────────────────────────────────────────────────────────────
$backupFile = $targetFile . '.bak';
$suffix = 2;
while (file_exists($backupFile)) {
    $backupFile = $targetFile . '.bak' . $suffix;
    $suffix++;
}

if (!copy($targetFile, $backupFile)) {
    fwrite(STDERR, "❌ Could not create backup at $backupFile — aborting, nothing written.\n");
    exit(1);
}

if (file_put_contents($targetFile, $content) === false) {
    fwrite(STDERR, "❌ Failed to write to $targetFile\n");
    fwrite(STDERR, "   Backup is safe at: $backupFile\n");
    exit(1);
}

echo "✅ Patched: $targetFile\n";
echo "✅ Backup:  $backupFile\n";
echo "✅ Patches applied (" . count($patchesApplied) . "):\n";
foreach ($patchesApplied as $p) {
    echo "   - $p\n";
}
echo "\nDone. Delete this script when satisfied.\n";
