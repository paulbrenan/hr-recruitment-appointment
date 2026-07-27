<?php
// patch_auth.php — run once from project root, then delete
// Usage: php patch_auth.php

$authTarget  = __DIR__ . '/resources/views/layouts/auth.blade.php';
$loginTarget = __DIR__ . '/resources/views/auth/login.blade.php';

foreach ([$authTarget, $loginTarget] as $f) {
    if (!file_exists($f)) die("❌  File not found: $f\n");
}

function apply_patch(string $file, string $old, string $new, string $label): void {
    $src   = file_get_contents($file);
    $count = substr_count($src, $old);
    if ($count === 0) die("❌  ABORT [{$label}]: old content not found in {$file}. No changes written.\n");
    if ($count  > 1) die("❌  ABORT [{$label}]: matched {$count} times (expected 1) in {$file}. No changes written.\n");

    $bak = $file . '.bak';
    $i   = 2;
    while (file_exists($bak)) { $bak = $file . '.bak' . $i++; }
    file_put_contents($bak, $src);

    file_put_contents($file, str_replace($old, $new, $src));
    echo "✅  [{$label}] patched. Backup → {$bak}\n";
}

// ══════════════════════════════════════════════════════════════════════════════
//  AUTH LAYOUT
// ══════════════════════════════════════════════════════════════════════════════

// 1. Replace entire <style> block with DepEd theme
apply_patch($authTarget,
<<<'OLD'
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
OLD,
<<<'NEW'
    <style>
        :root {
            --blue:       #003087;
            --blue-mid:   #0047b3;
            --blue-light: #e6ecf7;
            --blue-dark:  #0a1a33;
            --gold:       #ffd700;
            --red:        #CE1126;
            --text:       #1a2840;
            --muted:      #5a6880;
            --header-h:   64px;
        }

        /* ── Base ── */
        *, *::before, *::after { box-sizing: border-box; }
        html, body {
            min-height: 100vh;
            font-family: 'Segoe UI', Arial, sans-serif;
            color: var(--text);
        }

        /* ── Fixed background (same as welcome page) ── */
        body {
            background: url('/matatag-bg.png') center center / cover no-repeat fixed;
            position: relative;
        }
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            background: rgba(0, 48, 135, 0.72);
            z-index: 0;
            pointer-events: none;
        }

        /* ── Watermark seal ── */
        body::before {
            content: '';
            position: fixed;
            top: 50%; left: 50%;
            width: 560px; height: 560px;
            background: url('/images/deped-logo.png') center / contain no-repeat;
            transform: translate(-50%, -50%);
            opacity: 0.05;
            pointer-events: none;
            z-index: 0;
        }

        /* ── Nav / Header ── */
        .auth-header {
            height: var(--header-h);
            background: rgba(0, 48, 135, 0.96);
            border-bottom: none;
            color: #fff;
            padding: 0 1.75rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 200;
            box-shadow: 0 2px 16px rgba(0,0,0,.25);
            backdrop-filter: blur(8px);
        }
        .auth-header-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
        .auth-header-logo {
            height: 44px;
            width: auto;
            display: block;
            filter: drop-shadow(0 1px 4px rgba(0,0,0,.3));
        }
        .auth-header-text .org {
            font-size: .67rem;
            font-weight: 700;
            color: rgba(255,255,255,.7);
            letter-spacing: .1em;
            text-transform: uppercase;
        }
        .auth-header-text .sys {
            font-size: .9rem;
            font-weight: 800;
            color: #fff;
            line-height: 1.15;
        }
        .auth-header-datetime {
            color: rgba(255,255,255,.65);
            font-size: 0.78rem;
            font-weight: 600;
        }
        @media(max-width:560px){ .auth-header-datetime { display: none; } }
        .auth-header-spacer { width: 0; }
        @media(min-width:680px){ .auth-header-spacer { width: 180px; } }

        /* ── Centered wrapper ── */
        .auth-wrapper {
            min-height: calc(100vh - var(--header-h));
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2.5rem 1rem;
            position: relative;
            z-index: 1;
        }

        /* ── Card ── */
        .auth-card {
            width: 100%;
            max-width: 440px;
            border: none;
            border-radius: 14px;
            box-shadow: 0 12px 48px rgba(0,0,0,.3);
            overflow: hidden;
        }
        .auth-card .card-body {
            padding: 0 !important;
        }

        /* Card header banner */
        .auth-card-header {
            background: linear-gradient(120deg, var(--blue) 0%, var(--blue-dark) 100%);
            border-bottom: 3px solid var(--gold);
            padding: 24px 28px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            position: relative;
            overflow: hidden;
        }
        .auth-card-header::after {
            content: '';
            position: absolute;
            right: -30px; top: -30px;
            width: 140px; height: 140px;
            background: url('/images/deped-logo.png') center / contain no-repeat;
            opacity: 0.10;
            pointer-events: none;
        }
        .auth-card-header-logo {
            height: 50px; width: 50px;
            background: #fff;
            border-radius: 50%;
            padding: 3px;
            box-shadow: 0 2px 8px rgba(0,0,0,.25);
            flex-shrink: 0;
            position: relative; z-index: 1;
        }
        .auth-card-header-text {
            position: relative; z-index: 1;
        }
        .auth-card-header-text .eyebrow {
            font-size: .68rem; font-weight: 700;
            letter-spacing: .12em; text-transform: uppercase;
            color: var(--gold); margin-bottom: 3px;
        }
        .auth-card-header-text .title {
            font-size: 1.05rem; font-weight: 800;
            color: #fff; line-height: 1.2;
        }

        /* Card body padding */
        .auth-card-body {
            padding: 28px 28px 24px;
            background: #fff;
        }

        /* ── Form elements ── */
        .form-label { font-size: .82rem; font-weight: 600; color: var(--text); }
        .form-control {
            border-color: #c5d0e6;
            font-size: .92rem;
            border-radius: 8px;
            padding: 10px 14px;
        }
        .form-control:focus {
            border-color: var(--blue-mid);
            box-shadow: 0 0 0 3px rgba(0,71,179,.12);
        }

        /* ── Primary button ── */
        .btn-hr-primary {
            background: var(--blue);
            color: #fff;
            font-weight: 700;
            font-size: .92rem;
            padding: 11px;
            border-radius: 8px;
            border: none;
            transition: background .2s;
        }
        .btn-hr-primary:hover {
            background: var(--blue-dark);
            color: #fff;
        }

        /* ── Card footer ── */
        .auth-card-footer {
            background: var(--blue-light);
            border-top: 1px solid #dce5f5;
            padding: 14px 28px;
            font-size: .78rem;
            color: var(--muted);
            text-align: center;
        }
    </style>
NEW,
    'Auth layout CSS'
);

// 2. Replace the header HTML (logo text → SDO logo image + text)
apply_patch($authTarget,
<<<'OLD'
    <div class="auth-header">
        <span><i class="bi bi-people-fill me-2"></i>@yield('brand', 'HR Recruitment')</span>
        <span class="auth-header-datetime" id="authHeaderDateTime"></span>
        <span class="auth-header-spacer"></span>
    </div>
OLD,
<<<'NEW'
    <div class="auth-header">
        <a href="/" class="auth-header-brand">
            <img src="/sdo-logo.png" alt="SDO Cavite" class="auth-header-logo">
            <div class="auth-header-text">
                <div class="org">Schools Division Office of Cavite Province</div>
                <div class="sys">HR Recruitment System</div>
            </div>
        </a>
        <span class="auth-header-datetime" id="authHeaderDateTime"></span>
        <span class="auth-header-spacer"></span>
    </div>
NEW,
    'Auth header brand'
);

// 3. Replace the auth-wrapper / card structure to inject card header banner
apply_patch($authTarget,
<<<'OLD'
    <div class="auth-wrapper">
        <div class="card auth-card">
            <div class="card-body p-4">
                @yield('content')
            </div>
        </div>
    </div>
OLD,
<<<'NEW'
    <div class="auth-wrapper">
        <div class="card auth-card">
            <div class="card-body">
                <div class="auth-card-header">
                    <img src="/sdo-logo.png" alt="SDO Cavite" class="auth-card-header-logo">
                    <div class="auth-card-header-text">
                        <div class="eyebrow">Department of Education</div>
                        <div class="title">Schools Division Office<br>of Cavite Province</div>
                    </div>
                </div>
                <div class="auth-card-body">
                    @yield('content')
                </div>
                <div class="auth-card-footer">
                    &copy; {{ date('Y') }} DepEd &mdash; Schools Division Office of Cavite Province
                </div>
            </div>
        </div>
    </div>
NEW,
    'Auth card structure'
);

// ══════════════════════════════════════════════════════════════════════════════
//  LOGIN BLADE — update title yield
// ══════════════════════════════════════════════════════════════════════════════
apply_patch($loginTarget,
    "@section('title', 'Sign in')\n@section('brand', 'HR Recruitment')",
    "@section('title', 'Sign in')",
    'Login: remove brand section (now in layout)'
);

echo "\n🎉  All patches applied.\n";
echo "    Run: php artisan view:clear\n";
