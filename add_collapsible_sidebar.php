<?php
/**
 * add_collapsible_sidebar.php
 *
 * Adds a collapse/expand toggle to the sidebar in the shared layout
 * (resources/views/layouts/app.blade.php), so it can shrink to an
 * icon-only rail (giving the content area more horizontal room) and
 * expand back to full width with labels.
 *
 * What this does:
 *  1. Backs up layouts/app.blade.php -> .bak
 *  2. Adds CSS for the .collapsed state (sidebar width shrinks to 64px,
 *     nav-link labels hidden, icons centered, toggle button icon flips
 *     direction)
 *  3. Adds a toggle button at the top of the sidebar
 *  4. Adds JS that:
 *     - Toggles the .collapsed class on click
 *     - Persists the choice in localStorage so it survives page
 *       navigation (plain vanilla JS / Blade context — not a React
 *       artifact, so localStorage is fine here)
 *     - Initializes Bootstrap tooltips on each nav-link, enabled only
 *       while collapsed (so hovering an icon shows the page name)
 *
 * Usage:
 *   1. Drop this file into your hr-recruitment project root
 *   2. Run: php add_collapsible_sidebar.php
 *   3. Delete this file afterward
 */

$projectRoot = __DIR__;
$layoutPath = $projectRoot . '/resources/views/layouts/app.blade.php';

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
ok("Backed up layouts/app.blade.php -> .bak");

$content = file_get_contents($layoutPath);

// --- Fix 1: add CSS for the collapsed state, right after .hr-sidebar .nav-link.active rule ---

$oldCss = <<<'CSS'
        .hr-sidebar .nav-link.active {
            background-color: var(--hr-primary-dark);
            color: #fff;
            border-left-color: var(--hr-accent);
        }
CSS;

$newCss = <<<'CSS'
        .hr-sidebar .nav-link.active {
            background-color: var(--hr-primary-dark);
            color: #fff;
            border-left-color: var(--hr-accent);
        }
        .hr-sidebar-toggle {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding: 0.5rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .hr-sidebar-toggle button {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            color: #c9d4d9;
            border: 1px solid rgba(255,255,255,0.2);
            background-color: transparent;
        }
        .hr-sidebar-toggle button:hover {
            background-color: rgba(255,255,255,0.08);
            color: #fff;
        }
        .hr-sidebar.collapsed {
            width: 64px;
        }
        .hr-sidebar.collapsed .hr-sidebar-toggle {
            justify-content: center;
        }
        .hr-sidebar.collapsed .hr-sidebar-toggle button i {
            transform: rotate(180deg);
        }
        .hr-sidebar.collapsed .nav-link {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.65rem 0;
        }
        .hr-sidebar.collapsed .nav-link i {
            margin-right: 0;
            width: auto;
            font-size: 1.1rem;
        }
        .hr-sidebar.collapsed .nav-link .nav-label {
            display: none;
        }
CSS;

if (!str_contains($content, $oldCss)) {
    fail("Could not find the expected .hr-sidebar .nav-link.active CSS rule. No changes written.");
}
$content = str_replace($oldCss, $newCss, $content);
ok("Added collapsed-state CSS");

// --- Fix 2: add the toggle button + wrap nav-link text in .nav-label spans ---

$oldNav = <<<'BLADE'
        <nav class="hr-sidebar d-flex flex-column">
            <div class="nav flex-column py-2">
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="bi bi-grid-1x2"></i> Dashboard
                </a>
                <a href="{{ route('job-postings.index') }}" class="nav-link {{ request()->routeIs('job-postings.*') ? 'active' : '' }}">
                    <i class="bi bi-briefcase"></i> Job postings
                </a>
                <a href="{{ route('applications.index') }}" class="nav-link {{ request()->routeIs('applications.*') ? 'active' : '' }}">
                    <i class="bi bi-person-lines-fill"></i> Applications
                </a>
                <a href="{{ route('interviews.index') }}" class="nav-link {{ request()->routeIs('interviews.*') ? 'active' : '' }}">
                    <i class="bi bi-calendar-event"></i> Scheduling
                </a>
                <a href="{{ route('assessments.index') }}" class="nav-link {{ request()->routeIs('assessments.*') ? 'active' : '' }}">
                    <i class="bi bi-clipboard-check"></i> Assessment &amp; ranking
                </a>
                <a href="{{ route('offers.index') }}" class="nav-link {{ request()->routeIs('offers.*') ? 'active' : '' }}">
                    <i class="bi bi-envelope-paper"></i> Offer management
                </a>
                <a href="{{ route('talent-pool.index') }}" class="nav-link {{ request()->routeIs('talent-pool.*') ? 'active' : '' }}">
                    <i class="bi bi-people"></i> Talent pool
                </a>
                <a href="{{ route('appointments.index') }}" class="nav-link {{ request()->routeIs('appointments.*') ? 'active' : '' }}">
                    <i class="bi bi-file-earmark-check"></i> Appointment &amp; onboarding
                </a>
            </div>
        </nav>
BLADE;

$newNav = <<<'BLADE'
        <nav class="hr-sidebar d-flex flex-column" id="hrSidebar">
            <div class="hr-sidebar-toggle">
                <button type="button" id="hrSidebarToggleBtn" aria-label="Collapse sidebar">
                    <i class="bi bi-chevron-double-left"></i>
                </button>
            </div>
            <div class="nav flex-column py-2">
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                    <i class="bi bi-grid-1x2"></i> <span class="nav-label">Dashboard</span>
                </a>
                <a href="{{ route('job-postings.index') }}" class="nav-link {{ request()->routeIs('job-postings.*') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Job postings">
                    <i class="bi bi-briefcase"></i> <span class="nav-label">Job postings</span>
                </a>
                <a href="{{ route('applications.index') }}" class="nav-link {{ request()->routeIs('applications.*') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Applications">
                    <i class="bi bi-person-lines-fill"></i> <span class="nav-label">Applications</span>
                </a>
                <a href="{{ route('interviews.index') }}" class="nav-link {{ request()->routeIs('interviews.*') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Scheduling">
                    <i class="bi bi-calendar-event"></i> <span class="nav-label">Scheduling</span>
                </a>
                <a href="{{ route('assessments.index') }}" class="nav-link {{ request()->routeIs('assessments.*') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Assessment & ranking">
                    <i class="bi bi-clipboard-check"></i> <span class="nav-label">Assessment &amp; ranking</span>
                </a>
                <a href="{{ route('offers.index') }}" class="nav-link {{ request()->routeIs('offers.*') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Offer management">
                    <i class="bi bi-envelope-paper"></i> <span class="nav-label">Offer management</span>
                </a>
                <a href="{{ route('talent-pool.index') }}" class="nav-link {{ request()->routeIs('talent-pool.*') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Talent pool">
                    <i class="bi bi-people"></i> <span class="nav-label">Talent pool</span>
                </a>
                <a href="{{ route('appointments.index') }}" class="nav-link {{ request()->routeIs('appointments.*') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Appointment & onboarding">
                    <i class="bi bi-file-earmark-check"></i> <span class="nav-label">Appointment &amp; onboarding</span>
                </a>
            </div>
        </nav>
BLADE;

if (!str_contains($content, $oldNav)) {
    fail("Could not find the expected sidebar <nav> block. CSS fix above WAS already written. "
        . "Please check structure or re-paste the file.");
}
$content = str_replace($oldNav, $newNav, $content);
ok("Added sidebar toggle button, wrapped nav labels, added tooltip attributes");

// --- Fix 3: add the JS, right before @stack('scripts') ---

$oldScriptsStack = "    <script src=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js\"></script>\n    @stack('scripts')";

$newScriptsStack = <<<'BLADE'
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            const sidebar = document.getElementById('hrSidebar');
            const toggleBtn = document.getElementById('hrSidebarToggleBtn');
            const STORAGE_KEY = 'hrSidebarCollapsed';

            let tooltipInstances = [];

            function initTooltips() {
                tooltipInstances.forEach(t => t.dispose());
                tooltipInstances = [];
                if (sidebar.classList.contains('collapsed')) {
                    const triggers = sidebar.querySelectorAll('[data-bs-toggle="tooltip"]');
                    triggers.forEach(el => {
                        tooltipInstances.push(new bootstrap.Tooltip(el));
                    });
                }
            }

            function applyState(collapsed) {
                sidebar.classList.toggle('collapsed', collapsed);
                toggleBtn.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
                initTooltips();
            }

            const stored = localStorage.getItem(STORAGE_KEY);
            applyState(stored === 'true');

            toggleBtn.addEventListener('click', function () {
                const collapsed = !sidebar.classList.contains('collapsed');
                localStorage.setItem(STORAGE_KEY, collapsed ? 'true' : 'false');
                applyState(collapsed);
            });
        })();
    </script>
    @stack('scripts')
BLADE;

if (!str_contains($content, $oldScriptsStack)) {
    fail("Could not find the expected Bootstrap JS + @stack('scripts') anchor. "
        . "Earlier fixes WERE already written. Please check structure or re-paste the file.");
}
$content = str_replace($oldScriptsStack, $newScriptsStack, $content);
ok("Added collapse/expand JS with localStorage persistence and tooltip handling");

if (file_put_contents($layoutPath, $content) === false) {
    fail("Could not write updated layouts/app.blade.php");
}
ok("Wrote updated layouts/app.blade.php");

echo "\n=========================================================\n";
echo "DONE. Sidebar can now collapse to icon-only.\n";
echo "=========================================================\n\n";

echo "This change is in the shared layout, so it applies to every page\n";
echo "automatically — no need to touch individual views.\n\n";

echo "Test:\n";
echo "  - Click the chevron button at the top of the sidebar -> it should\n";
echo "    shrink to icons only, content area gets wider.\n";
echo "  - Hover any icon while collapsed -> a tooltip with the page name\n";
echo "    should appear to the right.\n";
echo "  - Click the chevron again -> expands back to full width with labels.\n";
echo "  - Navigate to a different page -> the collapsed/expanded state\n";
echo "    should persist (stored in the browser via localStorage).\n\n";

echo "You can delete this script (add_collapsible_sidebar.php) now.\n";
