<?php
/**
 * build_landing_page.php
 *
 * Creates the DepEd Cavite recruitment landing page and application tracker.
 *
 * What this does:
 *   1. Creates resources/views/welcome.blade.php  (public landing page)
 *   2. Creates resources/views/portal/track.blade.php  (track result page)
 *   3. Patches routes/web.php  (replaces '/' route + adds /portal/track)
 *
 * Usage: php build_landing_page.php  (from project root)
 *        Delete this script when done.
 */

function die_loud(string $msg): void {
    fwrite(STDERR, "\n[ABORTED] $msg\n\n"); exit(1);
}

function backup(string $path): void {
    if (!file_exists($path)) die_loud("File not found: $path");
    $b = $path.'.bak'; $n = 1;
    while (file_exists($b)) { $n++; $b = $path.'.bak'.$n; }
    copy($path, $b) or die_loud("Cannot backup $path");
    echo "  backed up → ".basename($b)."\n";
}

function patch(string $src, string $old, string $new, string $lbl): string {
    $c = substr_count($src, $old);
    if ($c !== 1) die_loud("Patch '$lbl': expected 1 match, found $c. File may have drifted.");
    return str_replace($old, $new, $src);
}

function write(string $path, string $body, string $lbl, bool $over = false): void {
    if (!$over && file_exists($path)) die_loud("$lbl already exists — script may have already run.");
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($path, $body) !== false or die_loud("Cannot write $path");
    echo ($over ? '  replaced' : '  created')." $lbl\n";
}

$root = __DIR__;

// ─── 1. welcome.blade.php ────────────────────────────────────────────────────

write("$root/resources/views/welcome.blade.php", <<<'BLADE'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DepEd Cavite – Online Recruitment</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --teal: #1a6b6b; --teal-mid: #2b8a8a; --teal-light: #e4f4f4;
    --green: #1a7a3c; --green-mid: #22a050;
    --dark: #0d1f1f; --text: #1a2e2e; --muted: #5a7070; --bg: #f0f8f8;
  }
  body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; flex-direction:column; }

  /* NAV */
  .topnav { display:flex; align-items:center; justify-content:space-between; padding:14px 32px; background:#fff; border-bottom:1px solid #d4eaea; position:sticky; top:0; z-index:100; box-shadow:0 1px 8px rgba(0,0,0,.06); }
  .topnav-brand { display:flex; align-items:center; gap:12px; text-decoration:none; }
  .topnav-logo { width:44px; height:44px; border-radius:50%; background:linear-gradient(135deg,var(--teal) 0%,var(--green) 100%); display:flex; align-items:center; justify-content:center; font-size:1.2rem; color:#fff; font-weight:900; flex-shrink:0; }
  .topnav-text .org { font-size:.7rem; font-weight:600; color:var(--muted); letter-spacing:.08em; text-transform:uppercase; }
  .topnav-text .sys { font-size:.95rem; font-weight:800; color:var(--dark); line-height:1.1; }
  .btn-admin { display:flex; align-items:center; gap:7px; background:var(--teal); color:#fff; padding:9px 18px; border-radius:8px; font-size:.82rem; font-weight:700; text-decoration:none; border:none; cursor:pointer; transition:background .2s; }
  .btn-admin:hover { background:var(--dark); color:#fff; }

  /* HERO */
  .hero { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:60px 24px 40px; text-align:center; position:relative; overflow:hidden; }
  .hero::before { content:''; position:absolute; inset:0; background:radial-gradient(ellipse 60% 50% at 50% 0%,rgba(43,138,138,.12) 0%,transparent 70%),radial-gradient(ellipse 40% 40% at 80% 80%,rgba(26,122,60,.08) 0%,transparent 60%); pointer-events:none; }
  .dot { position:absolute; border-radius:50%; background:var(--teal-mid); opacity:.12; animation:float 6s ease-in-out infinite; }
  .dot:nth-child(1){width:10px;height:10px;top:18%;left:12%;animation-delay:0s;}
  .dot:nth-child(2){width:6px;height:6px;top:30%;right:15%;animation-delay:1.5s;}
  .dot:nth-child(3){width:14px;height:14px;bottom:25%;left:20%;animation-delay:3s;}
  .dot:nth-child(4){width:8px;height:8px;bottom:20%;right:10%;animation-delay:4.5s;}
  @keyframes float{0%,100%{transform:translateY(0);}50%{transform:translateY(-12px);}}

  .hero-eyebrow { font-size:.75rem; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:var(--teal-mid); background:var(--teal-light); padding:5px 14px; border-radius:20px; margin-bottom:20px; display:inline-block; }
  .hero-title { font-size:clamp(2rem,5vw,3.2rem); font-weight:900; line-height:1.08; color:var(--dark); margin-bottom:6px; position:relative; z-index:1; }
  .hero-title .accent { color:var(--green-mid); }
  .hero-sub { font-size:1rem; color:var(--muted); margin-bottom:40px; font-weight:500; position:relative; z-index:1; }

  /* TRACK BOX */
  .track-box { background:#fff; border:1.5px solid #c8e6e6; border-radius:16px; padding:32px 36px; max-width:580px; width:100%; box-shadow:0 8px 40px rgba(43,138,138,.10); position:relative; z-index:1; margin-bottom:32px; }
  .track-box h2 { font-size:1rem; font-weight:700; color:var(--dark); margin-bottom:4px; }
  .track-box p { font-size:.82rem; color:var(--muted); margin-bottom:18px; }
  .track-input-row { display:flex; gap:10px; }
  .track-input { flex:1; padding:13px 16px; border:1.5px solid #c8e6e6; border-radius:8px; font-size:.92rem; font-family:'Inter',sans-serif; color:var(--dark); outline:none; transition:border-color .2s; }
  .track-input:focus { border-color:var(--teal-mid); }
  .track-input::placeholder { color:#9bbcbc; }
  .btn-scan { display:flex; align-items:center; gap:6px; padding:13px 16px; border-radius:8px; border:1.5px solid #c8e6e6; background:#fff; font-size:.82rem; font-weight:600; color:var(--muted); cursor:pointer; transition:.2s; white-space:nowrap; font-family:'Inter',sans-serif; }
  .btn-scan:hover { border-color:var(--teal-mid); color:var(--teal); }
  .btn-track { display:flex; align-items:center; gap:7px; padding:13px 22px; border-radius:8px; background:var(--green-mid); color:#fff; font-size:.88rem; font-weight:800; border:none; cursor:pointer; transition:.2s; font-family:'Inter',sans-serif; white-space:nowrap; text-transform:uppercase; letter-spacing:.04em; }
  .btn-track:hover { background:var(--green); }
  .divider { display:flex; align-items:center; gap:12px; margin:20px 0; color:var(--muted); font-size:.78rem; font-weight:500; }
  .divider::before,.divider::after { content:''; flex:1; height:1px; background:#d4eaea; }
  .btn-apply { display:flex; align-items:center; justify-content:center; gap:8px; width:100%; padding:13px; background:var(--teal); color:#fff; border-radius:8px; font-size:.9rem; font-weight:700; text-decoration:none; transition:.2s; }
  .btn-apply:hover { background:var(--dark); color:#fff; }
  .btn-login-candidate { display:flex; align-items:center; justify-content:center; gap:8px; width:100%; padding:11px; background:transparent; color:var(--teal); border:1.5px solid var(--teal); border-radius:8px; font-size:.85rem; font-weight:600; text-decoration:none; transition:.2s; margin-top:10px; }
  .btn-login-candidate:hover { background:var(--teal-light); }

  /* INFO STRIP */
  .info-strip { display:flex; gap:16px; flex-wrap:wrap; justify-content:center; margin-top:8px; max-width:580px; width:100%; position:relative; z-index:1; }
  .info-card { flex:1; min-width:140px; background:#fff; border:1px solid #d4eaea; border-radius:10px; padding:14px 16px; text-align:center; }
  .info-card i { font-size:1.3rem; color:var(--teal-mid); margin-bottom:5px; display:block; }
  .info-card .val { font-size:.78rem; font-weight:700; color:var(--dark); }
  .info-card .lbl { font-size:.7rem; color:var(--muted); }

  /* FOOTER */
  footer { padding:20px 32px; background:#fff; border-top:1px solid #d4eaea; display:flex; align-items:center; justify-content:center; gap:24px; flex-wrap:wrap; }
  .footer-logos { display:flex; align-items:center; gap:16px; }
  .footer-logo-box { width:36px; height:36px; border-radius:50%; background:linear-gradient(135deg,var(--teal) 0%,var(--green) 100%); display:flex; align-items:center; justify-content:center; font-size:.8rem; font-weight:900; color:#fff; }
  .footer-divider { width:1px; height:28px; background:#d4eaea; }
  .footer-copy { font-size:.72rem; color:var(--muted); font-weight:500; text-align:center; }

  @media(max-width:560px){
    .topnav{padding:12px 16px;}
    .track-box{padding:22px 18px;}
    .track-input-row{flex-wrap:wrap;}
    .btn-track,.btn-scan{width:100%;justify-content:center;}
  }
</style>
</head>
<body>

<nav class="topnav">
  <a class="topnav-brand" href="/">
    <div class="topnav-logo">D</div>
    <div class="topnav-text">
      <div class="org">DepEd Cavite</div>
      <div class="sys">Online Recruitment</div>
    </div>
  </a>
  <a class="btn-admin" href="{{ route('login') }}">
    <i class="bi bi-shield-lock"></i> Admin Login
  </a>
</nav>

<section class="hero">
  <div class="dot"></div><div class="dot"></div>
  <div class="dot"></div><div class="dot"></div>

  <span class="hero-eyebrow">Schools Division Office of Cavite Province</span>
  <h1 class="hero-title">ONLINE<br><span class="accent">RECRUITMENT</span></h1>
  <p class="hero-sub">Department of Education — Region IV-A</p>

  <div class="track-box">
    <h2><i class="bi bi-search" style="color:var(--teal-mid);margin-right:6px;"></i>Track Your Application</h2>
    <p>Enter your transaction number to check your application status.</p>

    <form action="{{ url('/portal/track') }}" method="GET">
      <div class="track-input-row">
        <input class="track-input" type="text" name="txn"
          placeholder="e.g. APP-20260629-A3F9K2"
          value="{{ request('txn') }}">
        <button type="button" class="btn-scan" onclick="alert('QR scanner coming soon.')">
          <i class="bi bi-qr-code-scan"></i> SCAN
        </button>
        <button type="submit" class="btn-track">
          TRACK <i class="bi bi-arrow-right"></i>
        </button>
      </div>
    </form>

    <div class="divider">or</div>

    <a class="btn-apply" href="{{ url('/portal/register') }}">
      <i class="bi bi-pencil-square"></i> Submit a New Application
    </a>
    <a class="btn-login-candidate" href="{{ url('/portal/login') }}">
      <i class="bi bi-person-circle"></i> Log in to My Portal
    </a>
  </div>

  <div class="info-strip">
    <div class="info-card">
      <i class="bi bi-clock-history"></i>
      <div class="val">Real-time</div>
      <div class="lbl">Status updates</div>
    </div>
    <div class="info-card">
      <i class="bi bi-envelope-check"></i>
      <div class="val">Email</div>
      <div class="lbl">Confirmation sent</div>
    </div>
    <div class="info-card">
      <i class="bi bi-shield-check"></i>
      <div class="val">Secure</div>
      <div class="lbl">Data Privacy Act 2012</div>
    </div>
  </div>
</section>

<footer>
  <div class="footer-logos">
    <div class="footer-logo-box">D</div>
    <div class="footer-divider"></div>
    <div class="footer-logo-box" style="background:linear-gradient(135deg,#1a4a8a,#2260c4);">PH</div>
    <div class="footer-divider"></div>
    <div class="footer-logo-box" style="background:linear-gradient(135deg,#8a1a1a,#c43022);">R4</div>
  </div>
  <div class="footer-copy">© 2026 DepEd — Schools Division Office of Cavite Province</div>
</footer>

</body>
</html>
BLADE, 'welcome.blade.php', over: true);

// ─── 2. portal/track.blade.php ───────────────────────────────────────────────

write("$root/resources/views/portal/track.blade.php", <<<'BLADE'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Track Application – DepEd Cavite</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
  :root{--teal:#1a6b6b;--teal-mid:#2b8a8a;--teal-light:#e4f4f4;--green:#1a7a3c;--green-mid:#22a050;--dark:#0d1f1f;--text:#1a2e2e;--muted:#5a7070;--bg:#f0f8f8;}
  body{font-family:'Inter',sans-serif;background:var(--bg);min-height:100vh;display:flex;flex-direction:column;}
  .topnav{display:flex;align-items:center;justify-content:space-between;padding:14px 32px;background:#fff;border-bottom:1px solid #d4eaea;box-shadow:0 1px 8px rgba(0,0,0,.06);}
  .topnav-brand{display:flex;align-items:center;gap:12px;text-decoration:none;}
  .topnav-logo{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--teal) 0%,var(--green) 100%);display:flex;align-items:center;justify-content:center;font-size:1rem;color:#fff;font-weight:900;}
  .topnav-text .org{font-size:.68rem;font-weight:600;color:var(--muted);letter-spacing:.08em;text-transform:uppercase;}
  .topnav-text .sys{font-size:.9rem;font-weight:800;color:var(--dark);}
  .btn-back{display:flex;align-items:center;gap:6px;color:var(--teal);font-size:.82rem;font-weight:600;text-decoration:none;padding:8px 14px;border:1.5px solid #c8e6e6;border-radius:8px;transition:.2s;}
  .btn-back:hover{background:var(--teal-light);}
  .main{flex:1;display:flex;align-items:center;justify-content:center;padding:40px 24px;}
  .card{background:#fff;border:1.5px solid #c8e6e6;border-radius:16px;padding:36px 40px;max-width:560px;width:100%;box-shadow:0 8px 40px rgba(43,138,138,.10);}
  .txn-badge{display:inline-flex;align-items:center;gap:7px;background:var(--teal-light);color:var(--teal);padding:5px 14px;border-radius:20px;font-size:.78rem;font-weight:700;letter-spacing:.04em;margin-bottom:16px;}
  .status-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;}
  .status-row h2{font-size:1.1rem;font-weight:800;color:var(--dark);}
  .badge-status{padding:5px 14px;border-radius:20px;font-size:.8rem;font-weight:700;}
  .badge-submitted{background:#e8eaf6;color:#3949ab;}
  .badge-screening{background:#e3f2fd;color:#1565c0;}
  .badge-shortlisted{background:#e8f5e9;color:#2e7d32;}
  .badge-interview_scheduled,.badge-assessed,.badge-ranked,.badge-ranking_sent{background:#fff8e1;color:#f57f17;}
  .badge-offer_sent,.badge-offer_accepted,.badge-hired{background:#e8f5e9;color:#1b5e20;}
  .badge-offer_declined,.badge-rejected{background:#ffebee;color:#b71c1c;}
  .badge-default{background:#f5f5f5;color:#616161;}

  /* Steps */
  .steps{display:flex;align-items:flex-start;margin:24px 0;}
  .step{flex:1;text-align:center;position:relative;}
  .step::before{content:'';position:absolute;top:14px;left:-50%;right:50%;height:2px;background:#d4eaea;z-index:0;}
  .step:first-child::before{display:none;}
  .step-dot{width:28px;height:28px;border-radius:50%;background:#d4eaea;color:#9bbcbc;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:800;margin:0 auto 5px;position:relative;z-index:1;}
  .step.done .step-dot{background:var(--green-mid);color:#fff;}
  .step.active .step-dot{background:var(--teal);color:#fff;box-shadow:0 0 0 4px var(--teal-light);}
  .step.done::before{background:var(--green-mid);}
  .step-lbl{font-size:.6rem;color:var(--muted);font-weight:600;line-height:1.2;}
  .step.done .step-lbl{color:var(--green);}
  .step.active .step-lbl{color:var(--teal);}

  .detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:20px;}
  .detail-item{background:#f7fbfb;border-radius:8px;padding:10px 14px;}
  .detail-item .lbl{font-size:.68rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.05em;}
  .detail-item .val{font-size:.85rem;font-weight:700;color:var(--dark);margin-top:2px;}
  .not-found{text-align:center;padding:20px 0;}
  .not-found i{font-size:2.5rem;color:#c8e6e6;display:block;margin-bottom:10px;}
  .not-found h3{font-size:1rem;font-weight:700;color:var(--dark);margin-bottom:6px;}
  .not-found p{font-size:.83rem;color:var(--muted);}
  .btn-portal{display:flex;align-items:center;justify-content:center;gap:7px;background:var(--teal);color:#fff;border-radius:8px;padding:12px;font-size:.88rem;font-weight:700;text-decoration:none;margin-top:20px;transition:.2s;}
  .btn-portal:hover{background:var(--dark);color:#fff;}
  @media(max-width:480px){.card{padding:24px 18px;}.detail-grid{grid-template-columns:1fr;}.step-lbl{font-size:.55rem;}}
</style>
</head>
<body>

<nav class="topnav">
  <a class="topnav-brand" href="{{ url('/') }}">
    <div class="topnav-logo">D</div>
    <div class="topnav-text">
      <div class="org">DepEd Cavite</div>
      <div class="sys">Online Recruitment</div>
    </div>
  </a>
  <a class="btn-back" href="{{ url('/') }}"><i class="bi bi-arrow-left"></i> Back</a>
</nav>

<main class="main">
  <div class="card">
    @if (!$application)
      <div class="not-found">
        <i class="bi bi-search"></i>
        <h3>Transaction number not found</h3>
        <p>No application matched <strong>{{ $txn }}</strong>.<br>Please double-check your transaction number and try again.</p>
        <a class="btn-portal" href="{{ url('/') }}" style="background:var(--teal);">
          <i class="bi bi-arrow-left"></i> Try Again
        </a>
      </div>
    @else
      @php
        $statusMap = ['submitted'=>1,'screening'=>2,'shortlisted'=>3,'interview_scheduled'=>4,'assessed'=>4,'ranked'=>4,'ranking_sent'=>4,'offer_sent'=>5,'offer_accepted'=>6,'hired'=>6,'offer_declined'=>6,'rejected'=>6];
        $currentStep = $statusMap[$application->status] ?? 1;
        $steps = ['Submitted','Screening','Shortlisted','Assessment','Offer','Final'];
        $badgeClass = 'badge-'.($application->status ?? 'default');
        $label = ucwords(str_replace('_',' ',$application->status));
      @endphp

      <div class="txn-badge"><i class="bi bi-hash"></i> {{ $txn }}</div>

      <div class="status-row">
        <h2>{{ $application->jobPosting->title ?? 'Application' }}</h2>
        <span class="badge-status {{ $badgeClass }}">{{ $label }}</span>
      </div>

      <div class="steps">
        @foreach($steps as $i => $step)
        <div class="step {{ $currentStep > $i+1 ? 'done' : ($currentStep === $i+1 ? 'active' : '') }}">
          <div class="step-dot">
            @if($currentStep > $i+1)
              <i class="bi bi-check2" style="font-size:.8rem;"></i>
            @else
              {{ $i+1 }}
            @endif
          </div>
          <div class="step-lbl">{{ $step }}</div>
        </div>
        @endforeach
      </div>

      <div class="detail-grid">
        <div class="detail-item">
          <div class="lbl">Applicant Name</div>
          <div class="val">{{ $application->candidate->full_name ?? '—' }}</div>
        </div>
        <div class="detail-item">
          <div class="lbl">Date Applied</div>
          <div class="val">{{ $application->applied_at ? \Carbon\Carbon::parse($application->applied_at)->format('M d, Y') : '—' }}</div>
        </div>
        <div class="detail-item">
          <div class="lbl">Position</div>
          <div class="val">{{ $application->jobPosting->title ?? '—' }}</div>
        </div>
        <div class="detail-item">
          <div class="lbl">Current Status</div>
          <div class="val">{{ $label }}</div>
        </div>
      </div>

      <a class="btn-portal" href="{{ url('/portal/login') }}">
        <i class="bi bi-person-circle"></i> Log in to My Portal for Full Details
      </a>
    @endif
  </div>
</main>

</body>
</html>
BLADE, 'portal/track.blade.php');

// ─── 3. Patch routes/web.php ─────────────────────────────────────────────────

$routesPath = "$root/routes/web.php";
$routes = file_get_contents($routesPath) or die_loud("Cannot read routes/web.php");
backup($routesPath);

// Replace the '/' route
$routes = patch($routes,
    "Route::get('/', function () {\n    return redirect('/portal/register');\n});",
    "// Public landing page\nRoute::get('/', function () {\n    return view('welcome');\n});\n\n// Public application tracker (no login required)\nRoute::get('/portal/track', function (\\Illuminate\\Http\\Request \$request) {\n    \$txn = strtoupper(trim(\$request->query('txn', '')));\n    \$application = null;\n    if (\$txn) {\n        \$application = \\App\\Models\\Application::with(['candidate', 'jobPosting'])\n            ->where('transaction_number', \$txn)\n            ->first();\n    }\n    return view('portal.track', compact('application', 'txn'));\n});",
    "replace / route and add /portal/track"
);

file_put_contents($routesPath, $routes) !== false or die_loud("Cannot write routes/web.php");
echo "  updated routes/web.php\n";

// ─── Done ─────────────────────────────────────────────────────────────────────

echo <<<TXT

✅ Done! No migration needed.

  Visit: http://127.0.0.1:8000/
    → DepEd Cavite recruitment landing page

  Enter a transaction number (e.g. APP-20260629-XXXXXX) → TRACK
    → Shows applicant name, position, 6-step progress bar, status badge

  Admin Login button (top right) → /login (HR staff)
  Submit a New Application → /portal/register
  Log in to My Portal → /portal/login

  Delete this script when done.
TXT;
