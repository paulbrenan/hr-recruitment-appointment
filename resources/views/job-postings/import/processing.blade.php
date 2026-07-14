@extends('layouts.app')

@section('page-title', 'Import Job Postings')

@section('content')
<div class="pdf-processing-wrap">
    <div class="pdf-processing-card">
        <div class="pdf-processing-logo">
            <img src="/sdo-logo.png" alt="DepEd Cavite">
        </div>

        <div class="pdf-wave-row" id="pdfWaveRow">
            <div class="pdf-wave-bar"></div>
            <div class="pdf-wave-bar"></div>
            <div class="pdf-wave-bar"></div>
            <div class="pdf-wave-bar"></div>
            <div class="pdf-wave-bar"></div>
            <div class="pdf-wave-bar"></div>
            <div class="pdf-wave-bar"></div>
            <div class="pdf-wave-bar"></div>
            <div class="pdf-wave-bar"></div>
        </div>

        <h1 class="pdf-processing-title">Processing your PDF</h1>
        <p class="pdf-processing-sub">This usually takes under a minute. You can leave this page open
            &mdash; it'll redirect automatically once it's done.</p>

        <div class="pdf-progress-track">
            <div class="pdf-progress-bar" id="pdfProgressBar"></div>
        </div>

        <p id="status-text" class="pdf-status-text">Status: processing<span class="pdf-dots"><span>.</span><span>.</span><span>.</span></span></p>
    </div>
</div>

<style>
    .pdf-processing-wrap {
        min-height: calc(100vh - var(--hr-header-h, 56px) - 180px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px 16px;
    }

    .pdf-processing-card {
        width: 100%;
        max-width: 480px;
        background: #fff;
        border: 1px solid #e2e6e8;
        border-radius: 18px;
        padding: 44px 40px 36px;
        text-align: center;
        box-shadow: 0 10px 40px rgba(0, 48, 135, 0.08);
        position: relative;
        overflow: hidden;
    }

    .pdf-processing-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--hr-primary, #003087), #ce1126, var(--hr-accent, #ffd700), var(--hr-primary, #003087));
        background-size: 200% 100%;
        animation: pdf-shimmer 2.5s linear infinite;
    }

    .pdf-processing-logo {
        width: 72px;
        height: 72px;
        margin: 0 auto 18px;
    }

    .pdf-processing-logo img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        animation: pdf-logo-pulse 1.4s ease-in-out infinite;
        filter: drop-shadow(0 2px 10px rgba(206, 17, 38, 0.25)) drop-shadow(0 0 14px rgba(255, 215, 0, 0.25));
    }

    .pdf-wave-row {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        height: 46px;
        margin-bottom: 22px;
    }

    .pdf-wave-bar {
        width: 5px;
        border-radius: 3px;
        background: linear-gradient(180deg, #ffd700, #ce1126, #003087);
        animation: pdf-wave 0.9s ease-in-out infinite;
        transform-origin: center;
    }

    .pdf-wave-bar:nth-child(1) { height: 12px; animation-delay: 0.00s; }
    .pdf-wave-bar:nth-child(2) { height: 20px; animation-delay: 0.05s; }
    .pdf-wave-bar:nth-child(3) { height: 30px; animation-delay: 0.10s; }
    .pdf-wave-bar:nth-child(4) { height: 40px; animation-delay: 0.15s; }
    .pdf-wave-bar:nth-child(5) { height: 46px; animation-delay: 0.20s; }
    .pdf-wave-bar:nth-child(6) { height: 40px; animation-delay: 0.15s; }
    .pdf-wave-bar:nth-child(7) { height: 30px; animation-delay: 0.10s; }
    .pdf-wave-bar:nth-child(8) { height: 20px; animation-delay: 0.05s; }
    .pdf-wave-bar:nth-child(9) { height: 12px; animation-delay: 0.00s; }

    .pdf-processing-title {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--hr-primary-dark, #0a1a33);
        margin-bottom: 8px;
    }

    .pdf-processing-sub {
        font-size: 0.85rem;
        color: #6c7780;
        margin-bottom: 26px;
        line-height: 1.5;
    }

    .pdf-progress-track {
        width: 100%;
        height: 6px;
        background: #eef1f5;
        border-radius: 6px;
        overflow: hidden;
        margin-bottom: 18px;
        position: relative;
    }

    .pdf-progress-bar {
        position: absolute;
        top: 0;
        left: -40%;
        width: 40%;
        height: 100%;
        background: linear-gradient(90deg, var(--hr-primary, #003087), #ce1126, var(--hr-accent, #ffd700));
        border-radius: 6px;
        animation: pdf-progress-slide 1.4s ease-in-out infinite;
    }

    .pdf-status-text {
        font-size: 0.8rem;
        font-weight: 600;
        color: #6c7780;
        margin: 0;
    }

    .pdf-status-text .pdf-dots span {
        animation: pdf-dots 1.2s infinite;
        opacity: 0;
    }
    .pdf-status-text .pdf-dots span:nth-child(1) { animation-delay: 0s; }
    .pdf-status-text .pdf-dots span:nth-child(2) { animation-delay: 0.2s; }
    .pdf-status-text .pdf-dots span:nth-child(3) { animation-delay: 0.4s; }

    .pdf-status-error {
        color: #c0392b !important;
        font-weight: 700;
    }

    @keyframes pdf-shimmer {
        0% { background-position: 0% 0; }
        100% { background-position: -200% 0; }
    }

    @keyframes pdf-logo-pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.08); }
    }

    @keyframes pdf-wave {
        0%, 100% { transform: scaleY(0.3); opacity: 0.6; }
        50% { transform: scaleY(1); opacity: 1; }
    }

    @keyframes pdf-progress-slide {
        0% { left: -40%; }
        100% { left: 100%; }
    }

    @keyframes pdf-dots {
        0%, 20% { opacity: 0; }
        50% { opacity: 1; }
        100% { opacity: 0; }
    }
</style>

<script>
(function poll() {
    fetch('{{ route("job-postings.import.status", $batch->id) }}')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'ready') {
                window.location.href = '{{ route("job-postings.import.review", $batch->id) }}';
            } else if (data.status === 'failed') {
                const progressBar = document.getElementById('pdfProgressBar');
                const waveRow = document.getElementById('pdfWaveRow');
                if (progressBar) progressBar.style.animationPlayState = 'paused';
                if (waveRow) waveRow.style.animationPlayState = 'paused';

                document.getElementById('status-text').innerHTML =
                    '<span class="pdf-status-error">' + (data.error_message || 'Something went wrong.') + '</span>';
            } else {
                setTimeout(poll, 2000);
            }
        })
        .catch(() => setTimeout(poll, 3000));
})();
</script>
@endsection