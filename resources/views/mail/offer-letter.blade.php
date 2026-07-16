<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Segoe UI, Arial, sans-serif; background:#f4f6f7; margin:0; padding:0; }
  .wrap { max-width:650px; margin:32px auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08); }
  .header { background:linear-gradient(120deg,#003087 0%,#0a1a33 100%); background-color:#003087; color:#fff; padding:32px 32px 26px; text-align:center; border-bottom:4px solid #ffd700; }
  .header .check-icon { width:52px; height:52px; border-radius:50%; background:#fff; display:inline-block;
                         line-height:52px; font-size:26px; font-weight:800; color:#b45309; margin-bottom:14px; }
  .header h1 { margin:0 0 10px; font-size:1.4rem; font-weight:800; }
  .header .brand { margin:0 0 4px; font-size:.85rem; font-weight:600; opacity:.95; }
  .header p  { margin:0; font-size:.8rem; opacity:.8; }
  .body { padding:28px 32px; color:#333; font-size:.88rem; line-height:1.6; }
  .txn { background:#fff8e1; border:2px dashed #b45309; border-radius:6px;
         text-align:center; padding:16px; margin:20px 0; }
  .txn .lbl { font-size:.78rem; color:#555; margin-bottom:4px; }
  .txn .num { font-size:1.35rem; font-weight:800; color:#b45309; letter-spacing:.02em; }
  .section-title { font-weight:700; font-size:.9rem; color:#003087;
                   border-bottom:2px solid #e6ecf7; padding-bottom:6px; margin:24px 0 12px; }
  .detail-row { display:flex; padding:5px 0; border-bottom:1px solid #f5f5f5; font-size:.84rem; }
  .detail-row .lbl { color:#666; min-width:170px; flex-shrink:0; }
  .detail-row .val { font-weight:500; }
  .note { background:#e6ecf7; border-radius:6px; padding:12px 16px; font-size:.78rem; color:#003087; margin-top:20px; line-height:1.55; }
  .footer { background:#f4f6f7; padding:16px 32px; font-size:.75rem; color:#888; text-align:center; border-top:1px solid #e3e8ec; }
</style>
</head>
<body>
<div class="wrap">

  <div class="header">
    <span class="check-icon">&#127942;</span>
    <h1>Congratulations, {{ $candidate->first_name }}!</h1>
    <p class="brand">Department of Education &ndash; Schools Division Office of Cavite Province</p>
    <p>Region IV-A &bull; Official Job Offer</p>
  </div>

  <div class="body">
    <p>Dear <strong>{{ $candidate->first_name }}</strong>,</p>
    <p>
      We are pleased to formally offer you the position of <strong>{{ $jobTitle }}</strong> at the
      Schools Division Office of Cavite Province.
    </p>

    <div class="txn">
      <div class="lbl">Monthly Compensation</div>
      <div class="num">&#8369;{{ $compensation }}</div>
    </div>

    <div class="section-title">Offer Details</div>
    <div class="detail-row"><span class="lbl">Position</span><span class="val">{{ $jobTitle }}</span></div>
    <div class="detail-row"><span class="lbl">Compensation</span><span class="val">&#8369;{{ $compensation }} / month</span></div>
    @if ($offer->benefits)
    <div class="detail-row"><span class="lbl">Benefits</span><span class="val">{{ $offer->benefits }}</span></div>
    @endif
    @if ($offer->terms)
    <div class="detail-row"><span class="lbl">Terms</span><span class="val">{{ $offer->terms }}</span></div>
    @endif
    @if ($deadline)
    <div class="detail-row"><span class="lbl">Respond By</span><span class="val">{{ $deadline }}</span></div>
    @endif

    <div class="note">
      <strong>&#128204; Next Step:</strong><br>
      Please review the details above and reply to this email to confirm your acceptance, or reach
      out to our HR team with any questions.
    </div>

    <p style="margin-top:20px;font-size:.82rem;color:#555;">
      For inquiries, please contact the Human Resource Unit at:<br>
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
