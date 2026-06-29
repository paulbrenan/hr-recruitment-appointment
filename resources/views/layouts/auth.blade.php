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
        }
        html, body {
            background-color: var(--hr-bg);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh;
        }
        .auth-header {
            background-color: var(--hr-primary);
            color: #fff;
            padding: 0.9rem 1.5rem;
            font-weight: 600;
            font-size: 1.05rem;
        }
        .auth-wrapper {
            min-height: calc(100vh - 56px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .auth-card {
            width: 100%;
            max-width: 440px;
            border: 1px solid #e2e6e8;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        }
        .btn-hr-primary {
            background-color: var(--hr-primary);
            color: #fff;
        }
        .btn-hr-primary:hover {
            background-color: var(--hr-primary-dark);
            color: #fff;
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="auth-header">
        <i class="bi bi-people-fill me-2"></i>@yield('brand', 'HR Recruitment')
    </div>
    <div class="auth-wrapper">
        <div class="card auth-card">
            <div class="card-body p-4">
                @yield('content')
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>