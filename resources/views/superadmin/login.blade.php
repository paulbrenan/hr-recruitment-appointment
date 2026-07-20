<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Superadmin Login | DepEd Cavite HR</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/deped-theme.css') }}">
</head>
<body class="deped-watermark">

    <div class="form-card" style="max-width: 440px;">

        <div class="deped-header deped-header-center">
            <img src="{{ asset('images/sdo-logo.png') }}" alt="DepEd Cavite" class="deped-logo">
            <div class="deped-header-text">
                <h1>Superadmin Login</h1>
                <p class="sub">HR Recruitment &amp; Appointment System<br>System Administration</p>
            </div>
        </div>

        <div class="p-4">

            @if ($errors->any())
                <div class="alert alert-danger py-2 small mb-3">
                    <i class="bi bi-exclamation-circle me-1"></i>{{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('superadmin.login.attempt') }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <div class="form-check mb-3">
                    <input type="checkbox" name="remember" class="form-check-input" id="remember">
                    <label class="form-check-label small" for="remember">Remember me</label>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="bi bi-shield-lock me-1"></i> Log in
                </button>
            </form>

        </div>

        <div class="form-footer">
            DepEd Schools Division Office &mdash; Cavite Province
        </div>

    </div>

</body>
</html>