<?php
/**
 * center_auth_header_clock.php
 *
 * Centers the date/time clock in the login page's header bar
 * (layouts/auth.blade.php), which was previously right-aligned next to
 * the brand via justify-content:space-between.
 *
 * Same technique already used for the public landing page's topnav
 * clock: since the header only has two real children (brand, clock),
 * simply centering the clock alone would skew it off-true-center
 * relative to the brand's width. Adds an invisible spacer matching the
 * brand's approximate width so the clock sits genuinely centered
 * between two equal-width flanks.
 *
 * Usage:
 *   1. Run fix_auth_layout_consistency.php FIRST if you haven't already
 *      (this script expects the clock element it added)
 *   2. Drop this file into your hr-recruitment project root
 *   3. Run: php center_auth_header_clock.php
 *   4. Delete this file afterward
 */

$projectRoot = __DIR__;
$layoutPath = $projectRoot . '/resources/views/layouts/auth.blade.php';

function fail(string $msg): void
{
    echo "\n[FAILED] $msg\n";
    exit(1);
}

function ok(string $msg): void
{
    echo "[OK] $msg\n";
}

if (!file_exists($layoutPath)) {
    fail("Could not find $layoutPath — run this script from the project root.");
}

$content = file_get_contents($layoutPath);

if (!str_contains($content, 'id="authHeaderDateTime"')) {
    fail("Could not find the auth header clock element (id=\"authHeaderDateTime\"). "
        . "Run fix_auth_layout_consistency.php first to add the clock before centering it.");
}

$backupPath = $layoutPath . '.bak2';
if (!copy($layoutPath, $backupPath)) {
    fail("Could not create backup at $backupPath");
}
ok("Backed up layouts/auth.blade.php -> .bak2");

if (str_contains($content, 'auth-header-spacer')) {
    ok("Clock is already centered (spacer present) — skipping.");
    echo "\nNo changes needed. You can delete this script now.\n";
    exit(0);
}

// ============================================================
// PART 1: CSS — add the spacer rule
// ============================================================

$oldCss = '@media(max-width:560px){ .auth-header-datetime { display: none; } }';
$newCss = <<<'CSS'
@media(max-width:560px){ .auth-header-datetime { display: none; } }
        .auth-header-spacer { width: 0; flex-shrink: 0; }
        @media(min-width:680px){ .auth-header-spacer { width: 180px; } }
CSS;

if (!str_contains($content, $oldCss)) {
    fail("Could not find the expected .auth-header-datetime mobile CSS rule to anchor the spacer rule after. "
        . "The file may differ from what fix_auth_layout_consistency.php produced. No changes written — "
        . "please re-paste the current file.");
}
$content = str_replace($oldCss, $newCss, $content);
ok("Added spacer CSS (matches the brand's approximate width on wider screens, for true centering)");

// ============================================================
// PART 2: HTML — add the spacer element after the clock
// ============================================================

$oldHtml = <<<'BLADE'
    <div class="auth-header">
        <span><i class="bi bi-people-fill me-2"></i>@yield('brand', 'HR Recruitment')</span>
        <span class="auth-header-datetime" id="authHeaderDateTime"></span>
    </div>
BLADE;

$newHtml = <<<'BLADE'
    <div class="auth-header">
        <span><i class="bi bi-people-fill me-2"></i>@yield('brand', 'HR Recruitment')</span>
        <span class="auth-header-datetime" id="authHeaderDateTime"></span>
        <span class="auth-header-spacer"></span>
    </div>
BLADE;

if (!str_contains($content, $oldHtml)) {
    fail("Could not find the expected .auth-header HTML block. "
        . "CSS change above WAS already written. Please check structure or re-paste the file.");
}
$content = str_replace($oldHtml, $newHtml, $content);
ok("Added spacer element so the clock sits centered between brand and spacer");

if (file_put_contents($layoutPath, $content) === false) {
    fail("Could not write updated layouts/auth.blade.php");
}
ok("Wrote updated layouts/auth.blade.php");

echo "\n=========================================================\n";
echo "DONE. Clock is now centered in the login page header.\n";
echo "=========================================================\n\n";

echo "Test: refresh /login on a normal-width browser window — the date/\n";
echo "time should now sit centered in the header bar, not pushed to the\n";
echo "right next to the brand.\n\n";

echo "You can delete this script (center_auth_header_clock.php) now.\n";
