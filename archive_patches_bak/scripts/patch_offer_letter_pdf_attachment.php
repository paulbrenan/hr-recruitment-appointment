<?php
/**
 * patch_offer_letter_pdf_attachment.php  (v2 -- matches existing house style)
 *
 * Attaches a job description PDF to the offer letter email, following the
 * exact same pattern already used in QualifiedScheduleBundleNotification::
 * toMail() -- Pdf::loadView(...)->setPaper('letter'), then a direct
 * ->attachData() chained onto the returned MailMessage. No extra
 * try/catch here: JobOfferController::send() already wraps the entire
 * ->notify() call (which includes toMail()) in a try/catch that logs
 * failures without breaking the request, so a second guard around just
 * the PDF portion would be redundant.
 *
 * barryvdh/laravel-dompdf is already installed and in use elsewhere in
 * this app (see QualifiedScheduleBundleNotification) -- no composer
 * install step needed for this patch.
 *
 * What this does:
 *   1. Creates resources/views/pdf/job-description.blade.php -- a
 *      DepEd-branded, dompdf-safe (table-based, no flexbox/grid) layout:
 *      title, SG, employment type, place(s) of assignment,
 *      qualifications, duties & responsibilities, mandatory requirements.
 *   2. Updates OfferLetterNotification::toMail() to render that view and
 *      attach it, matching QualifiedScheduleBundleNotification's style.
 *
 * Run once from the project root:
 *   php patch_offer_letter_pdf_attachment.php
 * Then delete this file.
 */

function apply_patch($path, $old, $new, $label) {
    if (!file_exists($path)) {
        fwrite(STDERR, "[ABORT] File not found: $path ($label)\n");
        exit(1);
    }
    $contents = file_get_contents($path);
    if (strpos($contents, $old) === false) {
        fwrite(STDERR, "[ABORT] Expected content not found for: $label\n");
        fwrite(STDERR, "        File may already be patched or is a different version. No changes made.\n");
        exit(1);
    }
    copy($path, $path . '.bak');
    $updated = str_replace($old, $new, $contents, $count);
    if ($count !== 1) {
        fwrite(STDERR, "[ABORT] Expected exactly 1 match for '$label', found $count. Restoring backup.\n");
        copy($path . '.bak', $path);
        exit(1);
    }
    file_put_contents($path, $updated);
    echo "[OK] $label\n";
}

function create_new_file($path, $content, $label) {
    if (file_exists($path)) {
        fwrite(STDERR, "[ABORT] File already exists, refusing to overwrite: $path ($label)\n");
        fwrite(STDERR, "        Delete it first (or back it up) if you want this script to recreate it.\n");
        exit(1);
    }
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        fwrite(STDERR, "[ABORT] Could not create directory: $dir ($label)\n");
        exit(1);
    }
    file_put_contents($path, $content);
    echo "[OK] $label (created $path)\n";
}

$notifFile = __DIR__ . '/app/Notifications/OfferLetterNotification.php';
$pdfView   = __DIR__ . '/resources/views/pdf/job-description.blade.php';

// ── 1. New Blade view for the job description PDF ───────────────────────

$pdfViewContent = <<<'BLADE'
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    /* dompdf only understands a subset of CSS -- keep this simple:
       table-based layout, no flexbox/grid, no CSS variables. */
    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 11px;
        color: #212529;
        margin: 0;
        padding: 0;
    }
    .header {
        background-color: #003087;
        color: #ffffff;
        padding: 16px 24px;
    }
    .header .gold-bar {
        height: 4px;
        background-color: #ffcc00;
    }
    .header h1 {
        margin: 0;
        font-size: 16px;
    }
    .header p {
        margin: 2px 0 0 0;
        font-size: 10px;
        color: #cfd8ea;
    }
    .content {
        padding: 20px 24px;
    }
    .job-title {
        font-size: 15px;
        font-weight: bold;
        color: #003087;
        margin-bottom: 2px;
    }
    .job-meta {
        font-size: 10px;
        color: #555555;
        margin-bottom: 16px;
    }
    .section-title {
        font-size: 11px;
        font-weight: bold;
        color: #003087;
        border-bottom: 1px solid #dee2e6;
        padding-bottom: 3px;
        margin-top: 16px;
        margin-bottom: 8px;
    }
    table.info-table {
        width: 100%;
        border-collapse: collapse;
    }
    table.info-table td {
        padding: 3px 0;
        vertical-align: top;
    }
    table.info-table td.label {
        width: 140px;
        font-weight: bold;
        color: #495057;
    }
    .duties-list, .req-list {
        margin: 0;
        padding-left: 16px;
    }
    .duties-list li, .req-list li {
        margin-bottom: 4px;
    }
    .footer {
        margin-top: 24px;
        padding-top: 10px;
        border-top: 1px solid #dee2e6;
        font-size: 9px;
        color: #6c757d;
        text-align: center;
    }
</style>
</head>
<body>
    <div class="header">
        <h1>Job Description</h1>
        <p>Schools Division Office of Cavite &mdash; Department of Education</p>
        <div class="gold-bar"></div>
    </div>

    <div class="content">
        <div class="job-title">{{ $posting->title }}</div>
        <div class="job-meta">
            SG {{ $posting->salary_grade }}
            @if ($posting->employment_type) &middot; {{ $posting->employment_type }} @endif
            @if ($posting->place_of_assignment) &middot; {{ $posting->place_of_assignment }} @endif
        </div>

        @if ($posting->locations && $posting->locations->count() > 1)
        <div class="section-title">Place(s) of Assignment</div>
        <table class="info-table">
            @foreach ($posting->locations as $loc)
            <tr>
                <td>{{ $loc->place_of_assignment }}</td>
                <td style="text-align:right; width:100px;">{{ $loc->vacancies }} vacanc{{ $loc->vacancies == 1 ? 'y' : 'ies' }}</td>
            </tr>
            @endforeach
        </table>
        @endif

        <div class="section-title">Minimum Qualifications</div>
        <table class="info-table">
            <tr>
                <td class="label">Education</td>
                <td>{{ $posting->qualification_education ?: 'Not specified' }}</td>
            </tr>
            <tr>
                <td class="label">Training</td>
                <td>{{ $posting->qualification_training ?: 'Not specified' }}</td>
            </tr>
            <tr>
                <td class="label">Experience</td>
                <td>{{ $posting->qualification_experience ?: 'Not specified' }}</td>
            </tr>
            <tr>
                <td class="label">Eligibility</td>
                <td>{{ $posting->qualification_eligibility ?: 'Not specified' }}</td>
            </tr>
        </table>

        @if ($posting->duties_responsibilities)
        <div class="section-title">Duties and Responsibilities</div>
        <ul class="duties-list">
            @foreach (array_filter(array_map('trim', explode(';', $posting->duties_responsibilities))) as $duty)
                <li>{{ $duty }}</li>
            @endforeach
        </ul>
        @endif

        @if (!empty($posting->mandatory_requirements))
        <div class="section-title">Mandatory Requirements</div>
        <ul class="req-list">
            @foreach (array_filter(array_map('trim', explode("\n", $posting->mandatory_requirements))) as $req)
                <li>{{ $req }}</li>
            @endforeach
        </ul>
        @endif
    </div>

    <div class="footer">
        Generated {{ now()->format('F d, Y') }} &mdash; DepEd Cavite HR Recruitment System
    </div>
</body>
</html>
BLADE;

create_new_file($pdfView, $pdfViewContent, 'Create resources/views/pdf/job-description.blade.php');

// ── 2. OfferLetterNotification.php -- import Pdf facade + attach ────────

$old1 = <<<'OLD'
use App\Models\JobOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
OLD;

$new1 = <<<'NEW'
use App\Models\JobOffer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
NEW;

apply_patch($notifFile, $old1, $new1, 'Import the Pdf facade (barryvdh/laravel-dompdf, already used by QualifiedScheduleBundleNotification)');

$old2 = <<<'OLD'
    public function toMail(object $notifiable): MailMessage
    {
        $candidate    = $this->offer->application->candidate;
        $jobTitle     = $this->offer->application->jobPosting->title ?? 'the position';
        $compensation = number_format($this->offer->compensation, 2);
        $deadline     = $this->offer->response_deadline
            ? \Carbon\Carbon::parse($this->offer->response_deadline)->format('F d, Y')
            : null;

        return (new MailMessage)
            ->subject("Official Job Offer — {$jobTitle}")
            ->view('mail.offer-letter', [
                'candidate'    => $candidate,
                'offer'        => $this->offer,
                'jobTitle'     => $jobTitle,
                'compensation' => $compensation,
                'deadline'     => $deadline,
            ]);
    }
OLD;

$new2 = <<<'NEW'
    public function toMail(object $notifiable): MailMessage
    {
        $application  = $this->offer->application;
        $candidate    = $application->candidate;
        $posting      = $application->jobPosting;
        $jobTitle     = $posting->title ?? 'the position';
        $compensation = number_format($this->offer->compensation, 2);
        $deadline     = $this->offer->response_deadline
            ? \Carbon\Carbon::parse($this->offer->response_deadline)->format('F d, Y')
            : null;

        // Same pattern as QualifiedScheduleBundleNotification::toMail():
        // render the PDF, then attach it directly via attachData() chained
        // onto the returned MailMessage. No try/catch needed here --
        // JobOfferController::send() already wraps the whole ->notify()
        // call (which includes this method) in one, logging failures
        // without breaking the request.
        $pdf = Pdf::loadView('pdf.job-description', ['posting' => $posting])
            ->setPaper('letter');

        $filename = 'Job-Description-' . ($application->transaction_number ?? $posting->id) . '.pdf';

        return (new MailMessage)
            ->subject("Official Job Offer — {$jobTitle}")
            ->view('mail.offer-letter', [
                'candidate'    => $candidate,
                'offer'        => $this->offer,
                'jobTitle'     => $jobTitle,
                'compensation' => $compensation,
                'deadline'     => $deadline,
            ])
            ->attachData($pdf->output(), $filename, ['mime' => 'application/pdf']);
    }
NEW;

apply_patch($notifFile, $old2, $new2, "toMail(): render + attach the job description PDF, matching QualifiedScheduleBundleNotification's style");

echo "\nDone. Offer letter emails now include a job-description PDF attachment,\n";
echo "generated the same way qualification notices already are elsewhere in this app.\n";
