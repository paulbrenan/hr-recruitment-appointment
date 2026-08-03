<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Segoe UI, Arial, sans-serif; background:#f4f6f7; margin:0; padding:0; }
  .wrap { max-width:650px; margin:32px auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08); }
  .header { background:linear-gradient(120deg,#003087 0%,#0a1a33 100%); background-color:#003087; color:#fff; padding:32px 32px 26px; text-align:center; border-bottom:4px solid #ffd700; }
  .header .icon { width:52px; height:52px; border-radius:50%; background:#fff; display:inline-block;
                   line-height:52px; font-size:26px; font-weight:800; color:#003087; margin-bottom:14px; }
  .header h1 { margin:0 0 10px; font-size:1.4rem; font-weight:800; }
  .header .brand { margin:0 0 4px; font-size:.85rem; font-weight:600; opacity:.95; }
  .header p  { margin:0; font-size:.8rem; opacity:.8; }
  .body { padding:28px 32px; color:#333; font-size:.88rem; line-height:1.6; }
  .highlight-box { background:#e6ecf7; border:2px dashed #003087; border-radius:6px;
                   text-align:center; padding:16px; margin:20px 0; }
  .highlight-box .lbl { font-size:.78rem; color:#555; margin-bottom:4px; }
  .highlight-box .val { font-size:1.25rem; font-weight:800; color:#003087; letter-spacing:.02em; }
  .section-title { font-weight:700; font-size:.9rem; color:#003087;
                   border-bottom:2px solid #e6ecf7; padding-bottom:6px; margin:24px 0 12px; }
  .detail-row { display:flex; padding:5px 0; border-bottom:1px solid #f5f5f5; font-size:.84rem; }
  .detail-row .lbl { color:#666; min-width:170px; flex-shrink:0; }
  .detail-row .val { font-weight:500; }
  .note { background:#fff8e1; border-left:4px solid #f59e0b; border-radius:0 6px 6px 0;
          padding:12px 16px; font-size:.78rem; color:#78350f; margin-top:20px; line-height:1.55; }
  .footer { background:#f4f6f7; padding:16px 32px; font-size:.75rem; color:#888; text-align:center; border-top:1px solid #e3e8ec; }
</style>
</head>
<body>
<div class="wrap">

  <div class="header">
    <span class="icon">&#128197;</span>
    <h1>You're Invited for Orientation!</h1>
    <p class="brand">Department of Education &ndash; Schools Division Office of Cavite Province</p>
    <p>Orientation Schedule Notice</p>
  </div>

  <div class="body">
    <p>Dear <strong>{{ $candidateName }}</strong>,</p>
    <p>
      Congratulations! You have been selected and scheduled for orientation for the position of
      <strong>{{ $jobTitle }}</strong>. Please review the details below.
    </p>

    {{-- Orientation date highlight --}}
    <div class="highlight-box">
      <div class="lbl">Orientation Date</div>
      <div class="val">&#128197; {{ $date }}</div>
      @if ($time)
      <div style="margin-top:6px;font-size:.85rem;color:#444;">&#128336; {{ $time }}</div>
      @endif
    </div>

    {{-- Job Information --}}
    <div class="section-title">Position Details</div>
    <div class="detail-row">
      <span class="lbl">Position Title</span>
      <span class="val">{{ $jobTitle }}</span>
    </div>
    @if ($salaryGrade)
    <div class="detail-row">
      <span class="lbl">Salary Grade</span>
      <span class="val">{{ $salaryGrade }}</span>
    </div>
    @endif
    @if ($employmentType)
    <div class="detail-row">
      <span class="lbl">Employment Type</span>
      <span class="val">{{ $employmentType }}</span>
    </div>
    @endif

    {{-- Schedule Details --}}
    <div class="section-title">Orientation Schedule</div>
    <div class="detail-row">
      <span class="lbl">Date</span>
      <span class="val">{{ $date }}</span>
    </div>
    @if ($time)
    <div class="detail-row">
      <span class="lbl">Time</span>
      <span class="val">{{ $time }}</span>
    </div>
    @endif
    @if ($place)
    <div class="detail-row">
      <span class="lbl">Place / Venue</span>
      <span class="val">{{ $place }}</span>
    </div>
    @endif

    <div class="note">
      <strong>&#9888;&#65039; Reminder:</strong><br>
      Please make sure to arrive on time and bring all necessary documents (valid ID, application
      requirements, and other credentials as needed). Failure to attend without prior notice may
      affect your application status.
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
