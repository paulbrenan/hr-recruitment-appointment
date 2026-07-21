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
  .txn-box { background:#e6ecf7; border:2px dashed #003087; border-radius:8px;
             text-align:center; padding:18px; margin:20px 24px; }
  .txn-box .label { font-size:.8rem; color:#555; margin-bottom:4px; }
  .txn-box .number { font-size:1.4rem; font-weight:800; color:#003087; letter-spacing:.04em; }
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

    @if ($transactionNumber)
    <div class="txn-box">
      <div class="label">Your Application Code</div>
      <div class="number">{{ $transactionNumber }}</div>
    </div>

    <p style="font-size:.82rem;color:#555;text-align:center;">
      A confirmation email has been sent to <strong>{{ $candidate->email }}</strong>.<br>
      Please keep your Application Code for follow-up inquiries.
    </p>

    <div class="text-center mb-3 mt-1">
      <a href="{{ url('/') }}?txn={{ urlencode($transactionNumber) }}"
         style="display:inline-flex;align-items:center;gap:8px;background:#003087;color:#fff;
                font-weight:700;font-size:.9rem;padding:10px 24px;border-radius:8px;
                text-decoration:none;">
        <i class="bi bi-search"></i> Track Your Application
      </a>
      <div style="font-size:.75rem;color:#888;margin-top:6px;">
        Uses your Application Code automatically
      </div>
    </div>
    @else
    <div class="txn-box">
      <div class="label">Application Status</div>
      <div class="number" style="font-size:1.05rem;">Pending Verification</div>
    </div>

    <p style="font-size:.82rem;color:#555;text-align:center;">
      A confirmation email has been sent to <strong>{{ $candidate->email }}</strong>.<br>
      Our Records Unit will verify your submitted requirements, and you will
      receive a follow-up email with your official <strong>Application Code</strong>
      once that's done — you can use it to track your application from that point on.
    </p>
    @endif

    @if (isset($jobPosting) && $jobPosting->memoPdfUrl())
    <div class="text-center mb-3">
      <a href="{{ $jobPosting->memoPdfUrl() }}" target="_blank" rel="noopener"
         style="color:#003087; font-weight:700; font-size:.85rem; text-decoration:underline;">
        <i class="bi bi-file-earmark-pdf"></i> View the Official Memo (PDF)
      </a>
    </div>
    @endif

    <p style="font-size:.75rem;color:#888;text-align:center;margin-top:16px;">
      All data is treated as confidential in compliance with the Data Privacy Act of 2012.
    </p>
  </div>
</div>
</body>
</html>