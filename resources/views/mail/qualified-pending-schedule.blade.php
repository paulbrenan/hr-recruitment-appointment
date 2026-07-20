<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Segoe UI, Arial, sans-serif; background:#f4f6f7; margin:0; padding:0; }
  .wrap { max-width:650px; margin:32px auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08); }
  .header { background:linear-gradient(120deg,#003087 0%,#0a1a33 100%); background-color:#003087; color:#fff; padding:32px 32px 26px; text-align:center; border-bottom:4px solid #ffd700; }
  .header .check-icon { width:52px; height:52px; border-radius:50%; background:#fff; display:inline-block;
                         line-height:52px; font-size:26px; font-weight:800; color:#1a7d3a; margin-bottom:14px; }
  .header h1 { margin:0 0 10px; font-size:1.4rem; font-weight:800; }
  .header .brand { margin:0 0 4px; font-size:.85rem; font-weight:600; opacity:.95; }
  .header p  { margin:0; font-size:.8rem; opacity:.8; }
  .body { padding:28px 32px; color:#333; font-size:.88rem; line-height:1.6; }
  .txn { background:#e6ecf7; border:2px dashed #0047b3; border-radius:6px;
         text-align:center; padding:16px; margin:20px 0; }
  .txn .lbl { font-size:.78rem; color:#555; margin-bottom:4px; }
  .txn .num { font-size:1.15rem; font-weight:800; color:#003087; letter-spacing:.02em; }
  .result-box { border-left:4px solid #1a7d3a; background:#e9f9ef; padding:16px 20px; border-radius:6px; margin-bottom:20px; }
  .result-box p { margin:0; font-weight:700; font-size:1rem; color:#1a7d3a; }
  .note { background:#fff8e1; border:1px solid #ffe082; border-radius:6px; padding:14px 18px; font-size:.82rem; color:#7a5b00; margin-top:20px; line-height:1.55; }
  .footer { background:#f4f6f7; padding:16px 32px; font-size:.75rem; color:#888; text-align:center; border-top:1px solid #e3e8ec; }
</style>
</head>
<body>
<div class="wrap">

  <div class="header">
    <span class="check-icon">&#10003;</span>
    <h1>You Are Qualified</h1>
    <p class="brand">Department of Education &ndash; Schools Division Office of Cavite Province</p>
    <p>Online Recruitment Form</p>
  </div>

  <div class="body">
    <p>Dear <strong>{{ $candidate->full_name }}</strong>,</p>

    <div class="txn">
      <div class="lbl">Transaction Number</div>
      <div class="num">{{ $application->transaction_number }}</div>
    </div>

    <div class="result-box">
      <p>You meet the qualification standards for this position.</p>
    </div>

    <p>
      Congratulations! Based on our review of your submitted documents against the qualification
      standards for <strong>{{ $jobPosting->title ?? 'the position' }}</strong>, your application has
      been marked <strong>Qualified</strong>.
    </p>

    <div class="note">
      <strong>&#9203; Schedule pending:</strong><br>
      An interview, examination, or open ranking schedule has not yet been set for your application.
      This does not affect your qualified status — we will email you separately with the date, time,
      and venue as soon as a schedule is available.
    </div>

    <p style="margin-top:20px;font-size:.82rem;color:#555;">
      If you have questions in the meantime, please contact the Human Resource Unit at:<br>
      &#128205; Cavite Capitol Compound, Brgy. Luciano, Trece Martires City, Cavite<br>
      &#128222; (046) 419-1286, 412-0349<br>
      &#127760; <a href="http://www.depedcavite.com.ph" style="color:#003087;">www.depedcavite.com.ph</a><br>
      &#9993;&#65039; deped.cavite@deped.gov.ph
    </p>
  </div>

  <div class="footer">
    DepEd Schools Division Office of Cavite Province &bull; Human Resource Unit<br>
    This is an automated email. Please do not reply directly to this message.
  </div>
</div>
</body>
</html>
