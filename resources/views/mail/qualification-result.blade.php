<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Segoe UI, Arial, sans-serif; background:#f4f6f7; margin:0; padding:0; }
  .wrap { max-width:650px; margin:32px auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08); }
  .header { background:linear-gradient(120deg,#003087 0%,#0a1a33 100%); background-color:#003087; color:#fff; padding:32px 32px 26px; text-align:center; border-bottom:4px solid #ffd700; }
  .header .check-icon { width:52px; height:52px; border-radius:50%; background:#fff; display:inline-block;
                         line-height:52px; font-size:26px; font-weight:800; margin-bottom:14px; }
  .header .check-icon.pass { color:#1a7d3a; }
  .header .check-icon.fail { color:#b3261e; }
  .header h1 { margin:0 0 10px; font-size:1.4rem; font-weight:800; }
  .header .brand { margin:0 0 4px; font-size:.85rem; font-weight:600; opacity:.95; }
  .header p  { margin:0; font-size:.8rem; opacity:.8; }
  .body { padding:28px 32px; color:#333; font-size:.88rem; line-height:1.6; }
  .txn { background:#e6ecf7; border:2px dashed #0047b3; border-radius:6px;
         text-align:center; padding:16px; margin:20px 0; }
  .txn .lbl { font-size:.78rem; color:#555; margin-bottom:4px; }
  .txn .num { font-size:1.15rem; font-weight:800; color:#003087; letter-spacing:.02em; }
  .result-box { border-left:4px solid; padding:16px 20px; border-radius:6px; margin-bottom:20px; }
  .result-box.pass { background:#e9f9ef; border-color:#1a7d3a; }
  .result-box.fail { background:#fdeceb; border-color:#b3261e; }
  .result-box p { margin:0; font-weight:700; font-size:1rem; }
  .result-box.pass p { color:#1a7d3a; }
  .result-box.fail p { color:#b3261e; }
  .section-title { font-weight:700; font-size:.9rem; color:#003087;
                   border-bottom:2px solid #e6ecf7; padding-bottom:6px; margin:24px 0 12px; }
  .crit-table { width:100%; border-collapse:collapse; font-size:.82rem; margin-top:4px; }
  .crit-table th { text-align:left; background:#f4f6f7; color:#555; font-size:.72rem; text-transform:uppercase;
                    letter-spacing:.03em; padding:8px 10px; border-bottom:2px solid #e3e8ec; }
  .crit-table td { padding:9px 10px; border-bottom:1px solid #f0f2f4; vertical-align:top; }
  .crit-table .badge { display:inline-block; padding:2px 9px; border-radius:10px; font-size:.72rem; font-weight:700; }
  .crit-table .badge.pass { background:#e9f9ef; color:#1a7d3a; }
  .crit-table .badge.fail { background:#fdeceb; color:#b3261e; }
  .note { background:#e6ecf7; border-radius:6px; padding:12px 16px; font-size:.78rem; color:#003087; margin-top:20px; line-height:1.55; }
  .footer { background:#f4f6f7; padding:16px 32px; font-size:.75rem; color:#888; text-align:center; border-top:1px solid #e3e8ec; }
</style>
</head>
<body>
<div class="wrap">

  <div class="header">
    <span class="check-icon {{ $passed ? 'pass' : 'fail' }}">{{ $passed ? '&#10003;' : '&#10007;' }}</span>
    <h1>{{ $passed ? 'You Are Qualified' : 'Qualification Result' }}</h1>
    <p class="brand">Department of Education &ndash; Schools Division Office of Cavite Province</p>
    <p>Region IV-A &bull; Online Recruitment Form</p>
  </div>

  <div class="body">
    <p>Dear <strong>{{ $candidate->full_name }}</strong>,</p>

    <div class="txn">
      <div class="lbl">Transaction Number</div>
      <div class="num">{{ $application->transaction_number }}</div>
    </div>

    @if ($passed)
    <div class="result-box pass">
      <p>You meet the qualification standards for this position.</p>
    </div>
    <p>
      Congratulations! Based on our review of your submitted documents against the qualification
      standards for <strong>{{ $jobPosting->title ?? 'the position' }}</strong>, your application has
      been marked <strong>Qualified</strong>. Your application will now proceed to the next stage of
      the recruitment process. We will notify you of further updates, including any scheduled
      interviews or assessments.
    </p>
    @else
    <div class="result-box fail">
      <p>You do not currently meet the qualification standards for this position.</p>
    </div>
    <p>
      Thank you for your interest in <strong>{{ $jobPosting->title ?? 'this position' }}</strong> at the
      Schools Division Office of Cavite Province. After careful review of your submitted documents
      against the position's qualification standards, we regret to inform you that your application
      has been marked <strong>Disqualified</strong> at this stage. We encourage you to apply again for
      future postings that match your qualifications.
    </p>
    @endif

    @if (!empty($criteriaRows))
    <div class="section-title">Qualification Standards Checked</div>
    <table class="crit-table">
      <thead>
        <tr>
          <th>Criterion</th>
          <th>Your Qualification</th>
          <th>Result</th>
        </tr>
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
          <td>
            <span class="badge {{ $row['passed'] ? 'pass' : 'fail' }}">
              {{ $row['passed'] ? 'Qualified' : 'Not qualified' }}
            </span>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @endif

    <div class="note">
      <strong>&#128206; Attached:</strong><br>
      The official {{ $passed ? 'Qualified' : 'Disqualified' }} Notice (PDF) is attached to this
      email for your records.
    </div>

    <p style="margin-top:20px;font-size:.82rem;color:#555;">
      If you have questions about this result, please contact the Human Resource Unit at:<br>
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
