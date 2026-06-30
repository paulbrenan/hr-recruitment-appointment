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
            --hr-primary: #1a5f4f;
            --hr-primary-dark: #134539;
            --hr-accent: #2fae57;
            --hr-bg: #f4f6f7;
            --hr-header-h: 56px;
        }
        html, body {
            background-color: var(--hr-bg);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh;
        }
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
        .auth-header-spacer { width: 0; flex-shrink: 0; }
        @media(min-width:680px){ .auth-header-spacer { width: 180px; } }
        .auth-wrapper {
            min-height: calc(100vh - var(--hr-header-h));
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
        <span><i class="bi bi-people-fill me-2"></i>@yield('brand', 'HR Recruitment')</span>
        <span class="auth-header-datetime" id="authHeaderDateTime"></span>
        <span class="auth-header-spacer"></span>
    </div>
    <div class="auth-wrapper">
        <div class="card auth-card">
            <div class="card-body p-4">
                @yield('content')
            </div>
        </div>
    </div>
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
</body>
</html>