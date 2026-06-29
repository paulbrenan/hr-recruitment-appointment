<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Application Submitted – DepEd Cavite</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
  body { background: linear-gradient(135deg,#d9f0ef 0%,#e8f5f5 50%,#c8e6e5 100%); min-height:100vh; font-family:'Segoe UI',Arial,sans-serif; display:flex; align-items:center; justify-content:center; }
  .card { max-width:520px; width:100%; border-radius:12px; box-shadow:0 4px 24px rgba(0,0,0,.1); overflow:hidden; }
  .card-header { background:#2b7a78; color:#fff; padding:24px 28px; text-align:center; }
  .card-header i { font-size:2.5rem; }
  .card-header h2 { font-size:1.2rem; font-weight:700; margin:8px 0 0; }
  .txn-box { background:#e8f5f5; border:2px dashed #2b7a78; border-radius:8px;
             text-align:center; padding:18px; margin:20px 0; }
  .txn-box .label { font-size:.8rem; color:#555; margin-bottom:4px; }
  .txn-box .number { font-size:1.4rem; font-weight:800; color:#2b7a78; letter-spacing:.04em; }
  .btn-portal { background:#2b7a78; color:#fff; font-weight:600; border:none; border-radius:8px; padding:10px 24px; width:100%; }
  .btn-portal:hover { background:#1d5e5c; color:#fff; }
</style>
</head>
<body>
<div class="card">
  <div class="card-header">
    <i class="bi bi-check-circle-fill"></i>
    <h2>Application Submitted Successfully!</h2>
  </div>
  <div class="card-body p-4">
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

    <a href="{{ route('portal.dashboard') }}" class="btn btn-portal mt-2">
      <i class="bi bi-house me-1"></i> Go to My Dashboard
    </a>

    <p style="font-size:.75rem;color:#888;text-align:center;margin-top:16px;">
      All data is treated as confidential in compliance with the Data Privacy Act of 2012.
    </p>
  </div>
</div>
</body>
</html>