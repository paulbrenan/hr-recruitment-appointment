<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Segoe UI, Arial, sans-serif; background:#f4f6f7; margin:0; padding:0; }
  .wrap { max-width:600px; margin:32px auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08); }
  .header { background:#2b7a78; color:#fff; padding:28px 32px; }
  .header h1 { margin:0; font-size:1.2rem; font-weight:700; }
  .header p  { margin:4px 0 0; font-size:.85rem; opacity:.85; }
  .body { padding:28px 32px; color:#333; font-size:.92rem; line-height:1.6; }
  .txn { background:#e8f5f5; border-left:4px solid #2b7a78; border-radius:4px;
         padding:14px 18px; margin:20px 0; font-size:1.1rem; font-weight:600; color:#2b7a78; }
  .row { display:flex; gap:8px; padding:6px 0; border-bottom:1px solid #f0f0f0; }
  .row .lbl { color:#666; min-width:160px; font-size:.85rem; }
  .row .val { font-weight:500; font-size:.85rem; }
  .footer { background:#f4f6f7; padding:16px 32px; font-size:.78rem; color:#888; text-align:center; }
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>Department of Education – Division of Cavite Province</h1>
    <p>Online Recruitment Form – Submission Confirmation</p>
  </div>
  <div class="body">
    <p>Dear <strong>{{ $candidate->full_name }}</strong>,</p>
    <p>
      Thank you for submitting your application to the DepEd Division of Cavite Province.
      Your submission has been received and is now under review by the Human Resource Unit.
    </p>

    <div class="txn">
      Transaction No.: {{ $transactionNumber }}
    </div>

    <p><strong>Please keep this transaction number for your records.</strong>
    You may use it when following up on the status of your application.</p>

    <p><strong>Application Details:</strong></p>
    <div class="row"><span class="lbl">Position Applied For</span><span class="val">{{ $position }}</span></div>
    <div class="row"><span class="lbl">Name</span><span class="val">{{ $candidate->full_name }}</span></div>
    <div class="row"><span class="lbl">Email Address</span><span class="val">{{ $candidate->email }}</span></div>
    <div class="row"><span class="lbl">Contact No.</span><span class="val">{{ $candidate->phone ?? '—' }}</span></div>
    <div class="row"><span class="lbl">Address</span><span class="val">{{ $candidate->address ?? '—' }}</span></div>
    <div class="row"><span class="lbl">Eligibility</span><span class="val">{{ $candidate->eligibility ?? '—' }}</span></div>
    <div class="row"><span class="lbl">Highest Education</span><span class="val">{{ $candidate->education ?? '—' }}</span></div>
    <div class="row"><span class="lbl">Training Hours</span><span class="val">{{ $candidate->training_hours ?? '—' }}</span></div>
    <div class="row"><span class="lbl">Years of Experience</span><span class="val">{{ $candidate->years_experience ?? '—' }}</span></div>

    <p style="margin-top:24px; font-size:.82rem; color:#555;">
      All data gathered from this form shall be used solely by the Human Resource Unit for
      Initial Evaluation and Assessment of Applicants' documents purposes and shall be treated
      as confidential in compliance to the Data Privacy Act of 2012.
    </p>
  </div>
  <div class="footer">
    DepEd Division of Cavite Province &bull; Human Resource Unit<br>
    This is an automated email. Please do not reply.
  </div>
</div>
</body>
</html>