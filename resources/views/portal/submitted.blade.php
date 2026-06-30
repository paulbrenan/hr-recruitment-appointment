<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Application Submitted – DepEd Cavite</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="{{ asset('css/deped-theme.css') }}">
<style>
  body { display:flex; align-items:center; justify-content:center; }
  .deped-card { max-width:520px; width:100%; }
  .deped-header i.bi-check-circle-fill { font-size:2.5rem; display:block; margin-bottom:6px; }
  .deped-header h2 { font-size:1.15rem; font-weight:700; margin:0; }
  .txn-box { background:#e8f5f5; border:2px dashed #2b7a78; border-radius:8px;
             text-align:center; padding:18px; margin:20px 24px; }
  .txn-box .label { font-size:.8rem; color:#555; margin-bottom:4px; }
  .txn-box .number { font-size:1.4rem; font-weight:800; color:#2b7a78; letter-spacing:.04em; }
</style>
</head>
<body class="deped-watermark">
<div class="deped-card">
  <div class="deped-header deped-header-center">
    <i class="bi bi-check-circle-fill"></i>
    <h2>Application Submitted Successfully!</h2>
  </div>
  <div class="p-4">
    <p class="text-center" style="font-size:.9rem;">
      Thank you, <strong>{{ $candidate->full_name }}</strong>!<br>
      Your application for <strong>{{ $position }}</strong> has been received.
    </p>

    <div class="txn-box">
      <div class="label">Your Transaction Number</div>
      <div class="number">{{ $transactionNumber }}</div>
    </div>

    <p style="font-size:.82rem;color:#555;text-align:center;">
      A confirmation email has been sent to <strong>{{ $candidate->email }}</strong>.<br>
      Please keep your transaction number for follow-up inquiries.
    </p>

    <p style="font-size:.75rem;color:#888;text-align:center;margin-top:16px;">
      All data is treated as confidential in compliance with the Data Privacy Act of 2012.
    </p>
  </div>
</div>
</body>
</html>