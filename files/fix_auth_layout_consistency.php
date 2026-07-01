<?php
/**
 * fix_auth_layout_consistency.php
 *
 * Brings resources/views/layouts/auth.blade.php (the login page's
 * layout) in line with the rest of the system:
 *
 *  1. Color palette was using DIFFERENT CSS variable values than both
 *     the authenticated app layout (app.blade.php) and the public
 *     landing page (welcome.blade.php) — a blue-grey (#2f4858) instead
 *     of the established teal/green (#1a5f4f / #2fae57). Fixed to match
 *     app.blade.php's values exactly, since that's the layout used by
 *     every other page in the HR module.
 *
 *  2. No live clock — app.blade.php already has one (added in an
 *     earlier session), the public landing page just got one too, but
 *     this layout never got it. Added the same date/time pattern,
 *     adapted to this layout's simpler single-bar header (no
 *     sidebar/fullscreen/home button row to coexist with).
 *
 * Usage:
 *   1. Drop this file into your hr-recruitment project root
 *   2. Run: php fix_auth_layout_consistency.php
 *   3. Delete this file afterward
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

$backupPath = $layoutPath . '.bak';
if (!copy($layoutPath, $backupPath)) {
    fail("Could not create backup at $backupPath");
}
ok("Backed up layouts/auth.blade.php -> .bak");

$content = file_get_contents($layoutPath);

// ============================================================
// FIX 1: Color palette — match app.blade.php's established values
// ============================================================

$oldRoot = <<<'CSS'
        :root {
            --hr-primary: #2f4858;
            --hr-primary-dark: #233843;
            --hr-accent: #3f7d8c;
            --hr-bg: #f4f6f7;
        }
CSS;

$newRoot = <<<'CSS'
        :root {
            --hr-primary: #1a5f4f;
            --hr-primary-dark: #134539;
            --hr-accent: #2fae57;
            --hr-bg: #f4f6f7;
            --hr-header-h: 56px;
        }
CSS;

if (str_contains($content, $newRoot)) {
    ok("Color palette already matches — skipping.");
} else {
    if (!str_contains($content, $oldRoot)) {
        fail("Could not find the expected :root CSS block. The file may differ from what was confirmed this session. "
            . "No changes written — please re-paste the current file.");
    }
    $content = str_replace($oldRoot, $newRoot, $content);
    ok("Updated color palette to match app.blade.php (teal/green, not blue-grey)");
}

// Fix the .auth-wrapper height calc, which hardcodes 56px — now that
// --hr-header-h exists as a variable here too, use it for consistency,
// though the value itself doesn't change.
$oldWrapper = '.auth-wrapper {
            min-height: calc(100vh - 56px);';
$newWrapper = '.auth-wrapper {
            min-height: calc(100vh - var(--hr-header-h));';

if (str_contains($content, $oldWrapper)) {
    $content = str_replace($oldWrapper, $newWrapper, $content);
    ok("Updated auth-wrapper to reference the --hr-header-h variable");
}

// ============================================================
// FIX 2: Header height + clock support
// ============================================================

$oldHeaderCss = <<<'CSS'
        .auth-header {
            background-color: var(--hr-primary);
            color: #fff;
            padding: 0.9rem 1.5rem;
            font-weight: 600;
            font-size: 1.05rem;
        }
CSS;

$newHeaderCss = <<<'CSS'
        .auth-header {
            height: var(--hr-header-h);
            background-color: var(--hr-primary);
            color: #fff;
            padding: 0 1.5rem;
            font-weight: 600;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .auth-header-datetime {
            color: #c9d4d9;
            font-size: 0.85rem;
            font-weight: 500;
        }
        @media(max-width:560px){ .auth-header-datetime { display: none; } }
CSS;

if (str_contains($content, $newHeaderCss)) {
    ok("Header clock CSS already present — skipping.");
} else {
    if (!str_contains($content, $oldHeaderCss)) {
        fail("Could not find the expected .auth-header CSS block. "
            . "Color palette fix above WAS already written. Please check structure or re-paste the file.");
    }
    $content = str_replace($oldHeaderCss, $newHeaderCss, $content);
    ok("Updated header CSS for fixed height + flex layout to fit the clock, hidden on mobile");
}

// ============================================================
// FIX 3: Header HTML — add the clock element
// ============================================================

$oldHeaderHtml = <<<'BLADE'
    <div class="auth-header">
        <i class="bi bi-people-fill me-2"></i>@yield('brand', 'HR Recruitment')
    </div>
BLADE;

$newHeaderHtml = <<<'BLADE'
    <div class="auth-header">
        <span><i class="bi bi-people-fill me-2"></i>@yield('brand', 'HR Recruitment')</span>
        <span class="auth-header-datetime" id="authHeaderDateTime"></span>
    </div>
BLADE;

if (str_contains($content, 'id="authHeaderDateTime"')) {
    ok("Header clock HTML already present — skipping.");
} else {
    if (!str_contains($content, $oldHeaderHtml)) {
        fail("Could not find the expected .auth-header HTML block. "
            . "Earlier changes above WERE already written. Please check structure or re-paste the file.");
    }
    $content = str_replace($oldHeaderHtml, $newHeaderHtml, $content);
    ok("Added clock element to the header HTML");
}

// ============================================================
// FIX 4: Clock JavaScript
// ============================================================

$oldScriptTag = '    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack(\'scripts\')';

$newScriptTag = <<<'BLADE'
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            const el = document.getElementById('authHeaderDateTime');
            if (!el) return;

            function update() {
                const now = new Date();
                const datePart = now.toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                });
                const timePart = now.toLocaleTimeString('en-US', {
                    hour: 'numeric',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true,
                });
                el.textContent = datePart + ' at ' + timePart;
            }

            update();
            setInterval(update, 1000);
        })();
    </script>
    @stack('scripts')
BLADE;

if (str_contains($content, "getElementById('authHeaderDateTime')")) {
    ok("Clock JavaScript already present — skipping.");
} else {
    if (!str_contains($content, $oldScriptTag)) {
        fail("Could not find the expected Bootstrap <script> + @stack('scripts') anchor. "
            . "Earlier changes above WERE already written. Please check structure or re-paste the file.");
    }
    $content = str_replace($oldScriptTag, $newScriptTag, $content);
    ok("Added live clock JavaScript (same pattern already used in app.blade.php)");
}

if (file_put_contents($layoutPath, $content) === false) {
    fail("Could not write updated layouts/auth.blade.php");
}
ok("Wrote updated layouts/auth.blade.php");

echo "\n=========================================================\n";
echo "DONE. Login page now matches the teal/green palette + has a clock.\n";
echo "=========================================================\n\n";

echo "This layout is shared by login.blade.php and any other page using\n";
echo "@extends('layouts.auth') — all of them will pick up the new colors\n";
echo "and clock automatically.\n\n";

echo "Test: refresh /login — the header bar should now be the same dark\n";
echo "teal-green as the rest of the HR module (not the old blue-grey),\n";
echo "and a live-updating date/time should appear on the right side of\n";
echo "the header, hidden on narrow/mobile screens.\n\n";

echo "You can delete this script (fix_auth_layout_consistency.php) now.\n";
