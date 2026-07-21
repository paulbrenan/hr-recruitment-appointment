<?php

namespace App\Notifications;

use App\Models\JobOffer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OfferLetterNotification extends Notification
{
    use Queueable;

    public JobOffer $offer;

    public function __construct(JobOffer $offer)
    {
        $this->offer = $offer;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    // -------------------------------------------------------------------------
    // EMAIL
    // -------------------------------------------------------------------------

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

    public function toArray(object $notifiable): array
    {
        return [
            'job_offer_id'   => $this->offer->id,
            'application_id' => $this->offer->application_id,
            'compensation'   => $this->offer->compensation,
        ];
    }
}
