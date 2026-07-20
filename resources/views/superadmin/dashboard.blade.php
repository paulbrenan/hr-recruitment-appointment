<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Superadmin Dashboard | DepEd Cavite HR</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/deped-theme.css') }}">

    <style>
        .sa-topbar {
            background: linear-gradient(120deg, var(--teal) 0%, var(--teal-dark) 100%);
            color: #fff;
            padding: 18px 36px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 4px solid var(--gold);
            position: relative;
            z-index: 1;
        }
        .sa-topbar-title {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .sa-topbar-title img {
            width: 44px;
            height: 44px;
            background: #fff;
            border-radius: 50%;
            padding: 3px;
        }
        .sa-topbar-title h1 {
            font-size: 1.1rem;
            font-weight: 800;
            margin: 0;
        }
        .sa-topbar-title p {
            font-size: .75rem;
            opacity: .85;
            margin: 0;
        }
        .sa-logout-btn {
            background: rgba(255,255,255,0.12);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.35);
            font-size: .8rem;
            font-weight: 600;
            padding: 8px 18px;
            border-radius: 8px;
            transition: .15s;
        }
        .sa-logout-btn:hover {
            background: rgba(255,255,255,0.22);
        }

        .sa-page-wrap {
            max-width: 980px;
            margin: 40px auto 60px;
            position: relative;
            z-index: 1;
        }
        .sa-card {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,.10);
        }
        .sa-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 22px 28px;
            border-bottom: 1px solid #eef0f2;
        }
        .sa-card-header h2 {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--teal);
            margin: 0;
        }
        .sa-card-header .sub {
            font-size: .8rem;
            color: #888;
            margin: 2px 0 0;
        }
        .sa-add-btn {
            background: var(--teal);
            color: #fff;
            font-weight: 700;
            font-size: .85rem;
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: .2s;
        }
        .sa-add-btn:hover {
            background: var(--teal-dark);
            color: #fff;
        }

        table.sa-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .9rem;
        }
        table.sa-table thead th {
            text-align: left;
            padding: 12px 28px;
            background: var(--teal-light);
            color: var(--teal);
            font-weight: 700;
            font-size: .8rem;
            text-transform: uppercase;
            letter-spacing: .03em;
        }
        table.sa-table tbody td {
            padding: 14px 28px;
            border-top: 1px solid #f0f2f5;
            vertical-align: middle;
            color: #333;
        }
        table.sa-table tbody tr {
            transition: background-color .15s ease;
        }
        table.sa-table tbody tr:hover {
            background-color: rgba(0, 48, 135, 0.03);
        }
        .sa-delete-btn {
            border: 1px solid #ce1126;
            color: #ce1126;
            background: none;
            font-size: .8rem;
            font-weight: 600;
            padding: 5px 14px;
            border-radius: 6px;
            transition: .15s;
        }
        .sa-delete-btn:hover {
            background: #ce1126;
            color: #fff;
        }
        .sa-empty {
            text-align: center;
            padding: 48px 0;
            color: #888;
            font-size: .9rem;
        }

        /* ── Add Account modal ─────────────────────────────────────────── */
        .sa-modal .modal-content {
            border: none;
            border-radius: 12px;
            overflow: hidden;
        }
        .sa-modal .modal-header {
            border-bottom: 2px solid var(--teal-light);
            padding: 20px 26px 16px;
        }
        .sa-modal .modal-header h2 {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--teal);
            margin: 0;
        }
        .sa-modal .modal-header p {
            font-size: .78rem;
            color: #888;
            margin: 3px 0 0;
        }
        .sa-modal .modal-body {
            padding: 22px 26px;
        }
        .sa-modal label {
            font-size: .82rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 6px;
        }
        .sa-modal .form-control {
            border-radius: 8px;
            border: 1px solid #dfe3e7;
            padding: 10px 14px;
            font-size: .9rem;
        }
        .sa-modal .form-control:focus {
            border-color: var(--teal-mid);
            box-shadow: 0 0 0 0.2rem rgba(0, 48, 135, 0.12);
        }
        .sa-modal .btn-submit {
            width: 100%;
        }
    </style>
</head>
<body class="deped-watermark">

    <div class="sa-topbar">
        <div class="sa-topbar-title">
            <img src="{{ asset('images/sdo-logo.png') }}" alt="DepEd Cavite">
            <div>
                <h1>Superadmin Dashboard</h1>
                <p>HR Recruitment &amp; Appointment System</p>
            </div>
        </div>
        <form method="POST" action="{{ route('superadmin.logout') }}">
            @csrf
            <button class="sa-logout-btn"><i class="bi bi-box-arrow-right me-1"></i>Logout</button>
        </form>
    </div>

    <div class="sa-page-wrap">

        <div class="sa-card">
            <div class="sa-card-header">
                <div>
                    <h2>HR / User Accounts</h2>
                    <p class="sub">Manage accounts for HR staff using the recruitment system</p>
                </div>
                <button type="button" class="sa-add-btn" data-bs-toggle="modal" data-bs-target="#addAccountModal">
                    <i class="bi bi-plus-lg"></i> Add Account
                </button>
            </div>

            @if (session('success'))
                <div class="alert alert-success rounded-0 mb-0 py-2 px-4 small">{{ session('success') }}</div>
            @endif

            @if ($users->count())
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Created</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->created_at->format('M d, Y') }}</td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('superadmin.users.destroy', $user) }}" onsubmit="return confirm('Remove this account?')">
                                        @csrf @method('DELETE')
                                        <button class="sa-delete-btn">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="p-3">
                    {{ $users->links() }}
                </div>
            @else
                <div class="sa-empty">No HR accounts yet.</div>
            @endif
        </div>

    </div>

    <!-- Add Account Modal -->
    <div class="modal fade sa-modal" id="addAccountModal" tabindex="-1" aria-labelledby="addAccountModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h2 id="addAccountModalLabel">New Account</h2>
                        <p>Create a login for an HR staff member</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">

                    @if ($errors->any())
                        <div class="alert alert-danger py-2 small mb-3">
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('superadmin.users.store') }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label d-block">Name</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label d-block">Email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label d-block">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label d-block">Confirm Password</label>
                            <input type="password" name="password_confirmation" class="form-control" required>
                        </div>

                        <button type="submit" class="btn-submit">
                            <i class="bi bi-person-plus me-1"></i> Create Account
                        </button>
                    </form>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @if ($errors->any())
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                new bootstrap.Modal(document.getElementById('addAccountModal')).show();
            });
        </script>
    @endif

</body>
</html>