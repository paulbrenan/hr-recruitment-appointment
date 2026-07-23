<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Segoe UI, Arial, sans-serif; background:#f4f6f7; margin:0; padding:0; }
  .wrap { max-width:650px; margin:32px auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08); }
  .header { background:linear-gradient(120deg,#003087 0%,#0a1a33 100%); background-color:#003087; color:#fff; padding:32px 32px 26px; text-align:center; border-bottom:4px solid #ffd700; }
  .header .check-icon { width:52px; height:52px; border-radius:50%; background:#fff; display:inline-block;
                         line-height:52px; font-size:26px; font-weight:800; color:#003087; margin-bottom:14px; }
  .header h1 { margin:0 0 10px; font-size:1.4rem; font-weight:800; }
  .header .brand { margin:0 0 4px; font-size:.85rem; font-weight:600; opacity:.95; }
  .header p  { margin:0; font-size:.8rem; opacity:.8; }
  .body { padding:28px 32px; color:#333; font-size:.88rem; line-height:1.6; }
  .txn { background:#e6ecf7; border:2px dashed #0047b3; border-radius:6px;
         text-align:center; padding:16px; margin:20px 0; }
  .txn .lbl { font-size:.78rem; color:#555; margin-bottom:4px; }
  .txn .num { font-size:1.35rem; font-weight:800; color:#003087; letter-spacing:.05em; }
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

  {{-- Header --}}
  <div class="header">
    <span class="check-icon">&#10003;</span>
    <h1>Your Application Code Has Been Assigned</h1>
    <p class="brand">Department of Education &ndash; Schools Division Office of Cavite Province</p>
    <p>Online Recruitment Form</p>
  </div>

  <div class="body">
    <p>Dear <strong>{{ $candidate->full_name }}</strong>,</p>
    <p>
      The Human Resource Unit has verified your submitted requirements for
      <strong>{{ $position }}</strong>. You may now use the Application Code below to track the
      status of your application.
    </p>

    <div style="text-align:center; margin:20px 0;">
      <a href="{{ url('/?txn=' . $transactionNumber) }}"
         style="background:#003087; color:#fff; text-decoration:none; padding:12px 32px;
                border-radius:6px; font-weight:700; font-size:.9rem; display:inline-block;">
        View My Application Status
      </a>
    </div>
    <p style="text-align:center; font-size:.78rem; color:#888; margin-top:-8px;">
      Or copy this link: {{ url('/?txn=' . $transactionNumber) }}
    </p>

    {{-- Application Code --}}
    <div class="txn">
      <div class="lbl">Your Application Code</div>
      <div class="num">{{ $transactionNumber }}</div>
    </div>
    <p style="font-size:.82rem;color:#555;text-align:center;margin-top:-8px;">
      Please keep this Application Code for your records and follow-up inquiries.
    </p>

    <div class="section-title">Application Details</div>
    <div class="detail-row"><span class="lbl">Position Applied For</span><span class="val">{{ $position }}</span></div>
    <div class="detail-row"><span class="lbl">Name</span><span class="val">{{ $candidate->full_name }}</span></div>
    <div class="detail-row"><span class="lbl">Application Code</span><span class="val">{{ $transactionNumber }}</span></div>

    <div class="note">
      <strong>&#128204; Next Step:</strong><br>
      Your application will now proceed through the recruitment process. We will notify you by
      email of any further updates, including interview or assessment schedules.
    </div>

    <p style="margin-top:20px;font-size:.82rem;color:#555;">
      For inquiries, please contact the Human Resource Unit at:<br>
      📍 Cavite Capitol Compound, Brgy. Luciano, Trece Martires City, Cavite<br>
      📞 (046) 419-1286, 412-0349<br>
      🌐 <a href="http://www.depedcavite.com.ph" style="color:#003087;">www.depedcavite.com.ph</a><br>
      ✉️ deped.cavite@deped.gov.ph
    </p>
  </div>

  <div class="footer">
    <img src="{{ $message->embed(public_path('images/deped-logo.png')) }}" alt="DepEd Logo" width="36" height="36" style="width:36px;height:36px;border-radius:50%;margin-bottom:8px;display:inline-block;">
    <br>
    DepEd Schools Division Office of Cavite Province &bull; Human Resource Unit<br>
    This is an automated email. Please do not reply directly to this message.
  </div>
</div>
</body>
</html>