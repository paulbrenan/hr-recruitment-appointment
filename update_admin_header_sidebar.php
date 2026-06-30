<?php
/**
 * update_admin_header_sidebar.php
 *
 * One-shot script: full-file replace of resources/views/layouts/app.blade.php.
 * Verifies the file matches the exact expected current content before writing
 * anything (full-file diff check, not a partial patch, since this touches the
 * header, sidebar, CSS, and JS all together).
 *
 * Changes:
 * - Header: hamburger icon button (left, next to title) becomes the ONLY
 *   sidebar toggle -- the old toggle button + its row inside the sidebar are
 *   removed entirely. Same collapse/expand CSS, localStorage persistence,
 *   and tooltip re-init logic carry over unchanged, just retargeted to the
 *   new header button.
 * - Header right side: home icon button (links to route('dashboard')) +
 *   fullscreen toggle icon button. The old back-arrow button is removed.
 * - No color changes -- same --hr-primary palette, just three consistent
 *   icon-button styles in the header instead of one back-button style.
 *
 * Usage: place this file in the project root (same folder as artisan) and run:
 *   php update_admin_header_sidebar.php
 * Then delete this script. No migration needed.
 *
 * Backs up the file before overwriting (.bak, .bak2, ... if needed).
 */

function die_loud($msg) {
    fwrite(STDERR, "\n[ABORTED] $msg\n\n");
    exit(1);
}

function backup_file($path) {
    if (!file_exists($path)) {
        die_loud("Expected file not found: $path");
    }
    $backupPath = $path . '.bak';
    $n = 1;
    while (file_exists($backupPath)) {
        $n++;
        $backupPath = $path . '.bak' . $n;
    }
    if (!copy($path, $backupPath)) {
        die_loud("Could not create backup at $backupPath");
    }
    echo "Backed up " . $path . " -> " . basename($backupPath) . "\n";
}

$root = __DIR__;
$layoutPath = $root . '/resources/views/layouts/app.blade.php';

$currentContent = file_get_contents($layoutPath);
if ($currentContent === false) {
    die_loud("Could not read $layoutPath");
}

$expectedCurrent = <<<'EXPECTED_EOF'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'HR Recruitment') &mdash; HR System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        :root {
            --hr-primary: #2f4858;
            --hr-primary-dark: #233843;
            --hr-accent: #3f7d8c;
            --hr-bg: #f4f6f7;
            --hr-header-h: 56px;
        }
        html, body {
            background-color: var(--hr-bg);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .hr-header {
            height: var(--hr-header-h);
            background-color: var(--hr-primary);
            border-bottom: 1px solid var(--hr-primary-dark);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1rem;
            position: sticky;
            top: 0;
            z-index: 1030;
        }
        .hr-header-title {
            color: #fff;
            font-weight: 600;
            font-size: 1.05rem;
        }
        .hr-header .btn-back {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            color: #e8edf0;
            border: 1px solid rgba(255,255,255,0.25);
            background-color: transparent;
        }
        .hr-header .btn-back:hover {
            background-color: rgba(255,255,255,0.1);
            color: #fff;
        }
        .hr-body {
            display: flex;
            min-height: calc(100vh - var(--hr-header-h));
        }
        .hr-sidebar {
            width: 240px;
            flex-shrink: 0;
            background-color: var(--hr-primary);
            color: #e8edf0;
        }
        .hr-sidebar .nav-link {
            color: #c9d4d9;
            padding: 0.65rem 1.25rem;
            font-size: 0.92rem;
            border-left: 3px solid transparent;
        }
        .hr-sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        .hr-sidebar .nav-link:hover {
            background-color: rgba(255,255,255,0.06);
            color: #fff;
        }
        .hr-sidebar .nav-link.active {
            background-color: var(--hr-primary-dark);
            color: #fff;
            border-left-color: var(--hr-accent);
        }
        .hr-sidebar-toggle {
            display: flex;
            align-items: center;
            padding: 0.65rem 1.25rem;
        }
        .hr-sidebar-toggle button {
            width: 20px;
            height: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border-radius: 4px;
            color: #c9d4d9;
            border: none;
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
            padding: 0.65rem 0;
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
        .hr-content {
            flex: 1;
            min-width: 0;
        }
        .hr-pagebar {
            background-color: #fff;
            border-bottom: 1px solid #e2e6e8;
            padding: 0.85rem 1.5rem;
        }
        .hr-main {
            padding: 1.5rem;
        }
        .badge-status {
            font-size: 0.75rem;
            font-weight: 500;
            padding: 0.35em 0.7em;
        }
        .card {
            border: 1px solid #e2e6e8;
            box-shadow: none;
        }
        .table thead th {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #6c7780;
            border-bottom-width: 1px;
            font-weight: 600;
        }
    </style>
    @stack('styles')
</head>
<body>

    <header class="hr-header">
        <span class="hr-header-title"><i class="bi bi-people-fill me-2"></i>HR Recruitment</span>
        <button type="button" class="btn-back" aria-label="Go back" onclick="history.back()">
            <i class="bi bi-arrow-left"></i>
        </button>
    </header>

    <div class="hr-body">
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
                <a href="{{ route('pipelines.index') }}" class="nav-link {{ request()->routeIs('pipelines.*') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Pipelines">
                    <i class="bi bi-diagram-3"></i> <span class="nav-label">Pipelines</span>
                </a>
                <a href="{{ route('appointments.index') }}" class="nav-link {{ request()->routeIs('appointments.*') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Appointment & onboarding">
                    <i class="bi bi-file-earmark-check"></i> <span class="nav-label">Appointment &amp; onboarding</span>
                </a>
            </div>
        </nav>

        <div class="hr-content">
            <div class="hr-pagebar d-flex justify-content-between align-items-center">
                <h5 class="mb-0">@yield('page-title', 'Dashboard')</h5>
                <div class="text-muted small">
                    <i class="bi bi-person-circle me-1"></i> HR Staff
                </div>
            </div>
            <div class="hr-main">
                @yield('content')
            </div>
        </div>
    </div>

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
</body>
</html>
EXPECTED_EOF;

if (trim($currentContent) !== trim($expectedCurrent)) {
    die_loud("resources/views/layouts/app.blade.php does not match the expected current content.\nIt may have drifted -- please re-paste it so this script can be updated.");
}

$newContent = <<<'NEW_LAYOUT_EOF'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'HR Recruitment') &mdash; HR System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        :root {
            --hr-primary: #2f4858;
            --hr-primary-dark: #233843;
            --hr-accent: #3f7d8c;
            --hr-bg: #f4f6f7;
            --hr-header-h: 56px;
        }
        html, body {
            background-color: var(--hr-bg);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .hr-header {
            height: var(--hr-header-h);
            background-color: var(--hr-primary);
            border-bottom: 1px solid var(--hr-primary-dark);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1rem;
            position: sticky;
            top: 0;
            z-index: 1030;
        }
        .hr-header-title {
            color: #fff;
            font-weight: 600;
            font-size: 1.05rem;
        }
        .hr-header .btn-icon {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            color: #e8edf0;
            border: 1px solid rgba(255,255,255,0.25);
            background-color: transparent;
            text-decoration: none;
        }
        .hr-header .btn-icon:hover {
            background-color: rgba(255,255,255,0.1);
            color: #fff;
        }
        .hr-body {
            display: flex;
            min-height: calc(100vh - var(--hr-header-h));
        }
        .hr-sidebar {
            width: 240px;
            flex-shrink: 0;
            background-color: var(--hr-primary);
            color: #e8edf0;
        }
        .hr-sidebar .nav-link {
            color: #c9d4d9;
            padding: 0.65rem 1.25rem;
            font-size: 0.92rem;
            border-left: 3px solid transparent;
        }
        .hr-sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        .hr-sidebar .nav-link:hover {
            background-color: rgba(255,255,255,0.06);
            color: #fff;
        }
        .hr-sidebar .nav-link.active {
            background-color: var(--hr-primary-dark);
            color: #fff;
            border-left-color: var(--hr-accent);
        }
        .hr-sidebar.collapsed {
            width: 64px;
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
        .hr-content {
            flex: 1;
            min-width: 0;
        }
        .hr-pagebar {
            background-color: #fff;
            border-bottom: 1px solid #e2e6e8;
            padding: 0.85rem 1.5rem;
        }
        .hr-main {
            padding: 1.5rem;
        }
        .badge-status {
            font-size: 0.75rem;
            font-weight: 500;
            padding: 0.35em 0.7em;
        }
        .card {
            border: 1px solid #e2e6e8;
            box-shadow: none;
        }
        .table thead th {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #6c7780;
            border-bottom-width: 1px;
            font-weight: 600;
        }
    </style>
    @stack('styles')
</head>
<body>

    <header class="hr-header">
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn-icon" id="hrSidebarToggleBtn" aria-label="Toggle sidebar">
                <i class="bi bi-list"></i>
            </button>
            <span class="hr-header-title"><i class="bi bi-people-fill me-2"></i>HR Recruitment</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('dashboard') }}" class="btn-icon" aria-label="Go to dashboard">
                <i class="bi bi-house"></i>
            </a>
            <button type="button" class="btn-icon" id="hrFullscreenBtn" aria-label="Toggle fullscreen">
                <i class="bi bi-arrows-fullscreen"></i>
            </button>
        </div>
    </header>

    <div class="hr-body">
        <nav class="hr-sidebar d-flex flex-column" id="hrSidebar">
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
                <a href="{{ route('pipelines.index') }}" class="nav-link {{ request()->routeIs('pipelines.*') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Pipelines">
                    <i class="bi bi-diagram-3"></i> <span class="nav-label">Pipelines</span>
                </a>
                <a href="{{ route('appointments.index') }}" class="nav-link {{ request()->routeIs('appointments.*') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Appointment & onboarding">
                    <i class="bi bi-file-earmark-check"></i> <span class="nav-label">Appointment &amp; onboarding</span>
                </a>
            </div>
        </nav>

        <div class="hr-content">
            <div class="hr-pagebar d-flex justify-content-between align-items-center">
                <h5 class="mb-0">@yield('page-title', 'Dashboard')</h5>
                <div class="text-muted small">
                    <i class="bi bi-person-circle me-1"></i> HR Staff
                </div>
            </div>
            <div class="hr-main">
                @yield('content')
            </div>
        </div>
    </div>

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

        (function () {
            const fullscreenBtn = document.getElementById('hrFullscreenBtn');
            const icon = fullscreenBtn.querySelector('i');

            function updateIcon() {
                icon.className = document.fullscreenElement ? 'bi bi-fullscreen-exit' : 'bi bi-arrows-fullscreen';
            }

            fullscreenBtn.addEventListener('click', function () {
                if (!document.fullscreenElement) {
                    document.documentElement.requestFullscreen().catch(function () {
                        // Fullscreen request can be denied/unsupported -- fail silently.
                    });
                } else {
                    document.exitFullscreen();
                }
            });

            document.addEventListener('fullscreenchange', updateIcon);
        })();
    </script>
    @stack('scripts')
</body>
</html>
NEW_LAYOUT_EOF;

backup_file($layoutPath);
if (file_put_contents($layoutPath, $newContent) === false) {
    die_loud("Could not write $layoutPath");
}
echo "Updated resources/views/layouts/app.blade.php\n";

echo "\nDone.\n";
echo "Next steps:\n";
echo "  1. Refresh any admin page -- confirm the hamburger icon (left of the title) toggles the sidebar.\n";
echo "  2. Confirm the home icon (right side) goes to /dashboard.\n";
echo "  3. Confirm the fullscreen icon toggles fullscreen and its icon swaps to the 'exit fullscreen' glyph while active.\n";
echo "  4. Confirm collapsed/expanded state still persists across page loads (localStorage).\n";
echo "  5. Delete this script once you've confirmed everything works.\n";
