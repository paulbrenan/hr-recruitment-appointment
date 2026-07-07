<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'HR Recruitment') &mdash; HR System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/page-loader.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        :root {
            --hr-primary: #003087;
            --hr-primary-dark: #0a1a33;
            --hr-accent: #ffd700;
            --hr-bg: #f0f4fa;
            --hr-header-h: 56px;
        }
        html, body {
            background-color: var(--hr-bg);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .hr-shell {
            display: flex;
            min-height: 100vh;
        }
        .hr-sidebar {
            width: 240px;
            flex-shrink: 0;
            background-color: var(--hr-primary);
            color: #e8edf0;
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        .hr-sidebar-brand {
            height: var(--hr-header-h);
            flex-shrink: 0;
            display: flex;
            align-items: center;
            padding: 0 1.25rem;
            color: #fff;
            font-weight: 600;
            font-size: 1.05rem;
            border-bottom: 1px solid var(--hr-primary-dark);
        }
        .hr-sidebar.collapsed .hr-sidebar-brand {
            justify-content: center;
            padding: 0 0.5rem;
        }
        .hr-sidebar.collapsed .hr-sidebar-brand .brand-label {
            display: none;
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
        .hr-content-wrapper {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
        }
        .hr-header {
            height: var(--hr-header-h);
            flex-shrink: 0;
            background-color: var(--hr-primary);
            border-bottom: 1px solid var(--hr-primary-dark);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1rem;
            position: sticky;
            top: 0;
            z-index: 1030;
        }
        .hr-header-datetime {
            color: #c9d4d9;
            font-size: 0.85rem;
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

    <div class="hr-shell">
        <nav class="hr-sidebar" id="hrSidebar">
            <div class="hr-sidebar-brand" style="justify-content:center;">
                 <img src="/sdo-logo.png" alt="DepEd Cavite" style="height:38px;width:auto;filter:drop-shadow(0 1px 4px rgba(0,0,0,.3));">
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

        <div class="hr-content-wrapper">
            <header class="hr-header" style="position:relative;">
            <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn-icon" id="hrSidebarToggleBtn" aria-label="Toggle sidebar">
            <i class="bi bi-list"></i>
        </button>
        <a href="{{ route('dashboard') }}" class="btn-icon" aria-label="Go to dashboard">
            <i class="bi bi-house"></i>
        </a>
         </div>
         <span class="hr-header-datetime" id="hrHeaderDateTime"
             style="position:absolute;left:50%;transform:translateX(-50%);"></span>
         <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn-icon" id="hrActivityLogBtn" aria-label="Activity log" data-bs-toggle="modal" data-bs-target="#activityLogModal">
            <i class="bi bi-journal-text"></i>
        </button>
        <button type="button" class="btn-icon" id="hrFullscreenBtn" aria-label="Toggle fullscreen">
            <i class="bi bi-arrows-fullscreen"></i>
        </button>
    </div>
        </header>

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
    </div>

    <!-- Activity Log Book Modal -->
    <div class="modal fade" id="activityLogModal" tabindex="-1" aria-labelledby="activityLogModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="activityLogModalLabel"><i class="bi bi-journal-text me-2"></i>Activity Log Book</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <label for="activityLogDateFilter" class="form-label small text-muted mb-0">Filter by date:</label>
                        <input type="date" id="activityLogDateFilter" class="form-control form-control-sm" style="max-width: 180px;">
                        <button type="button" id="activityLogDateClear" class="btn btn-sm btn-outline-secondary d-none">Clear</button>
                    </div>
                    <div id="activityLogLoading" class="text-center text-muted py-4">
                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                        Loading activity...
                    </div>
                    <div class="table-responsive d-none" id="activityLogTableWrap">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>When</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody id="activityLogTableBody"></tbody>
                        </table>
                    </div>
                    <div id="activityLogEmpty" class="text-center text-muted py-4 d-none">
                        No activity recorded yet.
                    </div>
                    <div id="activityLogError" class="text-center text-danger py-4 d-none">
                        Could not load activity log. Please try again.
                    </div>
                </div>
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

        (function () {
            const el = document.getElementById('hrHeaderDateTime');

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

        (function () {
            const modal = document.getElementById('activityLogModal');
            const loading = document.getElementById('activityLogLoading');
            const tableWrap = document.getElementById('activityLogTableWrap');
            const tableBody = document.getElementById('activityLogTableBody');
            const emptyState = document.getElementById('activityLogEmpty');
            const errorState = document.getElementById('activityLogError');

            function actionBadge(action) {
                const map = { created: 'success', updated: 'primary', deleted: 'danger' };
                const cls = map[action] || 'secondary';
                return '<span class="badge bg-' + cls + '-subtle text-' + cls + ' border border-' + cls + '-subtle text-capitalize">' + action + '</span>';
            }

            function render(logs) {
                loading.classList.add('d-none');
                if (!logs.length) {
                    emptyState.classList.remove('d-none');
                    return;
                }
                tableBody.innerHTML = logs.map(function (log) {
                    return '<tr>' +
                        '<td class="text-nowrap small">' + log.created_at + '</td>' +
                        '<td class="small">' + log.user + '</td>' +
                        '<td>' + actionBadge(log.action) + '</td>' +
                        '<td class="small">' + (log.subject_label || log.description || '') + '</td>' +
                        '</tr>';
                }).join('');
                tableWrap.classList.remove('d-none');
            }

            modal.addEventListener('show.bs.modal', function () {
                if (window.__activityLogUseFilter) { return; }
                loading.classList.remove('d-none');
                tableWrap.classList.add('d-none');
                emptyState.classList.add('d-none');
                errorState.classList.add('d-none');
                tableBody.innerHTML = '';

                fetch('{{ route("activity-logs.index") }}', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (res) {
                        if (!res.ok) throw new Error('bad response');
                        return res.json();
                    })
                    .then(function (data) {
                        render(data.logs || []);
                    })
                    .catch(function () {
                        loading.classList.add('d-none');
                        errorState.classList.remove('d-none');
                    });
            });
        })();
    </script>
        <script>
        (function () {
            var modal = document.getElementById('activityLogModal');
            var loading = document.getElementById('activityLogLoading');
            var tableWrap = document.getElementById('activityLogTableWrap');
            var tableBody = document.getElementById('activityLogTableBody');
            var emptyState = document.getElementById('activityLogEmpty');
            var errorState = document.getElementById('activityLogError');
            var dateFilter = document.getElementById('activityLogDateFilter');
            var dateClear = document.getElementById('activityLogDateClear');

            if (!modal || !dateFilter || !dateClear) {
                return;
            }

            window.__activityLogUseFilter = true;

            function actionBadgeFiltered(action) {
                var map = { created: 'success', updated: 'primary', deleted: 'danger' };
                var cls = map[action] || 'secondary';
                return '<span class="badge bg-' + cls + '-subtle text-' + cls + ' border border-' + cls + '-subtle text-capitalize">' + action + '</span>';
            }

            function renderFiltered(logs) {
                loading.classList.add('d-none');
                emptyState.classList.add('d-none');
                errorState.classList.add('d-none');
                tableBody.innerHTML = '';
                if (!logs.length) {
                    emptyState.classList.remove('d-none');
                    tableWrap.classList.add('d-none');
                    return;
                }
                tableBody.innerHTML = logs.map(function (log) {
                    return '<tr>' +
                        '<td class="text-nowrap small">' + log.created_at + '</td>' +
                        '<td class="small">' + log.user + '</td>' +
                        '<td>' + actionBadgeFiltered(log.action) + '</td>' +
                        '<td class="small">' + (log.subject_label || log.description || '') + '</td>' +
                        '</tr>';
                }).join('');
                tableWrap.classList.remove('d-none');
            }

            function loadFiltered(date) {
                loading.classList.remove('d-none');
                tableWrap.classList.add('d-none');
                emptyState.classList.add('d-none');
                errorState.classList.add('d-none');
                tableBody.innerHTML = '';
                var url = '{{ route("activity-logs.index") }}';
                if (date) {
                    url += '?date=' + encodeURIComponent(date);
                }
                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (res) {
                        if (!res.ok) throw new Error('bad response');
                        return res.json();
                    })
                    .then(function (data) {
                        renderFiltered(data.logs || []);
                    })
                    .catch(function () {
                        loading.classList.add('d-none');
                        errorState.classList.remove('d-none');
                    });
            }

            function getTodayStr() {
                var today = new Date();
                return today.getFullYear() + '-' +
                    String(today.getMonth() + 1).padStart(2, '0') + '-' +
                    String(today.getDate()).padStart(2, '0');
            }

            dateFilter.addEventListener('change', function () {
                if (dateFilter.value) {
                    dateClear.classList.remove('d-none');
                    loadFiltered(dateFilter.value);
                } else {
                    dateFilter.value = getTodayStr();
                    dateClear.classList.add('d-none');
                    loadFiltered(dateFilter.value);
                }
            });

            dateClear.addEventListener('click', function () {
                dateFilter.value = getTodayStr();
                dateClear.classList.add('d-none');
                loadFiltered(dateFilter.value);
            });

            modal.addEventListener('show.bs.modal', function () {
                dateFilter.value = getTodayStr();
                dateClear.classList.add('d-none');
                loadFiltered(dateFilter.value);
            });
        })();
    </script>
    @stack('scripts')
    <script src="{{ asset('js/page-loader.js') }}"></script>
</body>
</html>