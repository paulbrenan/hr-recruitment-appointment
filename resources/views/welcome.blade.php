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
    --blue: #003087;
    --blue-mid: #0047b3;
    --blue-light: #e6ecf7;
    --red: #CE1126;
    --red-dark: #a50e1e;
    --dark: #0a1a33;
    --text: #1a2840;
    --muted: #5a6880;
    --bg: #f0f4fa;
    --white: #ffffff;
  }
  body {
    font-family: 'Inter', sans-serif;
    color: var(--text);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    position: relative;
  }

  /* ── FIXED BACKGROUND ── */
  .page-bg {
    position: fixed;
    inset: 0;
    z-index: 0;
    background: url('/matatag-bg.png') center center / cover no-repeat;
  }
  .page-bg::after {
    content: '';
    position: absolute;
    inset: 0;
    background: rgba(0, 48, 135, 0.72);
  }

  /* All content layers above the fixed bg */
  .topnav, .page-content, footer { position: relative; z-index: 1; }

  /* ── NAV ── */
  .topnav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 32px;
    background: rgba(0, 48, 135, 0.96);
    border-bottom: none;
    position: sticky;
    top: 0;
    z-index: 200;
    box-shadow: 0 2px 16px rgba(0,0,0,.25);
    backdrop-filter: blur(8px);
  }
  .topnav-brand { display: flex; align-items: center; gap: 12px; text-decoration: none; }
  .topnav-logo { height: 48px; width: auto; display: block; filter: drop-shadow(0 1px 4px rgba(0,0,0,.3)); }
  .topnav-text .org { font-size: .68rem; font-weight: 700; color: rgba(255,255,255,.7); letter-spacing: .1em; text-transform: uppercase; }
  .topnav-text .sys { font-size: .92rem; font-weight: 800; color: #ffffff; line-height: 1.15; }
  .topnav-clock {
    display: flex;
    align-items: baseline;
    gap: 8px;
    line-height: 1.2;
  }
  .topnav-clock .clock-date { font-size: .75rem; font-weight: 600; color: rgba(255,255,255,.65); }
  .topnav-clock .clock-time { font-size: .9rem; font-weight: 800; color: #fff; letter-spacing: .02em; font-variant-numeric: tabular-nums; }
  @media(max-width:560px){ .topnav-clock { display: none; } }

  /* ── PAGE CONTENT ── */
  .page-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    padding: 40px 24px 40px;
    text-align: center;
  }

  .hero-eyebrow {
    font-size: .82rem; font-weight: 800; letter-spacing: .18em;
    text-transform: uppercase; color: #ffd700;
    background: transparent; padding: 0;
    border-radius: 0; margin-bottom: 14px; display: block;
    text-shadow: 0 1px 6px rgba(0,0,0,.4);
  }
  .hero-title {
    font-size: clamp(1.9rem, 5vw, 3rem);
    font-weight: 900; line-height: 1.1; color: #ffffff;
    margin-bottom: 8px;
    text-shadow: 0 2px 12px rgba(0,0,0,.3);
  }
  .hero-title .accent { color: #ffd700; }
  .hero-sub {
    font-size: 1rem; color: rgba(255,255,255,.8);
    margin-bottom: 32px; font-weight: 500;
  }

  /* ── TRACK BOX ── */
  .track-box {
    background: rgba(255,255,255,.97);
    border: none;
    border-radius: 16px;
    padding: 32px 36px;
    max-width: 580px; width: 100%;
    box-shadow: 0 12px 48px rgba(0,0,0,.3);
    margin-bottom: 28px;
  }
  .track-box h2 { font-size: 1rem; font-weight: 800; color: var(--dark); margin-bottom: 4px; }
  .track-box > p { font-size: .82rem; color: var(--muted); margin-bottom: 18px; }
  .track-input-row { display: flex; gap: 10px; }
  .track-input {
    flex: 1; padding: 13px 16px;
    border: 1.5px solid #c5d0e6; border-radius: 8px;
    font-size: .92rem; font-family: 'Inter', sans-serif;
    color: var(--dark); outline: none; transition: border-color .2s;
  }
  .track-input:focus { border-color: var(--blue-mid); }
  .track-input::placeholder { color: #9aa8c0; }
  .btn-track {
    display: flex; align-items: center; gap: 7px;
    padding: 13px 22px; border-radius: 8px;
    background: var(--blue); color: #fff;
    font-size: .88rem; font-weight: 800;
    border: none; cursor: pointer; transition: .2s;
    font-family: 'Inter', sans-serif;
    white-space: nowrap; text-transform: uppercase; letter-spacing: .04em;
  }
  .btn-track:hover { background: var(--dark); }
  .btn-track:disabled { opacity: .6; cursor: not-allowed; }

  /* ── CONTACT & LOCATION ── */
  .contact-section {
    width: 100%; max-width: 900px; margin: 48px auto 0;
    padding: 0 24px; text-align: center;
  }
  .contact-place { font-size: 1rem; font-weight: 800; color: #fff; margin-bottom: 28px; letter-spacing: .01em; }
  .contact-title { font-size: .95rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: rgba(255,255,255,.6); margin-bottom: 4px; }
  .contact-subtitle { font-size: .85rem; font-weight: 700; color: #fff; margin-bottom: 28px; }
  .contact-grid { display: flex; gap: 32px; justify-content: center; flex-wrap: wrap; margin-bottom: 36px; }
  .contact-item { flex: 1; min-width: 160px; max-width: 240px; }
  .contact-item i { font-size: 1.6rem; color: #ffd700; margin-bottom: 8px; display: block; }
  .contact-item .contact-label { font-size: .78rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: #fff; margin-bottom: 6px; }
  .contact-item .contact-value { font-size: .8rem; color: rgba(255,255,255,.75); line-height: 1.5; }
  .location-block i { font-size: 1.8rem; color: #ffd700; margin-bottom: 6px; display: block; }
  .location-label { font-size: .78rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: #fff; margin-bottom: 16px; }
  .location-map { display: inline-block; width: 100%; max-width: 520px; border-radius: 12px; overflow: hidden; border: 2px solid rgba(255,255,255,.2); box-shadow: 0 4px 24px rgba(0,0,0,.3); }
  .location-map iframe { width: 100%; height: 320px; border: 0; display: block; }

  /* ── FOOTER ── */
  footer {
    background: rgba(0, 48, 135, 0.96);
    border-top: 3px solid rgba(255,215,0,.6);
    padding: 22px 32px 16px;
    position: relative;
    z-index: 1;
    backdrop-filter: blur(8px);
  }
  .footer-inner {
    display: flex; flex-direction: column; align-items: center; gap: 14px;
  }
  .footer-items {
    display: flex; align-items: center; gap: 28px;
  }
  .footer-divider {
    width: 1px; height: 52px; background: rgba(255,255,255,.25);
  }
  .footer-logo-img { height: 56px; width: auto; }
  .footer-icon-link {
    display: flex; align-items: center; justify-content: center;
    width: 46px; height: 46px; border-radius: 50%;
    color: #fff; font-size: 1.25rem; text-decoration: none;
    transition: opacity .2s;
  }
  .footer-icon-link:hover { opacity: .82; }
  .footer-icon-link.fb { background: #1877f2; }
  .footer-icon-link.email { background: var(--red); }
  .footer-copy {
    font-size: .7rem; color: rgba(255,255,255,.65);
    letter-spacing: .06em; text-transform: uppercase;
  }

  /* ── MODAL ── */
  .modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,48,135,.6); z-index: 500; align-items: center; justify-content: center; padding: 16px; backdrop-filter: blur(4px); }
  .modal-backdrop.open { display: flex; }
  .modal { background: var(--white); border-radius: 16px; width: 100%; max-width: 520px; box-shadow: 0 24px 60px rgba(0,0,0,.3); overflow: hidden; animation: slideUp .25s ease; }
  @keyframes slideUp { from{transform:translateY(30px);opacity:0;} to{transform:translateY(0);opacity:1;} }
  .modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 0; border-bottom: 2px solid var(--blue-light); padding-bottom: 14px; }
  .modal-header h3 { font-size: 1rem; font-weight: 800; color: var(--dark); }
  .modal-close { background: none; border: none; font-size: 1.3rem; color: var(--muted); cursor: pointer; line-height: 1; padding: 4px; border-radius: 6px; }
  .modal-close:hover { background: var(--blue-light); color: var(--dark); }
  .modal-body { padding: 20px 24px 28px; }

  .txn-badge { display: inline-flex; align-items: center; gap: 6px; background: var(--blue-light); color: var(--blue); padding: 4px 12px; border-radius: 20px; font-size: .75rem; font-weight: 700; letter-spacing: .04em; margin-bottom: 14px; }

  .status-badge { padding: 4px 12px; border-radius: 20px; font-size: .78rem; font-weight: 700; }
  .s-submitted   { background: #e8eaf6; color: #3949ab; }
  .s-screening   { background: #e3f2fd; color: #1565c0; }
  .s-shortlisted { background: #e8f5e9; color: #2e7d32; }
  .s-interview,.s-assessed,.s-ranked,.s-ranking_sent { background: #fff8e1; color: #f57f17; }
  .s-offer_sent,.s-offer_accepted,.s-hired { background: #e8f5e9; color: #1b5e20; }
  .s-offer_declined,.s-rejected { background: #ffebee; color: #b71c1c; }
  .s-default { background: #f5f5f5; color: #616161; }

  .steps { display: flex; align-items: flex-start; margin: 18px 0; }
  .step { flex: 1; text-align: center; position: relative; }
  .step::before { content: ''; position: absolute; top: 13px; left: -50%; right: 50%; height: 2px; background: #d0daea; z-index: 0; }
  .step:first-child::before { display: none; }
  .step-dot { width: 26px; height: 26px; border-radius: 50%; background: #d0daea; color: #9aa8c0; display: flex; align-items: center; justify-content: center; font-size: .68rem; font-weight: 800; margin: 0 auto 4px; position: relative; z-index: 1; }
  .step.done .step-dot { background: var(--blue); color: #fff; }
  .step.active .step-dot { background: var(--blue-mid); color: #fff; box-shadow: 0 0 0 4px var(--blue-light); }
  .step.done::before { background: var(--blue); }
  .step-lbl { font-size: .58rem; color: var(--muted); font-weight: 600; line-height: 1.2; }
  .step.done .step-lbl { color: var(--blue); }
  .step.active .step-lbl { color: var(--blue-mid); }

  .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 16px; }
  .detail-item { background: var(--blue-light); border-radius: 8px; padding: 10px 14px; }
  .detail-item .lbl { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--blue-mid); margin-bottom: 3px; }
  .detail-item .val { font-size: .88rem; font-weight: 700; color: var(--dark); }

  .not-found-modal { text-align: center; padding: 20px 0; }
  .not-found-modal i { font-size: 2.5rem; color: var(--red); margin-bottom: 12px; display: block; }
  .not-found-modal h4 { font-size: 1rem; font-weight: 800; color: var(--dark); margin-bottom: 6px; }
  .not-found-modal p { font-size: .85rem; color: var(--muted); line-height: 1.6; }

  .spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid rgba(255,255,255,.4); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }
  @keyframes spin { to { transform: rotate(360deg); } }
</style>
</head>
<body>

<div class="page-bg"></div>

<!-- ── NAV ── -->
<nav class="topnav">
  <a href="/" class="topnav-brand">
    <img src="/sdo-logo.png" alt="SDO Cavite" class="topnav-logo">
    <div class="topnav-text">
      <div class="org">Schools Division Office of Cavite Province</div>
      <div class="sys">Online Recruitment Tracking System</div>
    </div>
  </a>
  <div class="topnav-clock">
    <span class="clock-date" id="clockDate"></span>
    <span class="clock-time" id="clockTime"></span>
  </div>
</nav>

<!-- ── MAIN CONTENT ── -->
<div class="page-content">
  <span class="hero-eyebrow">Department of Education</span>
<h1 class="hero-title">Online Recruitment<br><span class="accent">Tracking System</span></h1>
  <p class="hero-sub">Apply for positions or track your application status</p>

  <!-- Track Box -->
  <div class="track-box">
    <h2><i class="bi bi-search" style="color:var(--blue);margin-right:6px;"></i>Track Your Application</h2>
    <p>Enter your transaction number to check your application status.</p>
    <div class="track-input-row">
      <input type="text" id="txnInput" class="track-input" placeholder="e.g. APP-20260601-ABCD1234" autocomplete="off" spellcheck="false">
      <button class="btn-track" onclick="trackApplication()">TRACK <i class="bi bi-arrow-right"></i></button>
    </div>
  </div>

  <!-- Contact Section -->
  <div class="contact-section">
    <p class="contact-place">Schools Division Office of Cavite Province</p>
    <div class="contact-grid">
      <div class="contact-item">
        <i class="bi bi-telephone"></i>
        <div class="contact-label">Phone</div>
        <div class="contact-value">(046) 419-1286<br>(046) 412-0349</div>
      </div>
      <div class="contact-item">
        <i class="bi bi-envelope"></i>
        <div class="contact-label">Email</div>
        <div class="contact-value">deped.cavite@<br>deped.gov.ph</div>
      </div>
      <div class="contact-item">
        <i class="bi bi-geo-alt"></i>
        <div class="contact-label">Address</div>
        <div class="contact-value">Cavite Capitol Compound<br>Trece Martires City, Cavite</div>
      </div>
    </div>
    <div class="location-block">
      <i class="bi bi-pin-map"></i>
      <p class="location-label">Our Location</p>
      <div class="location-map">
        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3871.123456789!2d120.87!3d14.28!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMTTCsDE2JzQ4LjAiTiAxMjDCsDUyJzEyLjAiRQ!5e0!3m2!1sen!2sph!4v1234567890" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="SDO Cavite Location"></iframe>
      </div>
    </div>
  </div>
</div>

<!-- ── FOOTER ── -->
<footer>
  <div class="footer-inner">
    <div class="footer-items">
      <a href="https://depedcavite.com.ph/" target="_blank" rel="noopener">
        <img src="/sdo-logo.png" alt="SDO Cavite" class="footer-logo-img">
      </a>
      <div class="footer-divider"></div>
      <a href="https://www.facebook.com/depedtayocaviteprovince" target="_blank" rel="noopener" class="footer-icon-link fb" title="Facebook">
        <i class="bi bi-facebook"></i>
      </a>
      <div class="footer-divider"></div>
      <a href="mailto:deped.cavite@deped.gov.ph" class="footer-icon-link email" title="Email us">
        <i class="bi bi-envelope-fill"></i>
      </a>
    </div>
    <div class="footer-copy">&copy; {{ date('Y') }} DepEd &mdash; Schools Division Office of Cavite Province</div>
  </div>
</footer>

<!-- ── TRACKING MODAL ── -->
<div class="modal-backdrop" id="resultModal" onclick="closeModal(event)">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modalTitle">Application Status</h3>
      <button class="modal-close" onclick="closeModalDirect()"><i class="bi bi-x"></i></button>
    </div>
    <div class="modal-body" id="modalBody"></div>
  </div>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

// ── Live clock ────────────────────────────────────────────────────────────────
(function clock() {
  function tick() {
    const now = new Date();
    const datePart = now.toLocaleDateString('en-PH', {
      weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
    });
    const timePart = now.toLocaleTimeString('en-PH', {
      hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
    });
    document.getElementById('clockDate').textContent = datePart;
    document.getElementById('clockTime').textContent = timePart;
  }
  tick();
  setInterval(tick, 1000);
})();

async function trackApplication() {
  const input = document.getElementById('txnInput');
  const btn   = document.querySelector('.btn-track');
  const txn   = input.value.trim().toUpperCase();

  if (!txn) {
    input.style.borderColor = 'var(--red)';
    input.focus();
    setTimeout(() => input.style.borderColor = '', 1500);
    return;
  }

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

    const stepsHtml = stepLabels.map((s, i) => {
      const n = i + 1;
      const cls = current > n ? 'done' : current === n ? 'active' : '';
      const dot = current > n ? '<i class="bi bi-check2" style="font-size:.7rem;"></i>' : n;
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
        <div class="detail-item"><div class="lbl">Applicant Name</div><div class="val">${data.name}</div></div>
        <div class="detail-item"><div class="lbl">Date Applied</div><div class="val">${data.applied_at}</div></div>
        <div class="detail-item"><div class="lbl">Position</div><div class="val">${data.position}</div></div>
        <div class="detail-item"><div class="lbl">Current Status</div><div class="val">${label}</div></div>
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

document.getElementById('txnInput').addEventListener('keydown', e => {
  if (e.key === 'Enter') trackApplication();
});

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeModalDirect();
});

(function autoFillFromUrl() {
  const params = new URLSearchParams(window.location.search);
  const txn = params.get('txn');
  if (!txn) return;
  const input = document.getElementById('txnInput');
  if (!input) return;
  input.value = txn.trim().toUpperCase();
  setTimeout(trackApplication, 400);
})();
</script>
</body>
</html>