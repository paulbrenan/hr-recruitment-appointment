<?php
/**
 * add_topnav_clock.php
 *
 * Adds a live-updating date/time display to the center of the public
 * landing page's top nav (welcome.blade.php), e.g. "Tuesday, June 30,
 * 2026 — 2:45:31 PM", updating every second.
 *
 * Since .topnav previously only had two children (brand on the left;
 * Admin Login button on the right, now removed), centering a new
 * element with justify-content:space-between alone would just push it
 * next to the brand, not truly centered. This adds an empty spacer div
 * matching the brand's width on larger screens so the clock sits
 * genuinely centered, and hides the clock entirely on narrow/mobile
 * screens (where the existing topnav is already tight on space) rather
 * than letting it wrap awkwardly.
 *
 * Usage:
 *   1. Drop this file into your hr-recruitment project root
 *   2. Run: php add_topnav_clock.php
 *   3. Delete this file afterward
 */

$projectRoot = __DIR__;

function fail(string $msg): void
{
    echo "\n[FAILED] $msg\n";
    exit(1);
}

function ok(string $msg): void
{
    echo "[OK] $msg\n";
}

$candidates = [
    $projectRoot . '/resources/views/welcome.blade.php',
    $projectRoot . '/resources/views/portal/welcome.blade.php',
    $projectRoot . '/resources/views/track/welcome.blade.php',
];

$viewPath = null;
foreach ($candidates as $candidate) {
    if (file_exists($candidate)) {
        $viewPath = $candidate;
        break;
    }
}

if ($viewPath === null) {
    fail("Could not find welcome.blade.php in any of the expected locations:\n  - "
        . implode("\n  - ", $candidates)
        . "\n\nPlease edit \$candidates at the top of this script to add the correct path.");
}

ok("Found view at: $viewPath");

$backupPath = $viewPath . '.bak3';
if (!copy($viewPath, $backupPath)) {
    fail("Could not create backup at $backupPath");
}
ok("Backed up welcome.blade.php -> .bak3");

$content = file_get_contents($viewPath);

if (str_contains($content, 'id="topnavClock"')) {
    ok("Topnav clock already present — skipping entirely.");
    echo "\nNo changes needed. You can delete this script now.\n";
    exit(0);
}

// ============================================================
// PART 1: CSS for the clock + spacer
// ============================================================

$cssAnchor = '.btn-admin:hover { background:var(--dark); color:#fff; }';

$clockCss = <<<'CSS'
.btn-admin:hover { background:var(--dark); color:#fff; }
  .topnav-clock { display:flex; flex-direction:column; align-items:center; line-height:1.2; }
  .topnav-clock .clock-date { font-size:.78rem; font-weight:600; color:var(--muted); }
  .topnav-clock .clock-time { font-size:.92rem; font-weight:800; color:var(--dark); letter-spacing:.02em; font-variant-numeric:tabular-nums; }
  .topnav-spacer { width:0; flex-shrink:0; }
  @media(min-width:680px){ .topnav-spacer { width:172px; } }
  @media(max-width:560px){ .topnav-clock { display:none; } }
CSS;

if (!str_contains($content, $cssAnchor)) {
    fail("Could not find the .btn-admin:hover CSS rule to anchor the new clock CSS after. "
        . "The file may differ from what was confirmed this session. No changes written.");
}
$content = str_replace($cssAnchor, $clockCss, $content);
ok("Added clock CSS (centered date/time block + spacer for true centering on wider screens)");

// ============================================================
// PART 2: HTML — insert the clock + spacer into .topnav
// ============================================================

$oldNav = <<<'BLADE'
<nav class="topnav">
  <a class="topnav-brand" href="/">
    <div class="topnav-logo">D</div>
    <div class="topnav-text">
      <div class="org">DepEd Cavite</div>
      <div class="sys">Online Recruitment</div>
    </div>
  </a>
</nav>
BLADE;

$newNav = <<<'BLADE'
<nav class="topnav">
  <a class="topnav-brand" href="/">
    <div class="topnav-logo">D</div>
    <div class="topnav-text">
      <div class="org">DepEd Cavite</div>
      <div class="sys">Online Recruitment</div>
    </div>
  </a>
  <div class="topnav-clock" id="topnavClock">
    <div class="clock-date" id="topnavClockDate"></div>
    <div class="clock-time" id="topnavClockTime"></div>
  </div>
  <div class="topnav-spacer"></div>
</nav>
BLADE;

if (!str_contains($content, $oldNav)) {
    fail("Could not find the expected <nav class=\"topnav\"> markup (post Admin-Login-removal version). "
        . "CSS change above WAS already written. Please check structure or re-paste the file.");
}
$content = str_replace($oldNav, $newNav, $content);
ok("Inserted clock markup into the topnav, with a matching spacer for true centering");

// ============================================================
// PART 3: JS — live-updating clock
// ============================================================

$scriptAnchor = "<script>\nconst CSRF = document.querySelector('meta[name=\"csrf-token\"]').content;";

$clockScript = <<<'BLADE'
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

// ── Live header clock ──
function updateTopnavClock() {
  const now = new Date();
  const dateEl = document.getElementById('topnavClockDate');
  const timeEl = document.getElementById('topnavClockTime');
  if (!dateEl || !timeEl) return;

  dateEl.textContent = now.toLocaleDateString('en-US', {
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
  });
  timeEl.textContent = now.toLocaleTimeString('en-US', {
    hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true
  });
}
updateTopnavClock();
setInterval(updateTopnavClock, 1000);
BLADE;

if (!str_contains($content, $scriptAnchor)) {
    fail("Could not find the expected <script> opening / CSRF const line to anchor the clock JS after. "
        . "Earlier changes above WERE already written. Please check structure or re-paste the file.");
}
$content = str_replace($scriptAnchor, $clockScript, $content);
ok("Added live-updating clock JavaScript (updates every second)");

if (file_put_contents($viewPath, $content) === false) {
    fail("Could not write updated welcome.blade.php");
}
ok("Wrote updated welcome.blade.php");

echo "\n=========================================================\n";
echo "DONE. Live date/time now shows in the center of the top nav.\n";
echo "=========================================================\n\n";

echo "Format: \"Tuesday, June 30, 2026\" on one line, \"2:45:31 PM\" below it,\n";
echo "updating every second. Hidden on narrow/mobile screens (under 560px)\n";
echo "to keep the nav from getting cramped there.\n\n";

echo "Test: refresh the public landing page (/) — the clock should appear\n";
echo "centered in the top nav and tick forward every second without a\n";
echo "page refresh.\n\n";

echo "You can delete this script (add_topnav_clock.php) now.\n";
