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
  .section-title { font-weight:700; font-size:.9rem; color:#003087;
                   border-bottom:2px solid #e6ecf7; padding-bottom:6px; margin:24px 0 12px; }
  .sched-card { background:#fafbfc; border:1px solid #e3e8ec; border-radius:6px; padding:16px 20px; margin-bottom:14px; }
  .sched-card .type { font-weight:700; color:#003087; font-size:.92rem; margin-bottom:8px; }
  .detail-row { display:flex; padding:4px 0; font-size:.84rem; }
  .detail-row .lbl { color:#666; min-width:110px; flex-shrink:0; }
  .detail-row .val { font-weight:500; }
  .crit-table { width:100%; border-collapse:collapse; font-size:.82rem; margin-top:4px; }
  .crit-table th { text-align:left; background:#f4f6f7; color:#555; font-size:.72rem; text-transform:uppercase;
                    letter-spacing:.03em; padding:8px 10px; border-bottom:2px solid #e3e8ec; }
  .crit-table td { padding:9px 10px; border-bottom:1px solid #f0f2f4; vertical-align:top; }
  .crit-table .badge { display:inline-block; padding:2px 9px; border-radius:10px; font-size:.72rem; font-weight:700; }
  .crit-table .badge.pass { background:#e9f9ef; color:#1a7d3a; }
  .crit-table .badge.fail { background:#fdeceb; color:#b3261e; }
  .note { background:#e6ecf7; border-radius:6px; padding:12px 16px; font-size:.78rem; color:#003087; margin-top:20px; line-height:1.55; }
  .footer { background:#f4f6f7; padding:16px 32px; font-size:.75rem; color:#888; text-align:center; border-top:1px solid #e3e8ec; }
  .btn-wrap { text-align:center; margin:24px 0; }
  .btn { background:#003087; color:#fff; text-decoration:none; padding:12px 32px;
         border-radius:6px; font-weight:700; font-size:.9rem; display:inline-block; }
</style>
</head>
<body>
<div class="wrap">

  <div class="header">
    <span class="check-icon">&#10003;</span>
    <h1>You're Qualified &amp; Scheduled</h1>
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
      been marked <strong>Qualified</strong>. The official notice is attached to this email as a PDF.
    </p>

    <div class="section-title">
      Your Schedule{{ count($scheduleRows) > 1 ? 's' : '' }}
    </div>

    @foreach ($scheduleRows as $row)
    <div class="sched-card">
      <div class="type">{{ $row['type_label'] }}</div>
      <div class="detail-row"><span class="lbl">Date &amp; Time</span><span class="val">{{ $row['when'] }}</span></div>
      @if ($row['location'])
      <div class="detail-row"><span class="lbl">Location</span><span class="val">{{ $row['location'] }}</span></div>
      @endif
      @if ($row['panelists'])
      <div class="detail-row"><span class="lbl">Panel</span><span class="val">{{ $row['panelists'] }}</span></div>
      @endif
    </div>
    @endforeach

    @if (!empty($criteriaRows))
    <div class="section-title">Qualification Standards Checked</div>
    <table class="crit-table">
      <thead>
        <tr><th>Criterion</th><th>Your Qualification</th><th>Result</th></tr>
      </thead>
      <tbody>
        @foreach ($criteriaRows as $row)
        <tr>
          <td>
            <strong>{{ $row['label'] }}</strong>
            @if (!empty($row['required']))
              <div style="color:#888; font-size:.75rem; margin-top:2px;">Required: {{ $row['required'] }}</div>
            @endif
          </td>
          <td>{{ $row['actual'] ?: '—' }}</td>
          <td><span class="badge {{ $row['passed'] ? 'pass' : 'fail' }}">{{ $row['passed'] ? 'Qualified' : 'Not qualified' }}</span></td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @endif

    <div class="note">
      <strong>&#128204; Reminder:</strong><br>
      If you are unable to attend any of the schedules above, please contact the Human Resource Unit
      as soon as possible.
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