<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>DepEd Cavite – Online Recruitment</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --teal: #1a6b6b; --teal-mid: #2b8a8a; --teal-light: #e4f4f4;
    --green: #1a7a3c; --green-mid: #22a050;
    --dark: #0d1f1f; --text: #1a2e2e; --muted: #5a7070;
    --bg: #f0f8f8; --white: #ffffff;
  }
  body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; flex-direction:column; }

  /* ── NAV ── */
  .topnav { display:flex; align-items:center; justify-content:space-between; padding:14px 32px; background:var(--white); border-bottom:1px solid #d4eaea; position:sticky; top:0; z-index:200; box-shadow:0 1px 8px rgba(0,0,0,.06); }
  .topnav-brand { display:flex; align-items:center; gap:12px; text-decoration:none; }
  .topnav-logo { width:44px; height:44px; border-radius:50%; background:linear-gradient(135deg,var(--teal) 0%,var(--green) 100%); display:flex; align-items:center; justify-content:center; font-size:1.2rem; color:#fff; font-weight:900; flex-shrink:0; }
  .topnav-text .org { font-size:.7rem; font-weight:600; color:var(--muted); letter-spacing:.08em; text-transform:uppercase; }
  .topnav-text .sys { font-size:.95rem; font-weight:800; color:var(--dark); line-height:1.1; }
  .btn-admin { display:flex; align-items:center; gap:7px; background:var(--teal); color:#fff; padding:9px 18px; border-radius:8px; font-size:.82rem; font-weight:700; text-decoration:none; transition:background .2s; }
  .btn-admin:hover { background:var(--dark); color:#fff; }
  .topnav-clock { display:flex; flex-direction:column; align-items:center; line-height:1.2; }
  .topnav-clock .clock-date { font-size:.78rem; font-weight:600; color:var(--muted); }
  .topnav-clock .clock-time { font-size:.92rem; font-weight:800; color:var(--dark); letter-spacing:.02em; font-variant-numeric:tabular-nums; }
  .topnav-spacer { width:0; flex-shrink:0; }
  @media(min-width:680px){ .topnav-spacer { width:172px; } }
  @media(max-width:560px){ .topnav-clock { display:none; } }

  /* ── HERO ── */
  .hero { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:flex-start; padding:32px 24px 32px; text-align:center; position:relative; overflow:hidden; }
  .hero::before { content:''; position:absolute; inset:0; background:radial-gradient(ellipse 60% 50% at 50% 0%,rgba(43,138,138,.12) 0%,transparent 70%),radial-gradient(ellipse 40% 40% at 80% 80%,rgba(26,122,60,.08) 0%,transparent 60%); pointer-events:none; }
  .dot { position:absolute; border-radius:50%; background:var(--teal-mid); opacity:.12; animation:float 6s ease-in-out infinite; }
  .dot:nth-child(1){width:10px;height:10px;top:18%;left:12%;animation-delay:0s;}
  .dot:nth-child(2){width:6px;height:6px;top:30%;right:15%;animation-delay:1.5s;}
  .dot:nth-child(3){width:14px;height:14px;bottom:25%;left:20%;animation-delay:3s;}
  .dot:nth-child(4){width:8px;height:8px;bottom:20%;right:10%;animation-delay:4.5s;}
  @keyframes float{0%,100%{transform:translateY(0);}50%{transform:translateY(-12px);}}

  .hero-eyebrow { font-size:.75rem; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:var(--teal-mid); background:var(--teal-light); padding:5px 14px; border-radius:20px; margin-bottom:20px; display:inline-block; position:relative; z-index:1; }
  .hero-title { font-size:clamp(2rem,5vw,3.2rem); font-weight:900; line-height:1.08; color:var(--dark); margin-bottom:6px; position:relative; z-index:1; }
  .hero-title .accent { color:var(--green-mid); }
  .hero-sub { font-size:1rem; color:var(--muted); margin-bottom:28px; font-weight:500; position:relative; z-index:1; }

  /* ── TRACK BOX ── */
  .track-box { background:var(--white); border:1.5px solid #c8e6e6; border-radius:16px; padding:32px 36px; max-width:580px; width:100%; box-shadow:0 8px 40px rgba(43,138,138,.10); position:relative; z-index:1; margin-bottom:32px; }
  .track-box h2 { font-size:1rem; font-weight:700; color:var(--dark); margin-bottom:4px; }
  .track-box > p { font-size:.82rem; color:var(--muted); margin-bottom:18px; }
  .track-input-row { display:flex; gap:10px; }
  .track-input { flex:1; padding:13px 16px; border:1.5px solid #c8e6e6; border-radius:8px; font-size:.92rem; font-family:'Inter',sans-serif; color:var(--dark); outline:none; transition:border-color .2s; }
  .track-input:focus { border-color:var(--teal-mid); }
  .track-input::placeholder { color:#9bbcbc; }
  .btn-track { display:flex; align-items:center; gap:7px; padding:13px 22px; border-radius:8px; background:var(--green-mid); color:#fff; font-size:.88rem; font-weight:800; border:none; cursor:pointer; transition:.2s; font-family:'Inter',sans-serif; white-space:nowrap; text-transform:uppercase; letter-spacing:.04em; }
  .btn-track:hover { background:var(--green); }
  .btn-track:disabled { opacity:.6; cursor:not-allowed; }
  .divider { display:flex; align-items:center; gap:12px; margin:20px 0; color:var(--muted); font-size:.78rem; font-weight:500; }
  .divider::before,.divider::after { content:''; flex:1; height:1px; background:#d4eaea; }
  .btn-apply { display:flex; align-items:center; justify-content:center; gap:8px; width:100%; padding:13px; background:var(--teal); color:#fff; border-radius:8px; font-size:.9rem; font-weight:700; text-decoration:none; transition:.2s; }
  .btn-apply:hover { background:var(--dark); color:#fff; }

  /* ── CONTACT & LOCATION ── */
  .contact-section { width:100%; max-width:900px; margin:48px auto 0; padding:0 24px; position:relative; z-index:1; text-align:center; }
  .contact-title { font-size:.95rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; color:var(--muted); margin-bottom:4px; }
  .contact-subtitle { font-size:.85rem; font-weight:700; color:var(--dark); margin-bottom:28px; }
  .contact-grid { display:flex; gap:32px; justify-content:center; flex-wrap:wrap; margin-bottom:36px; }
  .contact-item { flex:1; min-width:160px; max-width:240px; }
  .contact-item i { font-size:1.6rem; color:var(--teal-mid); margin-bottom:8px; display:block; }
  .contact-item .contact-label { font-size:.78rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; color:var(--dark); margin-bottom:6px; }
  .contact-item .contact-value { font-size:.8rem; color:var(--muted); line-height:1.5; }
  .location-block i { font-size:1.8rem; color:var(--teal-mid); margin-bottom:6px; display:block; }
  .location-label { font-size:.78rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; color:var(--dark); margin-bottom:16px; }
  .location-map { display:inline-block; width:100%; max-width:520px; border-radius:12px; overflow:hidden; border:1px solid #d4eaea; box-shadow:0 4px 20px rgba(43,138,138,.08); }
  .location-map iframe { width:100%; height:320px; border:0; display:block; }

  /* ── INFO STRIP ── */
  .info-strip { display:flex; gap:16px; flex-wrap:wrap; justify-content:center; margin-top:8px; max-width:580px; width:100%; position:relative; z-index:1; }
  .info-card { flex:1; min-width:140px; background:var(--white); border:1px solid #d4eaea; border-radius:10px; padding:14px 16px; text-align:center; }
  .info-card i { font-size:1.3rem; color:var(--teal-mid); margin-bottom:5px; display:block; }
  .info-card .val { font-size:.78rem; font-weight:700; color:var(--dark); }
  .info-card .lbl { font-size:.7rem; color:var(--muted); }

  /* ── FOOTER ── */
  footer { padding:20px 32px; background:var(--white); border-top:1px solid #d4eaea; display:flex; align-items:center; justify-content:center; gap:24px; flex-wrap:wrap; }
  .footer-logos { display:flex; align-items:center; gap:16px; }
  .footer-logo-box { width:36px; height:36px; border-radius:50%; background:linear-gradient(135deg,var(--teal) 0%,var(--green) 100%); display:flex; align-items:center; justify-content:center; font-size:.8rem; font-weight:900; color:#fff; }
  .footer-divider { width:1px; height:28px; background:#d4eaea; }
  .footer-copy { font-size:.72rem; color:var(--muted); font-weight:500; text-align:center; }

  /* ── MODAL ── */
  .modal-backdrop { display:none; position:fixed; inset:0; background:rgba(13,31,31,.55); z-index:500; align-items:center; justify-content:center; padding:16px; backdrop-filter:blur(3px); }
  .modal-backdrop.open { display:flex; }
  .modal { background:var(--white); border-radius:16px; width:100%; max-width:520px; box-shadow:0 24px 60px rgba(0,0,0,.18); overflow:hidden; animation:slideUp .25s ease; }
  @keyframes slideUp { from{transform:translateY(30px);opacity:0;} to{transform:translateY(0);opacity:1;} }
  .modal-header { display:flex; align-items:center; justify-content:space-between; padding:20px 24px 0; }
  .modal-header h3 { font-size:1rem; font-weight:800; color:var(--dark); }
  .modal-close { background:none; border:none; font-size:1.3rem; color:var(--muted); cursor:pointer; line-height:1; padding:4px; border-radius:6px; }
  .modal-close:hover { background:#f0f8f8; color:var(--dark); }
  .modal-body { padding:20px 24px 28px; }

  /* txn badge inside modal */
  .txn-badge { display:inline-flex; align-items:center; gap:6px; background:var(--teal-light); color:var(--teal); padding:4px 12px; border-radius:20px; font-size:.75rem; font-weight:700; letter-spacing:.04em; margin-bottom:14px; }

  /* status badge */
  .status-badge { padding:4px 12px; border-radius:20px; font-size:.78rem; font-weight:700; }
  .s-submitted   { background:#e8eaf6; color:#3949ab; }
  .s-screening   { background:#e3f2fd; color:#1565c0; }
  .s-shortlisted { background:#e8f5e9; color:#2e7d32; }
  .s-interview,.s-assessed,.s-ranked,.s-ranking_sent { background:#fff8e1; color:#f57f17; }
  .s-offer_sent,.s-offer_accepted,.s-hired { background:#e8f5e9; color:#1b5e20; }
  .s-offer_declined,.s-rejected { background:#ffebee; color:#b71c1c; }
  .s-default { background:#f5f5f5; color:#616161; }

  /* progress steps */
  .steps { display:flex; align-items:flex-start; margin:18px 0; }
  .step { flex:1; text-align:center; position:relative; }
  .step::before { content:''; position:absolute; top:13px; left:-50%; right:50%; height:2px; background:#d4eaea; z-index:0; }
  .step:first-child::before { display:none; }
  .step-dot { width:26px; height:26px; border-radius:50%; background:#d4eaea; color:#9bbcbc; display:flex; align-items:center; justify-content:center; font-size:.68rem; font-weight:800; margin:0 auto 4px; position:relative; z-index:1; }
  .step.done .step-dot { background:var(--green-mid); color:#fff; }
  .step.active .step-dot { background:var(--teal); color:#fff; box-shadow:0 0 0 4px var(--teal-light); }
  .step.done::before { background:var(--green-mid); }
  .step-lbl { font-size:.58rem; color:var(--muted); font-weight:600; line-height:1.2; }
  .step.done .step-lbl { color:var(--green); }
  .step.active .step-lbl { color:var(--teal); }

  /* detail grid */
  .detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:16px; }
  .detail-item { background:#f7fbfb; border-radius:8px; padding:10px 12px; }
  .detail-item .lbl { font-size:.65rem; color:var(--muted); font-weight:600; text-transform:uppercase; letter-spacing:.05em; }
  .detail-item .val { font-size:.82rem; font-weight:700; color:var(--dark); margin-top:2px; word-break:break-word; }

  /* not found state */
  .not-found-modal { text-align:center; padding:16px 0; }
  .not-found-modal i { font-size:2.2rem; color:#c8e6e6; display:block; margin-bottom:8px; }
  .not-found-modal h4 { font-size:.95rem; font-weight:700; color:var(--dark); margin-bottom:4px; }
  .not-found-modal p { font-size:.82rem; color:var(--muted); }

  /* spinner */
  .spinner { display:inline-block; width:18px; height:18px; border:2px solid rgba(255,255,255,.4); border-top-color:#fff; border-radius:50%; animation:spin .7s linear infinite; vertical-align:middle; margin-right:6px; }
  @keyframes spin{to{transform:rotate(360deg);}}

  @media(max-width:560px){
    .topnav{padding:12px 16px;}
    .track-box{padding:22px 18px;}
    .track-input-row{flex-wrap:wrap;}
    .btn-track{width:100%;justify-content:center;}
    .detail-grid{grid-template-columns:1fr;}
    .contact-section{padding:0 16px;margin-top:36px;}
    .contact-grid{gap:20px;}
    .contact-item{min-width:100%;max-width:100%;}
    .location-map iframe{height:240px;}
  }
</style>
</head>
<body>

{{-- ── NAV ── --}}
<nav class="topnav">
  <a class="topnav-brand" href="/">
    <div class="topnav-logo">D</div>
    <div class="topnav-text">
      <div class="org">DepEd Cavite</div>
      <div class="sys">Online Recruitment</div>
    </div>
  </a>
  <div class="topnav-clock" id="topnavClock">
    <div class="clock-date" id="topnavClockDate"></div>
    <div class="clock-time" id="topnavClockTime"></div>
  </div>
  <div class="topnav-spacer"></div>
</nav>

{{-- ── HERO ── --}}
<section class="hero">
  <div class="dot"></div><div class="dot"></div>
  <div class="dot"></div><div class="dot"></div>

  <span class="hero-eyebrow">Schools Division Office of Cavite Province</span>
  <h1 class="hero-title">ONLINE RECRUITMENT<br><span class="accent">TRACKING SYSTEM</span></h1>
  <p class="hero-sub">Department of Education — Region IV-A</p>

  <div class="track-box">
    <h2><i class="bi bi-search" style="color:var(--teal-mid);margin-right:6px;"></i>Track Your Application</h2>
    <p>Enter your transaction number to check your application status.</p>

    <div class="track-input-row">
      <input class="track-input" type="text" id="txnInput"
        placeholder="e.g. APP-20260629-A3F9K2"
        autocomplete="off" spellcheck="false">
      <button class="btn-track" id="trackBtn" onclick="trackApplication()">
        TRACK <i class="bi bi-arrow-right"></i>
      </button>
    </div>

  </div>


  <div class="contact-section">
    <div class="contact-title">Welcome to Online Document Tracking System</div>
    <div class="contact-subtitle">Department of Education &mdash; Schools Division Office of Cavite Province</div>

    <div class="contact-grid">
      <div class="contact-item">
        <i class="bi bi-geo-alt-fill"></i>
        <div class="contact-label">Address</div>
        {{-- PLACEHOLDER: replace with the real division office address --}}
        <div class="contact-value">Cavite Capitol Compound, Brgy. Luciano, Trece Martires City, Cavite</div>
      </div>
      <div class="contact-item">
        <i class="bi bi-telephone-fill"></i>
        <div class="contact-label">Contact No.</div>
        {{-- PLACEHOLDER: replace with the real division office contact numbers --}}
        <div class="contact-value">(046) 419-1286,<br>(046) 412-0349</div>
      </div>
      <div class="contact-item">
        <i class="bi bi-envelope-fill"></i>
        <div class="contact-label">Email</div>
        {{-- PLACEHOLDER: replace with the real division office email --}}
        <div class="contact-value">deped.cavite@deped.gov.ph</div>
      </div>
    </div>

    <div class="location-block">
      <i class="bi bi-globe2"></i>
      <div class="location-label">Location</div>
      <div class="location-map">
        {{-- PLACEHOLDER: replace this src with the real division office's Google Maps embed URL --}}
        <iframe
          src="https://www.google.com/maps?q=Schools+Division+Office+of+Cavite+Province&output=embed"
          loading="lazy"
          referrerpolicy="no-referrer-when-downgrade">
        </iframe>
      </div>
    </div>
  </div>

</section>

{{-- ── FOOTER ── --}}
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

{{-- ── MODAL ── --}}
<div class="modal-backdrop" id="resultModal" onclick="closeModal(event)">
  <div class="modal" id="modalBox">
    <div class="modal-header">
      <h3 id="modalTitle">Application Status</h3>
      <button class="modal-close" onclick="closeModalDirect()">&#x2715;</button>
    </div>
    <div class="modal-body" id="modalBody">
      {{-- filled by JS --}}
    </div>
  </div>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

// ── Live header clock ──
function updateTopnavClock() {
  const now = new Date();
  const dateEl = document.getElementById('topnavClockDate');
  const timeEl = document.getElementById('topnavClockTime');
  if (!dateEl || !timeEl) return;

  dateEl.textContent = now.toLocaleDateString('en-US', {
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
  });
  timeEl.textContent = now.toLocaleTimeString('en-US', {
    hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true
  });
}
updateTopnavClock();
setInterval(updateTopnavClock, 1000);

async function trackApplication() {
  const input = document.getElementById('txnInput');
  const btn   = document.getElementById('trackBtn');
  const txn   = input.value.trim().toUpperCase();

  if (!txn) {
    input.style.borderColor = '#e53e3e';
    input.focus();
    setTimeout(() => input.style.borderColor = '', 1500);
    return;
  }

  // Loading state
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> TRACKING…';

  try {
    const res  = await fetch('/api/track?txn=' + encodeURIComponent(txn), {
      headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
    });
    const data = await res.json();
    renderModal(txn, data);
  } catch (e) {
    renderModal(txn, { found: false, error: true });
  } finally {
    btn.disabled = false;
    btn.innerHTML = 'TRACK <i class="bi bi-arrow-right"></i>';
  }
}

function renderModal(txn, data) {
  const body  = document.getElementById('modalBody');
  const title = document.getElementById('modalTitle');

  if (!data.found) {
    title.textContent = 'Not Found';
    body.innerHTML = `
      <div class="not-found-modal">
        <i class="bi bi-search"></i>
        <h4>Transaction number not found</h4>
        <p>No application matched <strong>${txn}</strong>.<br>
        Please double-check your transaction number.</p>
      </div>`;
  } else {
    const stepMap = { submitted:1, screening:2, shortlisted:3, interview_scheduled:4, assessed:4, ranked:4, ranking_sent:4, offer_sent:5, offer_accepted:6, hired:6, offer_declined:6, rejected:6 };
    const stepLabels = ['Submitted','Screening','Shortlisted','Assessment','Offer','Final'];
    const current = stepMap[data.status] || 1;
    const label   = data.status.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase());

    const badgeMap = {
      submitted:'s-submitted', screening:'s-screening', shortlisted:'s-shortlisted',
      interview_scheduled:'s-interview', assessed:'s-assessed', ranked:'s-ranked',
      ranking_sent:'s-ranking_sent', offer_sent:'s-offer_sent',
      offer_accepted:'s-offer_accepted', hired:'s-hired',
      offer_declined:'s-offer_declined', rejected:'s-rejected'
    };
    const badgeCls = badgeMap[data.status] || 's-default';

    // Build steps HTML
    const stepsHtml = stepLabels.map((s, i) => {
      const n = i + 1;
      const cls = current > n ? 'done' : current === n ? 'active' : '';
      const dot = current > n
        ? '<i class="bi bi-check2" style="font-size:.7rem;"></i>'
        : n;
      return `<div class="step ${cls}">
        <div class="step-dot">${dot}</div>
        <div class="step-lbl">${s}</div>
      </div>`;
    }).join('');

    title.textContent = 'Application Status';
    body.innerHTML = `
      <div class="txn-badge"><i class="bi bi-hash"></i> ${txn}</div>
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:4px;">
        <div style="font-size:1rem;font-weight:800;color:var(--dark);">${data.position}</div>
        <span class="status-badge ${badgeCls}">${label}</span>
      </div>
      <div class="steps">${stepsHtml}</div>
      <div class="detail-grid">
        <div class="detail-item">
          <div class="lbl">Applicant Name</div>
          <div class="val">${data.name}</div>
        </div>
        <div class="detail-item">
          <div class="lbl">Date Applied</div>
          <div class="val">${data.applied_at}</div>
        </div>
        <div class="detail-item">
          <div class="lbl">Position</div>
          <div class="val">${data.position}</div>
        </div>
        <div class="detail-item">
          <div class="lbl">Current Status</div>
          <div class="val">${label}</div>
        </div>
      </div>`;
  }

  document.getElementById('resultModal').classList.add('open');
}

function closeModal(e) {
  if (e.target === document.getElementById('resultModal')) closeModalDirect();
}
function closeModalDirect() {
  document.getElementById('resultModal').classList.remove('open');
}

// Enter key support
document.getElementById('txnInput').addEventListener('keydown', e => {
  if (e.key === 'Enter') trackApplication();
});

// ESC to close modal
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeModalDirect();
});
</script>
</body>
</html>