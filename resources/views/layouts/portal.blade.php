<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Applicant Portal') — HR System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        :root {
            --hr-primary: #003087;
            --hr-primary-dark: #0a1a33;
            --hr-accent: #ffd700;
            --hr-bg: #f0f4fa;
        }
        body { background: var(--hr-bg); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .portal-navbar {
            background: var(--hr-primary);
            color: #fff;
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .portal-navbar a { color: rgba(255,255,255,0.85); text-decoration: none; font-size: 0.875rem; }
        .portal-navbar a:hover { color: #fff; }
        .portal-sidebar {
            width: 220px;
            min-height: calc(100vh - 52px);
            background: #fff;
            border-right: 1px solid #e3e8ec;
            padding: 1.25rem 0;
            flex-shrink: 0;
        }
        .portal-sidebar .nav-link {
            color: #4a5568;
            font-size: 0.875rem;
            padding: 0.5rem 1.25rem;
            border-radius: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .portal-sidebar .nav-link:hover,
        .portal-sidebar .nav-link.active {
            background: #f0f4f6;
            color: var(--hr-primary);
            font-weight: 600;
        }
        .portal-main { flex: 1; padding: 1.75rem; max-width: 900px; }
        .btn-hr-primary { background: var(--hr-primary); color: #fff; }
        .btn-hr-primary:hover { background: var(--hr-primary-dark); color: #fff; }
    </style>
    @stack('styles')
</head>
<body>
    {{-- Top navbar --}}
    <div class="portal-navbar">
        <span class="fw-semibold"><i class="bi bi-people-fill me-2"></i>Applicant Portal</span>
        <div class="d-flex align-items-center gap-3">
            <span style="font-size:0.8rem;opacity:.75;">{{ auth()->guard('candidate')->user()->full_name }}</span>
            <form action="{{ route('portal.logout') }}" method="POST" class="m-0">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-light py-0 px-2" style="font-size:0.8rem;">Log out</button>
            </form>
        </div>
    </div>

    <div class="d-flex">
        {{-- Sidebar --}}
        <nav class="portal-sidebar d-none d-md-block">
            <a href="{{ route('portal.dashboard') }}"
               class="nav-link {{ request()->routeIs('portal.dashboard') ? 'active' : '' }}">
                <i class="bi bi-house"></i> Dashboard
            </a>
            <a href="{{ route('portal.jobs.index') }}"
               class="nav-link {{ request()->routeIs('portal.jobs.*') ? 'active' : '' }}">
                <i class="bi bi-briefcase"></i> Open Positions
            </a>
            <a href="{{ route('portal.my-applications') }}"
               class="nav-link {{ request()->routeIs('portal.my-applications') ? 'active' : '' }}">
                <i class="bi bi-file-earmark-text"></i> My Applications
            </a>
        </nav>

        {{-- Main content --}}
        <main class="portal-main">
            @if ($errors->any())
                <div class="alert alert-danger small mb-3">
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @yield('content')
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>