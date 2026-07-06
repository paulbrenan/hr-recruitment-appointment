<?php
/**
 * fix_hero_spacing_and_address_wrap.php
 *
 * Two fixes to welcome.blade.php:
 *
 *  1. Hero section was vertically centering its content via
 *     justify-content:center within a flex:1 container, which on a
 *     typical viewport height pushed the tracker box's bottom edge
 *     below the fold. Switched to flex-start with tighter top padding
 *     so the hero content starts closer to the nav and fits within a
 *     normal viewport without scrolling.
 *
 *  2. The Address placeholder had a manually hardcoded <br> ("...Brgy.
 *     Luciano,<br>Trece Martires City, Cavite") which, combined with
 *     the container's actual width, caused the text to wrap TWICE —
 *     once naturally (before reaching the <br>) and once at the forced
 *     break — producing an awkward 3-line result instead of a clean
 *     2-line one. Removed the manual <br> so the browser wraps the
 *     address naturally at whatever point actually fits the container.
 *
 * Usage:
 *   1. Drop this file into your hr-recruitment project root
 *   2. Run: php fix_hero_spacing_and_address_wrap.php
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

$backupPath = $viewPath . '.bak2';
if (!copy($viewPath, $backupPath)) {
    fail("Could not create backup at $backupPath");
}
ok("Backed up welcome.blade.php -> .bak2");

$content = file_get_contents($viewPath);

// ============================================================
// FIX 1: Hero vertical spacing / viewport fit
// ============================================================

$oldHero = '.hero { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:60px 24px 40px; text-align:center; position:relative; overflow:hidden; }';
$newHero = '.hero { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:flex-start; padding:32px 24px 32px; text-align:center; position:relative; overflow:hidden; }';

if (str_contains($content, $newHero)) {
    ok("Hero spacing already fixed — skipping.");
} else {
    if (!str_contains($content, $oldHero)) {
        fail("Could not find the expected .hero CSS rule. The file may differ from what was confirmed this session. "
            . "No changes written — please re-paste the current file.");
    }
    $content = str_replace($oldHero, $newHero, $content);
    ok("Adjusted hero section to start closer to the nav (flex-start, reduced top padding) instead of centering vertically");
}

// Also tighten the hero-sub bottom margin slightly, since less vertical
// space is now being given to push content down — keeps proportions
// looking right rather than just cramped.
$oldSub = '.hero-sub { font-size:1rem; color:var(--muted); margin-bottom:40px; font-weight:500; position:relative; z-index:1; }';
$newSub = '.hero-sub { font-size:1rem; color:var(--muted); margin-bottom:28px; font-weight:500; position:relative; z-index:1; }';

if (str_contains($content, $oldSub)) {
    $content = str_replace($oldSub, $newSub, $content);
    ok("Reduced hero-sub bottom margin slightly to match the tighter overall spacing");
} else {
    ok("hero-sub margin already adjusted or differs — skipping that small tweak.");
}

// ============================================================
// FIX 2: Address line wrap
// ============================================================

$oldAddress = '<div class="contact-value">Cavite Capitol Compound, Brgy. Luciano,<br>Trece Martires City, Cavite</div>';
$newAddress = '<div class="contact-value">Cavite Capitol Compound, Brgy. Luciano, Trece Martires City, Cavite</div>';

if (str_contains($content, $newAddress)) {
    ok("Address line already fixed — skipping.");
} else {
    if (!str_contains($content, $oldAddress)) {
        fail("Could not find the expected address placeholder line. "
            . "Hero fix above WAS already written. Please check structure or re-paste the file.");
    }
    $content = str_replace($oldAddress, $newAddress, $content);
    ok("Removed the manual <br> from the address placeholder, letting it wrap naturally");
}

if (file_put_contents($viewPath, $content) === false) {
    fail("Could not write updated welcome.blade.php");
}
ok("Wrote updated welcome.blade.php");

echo "\n=========================================================\n";
echo "DONE.\n";
echo "=========================================================\n\n";

echo "Test: refresh the public landing page (/) at a normal browser window\n";
echo "size — the tracker box should now fit comfortably within the\n";
echo "viewport without needing to scroll. Check the Address card in the\n";
echo "new Contact & Location section — it should now wrap cleanly into 2\n";
echo "lines at a natural break point instead of 3 awkward ones.\n\n";

echo "Note: the address still wraps wherever the browser decides is\n";
echo "natural for the container width — if you want a SPECIFIC break\n";
echo "point once you swap in the real address, you can add a <br> at\n";
echo "that exact spot once you know the final text and container width\n";
echo "you're testing against.\n\n";

echo "You can delete this script (fix_hero_spacing_and_address_wrap.php) now.\n";
